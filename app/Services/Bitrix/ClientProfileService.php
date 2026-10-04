<?php

namespace App\Services\Bitrix;

use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class ClientProfileService
{
    public function __construct(
        protected OpenChannelService $openChannel,
        protected BitrixCrmService $crm,
    ) {}

    /**
     * Capture the client data that Bitrix already knows about an open-line
     * chat from the CRM entity linked to the dialog (name, last name, phone,
     * email, locality and company). The chat name is deliberately ignored:
     * livechat generates it (e.g. "Green Guest #17 - <page title>") and it is
     * not a reliable visitor name. Persists the result on the conversation
     * (once per conversation; a null client_data means it was never attempted,
     * an empty array that nothing was found).
     */
    public function capture(Conversation $conversation): void
    {
        if ($conversation->client_data !== null) {
            return;
        }

        $dialog = $this->openChannel->getDialog($conversation->bitrix_chat_id);

        if (isset($dialog['error'])) {
            Log::warning('ClientProfileService: no se pudo leer el dialogo', [
                'conversation_id' => $conversation->id,
                'chat_id' => $conversation->bitrix_chat_id,
                'error' => $dialog,
            ]);

            return;
        }

        if (($dialog['entity_type'] ?? '') !== 'LINES') {
            return;
        }

        $profile = [];
        $entity = $this->entityFromData((string) ($dialog['entity_data_1'] ?? ''));

        if ($entity !== null) {
            $profile = array_merge($profile, $this->entityProfile($entity));
        }

        $updates = ['client_data' => $profile];

        if ($entity !== null && $entity['type'] === 'CONTACT') {
            $updates['contact_id'] = (string) $entity['id'];
        }

        $conversation->update($updates);

        Log::info('Datos del cliente capturados del open channel', [
            'conversation_id' => $conversation->id,
            'client_data' => $profile,
        ]);
    }

    /**
     * Extract the CRM entity (type + id) that the open line registered for the
     * dialog from entity_data_1, whose canonical shape is
     * "Y|LEAD|1209|N|N|343|1773682918|0|0|0". Returns null when no entity is
     * bound or the string is not parseable.
     *
     * @return array{type: string, id: int}|null
     */
    protected function entityFromData(string $entityData): ?array
    {
        $parts = explode('|', $entityData);
        $type = strtoupper($parts[1] ?? '');
        $id = (int) ($parts[2] ?? 0);

        if (($parts[0] ?? '') !== 'Y' || ! in_array($type, ['LEAD', 'CONTACT'], true) || $id <= 0) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * @param  array{type: string, id: int}  $entity
     */
    protected function entityProfile(array $entity): array
    {
        $data = $entity['type'] === 'CONTACT'
            ? $this->crm->getContact((string) $entity['id'])
            : $this->crm->getLead((string) $entity['id']);

        if (! $data) {
            return [];
        }

        $profile = [
            'crm_entity_type' => $entity['type'],
            'crm_entity_id' => $entity['id'],
        ];

        foreach (['NAME' => 'name', 'LAST_NAME' => 'last_name', 'ADDRESS_CITY' => 'locality'] as $field => $key) {
            $value = trim((string) ($data[$field] ?? ''));

            if ($value !== '') {
                $profile[$key] = $value;
            }
        }

        foreach (['PHONE' => 'phone', 'EMAIL' => 'email'] as $field => $key) {
            $value = $this->firstMultiValue($data[$field] ?? []);

            if ($value !== '') {
                $profile[$key] = $value;
            }
        }

        $company = $this->companyTitle($entity, $data);

        if ($company !== '') {
            $profile['company'] = $company;
        }

        return $profile;
    }

    /**
     * Extract the first non-empty value from a CRM multifield list (e.g. the
     * "VALUE" of a PHONE/EMAIL entry).
     */
    protected function firstMultiValue(mixed $values): string
    {
        foreach ((array) $values as $item) {
            if (! empty($item['VALUE'])) {
                return trim((string) $item['VALUE']);
            }
        }

        return '';
    }

    /**
     * Resolve the company title for the linked CRM entity: leads carry
     * COMPANY_TITLE, contacts need a lookup by COMPANY_ID.
     *
     * @param  array{type: string, id: int}  $entity
     */
    protected function companyTitle(array $entity, array $data): string
    {
        $company = trim((string) ($data['COMPANY_TITLE'] ?? ''));

        if ($company !== '' || ($entity['type'] !== 'CONTACT' && empty($data['COMPANY_ID']))) {
            return $company;
        }

        $companyData = $this->crm->searchCompany((string) $data['COMPANY_ID']);

        return trim((string) ($companyData['TITLE'] ?? ''));
    }
}
