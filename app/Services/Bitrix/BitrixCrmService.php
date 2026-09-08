<?php

namespace App\Services\Bitrix;

use Illuminate\Support\Facades\Log;

class BitrixCrmService
{
    protected BitrixService $bitrix;

    public function __construct(BitrixService $bitrix)
    {
        $this->bitrix = $bitrix;
    }

    public function searchContact(string $query): array
    {
        $result = $this->bitrix->get('crm.contact.list', [
            'filter' => [
                'PHONE' => $query,
                'EMAIL' => $query,
            ],
            'select' => ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'COMPANY_ID'],
            'order' => ['DATE_CREATE' => 'DESC'],
        ]);

        if (isset($result['error'])) {
            return [];
        }

        $contacts = is_array($result) ? ($result['contacts'] ?? $result) : [];

        if (empty($contacts)) {
            $result = $this->bitrix->get('crm.contact.list', [
                'filter' => [
                    'NAME' => $query,
                ],
                'select' => ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'COMPANY_ID'],
                'order' => ['DATE_CREATE' => 'DESC'],
            ]);

            $contacts = is_array($result) ? ($result['contacts'] ?? $result) : [];
        }

        return $contacts;
    }

    public function getContact(string $contactId): ?array
    {
        $result = $this->bitrix->get('crm.contact.get', [
            'ID' => $contactId,
        ]);

        if (isset($result['error']) || empty($result)) {
            return null;
        }

        return $result;
    }

    public function searchDeals(string $contactId): array
    {
        $result = $this->bitrix->get('crm.deal.list', [
            'filter' => [
                'CONTACT_ID' => $contactId,
            ],
            'select' => ['ID', 'TITLE', 'STAGE_ID', 'DATE_CREATE', 'OPPORTUNITY', 'CURRENCY_ID'],
            'order' => ['DATE_CREATE' => 'DESC'],
        ]);

        if (isset($result['error'])) {
            return [];
        }

        return is_array($result) ? ($result['deals'] ?? $result) : [];
    }

    public function searchCompany(string $companyId): ?array
    {
        $result = $this->bitrix->get('crm.company.get', [
            'ID' => $companyId,
        ]);

        if (isset($result['error']) || empty($result)) {
            return null;
        }

        return $result;
    }

    public function searchCompanies(string $query): array
    {
        $result = $this->bitrix->get('crm.company.list', [
            'filter' => [
                'TITLE' => $query,
            ],
            'select' => ['ID', 'TITLE', 'PHONE', 'ADDRESS'],
            'order' => ['DATE_CREATE' => 'DESC'],
        ]);

        if (isset($result['error'])) {
            return [];
        }

        return is_array($result) ? ($result['companies'] ?? $result) : [];
    }

    public function createActivity(array $params): array
    {
        return $this->bitrix->request('crm.activity.add', [
            'fields' => [
                'OWNER_TYPE_ID' => $params['owner_type_id'] ?? 3,
                'OWNER_ID' => $params['owner_id'],
                'TYPE_ID' => $params['type_id'] ?? 4,
                'SUBJECT' => $params['subject'],
                'DESCRIPTION' => $params['description'] ?? '',
                'RESPONSIBLE_ID' => $params['responsible_id'],
                'DEADLINE' => $params['deadline'] ?? null,
                'COMPLETED' => 'N',
            ],
        ]);
    }
}
