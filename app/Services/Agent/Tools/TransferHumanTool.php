<?php

namespace App\Services\Agent\Tools;

use App\Models\Bot;
use App\Services\Bitrix\OpenChannelService;
use Illuminate\Support\Facades\Log;

class TransferHumanTool implements ToolInterface
{
    protected OpenChannelService $openChannelService;

    public function __construct()
    {
        $this->openChannelService = app(OpenChannelService::class);
    }

    public function getName(): string
    {
        return 'transfer_to_human';
    }

    public function getDescription(): string
    {
        return 'Transferir la conversacion a un operador humano cuando la IA no puede resolver la consulta o el cliente solicita hablar con una persona';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => [
                    'type' => 'string',
                    'description' => 'Razon de la transferencia',
                ],
            ],
        ];
    }

    public function execute(array $parameters): mixed
    {
        $reason = $parameters['reason'] ?? 'Transferencia solicitada';

        Log::info('Transferencia a humano solicitada', ['reason' => $reason]);

        $chatId = $parameters['chat_id'] ?? null;
        $botId = $parameters['bot_id'] ?? null;

        $transferResult = ['transfer' => true, 'reason' => $reason];

        $menuEnabled = false;
        if ($botId) {
            $bot = Bot::withoutGlobalScope('tenant')->find($botId);
            $menuEnabled = $bot && $bot->openline_id !== null && $bot->menuEnabled();
        }

        if ($menuEnabled) {
            $transferResult['menu'] = true;
            $transferResult['message'] = 'El destino se resuelve por el menu de canales del bot';

            return $transferResult;
        }

        if ($chatId) {
            $result = $this->openChannelService->transferToFreeOperator($chatId);

            if (! isset($result['error'])) {
                $transferResult['success'] = true;
                $transferResult['message'] = 'Conversacion transferida a un operador humano';
            } else {
                Log::error('Error en transferencia a operador libre', [
                    'chat_id' => $chatId,
                    'error' => $result,
                ]);
                $transferResult['success'] = false;
                $transferResult['error'] = $result['message'] ?? 'No se pudo transferir';
            }
        } else {
            $transferResult['success'] = true;
            $transferResult['message'] = 'Transferencia marcada en el sistema local';
        }

        return $transferResult;
    }
}
