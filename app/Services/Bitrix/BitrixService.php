<?php

namespace App\Services\Bitrix;

use App\Models\BitrixToken;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitrixService
{
    public function __construct(
        protected BitrixOAuthService $oauth,
    ) {}

    /**
     * Resolve the active tenant token from the request context, falling back
     * to the first configured token when no tenant is bound.
     */
    protected function token(): ?BitrixToken
    {
        $memberId = TenantContext::memberId();

        if ($memberId) {
            return BitrixToken::where('member_id', $memberId)->first();
        }

        return BitrixToken::query()->orderBy('id')->first();
    }

    public function request(string $method, array $params = []): array
    {
        $token = $this->token();

        if (! $token) {
            return ['error' => true, 'message' => 'No hay portal Bitrix24 configurado'];
        }

        $domain = trim((string) ($token->domain ?? ''));

        if ($domain === '') {
            $domain = $this->oauth->portalDomainFromEndpoint($token->client_endpoint);
        }

        $accessToken = $this->oauth->getAccessToken($token->member_id);

        if ($domain === '' || ! $accessToken) {
            Log::warning('BitrixService: sin dominio o token para el tenant', [
                'member_id' => $token->member_id,
            ]);

            return ['error' => true, 'message' => 'No se pudo conectar con el portal Bitrix24 del cliente'];
        }

        $url = "https://{$domain}/rest/{$method}";
        $params['auth'] = $accessToken;

        return $this->call($url, $params, 'POST', $method);
    }

    public function get(string $method, array $params = []): array
    {
        $token = $this->token();

        if (! $token) {
            return ['error' => true, 'message' => 'No hay portal Bitrix24 configurado'];
        }

        $domain = trim((string) ($token->domain ?? ''));

        if ($domain === '') {
            $domain = $this->oauth->portalDomainFromEndpoint($token->client_endpoint);
        }

        $accessToken = $this->oauth->getAccessToken($token->member_id);

        if ($domain === '' || ! $accessToken) {
            Log::warning('BitrixService(get): sin dominio o token para el tenant', [
                'member_id' => $token->member_id,
            ]);

            return ['error' => true, 'message' => 'No se pudo conectar con el portal Bitrix24 del cliente'];
        }

        $params['auth'] = $accessToken;
        $url = "https://{$domain}/rest/{$method}";

        return $this->call($url, $params, 'GET', $method);
    }

    protected function call(string $url, array $params, string $verb, string $method): array
    {
        try {
            $response = $verb === 'GET'
                ? Http::timeout(30)->retry(3, 1000)->get($url, $params)
                : Http::timeout(30)->retry(3, 1000)->post($url, $params);

            if ($response->failed()) {
                Log::error("Bitrix API error: {$method}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['error' => true, 'message' => 'Error en la solicitud a Bitrix24'];
            }

            $data = $response->json();

            if (isset($data['error'])) {
                Log::error("Bitrix API response error: {$method}", [
                    'error' => $data['error'],
                    'error_description' => $data['error_description'] ?? '',
                ]);

                return ['error' => true, 'message' => $data['error_description'] ?? $data['error']];
            }

            return is_array($data['result']) ? $data['result'] : ['result' => $data['result'] ?? null];
        } catch (\Exception $e) {
            Log::error("Bitrix API exception: {$method}", [
                'message' => $e->getMessage(),
            ]);

            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function getWebhookUrl(): string
    {
        $token = $this->token();

        if (! $token) {
            return '';
        }

        return 'https://'.trim((string) ($token->domain ?? '')).'/rest/';
    }

    public function getDomain(): string
    {
        $token = $this->token();

        if (! $token) {
            return '';
        }

        return trim((string) ($token->domain ?? ''));
    }
}
