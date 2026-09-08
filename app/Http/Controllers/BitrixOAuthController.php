<?php

namespace App\Http\Controllers;

use App\Models\BitrixToken;
use App\Services\Bitrix\BitrixBotProvisioningService;
use App\Services\Bitrix\BitrixOAuthService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BitrixOAuthController extends Controller
{
    public function __construct(
        protected BitrixOAuthService $oauth,
        protected SettingsService $settings,
        protected BitrixBotProvisioningService $provisioning,
    ) {}

    /**
     * Run after tokens are persisted so a fresh installation automatically
     * registers its own file-capable bot (Chatbots 2.0) in the new portal.
     */
    protected function provisionForToken(BitrixToken $token, ?string $domain = null): void
    {
        if ($domain) {
            $token->update(['domain' => $domain]);
        }

        Log::info('Provisioning file bot for portal', ['domain' => $domain]);
        $this->provisioning->provision($token);
    }

    /**
     * Redirect the user to Bitrix24 to authorize the app.
     */
    public function start(Request $request)
    {
        if (! $this->oauth->isConfigured()) {
            return redirect()->route('settings.index')
                ->with('error', 'Configura primero el Client ID y Client Secret de Bitrix24 en la seccion de Configuracion.');
        }

        $state = $request->session()->get('oauth_state', '');

        if ($state === '') {
            $state = Str::random(32);
            $request->session()->put('oauth_state', $state);
        }

        return redirect()->away($this->oauth->authorizeUrl($state));
    }

    /**
     * Handle the OAuth callback from Bitrix24.
     *
     * Supports both the redirect after a full OAuth authorization (GET with a
     * code) and the installation callback posted by Bitrix24 when the app
     * completes its own installation (POST with installation tokens).
     */
    public function callback(Request $request)
    {
        $isInstallation = $request->isMethod('post')
            && ($request->input('AUTH_ID') || $request->filled('auth'));

        if ($isInstallation) {
            return $this->install($request);
        }

        $storedState = $request->session()->pull('oauth_state', '');
        $returnedState = (string) $request->query('state', '');

        if ($storedState !== '' && $returnedState !== '' && $storedState !== $returnedState) {
            Log::warning('OAuth state mismatch', ['stored' => $storedState, 'returned' => $returnedState]);

            return redirect()->route('settings.index')
                ->with('error', 'La verificacion de estado de OAuth fallo. Intenta de nuevo.');
        }

        $code = (string) $request->query('code', '');
        $memberId = (string) $request->query('member_id', '');
        $domain = (string) $request->query('domain', '');

        if ($code === '' || $memberId === '') {
            return redirect()->route('settings.index')
                ->with('error', 'La autorizacion de Bitrix24 no se completo correctamente.');
        }

        if ($domain && $this->oauth->domainConfigured($domain) === false) {
            if ($this->settings->get('bitrix.domain') === '') {
                $this->settings->set(['bitrix.domain' => $domain]);
            }
        }

        $result = $this->oauth->exchangeCode($code);

        if ($result['status'] !== 200 || isset($result['body']['error'])) {
            Log::error('OAuth token exchange failed', $result);

            return redirect()->route('settings.index')
                ->with('error', 'No se pudo obtener el token de Bitrix24. Verifica tu configuracion.');
        }

        $token = $this->oauth->saveTokens($result['body'], $result['body']['user_id'] ?? null);

        $this->provisionForToken($token, $domain);

        $request->session()->put('bitrix_member_id', $token->member_id);
        $request->session()->put('iframe_mode', true);

        return redirect()->route('dashboard', ['embed' => 1])
            ->with('success', 'Conectado correctamente con Bitrix24.');
    }

    /**
     * Handle the automatic installation callback from Bitrix24.
     *
     * Bitrix24 POSTs a payload to the handler URL when the application is
     * installed with the "Application completes the installation itself"
     * option. Two payload shapes are accepted:
     *   - nested auth array (standard)  : auth[access_token], auth[member_id]...
     *   - flat uppercase keys (iframe)  : AUTH_ID, REFRESH_ID, member_id...
     */
    public function install(Request $request)
    {
        $auth = (array) $request->input('auth', []);
        $memberId = (string) ($auth['member_id'] ?? $request->input('member_id', ''));
        $accessToken = (string) ($auth['access_token'] ?? $request->input('AUTH_ID', ''));
        $refreshToken = (string) ($auth['refresh_token'] ?? $request->input('REFRESH_ID', ''));
        $applicationToken = (string) ($auth['application_token'] ?? $request->input('APPLICATION_TOKEN', ''));

        if ($memberId === '' || $accessToken === '') {
            Log::warning('Bitrix install callback missing auth data', ['request' => $request->all()]);

            return response()->json(['installation' => 'MISSING_DATA'], 400);
        }

        $storedApplicationToken = (string) $this->settings->get('bitrix.application_token', '');

        if ($storedApplicationToken !== '' && $applicationToken !== $storedApplicationToken) {
            Log::warning('Bitrix install callback application_token mismatch');

            return response()->json(['installation' => 'BAD_APPLICATION_TOKEN'], 401);
        }

        $this->saveFromInstallPayload($request);

        Log::info('Bitrix application installed via callback', ['member_id' => $memberId]);

        $token = BitrixToken::where('member_id', $memberId)->first();
        $domain = (string) ($auth['domain'] ?? $request->input('domain', ''));

        if ($token) {
            $this->provisionForToken($token, $domain === '' ? null : $domain);
        }

        if (! $request->expectsJson() && ! $request->ajax()) {
            $request->session()->put('iframe_mode', true);
            $request->session()->put('bitrix_member_id', $memberId);

            return redirect()->route('dashboard', ['embed' => 1]);
        }

        return response()->json(['installation' => 'OK']);
    }

    /**
     * Persist an installation payload regardless of its key casing.
     */
    protected function saveFromInstallPayload(Request $request): void
    {
        $auth = (array) $request->input('auth', []);

        $clientEndpoint = $auth['client_endpoint'] ?? $request->input('SERVER_ENDPOINT');
        $domain = $auth['domain'] ?? $request->input('domain', '');
        $domain = (string) $domain;

        if ($domain === '') {
            $domain = $this->oauth->portalDomainFromEndpoint($clientEndpoint);
        }

        $data = [
            'member_id' => $auth['member_id'] ?? $request->input('member_id'),
            'access_token' => $auth['access_token'] ?? $request->input('AUTH_ID'),
            'refresh_token' => $auth['refresh_token'] ?? $request->input('REFRESH_ID'),
            'client_endpoint' => $clientEndpoint,
            'domain' => $domain !== '' ? $domain : null,
            'expires_in' => $auth['expires_in'] ?? $request->input('AUTH_EXPIRES'),
            'scope' => $auth['scope'] ?? $request->input('APPLICATION_SCOPE'),
            'user_id' => $auth['user_id'] ?? null,
        ];

        $userId = $data['user_id'] ?? $request->input('USER_ID');

        $this->oauth->saveTokens(array_filter($data, fn ($v) => $v !== null), $userId);
    }
}
