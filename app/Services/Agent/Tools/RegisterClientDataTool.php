<?php

namespace App\Services\Agent\Tools;

use App\Models\Conversation;
use App\Services\Bitrix\ClientRegistrationService;
use Illuminate\Support\Facades\Log;

class RegisterClientDataTool implements ToolInterface
{
    protected ClientRegistrationService $registrationService;

    public function __construct()
    {
        $this->registrationService = app(ClientRegistrationService::class);
    }

    public function getName(): string
    {
        return 'register_client_data';
    }

    public function getDescription(): string
    {
        return 'Registrar en el CRM los datos de contacto que el cliente indico en la conversacion (nombre, apellido, telefono, email, empresa o localidad). Llamala solo cuando el cliente entregue alguno de estos datos de forma explicita.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Nombre del cliente'],
                'last_name' => ['type' => 'string', 'description' => 'Apellido del cliente'],
                'phone' => ['type' => 'string', 'description' => 'Numero de telefono del cliente'],
                'email' => ['type' => 'string', 'description' => 'Correo electronico del cliente'],
                'company' => ['type' => 'string', 'description' => 'Empresa o compania del cliente'],
                'locality' => ['type' => 'string', 'description' => 'Ciudad o localidad del cliente'],
            ],
        ];
    }

    public function execute(array $parameters): mixed
    {
        $conversationId = $parameters['conversation_id'] ?? null;

        if (! $conversationId) {
            return ['success' => false, 'error' => 'Falta la conversacion de contexto'];
        }

        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            return ['success' => false, 'error' => 'Conversacion no encontrada'];
        }

        $fields = [
            'name' => (string) ($parameters['name'] ?? ''),
            'last_name' => (string) ($parameters['last_name'] ?? ''),
            'phone' => (string) ($parameters['phone'] ?? ''),
            'email' => (string) ($parameters['email'] ?? ''),
            'company' => (string) ($parameters['company'] ?? ''),
            'locality' => (string) ($parameters['locality'] ?? ''),
        ];

        Log::info('Tool register_client_data llamada', [
            'conversation_id' => $conversationId,
            'client_id' => $conversation->bitrix_chat_id,
            'fields' => $fields,
        ]);

        return $this->registrationService->register($conversation, $fields);
    }
}
