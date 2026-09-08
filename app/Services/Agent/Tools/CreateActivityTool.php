<?php

namespace App\Services\Agent\Tools;

use App\Services\Bitrix\BitrixCrmService;

class CreateActivityTool implements ToolInterface
{
    protected BitrixCrmService $crmService;

    public function __construct()
    {
        $this->crmService = app(BitrixCrmService::class);
    }

    public function getName(): string
    {
        return 'create_activity';
    }

    public function getDescription(): string
    {
        return 'Crear una actividad o tarea para un responsable en Bitrix24';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'owner_id' => [
                    'type' => 'string',
                    'description' => 'ID del registro propietario (negocio o contacto)',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'Asunto de la actividad',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Descripcion detallada de la actividad',
                ],
                'responsible_id' => [
                    'type' => 'string',
                    'description' => 'ID del responsable en Bitrix24',
                ],
                'deadline' => [
                    'type' => 'string',
                    'description' => 'Fecha limite en formato YYYY-MM-DD HH:MM:SS',
                ],
            ],
            'required' => ['owner_id', 'subject', 'responsible_id'],
        ];
    }

    public function execute(array $parameters): mixed
    {
        $required = ['owner_id', 'subject', 'responsible_id'];

        foreach ($required as $field) {
            if (empty($parameters[$field])) {
                return ['error' => "Campo requerido: {$field}"];
            }
        }

        $result = $this->crmService->createActivity([
            'owner_id' => $parameters['owner_id'],
            'subject' => $parameters['subject'],
            'description' => $parameters['description'] ?? '',
            'responsible_id' => $parameters['responsible_id'],
            'deadline' => $parameters['deadline'] ?? null,
        ]);

        if (isset($result['error'])) {
            return ['success' => false, 'message' => 'Error al crear la actividad'];
        }

        return [
            'success' => true,
            'message' => 'Actividad creada correctamente',
            'activity_id' => $result['activity'] ?? null,
        ];
    }
}
