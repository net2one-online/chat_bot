<?php

namespace App\Services\Bitrix;

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

    public function getLead(string $leadId): ?array
    {
        $result = $this->bitrix->get('crm.lead.get', [
            'ID' => $leadId,
        ]);

        if (isset($result['error']) || empty($result)) {
            return null;
        }

        return $result;
    }

    public function updateLead(string $id, array $fields): array
    {
        return $this->bitrix->request('crm.lead.update', [
            'id' => $id,
            'fields' => $fields,
        ]);
    }

    public function updateContact(string $id, array $fields): array
    {
        return $this->bitrix->request('crm.contact.update', [
            'ID' => $id,
            'FIELDS' => $fields,
        ]);
    }

    /**
     * Find CRM entity ids (leads/contacts/companies) holding the given phone
     * number or email address. Returns the raw result (e.g. ["CONTACT" => []])
     * or an empty array on error.
     */
    public function findDuplicates(string $type, array $values, string $entityType): array
    {
        $result = $this->bitrix->get('crm.duplicate.findbycomm', [
            'type' => $type,
            'values' => $values,
            'entity_type' => $entityType,
        ]);

        if (isset($result['error'])) {
            return [];
        }

        return is_array($result) ? $result : [];
    }

    /**
     * Find an existing company whose title matches exactly (case-insensitive).
     */
    public function findCompanyByTitle(string $title): ?int
    {
        foreach ($this->searchCompanies($title) as $company) {
            if (mb_strtolower(trim((string) ($company['TITLE'] ?? ''))) === mb_strtolower(trim($title))) {
                $id = (int) ($company['ID'] ?? 0);

                if ($id > 0) {
                    return $id;
                }
            }
        }

        return null;
    }

    public function createCompany(string $title): ?int
    {
        $result = $this->bitrix->request('crm.company.add', [
            'fields' => ['TITLE' => $title],
        ]);

        if (isset($result['error'])) {
            return null;
        }

        $id = (int) ($result['result'] ?? 0);

        return $id > 0 ? $id : null;
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
