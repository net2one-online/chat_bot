<?php

namespace App\Services\Bitrix;

use App\Models\BitrixToken;
use App\Services\Settings\SettingsService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BitrixOAuthService
{
    public const AUTHORIZE_URL = 'https://oauth.bitrix.info/oauth/authorize/';

    public const TOKEN_URL = 'https://oauth.bitrix.info/oauth/token/';

    public function __construct(protected SettingsService $settings) {}

    /**
     * Build the URL where the user authorizes in Bitrix24.
     */
    public function authorizeUrl(string $state = ''): string
    {
        $clientId = (string) $this->settings->get('bitrix.client_id', '');

        if ($state === '') {
            $state = Str::random(32);
        }

        return self::AUTHORIZE_URL
            .'?'.http_build_query(['client_id' => $clientId, 'state' => $state]);
    }

    /**
     * Exchange an authorization code for a token pair.
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::timeout(30)->get(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->settings->get('bitrix.client_id'),
            'client_secret' => $this->settings->get('bitrix.client_secret'),
            'code' => $code,
        ]);

        return [
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }

    /**
     * Store a token pair associated with a member_id.
     */
    public function saveTokens(array $data, int|string|null $userId = null): BitrixToken
    {
        $expiresAt = isset($data['expires_in'])
            ? now()->addSeconds((int) $data['expires_in'])
            : null;

        $attributes = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'client_endpoint' => $data['client_endpoint'] ?? null,
            'expires_at' => $expiresAt,
            'scope' => $data['scope'] ?? null,
            'user_id' => $userId,
        ];

        // Only persist a domain when one is actually provided, so an empty
        // payload (e.g. a generic OAuth endpoint on a subsequent iframe load)
        // never wipes out a previously stored portal domain.
        if (isset($data['domain']) && $data['domain'] !== '') {
            $attributes['domain'] = $data['domain'];
        }

        return BitrixToken::updateOrCreate(
            ['member_id' => $data['member_id']],
            $attributes,
        );
    }

    /**
     * Get a valid access token for the given member, refreshing it if needed.
     */
    public function getAccessToken(string $memberId): ?string
    {
        $token = BitrixToken::where('member_id', $memberId)->first();

        if (! $token) {
            return null;
        }

        if ($token->hasToken() && $token->isExpiringSoon()) {
            if (! $this->refresh($token)) {
                return null;
            }

            $token->refresh();
        }

        return $token->access_token;
    }

    /**
     * Get a valid access token for the portal matching the given domain.
     */
    public function getAccessTokenForDomain(string $domain): ?string
    {
        $domain = strtolower(preg_replace('/^https?:\/\//', '', rtrim($domain, '/')));

        $token = BitrixToken::query()
            ->get()
            ->first(function ($t) use ($domain) {
                $tDomain = strtolower((string) $t->domain);

                if ($tDomain !== '' && $tDomain === $domain) {
                    return $t->hasToken();
                }

                $endpoint = strtolower((string) $t->client_endpoint);

                return $endpoint !== '' && str_contains($endpoint, $domain);
            });

        if (! $token) {
            $token = BitrixToken::query()
                ->get()
                ->first();
        }

        if (! $token || ! $token->hasToken()) {
            return null;
        }

        if ($token->isExpiringSoon()) {
            if (! $this->refresh($token)) {
                return null;
            }

            $token->refresh();
        }

        return $token->access_token;
    }

    /**
     * Refresh a token pair for the given member.
     */
    public function refresh(BitrixToken $token): bool
    {
        $response = Http::timeout(30)->get(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => $this->settings->get('bitrix.client_id'),
            'client_secret' => $this->settings->get('bitrix.client_secret'),
            'refresh_token' => $token->refresh_token,
        ]);

        if ($response->failed()) {
            return false;
        }

        $data = $response->json();

        if (isset($data['error'])) {
            return false;
        }

        $this->saveTokens($data, $token->user_id);

        return true;
    }

    /**
     * Return the configured client id (for display checks).
     */
    public function isConfigured(): bool
    {
        return ! empty($this->settings->get('bitrix.client_id'))
            && ! empty($this->settings->get('bitrix.client_secret'));
    }

    /**
     * Get the file-capable bot credentials stored against the token matching
     * the given portal domain.
     */
    public function fileBotForDomain(string $domain): ?BitrixToken
    {
        $domain = strtolower(preg_replace('/^https?:\/\//', '', rtrim($domain, '/')));

        return BitrixToken::query()
            ->get()
            ->first(function (BitrixToken $t) use ($domain) {
                if ($t->domain !== null && strtolower(trim($t->domain)) === $domain) {
                    return ! empty($t->file_bot_id) && ! empty($t->file_bot_token);
                }

                $endpoint = strtolower((string) $t->client_endpoint);

                return $endpoint !== '' && str_contains($endpoint, $domain)
                    && ! empty($t->file_bot_id) && ! empty($t->file_bot_token);
            });
    }

    /**
     * Get the token record for the active tenant, or the first configured token
     * when no tenant is bound.
     */
    public function tenantToken(): ?BitrixToken
    {
        $memberId = TenantContext::memberId();

        if ($memberId) {
            return BitrixToken::where('member_id', $memberId)->first();
        }

        return BitrixToken::query()->orderBy('id')->first();
    }

    /**
     * Whether the given domain already matches the stored domain setting.
     */
    public function domainConfigured(string $domain): bool
    {
        return strcasecmp((string) $this->settings->get('bitrix.domain'), $domain) === 0;
    }

    /**
     * Derive the portal domain from a Bitrix endpoint, ignoring generic OAuth
     * endpoints (oauth.bitrix.info, scopes.bitrix24.com) that carry no portal
     * host. Returns the host when it looks like a real portal domain.
     */
    public function portalDomainFromEndpoint(?string $endpoint): string
    {
        $endpoint = (string) $endpoint;

        if ($endpoint === '') {
            return '';
        }

        $host = (string) parse_url($endpoint, PHP_URL_HOST);

        if ($host === '') {
            return '';
        }

        $host = strtolower($host);

        $generic = [
            'oauth.bitrix.info',
            'oauth.bitrix24.com',
            'scopes.bitrix24.com',
        ];

        foreach ($generic as $g) {
            if ($host === $g || str_ends_with($host, '.'.$g)) {
                return '';
            }
        }

        return trim($host, '.');
    }
}
