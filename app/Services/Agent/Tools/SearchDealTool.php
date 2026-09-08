<?php

namespace App\Services\Agent\Tools;

use App\Services\Bitrix\BitrixCrmService;

class SearchDealTool implements ToolInterface
{
    protected BitrixCrmService $crmService;

    public function __construct()
    {
        $this->crmService = app(BitrixCrmService::class);
    }

    public function getName(): string
    {
        return 'search_deals';
    }

    public function getDescription(): string
    {
        return 'Buscar negociaciones o deals relacionados con un contacto';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'contact_id' => [
                    'type' => 'string',
                    'description' => 'ID del contacto en Bitrix24',
                ],
            ],
            'required' => ['contact_id'],
        ];
    }

    public function execute(array $parameters): mixed
    {
        $contactId = $parameters['contact_id'] ?? '';

        if (empty($contactId)) {
            return ['error' => 'Se requiere el ID del contacto'];
        }

        $deals = $this->crmService->searchDeals($contactId);

        if (empty($deals)) {
            return ['found' => false, 'message' => 'No se encontraron negociaciones'];
        }

        $results = [];
        foreach (array_slice($deals, 0, 5) as $deal) {
            $results[] = [
                'id' => $deal['ID'] ?? null,
                'title' => $deal['TITLE'] ?? 'Sin titulo',
                'stage' => $deal['STAGE_ID'] ?? 'N/A',
                'opportunity' => $deal['OPPORTUNITY'] ?? null,
            ];
        }

        return ['found' => true, 'deals' => $results];
    }
}
