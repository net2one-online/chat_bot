<?php

namespace App\Services\Bitrix;

use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

/**
 * Writes client information collected during the conversation back to the
 * CRM entity linked to the open-line dialog. The linked lead is reused (or
 * created via imopenlines.crm.lead.create when the chat has no entity yet),
 * duplicates found by phone/e-mail are linked instead, the company is reused
 * or created, and the result is persisted on the conversation client_data.
 */
class ClientRegistrationService
{
    protected BitrixCrmService $crm;

    protected OpenChannelService $openChannel;

    public function __construct(BitrixCrmService $crm, OpenChannelService $openChannel)
    {
        $this->crm = $crm;
        $this->openChannel = $openChannel;
    }

    /**
     * @param  array<string, string>  $fields  Keys: name, last_name, phone, email, company, locality.
     * @return array{success: bool, error?: string, message?: string, client_data?: array<string, mixed>}
     */
    public function register(Conversation $conversation, array $fields): array
    {
        $fields = $this->cleanFields($fields);

        if ($fields === []) {
            return ['success' => false, 'error' => 'No hay datos de contacto que registrar'];
        }

        $entity = $this->resolveEntity($conversation);

        if ($entity === null) {
            return ['success' => false, 'error' => 'No se pudo vincular la conversacion a una entidad del CRM'];
        }

        if (! in_array($entity['type'], ['CONTACT', 'LEAD'], true)) {
            return ['success' => false, 'error' => 'La entidad vinculada no es un lead ni un contacto'];
        }

        $entity = $this->findDuplicateEntity($entity, $fields) ?? $entity;

        if (($fields['company'] ?? '') !== '') {
            $companyId = $this->companyId($fields['company']);

            if ($companyId !== null) {
                $fields['company_id'] = $companyId;
            }
        }

        $updated = $this->persistEntity($entity, $fields);

        if (isset($updated['error'])) {
            return ['success' => false, 'error' => $this->humanError($updated)];
        }

        $clientData = array_merge($conversation->client_data ?? [], $this->clientData($fields));
        $clientData['crm_entity_type'] = $entity['type'];
        $clientData['crm_entity_id'] = $entity['id'];

        $updates = ['client_data' => $clientData];

        if ($entity['type'] === 'CONTACT') {
            $updates['contact_id'] = (string) $entity['id'];
        }

        $conversation->update($updates);

        Log::info('Datos del cliente registrados en el CRM', [
            'conversation_id' => $conversation->id,
            'entity' => $entity,
            'client_data' => $clientData,
        ]);

        $label = $entity['type'] === 'CONTACT' ? 'contacto' : 'lead';

        return [
            'success' => true,
            'message' => "Datos del cliente registrados en el {$label} #{$entity['id']} del CRM.",
            'client_data' => $clientData,
        ];
    }

    protected function resolveEntity(Conversation $conversation): ?array
    {
        $clientData = $conversation->client_data;

        if (is_array($clientData)) {
            $type = (string) ($clientData['crm_entity_type'] ?? '');
            $id = (int) ($clientData['crm_entity_id'] ?? 0);

            if ($type !== '' && $id > 0) {
                return ['type' => $type, 'id' => $id];
            }
        }

        $dialog = $this->openChannel->getDialog($conversation->bitrix_chat_id);

        if (isset($dialog['error']) || ($dialog['entity_type'] ?? '') !== 'LINES') {
            return null;
        }

        $entity = $this->entityFromData((string) ($dialog['entity_data_1'] ?? ''));

        if ($entity !== null) {
            return $entity;
        }

        $created = $this->openChannel->createLeadFromChat($conversation->bitrix_chat_id);

        if (isset($created['error'])) {
            return null;
        }

        $dialog = $this->openChannel->getDialog($conversation->bitrix_chat_id);

        return $this->entityFromData((string) ($dialog['entity_data_1'] ?? ''));
    }

    protected function findDuplicateEntity(array $entity, array $fields): ?array
    {
        foreach (['PHONE' => 'phone', 'EMAIL' => 'email'] as $crmType => $key) {
            $value = (string) ($fields[$key] ?? '');

            if ($value === '') {
                continue;
            }

            $contactId = $this->firstEntityId($this->crm->findDuplicates($crmType, [$value], 'CONTACT'), 'CONTACT');

            if ($contactId !== null) {
                return ['type' => 'CONTACT', 'id' => (int) $contactId];
            }

            $leadId = $this->firstEntityId($this->crm->findDuplicates($crmType, [$value], 'LEAD'), 'LEAD');

            if ($leadId !== null && $entity['type'] === 'LEAD' && $entity['id'] !== (int) $leadId) {
                return ['type' => 'LEAD', 'id' => (int) $leadId];
            }
        }

        return null;
    }

    protected function firstEntityId(array $result, string $entityType): ?string
    {
        foreach ($result[$entityType] ?? [] as $id) {
            if ((string) $id !== '') {
                return (string) $id;
            }
        }

        return null;
    }

    protected function companyId(string $title): ?int
    {
        return $this->crm->findCompanyByTitle($title) ?? $this->crm->createCompany($title);
    }

    protected function persistEntity(array $entity, array $fields): array
    {
        $crmFields = [];

        foreach (['name' => 'NAME', 'last_name' => 'LAST_NAME', 'locality' => 'ADDRESS_CITY'] as $key => $field) {
            if (($fields[$key] ?? '') !== '') {
                $crmFields[$field] = $fields[$key];
            }
        }

        if (($fields['phone'] ?? '') !== '') {
            $crmFields['PHONE'] = [['VALUE' => $fields['phone'], 'VALUE_TYPE' => 'WORK']];
        }

        if (($fields['email'] ?? '') !== '') {
            $crmFields['EMAIL'] = [['VALUE' => $fields['email'], 'VALUE_TYPE' => 'WORK']];
        }

        if (($fields['company_id'] ?? '') !== '') {
            $crmFields['COMPANY_ID'] = $fields['company_id'];
        } elseif (($fields['company'] ?? '') !== '' && $entity['type'] === 'LEAD') {
            $crmFields['COMPANY_TITLE'] = $fields['company'];
        }

        if ($crmFields === []) {
            return ['result' => true];
        }

        return $entity['type'] === 'CONTACT'
            ? $this->crm->updateContact($entity['id'], $crmFields)
            : $this->crm->updateLead($entity['id'], $crmFields);
    }

    protected function cleanFields(array $fields): array
    {
        $allowed = ['name', 'last_name', 'phone', 'email', 'company', 'locality'];
        $clean = [];

        foreach ($fields as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $value = trim((string) ($value ?? ''));

            if ($value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    protected function clientData(array $fields): array
    {
        $data = [];

        foreach (['name', 'last_name', 'phone', 'email', 'company', 'locality'] as $key) {
            $value = $fields[$key] ?? '';

            if ($value !== '' && $value !== null) {
                $data[$key] = (string) $value;
            }
        }

        return $data;
    }

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

    protected function humanError(array $updated): string
    {
        $message = (string) ($updated['error_description'] ?? $updated['message'] ?? 'No se pudo registrar los datos en el CRM');

        return str_contains($message, 'error') ? $message : "No se pudo registrar los datos en el CRM: {$message}";
    }
}
