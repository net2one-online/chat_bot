<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Bot;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BitrixWebhookController extends Controller
{
    public function webhook(Request $request)
    {
        try {
            $payload = $request->all();

            // The webhook URL is registered per portal with ?member=<member_id>.
            // Scope the whole request to that tenant.
            $memberId = trim((string) ($request->query('member', '')));

            if ($memberId === '') {
                $memberId = trim((string) ($payload['member_id'] ?? ''));
            }

            if ($memberId !== '') {
                TenantContext::set($memberId);
            }

            $event = $payload['event'] ?? $payload['type'] ?? null;

            Log::info('Webhook recibido de Bitrix24', [
                'event' => $event ?? 'unknown',
                'member_id' => $memberId,
            ]);

            if (! $event) {
                return response()->json(['status' => 'ignored'], 200);
            }

            match ($event) {
                'ONIMBOTMESSAGEADD' => $this->handleIncomingMessage($payload),
                'ONIMBOTV2MESSAGEADD' => $this->handleIncomingMessage($payload),
                'ONIMBOTJOINCHAT' => $this->handleChatCreated($payload),
                'ONIMBOTV2JOINCHAT' => $this->handleChatCreated($payload),
                'ONIMOPENLINESMESSAGEADD' => $this->handleIncomingMessage($payload),
                default => Log::info('Tipo de evento no manejado', ['event' => $event]),
            };

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error('Error procesando webhook de Bitrix24', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    protected function handleIncomingMessage(array $payload): void
    {
        $data = $payload['data'] ?? [];
        $event = $payload['event'] ?? '';

        if (str_starts_with($event, 'ONIMBOTV2')) {
            $parsed = $this->parseV2Event($data);
        } else {
            $parsed = $this->parseV1Event($data);
        }

        $botId = $parsed['bot_id'];
        $chatId = $parsed['chat_id'];
        $message = trim((string) $parsed['message']);
        $sessionId = $parsed['session_id'];
        $contactId = $parsed['contact_id'];
        $bitrixMessageId = $parsed['bitrix_message_id'];

        if (! $botId || ! $chatId || $message === '') {
            Log::warning('Datos incompletos en webhook', [
                'event' => $event,
                'data' => $data,
            ]);

            return;
        }

        $bot = Bot::withoutGlobalScope('tenant')->where('bitrix_bot_id', (string) $botId)->first();

        if (! $bot) {
            Log::warning('Bot no encontrado', ['bitrix_bot_id' => $botId]);

            return;
        }

        ProcessIncomingMessage::dispatch(
            botId: $bot->id,
            chatId: (string) $chatId,
            message: $message,
            sessionId: $sessionId ? (string) $sessionId : null,
            contactId: $contactId ? (string) $contactId : null,
            bitrixMessageId: $bitrixMessageId ? (string) $bitrixMessageId : null,
        );
    }

    protected function handleChatCreated(array $payload): void
    {
        $data = $payload['data'] ?? [];
        $event = $payload['event'] ?? '';

        if (str_starts_with($event, 'ONIMBOTV2')) {
            $botId = $data['bot']['id'] ?? null;
        } else {
            $botId = $this->extractBotId($data);
        }

        Log::info('Bot unido a chat en Bitrix24', [
            'bot_id' => $botId,
        ]);
    }

    /**
     * Normalize a v1 event payload (ONIMBOTMESSAGEADD / ONIMOPENLINESMESSAGEADD).
     */
    protected function parseV1Event(array $data): array
    {
        $params = $data['PARAMS'] ?? [];

        return [
            'bot_id' => $this->extractBotId($data),
            'chat_id' => $params['DIALOG_ID']
                ?? $params['CHAT_ID']
                ?? $data['CHAT_ID']
                ?? null,
            'message' => $params['MESSAGE'] ?? '',
            'session_id' => $params['SESSION_ID'] ?? null,
            'contact_id' => $data['USER']['ID'] ?? null,
            'bitrix_message_id' => $params['MESSAGE_ID'] ?? null,
        ];
    }

    /**
     * Normalize a v2 event payload (ONIMBOTV2MESSAGEADD).
     * In webhook mode the values arrive as strings and are nested under bot/message/chat/user.
     */
    protected function parseV2Event(array $data): array
    {
        return [
            'bot_id' => $data['bot']['id'] ?? null,
            'chat_id' => $data['chat']['dialogId']
                ?? $data['chat']['id']
                ?? null,
            'message' => $data['message']['text'] ?? '',
            'session_id' => null,
            'contact_id' => $data['user']['id'] ?? null,
            'bitrix_message_id' => $data['message']['id'] ?? null,
        ];
    }

    protected function extractBotId(array $data): ?string
    {
        if (! empty($data['BOT']['ID'])) {
            return (string) $data['BOT']['ID'];
        }

        foreach ((array) ($data['BOT'] ?? []) as $key => $bot) {
            if (is_array($bot)) {
                return (string) ($bot['BOT_ID'] ?? $key);
            }
        }

        return ! empty($data['BOT_ID']) ? (string) $data['BOT_ID'] : null;
    }
}
