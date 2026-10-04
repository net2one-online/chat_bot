<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\Conversation;
use App\Services\Bitrix\BitrixCrmService;
use App\Services\Bitrix\ClientProfileService;
use App\Services\Bitrix\OpenChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(OpenChannelService $openChannel, BitrixCrmService $crm): ClientProfileService
    {
        return new ClientProfileService($openChannel, $crm);
    }

    private function conversation(): Conversation
    {
        $bot = Bot::create(['name' => 'Bot Open Line', 'openline_id' => 2]);

        return Conversation::create([
            'bot_id' => $bot->id,
            'bitrix_chat_id' => 'chat1234',
            'contact_id' => '599',
            'status' => 'active',
        ]);
    }

    private function dialog(array $overrides = []): array
    {
        return array_merge([
            'entity_type' => 'LINES',
            'entity_data_1' => 'N|0|0|N|N|55|1773682918|0|0|0',
            'name' => 'María Gonzalez',
        ], $overrides);
    }

    public function test_capture_stores_chat_name_when_no_crm_entity_is_bound(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('getDialog')
            ->with('chat1234')
            ->willReturn($this->dialog());

        $crm = $this->createMock(BitrixCrmService::class);
        $conversation = $this->conversation();

        $this->service($openChannel, $crm)->capture($conversation);

        $fresh = $conversation->fresh();
        $this->assertSame('María Gonzalez', $fresh->client_data['name']);
        $this->assertNull($fresh->client_data['phone'] ?? null);
        $this->assertArrayNotHasKey('crm_entity_type', $fresh->client_data);
        $this->assertSame('599', $fresh->contact_id);
    }

    public function test_capture_strips_livechat_guest_boilerplate_from_chat_name(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('getDialog')
            ->with('chat1234')
            ->willReturn($this->dialog(['name' => 'Green Guest #17 - María Gonzalez']));

        $crm = $this->createMock(BitrixCrmService::class);
        $conversation = $this->conversation();

        $this->service($openChannel, $crm)->capture($conversation);

        $this->assertSame('María Gonzalez', $conversation->fresh()->client_data['name']);
    }

    public function test_capture_treats_plain_guest_placeholder_as_unknown_name(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('getDialog')
            ->with('chat1234')
            ->willReturn($this->dialog(['name' => 'Green Guest #17']));

        $crm = $this->createMock(BitrixCrmService::class);
        $conversation = $this->conversation();

        $this->service($openChannel, $crm)->capture($conversation);

        $this->assertSame([], $conversation->fresh()->client_data);
    }

    public function test_capture_merges_contact_entity_and_links_conversation(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('getDialog')
            ->with('chat1234')
            ->willReturn($this->dialog(['entity_data_1' => 'Y|CONTACT|275|N|N|55|1773682918|0|0|0']));

        $crm = $this->createMock(BitrixCrmService::class);
        $crm->expects($this->once())
            ->method('getContact')
            ->with('275')
            ->willReturn([
                'NAME' => 'María',
                'LAST_NAME' => 'Gonzalez',
                'PHONE' => [['TYPE' => 'WORK', 'VALUE' => '1122334455']],
                'EMAIL' => [['TYPE' => 'WORK', 'VALUE' => 'maria@example.com']],
                'ADDRESS_CITY' => 'Rosario',
            ]);

        $conversation = $this->conversation();

        $this->service($openChannel, $crm)->capture($conversation);

        $fresh = $conversation->fresh();
        $this->assertSame('María', $fresh->client_data['name']);
        $this->assertSame('Gonzalez', $fresh->client_data['last_name']);
        $this->assertSame('1122334455', $fresh->client_data['phone']);
        $this->assertSame('maria@example.com', $fresh->client_data['email']);
        $this->assertSame('Rosario', $fresh->client_data['locality']);
        $this->assertSame('CONTACT', $fresh->client_data['crm_entity_type']);
        $this->assertSame(275, $fresh->client_data['crm_entity_id']);
        $this->assertSame('275', $fresh->contact_id);
    }

    public function test_capture_uses_lead_entity_without_changing_contact_id(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('getDialog')
            ->with('chat1234')
            ->willReturn($this->dialog(['entity_data_1' => 'Y|LEAD|1209|N|N|343|1773682918|0|0|0']));

        $crm = $this->createMock(BitrixCrmService::class);
        $crm->expects($this->once())
            ->method('getLead')
            ->with('1209')
            ->willReturn(['NAME' => 'Juan', 'LAST_NAME' => 'Perez']);

        $conversation = $this->conversation();

        $this->service($openChannel, $crm)->capture($conversation);

        $fresh = $conversation->fresh();
        $this->assertSame('Juan', $fresh->client_data['name']);
        $this->assertSame('LEAD', $fresh->client_data['crm_entity_type']);
        $this->assertSame(1209, $fresh->client_data['crm_entity_id']);
        $this->assertSame('599', $fresh->contact_id);
    }

    public function test_capture_prefers_crm_entity_fields_over_chat_name(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('getDialog')
            ->with('chat1234')
            ->willReturn($this->dialog([
                'entity_data_1' => 'Y|LEAD|1209|N|N|343|1773682918|0|0|0',
                'name' => 'Green Guest #17 - María Fernandez',
            ]));

        $crm = $this->createMock(BitrixCrmService::class);
        $crm->expects($this->once())
            ->method('getLead')
            ->with('1209')
            ->willReturn(['NAME' => 'María']);

        $conversation = $this->conversation();

        $this->service($openChannel, $crm)->capture($conversation);

        $this->assertSame('María', $conversation->fresh()->client_data['name']);
    }

    public function test_capture_is_a_noop_when_client_data_is_already_captured(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->never())->method('getDialog');

        $crm = $this->createMock(BitrixCrmService::class);
        $conversation = $this->conversation();
        $conversation->update(['client_data' => ['name' => 'María']]);

        $this->service($openChannel, $crm)->capture($conversation);

        $this->assertSame('María', $conversation->fresh()->client_data['name']);
    }

    public function test_capture_leaves_client_data_null_when_dialog_errors(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('getDialog')
            ->with('chat1234')
            ->willReturn(['error' => true, 'message' => 'Access denied']);

        $crm = $this->createMock(BitrixCrmService::class);
        $conversation = $this->conversation();

        $this->service($openChannel, $crm)->capture($conversation);

        $this->assertNull($conversation->fresh()->client_data);
        $this->assertSame('599', $conversation->fresh()->contact_id);
    }

    public function test_capture_does_not_run_for_non_openline_chats(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('getDialog')
            ->with('chat1234')
            ->willReturn(array_merge($this->dialog(), ['entity_type' => 'PRIVATE']));

        $crm = $this->createMock(BitrixCrmService::class);
        $conversation = $this->conversation();

        $this->service($openChannel, $crm)->capture($conversation);

        $this->assertNull($conversation->fresh()->client_data);
    }
}
