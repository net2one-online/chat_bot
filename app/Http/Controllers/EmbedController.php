<?php

namespace App\Http\Controllers;

use App\Models\BitrixToken;
use App\Services\Bitrix\BitrixBotProvisioningService;
use App\Services\Bitrix\BitrixOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmbedController extends Controller
{
    public function __construct(
        protected BitrixOAuthService $oauth,
        protected BitrixBotProvisioningService $provisioning,
    ) {}

    /**
     * Handle the Bitrix24 iframe entry point.
     *
     * Bitrix24 POSTs fresh authorization tokens every time the application
     * frame is opened. This handler refreshes the stored token and forwards to
     * the dashboard so the in-frame panel always shows the full view (filters
     * included), instead of a stripped-down landing page.
     */
    public function index(Request $request)
    {
        $this->persistIframeToken($request);

        $request->session()->put('iframe_mode', true);
        $this->captureBitrixLanguage($request);
        $this->provisionIfNeeded($request);

        return redirect()->route('dashboard', ['embed' => 1]);
    }

    /**
     * Store the authorization tokens posted by Bitrix24 for the iframe.
     */
    protected function persistIframeToken(Request $request): void
    {
        $auth = (array) $request->input('auth', []);

        $accessToken = (string) ($auth['access_token'] ?? $request->input('AUTH_ID', ''));
        $memberId = (string) ($auth['member_id'] ?? $request->input('member_id', ''));

        if ($accessToken === '' || $memberId === '') {
            return;
        }

        $request->session()->put('bitrix_member_id', $memberId);

        $clientEndpoint = $auth['client_endpoint'] ?? $request->input('SERVER_ENDPOINT');
        $domain = $auth['domain'] ?? $request->input('domain', '');
        $domain = (string) $domain;

        if ($domain === '') {
            $domain = $this->oauth->portalDomainFromEndpoint($clientEndpoint);
        }

        $data = [
            'member_id' => $memberId,
            'access_token' => $accessToken,
            'refresh_token' => $auth['refresh_token'] ?? $request->input('REFRESH_ID'),
            'client_endpoint' => $clientEndpoint,
            'domain' => $domain !== '' ? $domain : null,
            'expires_in' => $auth['expires_in'] ?? $request->input('AUTH_EXPIRES'),
            'scope' => $auth['scope'] ?? $request->input('APPLICATION_SCOPE'),
        ];

        $this->oauth->saveTokens(array_filter($data, fn ($v) => $v !== null),
            $auth['user_id'] ?? $request->input('USER_ID'));
    }

    /**
     * Remember which language Bitrix24 reports so the in-frame interface can
     * follow it. Spanish maps to "es", any other language to "en".
     */
    protected function captureBitrixLanguage(Request $request): void
    {
        $lang = (string) ($request->input('LANG') ?: $request->query('LANG', ''));
        $lang = strtolower($lang);

        if ($lang !== '') {
            $request->session()->put('bitrix_lang', $lang);
        }
    }

    /**
     * Give installation time to converge: if the current portal has fresh
     * tokens but no file bot yet, attempt to register it. The iframe receives
     * fresh tokens on every load, which naturally retries a provision that
     * previously failed (e.g. an expired token at install time).
     */
    protected function provisionIfNeeded(Request $request): void
    {
        $auth = (array) $request->input('auth', []);
        $memberId = (string) ($auth['member_id'] ?? $request->input('member_id', ''));

        if ($memberId === '') {
            return;
        }

        $token = BitrixToken::where('member_id', $memberId)->first();

        if (! $token || $token->file_bot_id) {
            return;
        }

        $result = $this->provisioning->provision($token);

        if (isset($result['error'])) {
            Log::warning('EmbedController: provision pendiente', [
                'member_id' => $memberId,
                'reason' => $result['message'] ?? 'unknown',
            ]);
        }
    }
}
