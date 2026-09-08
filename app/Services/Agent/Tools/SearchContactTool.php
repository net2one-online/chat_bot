<?php

namespace App\Services\Agent\Tools;

use App\Services\Bitrix\BitrixCrmService;

class SearchContactTool implements ToolInterface
{
    protected BitrixCrmService $crmService;

    public function __construct()
    {
        $this->crmService = app(BitrixCrmService::class);
    }

    public function getName(): string
    {
        return 'search_contact';
    }

    public function getDescription(): string
    {
        return 'Buscar un contacto en Bitrix24 por telefono, correo electronico o nombre';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Telefono, correo electronico o nombre del contacto',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $parameters): mixed
    {
        $query = $parameters['query'] ?? '';

        if (empty($query)) {
            return ['error' => 'Se requiere un termino de busqueda'];
        }

        $contacts = $this->crmService->searchContact($query);

        if (empty($contacts)) {
            return ['found' => false, 'message' => 'No se encontraron contactos'];
        }

        $results = [];
        foreach (array_slice($contacts, 0, 5) as $contact) {
            $results[] = [
                'id' => $contact['ID'] ?? null,
                'name' => trim(($contact['NAME'] ?? '') . ' ' . ($contact['LAST_NAME'] ?? '')),
                'company_id' => $contact['COMPANY_ID'] ?? null,
            ];
        }

        return ['found' => true, 'contacts' => $results];
    }
}
