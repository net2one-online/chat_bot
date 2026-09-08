<?php

namespace App\Services\Agent\Tools;

use App\Models\KnowledgeDocument;
use App\Services\Bitrix\BitrixOAuthService;
use App\Services\Bitrix\OpenChannelService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

class SendFileTool implements ToolInterface
{
    protected OpenChannelService $openChannelService;

    public function __construct()
    {
        $this->openChannelService = app(OpenChannelService::class);
    }

    public function getName(): string
    {
        return 'send_file';
    }

    public function getDescription(): string
    {
        return 'Enviar un archivo adjunto (PDF, imagen u otro) al cliente en la conversacion. Usar exclusivamente cuando la base de conocimiento lo especifica explicitamente (por ejemplo: "enviar manual.pdf" o "adjuntar catalogo.pdf").';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file_name' => [
                    'type' => 'string',
                    'description' => 'Nombre del archivo con extension que se debe enviar, tal como figura en la base de conocimiento (ej: manual.pdf, catalogo.png).',
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'Mensaje breve que acompanara al archivo',
                ],
            ],
            'required' => ['file_name'],
        ];
    }

    public function execute(array $parameters): mixed
    {
        $fileName = $parameters['file_name'] ?? '';
        $message = $parameters['message'] ?? '';
        $chatId = $parameters['chat_id'] ?? null;
        $botId = $parameters['bot_id'] ?? null;

        if (! $fileName) {
            return ['success' => false, 'error' => 'No se indico el nombre del archivo a enviar'];
        }

        $document = KnowledgeDocument::where('file_name', $fileName)
            ->whereNotNull('file_data')
            ->when($botId, fn ($q) => $q->where('bot_id', $botId))
            ->first();

        if (! $document) {
            Log::warning('Archivo no encontrado en base de conocimiento', ['file_name' => $fileName]);

            return [
                'success' => false,
                'error' => "No existe el archivo {$fileName} en la base de conocimiento. Informar al cliente en texto que no hay disponible.",
            ];
        }

        $bot = $document->bot;

        $domain = $this->openChannelService->getDomain();

        $token = $domain
            ? app(BitrixOAuthService::class)->fileBotForDomain($domain)
            : null;

        if ($token) {
            $bitrixBotId = (int) $token->file_bot_id;
            $botToken = (string) $token->file_bot_token;
        } else {
            $settings = app(SettingsService::class);
            $bitrixBotId = (int) $settings->get('bitrix.file_bot_id', $bot ? $bot->bitrix_bot_id : 0);
            $botToken = (string) $settings->get('bitrix.file_bot_token', $bot ? $bot->bot_token : '');
        }

        if (! $chatId || ! $bitrixBotId || ! $botToken) {
            return ['success' => false, 'error' => 'No se dispone de chat o bot para enviar el archivo'];
        }

        $fileData = is_resource($document->file_data)
            ? stream_get_contents($document->file_data)
            : (string) $document->file_data;

        $result = $this->openChannelService->sendFile(
            $chatId,
            $bitrixBotId,
            $botToken,
            $document->file_name,
            $document->file_mime ?? 'application/octet-stream',
            $fileData,
            $message
        );

        if (isset($result['error'])) {
            Log::error('Error enviando archivo a Bitrix', [
                'file_name' => $document->file_name,
                'error' => $result,
            ]);

            return [
                'success' => false,
                'error' => $result['message'] ?? 'No se pudo enviar el archivo',
            ];
        }

        Log::info('Archivo enviado a Bitrix', [
            'file_name' => $document->file_name,
            'result' => $result,
        ]);

        return [
            'success' => true,
            'message' => "Archivo {$document->file_name} enviado al cliente",
        ];
    }
}
