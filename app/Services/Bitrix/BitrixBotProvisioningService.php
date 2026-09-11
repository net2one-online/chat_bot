<?php

namespace App\Services\Bitrix;

use App\Models\BitrixToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitrixBotProvisioningService
{
    public function __construct(
        protected BitrixOAuthService $oauth,
    ) {}

    /**
     * Resolve the portal domain for a token. Prefers the stored domain on the
     * token, then derives it from the portal endpoint. It deliberately avoids
     * falling back to a globally configured domain so provisioning never runs
     * against the wrong portal in a multi-tenant deployment.
     */
    public function resolveDomain(BitrixToken $token): string
    {
        if ($token->domain) {
            return trim($token->domain);
        }

        return $this->oauth->portalDomainFromEndpoint($token->client_endpoint);
    }

    /**
     * Ensure a file-capable bot (Chatbots 2.0, open line) exists for the given
     * token. Idempotent: if the token already has a file bot, it does nothing.
     */
    public function provision(BitrixToken $token): array
    {
        if ($token->file_bot_id) {
            return ['bot_id' => $token->file_bot_id, 'bot_token' => $token->file_bot_token];
        }

        $result = $this->registerChatbot($token, 'Asistente de archivos', 'asistente_archivos_'.substr($token->member_id, 0, 8));

        if (isset($result['error'])) {
            return $result;
        }

        $token->update([
            'domain' => $result['domain'],
            'file_bot_id' => $result['bot_id'],
            'file_bot_token' => $result['bot_token'],
        ]);

        Log::info('BitrixBotProvisioning: bot de archivos registrado', [
            'member_id' => $token->member_id,
            'bot_id' => $result['bot_id'],
        ]);

        return ['bot_id' => $result['bot_id'], 'bot_token' => $result['bot_token']];
    }

    /**
     * Resolve the public base URL of this installation so bot webhooks always
     * point to a reachable HTTPS host. Prefers APP_URL when it is an explicit
     * non-local URL, then the Railway public domain, then the configured URL.
     */
    public function resolveAppUrl(): string
    {
        $appUrl = rtrim((string) env('APP_URL', ''), '/');

        if ($appUrl !== '' && ! str_contains($appUrl, 'localhost')) {
            return $appUrl;
        }

        $railwayDomain = rtrim((string) env('RAILWAY_PUBLIC_DOMAIN', ''), '/');

        if ($railwayDomain !== '') {
            return 'https://'.str_replace(['https://', 'http://'], '', $railwayDomain);
        }

        return rtrim(config('app.url'), '/');
    }

    /**
     * Register a Chatbot 2.0 (open line) in the portal of the given token so it
     * shows up in the Contact Center bot selector. Returns the Bitrix bot id,
     * the generated bot token and the resolved domain.
     */
    public function registerChatbot(BitrixToken $token, string $name, string $code): array
    {
        $domain = $this->resolveDomain($token);
        $accessToken = $this->oauth->getAccessToken($token->member_id);

        if (! $domain || ! $accessToken) {
            Log::warning('BitrixBotProvisioning: sin dominio o token', [
                'member_id' => $token->member_id,
            ]);

            return ['error' => true, 'message' => 'No se pudo registrar el chatbot (dominio o token ausentes)'];
        }

        $botToken = 'bt_'.bin2hex(random_bytes(24));
        $webhookUrl = $this->resolveAppUrl().'/api/bitrix/webhook?member='.urlencode($token->member_id);

        $response = Http::timeout(30)->post("https://{$domain}/rest/imbot.v2.Bot.register", [
            'auth' => $accessToken,
            'fields' => [
                'code' => $code,
                'botToken' => $botToken,
                'properties' => [
                    'name' => $name,
                    'workPosition' => 'Asistente de Open Channels',
                ],
                'type' => 'openline',
                'eventMode' => 'webhook',
                'webhookUrl' => $webhookUrl,
                'isSupportOpenline' => true,
            ],
        ]);

        if ($response->failed()) {
            Log::error('BitrixBotProvisioning: error registrando chatbot', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'error' => true,
                'message' => $response->json('error_description') ?? 'No se pudo registrar el chatbot',
            ];
        }

        $botId = (int) ($response->json('result.bot.id') ?? 0);

        if ($botId === 0) {
            $errorDescription = (string) ($response->json('error_description') ?: $response->json('error', ''));

            Log::error('BitrixBotProvisioning: error registrando chatbot', [
                'member_id' => $token->member_id,
                'domain' => $domain,
                'error' => $errorDescription,
                'body' => $response->body(),
            ]);

            return ['error' => true, 'message' => $errorDescription !== '' ? $errorDescription : 'Registro de chatbot devolvio un ID invalido'];
        }

        Log::info('BitrixBotProvisioning: chatbot 2.0 registrado', [
            'member_id' => $token->member_id,
            'bot_id' => $botId,
            'name' => $name,
        ]);

        return [
            'bot_id' => (string) $botId,
            'bot_token' => $botToken,
            'domain' => $domain,
        ];
    }

    /**
     * Update the public name of an already registered Chatbot 2.0.
     */
    public function updateChatbotName(BitrixToken $token, int|string $botId, string $botToken, string $name): array
    {
        $domain = $this->resolveDomain($token);
        $accessToken = $this->oauth->getAccessToken($token->member_id);

        if (! $domain || ! $accessToken) {
            return ['error' => true, 'message' => 'Sin dominio o token para actualizar el chatbot'];
        }

        $response = Http::timeout(30)->post("https://{$domain}/rest/imbot.v2.Bot.update", [
            'auth' => $accessToken,
            'botId' => (int) $botId,
            'botToken' => $botToken,
            'fields' => [
                'BOT' => [
                    'properties' => [
                        'name' => $name,
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            Log::error('BitrixBotProvisioning: error actualizando chatbot', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => true, 'message' => $response->json('error_description') ?? 'No se pudo actualizar el chatbot'];
        }

        return ['success' => true];
    }

    /**
     * Remove a registered Chatbot 2.0 from the portal.
     */
    public function unregisterChatbot(BitrixToken $token, int|string $botId, string $botToken): array
    {
        $domain = $this->resolveDomain($token);
        $accessToken = $this->oauth->getAccessToken($token->member_id);

        if (! $domain || ! $accessToken) {
            return ['error' => true, 'message' => 'Sin dominio o token para eliminar el chatbot'];
        }

        $response = Http::timeout(30)->post("https://{$domain}/rest/imbot.v2.Bot.unregister", [
            'auth' => $accessToken,
            'botId' => (int) $botId,
            'fields' => [
                'botToken' => $botToken,
            ],
        ]);

        if ($response->failed()) {
            Log::error('BitrixBotProvisioning: error eliminando chatbot', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => true, 'message' => $response->json('error_description') ?? 'No se pudo eliminar el chatbot'];
        }

        return ['success' => true];
    }
}
