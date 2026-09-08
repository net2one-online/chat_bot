<?php

namespace App\Services\Bitrix;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenChannelService
{
    protected BitrixService $bitrix;

    public function __construct(BitrixService $bitrix)
    {
        $this->bitrix = $bitrix;
    }

    public function listChannels(): array
    {
        $result = $this->bitrix->get('imopenlines.config.list.get', [
            'PARAMS' => [
                'select' => ['ID', 'LINE_NAME', 'ACTIVE'],
                'order' => ['ID' => 'ASC'],
                'limit' => 200,
                'offset' => 0,
            ],
            'OPTIONS' => [
                'QUEUE' => 'Y',
                'CONFIG_QUEUE' => 'Y',
            ],
        ]);

        if (isset($result['error'])) {
            Log::warning('Error listando canales abiertos', ['error' => $result]);

            return [];
        }

        return $result;
    }

    /**
     * Build a flat map of line id => queue members usable to populate the
     * welcome menu transfer targets, resolving user names from the payload.
     */
    public function channelQueues(): array
    {
        $queues = [];

        foreach ($this->listChannels() as $channel) {
            $lineId = (string) ($channel['ID'] ?? '');
            $lineName = (string) ($channel['LINE_NAME'] ?? '');
            $members = $channel['QUEUE'] ?? [];

            if ($lineId === '' || empty($members)) {
                continue;
            }

            $entityTypes = [];

            foreach ($channel['CONFIG_QUEUE'] ?? [] as $configItem) {
                $entityTypes[(string) ($configItem['ENTITY_ID'] ?? '')] = (string) ($configItem['ENTITY_TYPE'] ?? 'user');
            }

            foreach ($members as $entityId) {
                $entityId = (string) $entityId;
                $type = $entityTypes[$entityId] ?? 'user';
                $userFields = $channel['QUEUE_USERS_FIELDS'][$entityId] ?? [];
                $name = trim((string) ($userFields['USER_NAME'] ?? ''));

                $label = $name !== ''
                    ? $name.' ('.$lineName.')'
                    : ($type === 'department' ? 'Departamento '.$entityId.' ('.$lineName.')' : 'Operador '.$entityId.' ('.$lineName.')');

                $queues[$lineId][] = [
                    'entity_id' => $entityId,
                    'entity_type' => $type,
                    'label' => $label,
                ];
            }
        }

        return $queues;
    }

    public function getDomain(): string
    {
        return $this->bitrix->getDomain();
    }

    public function sendMessage(string $chatId, string $message): array
    {
        return $this->bitrix->request('imopenlines.bot.session.message.send', [
            'CHAT_ID' => $this->normalizeChatId($chatId),
            'NAME' => 'DEFAULT',
            'MESSAGE' => $message,
        ]);
    }

    public function sendFile(string $chatId, int $botId, string $botToken, string $fileName, string $fileMime, string $fileData, string $message = ''): array
    {
        $chatId = $this->normalizeChatId($chatId);
        $domain = $this->bitrix->getDomain();

        $oauth = app(BitrixOAuthService::class);
        $accessToken = $oauth->getAccessTokenForDomain($domain);

        if (! $accessToken) {
            return ['error' => true, 'message' => 'No hay token OAuth de la aplicacion para enviar archivos'];
        }

        $response = Http::timeout(30)->post("https://{$domain}/rest/imbot.v2.File.upload", [
            'auth' => $accessToken,
            'botId' => $botId,
            'dialogId' => "chat{$chatId}",
            'fields' => [
                'name' => $fileName,
                'content' => base64_encode($fileData),
                'message' => $message,
            ],
        ]);

        if ($response->failed()) {
            Log::error('Error de API en sendFile', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => true, 'message' => $response->json('error_description') ?? 'No se pudo enviar el archivo'];
        }

        return $response->json('result') ?? ['result' => $response->json()];
    }

    public function transferToFreeOperator(string $chatId): array
    {
        return $this->bitrix->request('imopenlines.bot.session.operator', [
            'CHAT_ID' => $this->normalizeChatId($chatId),
        ]);
    }

    public function transferToOperator(string $chatId, int $userId): array
    {
        return $this->bitrix->request('imopenlines.bot.session.transfer', [
            'CHAT_ID' => $this->normalizeChatId($chatId),
            'USER_ID' => $userId,
        ]);
    }

    public function transferToQueue(string $chatId): array
    {
        return $this->bitrix->request('imopenlines.bot.session.transfer', [
            'CHAT_ID' => $this->normalizeChatId($chatId),
            'TRANSFER_ID' => 'queue',
        ]);
    }

    /**
     * Transfer the dialog to the queue of another open line (channel). This is
     * an operator method: the QUEUE_ID is the destination line ID returned by
     * imopenlines.config.list.get. Requires operator rights + imopenlines scope.
     */
    public function transferToLine(string $chatId, int $lineId): array
    {
        return $this->bitrix->request('imopenlines.operator.transfer', [
            'CHAT_ID' => $this->normalizeChatId($chatId),
            'QUEUE_ID' => $lineId,
        ]);
    }

    /**
     * Transfer the dialog to a queue member (operator) of the bot's open line.
     * Departments use QUEUE_ID, users use USER_ID; the bot leaves the chat so
     * it stops answering after the handoff.
     */
    public function transferToQueueMember(string $chatId, int $entityId, string $entityType = 'user', string $clientId = ''): array
    {
        $params = [
            'CHAT_ID' => $this->normalizeChatId($chatId),
            'LEAVE' => 'Y',
        ];

        if ($entityType === 'department') {
            $params['QUEUE_ID'] = $entityId;
        } else {
            $params['USER_ID'] = $entityId;
        }

        if ($clientId !== '') {
            $params['CLIENT_ID'] = $clientId;
        }

        return $this->bitrix->request('imopenlines.bot.session.transfer', $params);
    }

    public function finishSession(string $chatId): array
    {
        return $this->bitrix->request('imopenlines.bot.session.finish', [
            'CHAT_ID' => $this->normalizeChatId($chatId),
        ]);
    }

    public function getDialog(string $chatId): array
    {
        return $this->bitrix->get('imopenlines.dialog.get', [
            'CHAT_ID' => $this->normalizeChatId($chatId),
        ]);
    }

    protected function normalizeChatId(string $chatId): int
    {
        $chatId = trim($chatId);
        $chatId = preg_replace('/^chat/i', '', $chatId);
        $chatId = preg_replace('/^crm_/i', '', $chatId);

        return (int) $chatId;
    }
}
