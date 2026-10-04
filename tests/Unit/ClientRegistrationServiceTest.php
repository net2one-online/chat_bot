<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\Conversation;
use App\Services\Bitrix\BitrixCrmService;
use App\Services\Bitrix\ClientRegistrationService;
use App\Services\Bitrix\OpenChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(OpenChannelService $openChannel, BitrixCrmService $crm): ClientRegistrationService
    {
        return new ClientRegistrationService($crm, $openChannel);
    }

    private function conversation(): Conversation
    {
        $bot = Bot::create(['name' => 'Bot Open Line', 'openline_id' => 2]);

        return Conversation::create([
            'bot_id' => $bot->id,
            'bitrix_chat_id' => 'chat1234',
            'status' => 'active',
        ]);
    }

    private function crmWithFindDuplicatesEmpty(): BitrixCrmService
    {
        $crm = $this->createMock(BitrixCrmService::class);
        $crm->method('findDuplicates')->willReturn([]);

        return $crm;
    }

    public function test_registers_fields_on_linked_lead_and_persists_client_data(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->never())->method('getDialog');

        $crm = $this->crmWithFindDuplicatesEmpty();
        $crm->expects($this->once())
            ->method('findCompanyByTitle')
            ->with('Tostring S.A.')
            ->willReturn(null);
        $crm->expects($this->once())
            ->method('createCompany')
            ->with('Tostring S.A.')
            ->willReturn(777);
        $crm->expects($this->once())
            ->method('updateLead')
            ->with('1209', $this->callback(fn (array $fields): bool => $fields === [
                'NAME' => 'Alfonso',
                'LAST_NAME' => 'Podesta',
                'ADDRESS_CITY' => 'Panama',
                'PHONE' => [['VALUE' => '+50767205785', 'VALUE_TYPE' => 'WORK']],
                'EMAIL' => [['VALUE' => 'asurez@podesta.com', 'VALUE_TYPE' => 'WORK']],
                'COMPANY_ID' => 777,
            ]))
            ->willReturn(['result' => true]);

        $conversation = $this->conversation();
        $conversation->update(['client_data' => ['crm_entity_type' => 'LEAD', 'crm_entity_id' => 1209]]);

        $result = $this->service($openChannel, $crm)->register($conversation, [
            'name' => 'Alfonso',
            'last_name' => 'Podesta',
            'phone' => '+50767205785',
            'email' => 'asurez@podesta.com',
            'locality' => 'Panama',
            'company' => 'Tostring S.A.',
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('1209', $result['message']);

        $fresh = $conversation->fresh();
        $this->assertSame('Alfonso', $fresh->client_data['name']);
        $this->assertSame('Tostring S.A.', $fresh->client_data['company']);
        $this->assertSame('LEAD', $fresh->client_data['crm_entity_type']);
        $this->assertSame(1209, $fresh->client_data['crm_entity_id']);
    }

    public function test_creates_lead_bound_to_chat_when_no_entity_is_linked(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->exactly(2))
            ->method('getDialog')
            ->with('chat1234')
            ->willReturnOnConsecutiveCalls(
                ['entity_type' => 'LINES', 'entity_data_1' => 'N|0|0|N|N|55|1773682918|0|0|0'],
                ['entity_type' => 'LINES', 'entity_data_1' => 'Y|LEAD|1209|N|N|343|1773682918|0|0|0'],
            );
        $openChannel->expects($this->once())
            ->method('createLeadFromChat')
            ->with('chat1234')
            ->willReturn(['result' => true]);

        $crm = $this->crmWithFindDuplicatesEmpty();
        $crm->expects($this->once())
            ->method('updateLead')
            ->with('1209', ['NAME' => 'Alfonso'])
            ->willReturn(['result' => true]);

        $conversation = $this->conversation();

        $result = $this->service($openChannel, $crm)->register($conversation, ['name' => 'Alfonso']);

        $this->assertTrue($result['success']);
        $this->assertSame('LEAD', $conversation->fresh()->client_data['crm_entity_type']);
        $this->assertSame(1209, $conversation->fresh()->client_data['crm_entity_id']);
    }

    public function test_links_to_existing_contact_when_duplicate_is_found_by_phone(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->never())->method('getDialog');

        $crm = $this->createMock(BitrixCrmService::class);
        $crm->method('findDuplicates')
            ->willReturnCallback(fn (string $type, array $values, string $entityType): array => $entityType === 'CONTACT'
                ? ['CONTACT' => [275]]
                : []);
        $crm->expects($this->never())->method('findCompanyByTitle');
        $crm->expects($this->once())
            ->method('updateContact')
            ->with('275', ['PHONE' => [['VALUE' => '+50767205785', 'VALUE_TYPE' => 'WORK']]])
            ->willReturn(['result' => true]);

        $conversation = $this->conversation();
        $conversation->update(['client_data' => ['crm_entity_type' => 'LEAD', 'crm_entity_id' => 1209]]);

        $result = $this->service($openChannel, $crm)->register($conversation, ['phone' => '+50767205785']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('275', $result['message']);

        $fresh = $conversation->fresh();
        $this->assertSame('CONTACT', $fresh->client_data['crm_entity_type']);
        $this->assertSame(275, $fresh->client_data['crm_entity_id']);
        $this->assertSame('275', $fresh->contact_id);
    }

    public function test_reuses_existing_company_and_does_not_create_it(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->never())->method('getDialog');

        $crm = $this->crmWithFindDuplicatesEmpty();
        $crm->expects($this->once())
            ->method('findCompanyByTitle')
            ->with('Tostring S.A.')
            ->willReturn(42);
        $crm->expects($this->never())->method('createCompany');
        $crm->expects($this->once())
            ->method('updateLead')
            ->with('1209', ['NAME' => 'Alfonso', 'COMPANY_ID' => 42])
            ->willReturn(['result' => true]);

        $conversation = $this->conversation();
        $conversation->update(['client_data' => ['crm_entity_type' => 'LEAD', 'crm_entity_id' => 1209]]);

        $result = $this->service($openChannel, $crm)->register($conversation, [
            'name' => 'Alfonso',
            'company' => 'Tostring S.A.',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('Tostring S.A.', $conversation->fresh()->client_data['company']);
    }

    public function test_rejects_empty_fields_without_calling_bitrix(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->never())->method('getDialog');

        $crm = $this->createMock(BitrixCrmService::class);
        $crm->expects($this->never())->method('updateLead');

        $conversation = $this->conversation();

        $result = $this->service($openChannel, $crm)->register($conversation, ['name' => '', 'phone' => '  ']);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
}
