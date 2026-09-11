<?php

namespace App\Services\Bitrix;

use App\Models\Bot;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Log;

class BitrixBotService
{
    protected BitrixService $bitrix;

    public function __construct(BitrixService $bitrix)
    {
        $this->bitrix = $bitrix;
    }

    protected function webhookUrl(string $memberId): string
    {
        $appUrl = rtrim((string) env('APP_URL', ''), '/');

        if ($appUrl === '' || str_contains($appUrl, 'localhost')) {
            $appUrl = resolve(BitrixBotProvisioningService::class)->resolveAppUrl();
        }

        return $appUrl.'/api/bitrix/webhook?member='.urlencode($memberId);
    }

    public function registerBot(array $params): array
    {
        $eventHandler = $this->webhookUrl(TenantContext::memberId() ?? '');

        $payload = [
            'CODE' => $params['code'] ?? 'assistant_'.time(),
            'TYPE' => 'O',
            'OPENLINE' => 'Y',
            'EVENT_HANDLER' => $eventHandler,
            'PROPERTIES' => [
                'NAME' => $params['name'],
                'WORK_POSITION' => $params['work_position'] ?? 'Asistente de Open Channels',
            ],
        ];

        if (! empty($params['client_id'])) {
            $payload['CLIENT_ID'] = $params['client_id'];
        }

        $result = $this->bitrix->request('imbot.register', $payload);

        if (isset($result['error'])) {
            Log::error('Error registering Bitrix bot', ['result' => $result]);

            return $result;
        }

        return $result;
    }

    public function updateBot(string $botId, array $params): array
    {
        $eventHandler = $this->webhookUrl(TenantContext::memberId() ?? '');

        $payload = [
            'EVENT_HANDLER' => $eventHandler,
        ];

        if (! empty($params['name'])) {
            $payload['PROPERTIES']['NAME'] = $params['name'];
        }

        $result = $this->bitrix->request('imbot.update', ['BOT_ID' => $botId] + $payload);

        if (isset($result['error'])) {
            Log::error('Error updating Bitrix bot', ['result' => $result]);

            return $result;
        }

        return $result;
    }

    public function sendMessage(string $chatId, string $message, array $options = []): array
    {
        $params = [
            'DIALOG_ID' => $chatId,
            'MESSAGE' => $message,
        ];

        if (isset($options['keyboard'])) {
            $params['KEYBOARD'] = $options['keyboard'];
        }

        if (isset($options['attachment'])) {
            $params['ATTACHMENT'] = $options['attachment'];
        }

        return $this->bitrix->request('im.message.add', $params);
    }

    /**
     * Send a message on behalf of a Chatbot 2.0 (imbot.v2) bot.
     * Requires the bot's own botToken, which is stored on the Bot model.
     */
    public function sendV2Message(Bot $bot, string $dialogId, string $message, array $options = []): array
    {
        $fields = ['message' => $message];

        if (isset($options['keyboard'])) {
            $fields['keyboard'] = $options['keyboard'];
        }

        return $this->bitrix->request('imbot.v2.Chat.Message.send', [
            'botId' => (int) $bot->bitrix_bot_id,
            'botToken' => $bot->bot_token,
            'dialogId' => $dialogId,
            'fields' => $fields,
        ]);
    }

    public function getChatMessages(string $chatId, int $limit = 50): array
    {
        return $this->bitrix->get('im.message.get', [
            'DIALOG_ID' => $chatId,
            'LIMIT' => $limit,
        ]);
    }

    public function getOpenChannels(): array
    {
        $result = $this->bitrix->get('imopenlines.config.list.get', [
            'PARAMS' => [
                'select' => ['ID', 'LINE_NAME', 'ACTIVE'],
                'order' => ['ID' => 'ASC'],
                'limit' => 200,
                'offset' => 0,
            ],
        ]);

        if (isset($result['error'])) {
            Log::warning('Error obteniendo canales abiertos', ['error' => $result]);

            return [];
        }

        return $result;
    }
}
