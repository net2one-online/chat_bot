<?php

namespace App\Services\Agent\Tools;

use App\Services\Bitrix\BitrixCrmService;

class SearchCompanyTool implements ToolInterface
{
    protected BitrixCrmService $crmService;

    public function __construct()
    {
        $this->crmService = app(BitrixCrmService::class);
    }

    public function getName(): string
    {
        return 'search_company';
    }

    public function getDescription(): string
    {
        return 'Buscar informacion de una empresa en Bitrix24';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Nombre de la empresa a buscar',
                ],
                'company_id' => [
                    'type' => 'string',
                    'description' => 'ID de la empresa en Bitrix24',
                ],
            ],
        ];
    }

    public function execute(array $parameters): mixed
    {
        $companyId = $parameters['company_id'] ?? null;
        $query = $parameters['query'] ?? null;

        if ($companyId) {
            $company = $this->crmService->searchCompany($companyId);

            if (!$company) {
                return ['found' => false, 'message' => 'Empresa no encontrada'];
            }

            return [
                'found' => true,
                'company' => [
                    'id' => $company['ID'] ?? null,
                    'title' => $company['TITLE'] ?? 'Sin nombre',
                    'phone' => $company['PHONE'] ?? null,
                    'address' => $company['ADDRESS'] ?? null,
                ],
            ];
        }

        if ($query) {
            $companies = $this->crmService->searchCompanies($query);

            if (empty($companies)) {
                return ['found' => false, 'message' => 'No se encontraron empresas'];
            }

            $results = [];
            foreach (array_slice($companies, 0, 5) as $company) {
                $results[] = [
                    'id' => $company['ID'] ?? null,
                    'title' => $company['TITLE'] ?? 'Sin nombre',
                ];
            }

            return ['found' => true, 'companies' => $results];
        }

        return ['error' => 'Se requiere un query o company_id'];
    }
}
