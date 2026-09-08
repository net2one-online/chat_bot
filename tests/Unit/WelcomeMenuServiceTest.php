<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\Conversation;
use App\Services\Agent\WelcomeMenuService;
use App\Services\Bitrix\OpenChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeMenuServiceTest extends TestCase
{
    use RefreshDatabase;

    private WelcomeMenuService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WelcomeMenuService($this->createMock(OpenChannelService::class));
    }

    private function bot(array $options = [], string $greeting = ''): Bot
    {
        return new Bot([
            'openline_id' => '2',
            'welcome_menu' => [
                'enabled' => true,
                'greeting' => $greeting,
                'options' => $options,
            ],
        ]);
    }

    public function test_menu_text_numbers_the_options(): void
    {
        $bot = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
            ['label' => 'Ventas', 'entity_id' => '2', 'entity_type' => 'user'],
        ]);

        $this->assertSame("1. Soporte\n2. Ventas", $this->service->menuText($bot));
    }

    public function test_resolve_option_matches_number(): void
    {
        $bot = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
            ['label' => 'Ventas', 'entity_id' => '2', 'entity_type' => 'user'],
        ]);

        $option = $this->service->resolveOption($bot, '2.');

        $this->assertSame('Ventas', $option['label']);
    }

    public function test_resolve_option_matches_label_case_insensitive(): void
    {
        $bot = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
        ]);

        $option = $this->service->resolveOption($bot, 'soporte');

        $this->assertSame('Soporte', $option['label']);
    }

    public function test_resolve_option_returns_null_for_unknown_input(): void
    {
        $bot = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
        ]);

        $this->assertNull($this->service->resolveOption($bot, 'quiero ayuda con la factura'));
    }

    public function test_resolve_option_returns_null_for_out_of_range_number(): void
    {
        $bot = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
        ]);

        $this->assertNull($this->service->resolveOption($bot, '9'));
    }

    public function test_greeting_uses_default_when_blank(): void
    {
        $bot = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
        ], '');

        $greeting = $this->service->greeting($bot);

        $this->assertStringContainsString('responde solo con el numero', $greeting);
        $this->assertStringContainsString('1. Soporte', $greeting);
    }

    public function test_greeting_uses_custom_text(): void
    {
        $bot = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
        ], 'Bienvenido, elige una opcion:');

        $this->assertStringContainsString('Bienvenido, elige una opcion:', $this->service->greeting($bot));
    }

    public function test_confirmation_includes_the_chosen_label(): void
    {
        $bot = $this->bot([]);
        $option = ['label' => 'Ventas', 'entity_id' => '2', 'entity_type' => 'user'];

        $this->assertStringContainsString('Ventas', $this->service->confirmation($bot, $option));
    }

    public function test_retry_text_includes_the_menu(): void
    {
        $bot = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
        ]);

        $this->assertStringContainsString('1. Soporte', $this->service->retryText($bot));
    }

    public function test_is_enabled_requires_openline_and_options(): void
    {
        $withLine = $this->bot([
            ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
        ]);
        $this->assertTrue($this->service->isEnabled($withLine));

        $withoutLine = new Bot([
            'openline_id' => null,
            'welcome_menu' => [
                'enabled' => true,
                'options' => [
                    ['label' => 'Soporte', 'entity_id' => '1', 'entity_type' => 'user'],
                ],
            ],
        ]);
        $this->assertFalse($this->service->isEnabled($withoutLine));

        $withoutOptions = $this->bot([], '');
        $this->assertFalse($this->service->isEnabled($withoutOptions));
    }

    public function test_menu_is_pending_only_after_offered(): void
    {
        $offered = new Conversation([
            'welcome_menu_shown' => true,
            'welcome_menu_attempts' => 1,
            'welcome_menu_choice' => null,
        ]);
        $this->assertTrue($this->service->isMenuPending($offered));

        $notOffered = new Conversation([
            'welcome_menu_shown' => false,
            'welcome_menu_attempts' => 0,
            'welcome_menu_choice' => null,
        ]);
        $this->assertFalse($this->service->isMenuPending($notOffered));

        $alreadyChosen = new Conversation([
            'welcome_menu_shown' => true,
            'welcome_menu_attempts' => 0,
            'welcome_menu_choice' => '2',
        ]);
        $this->assertFalse($this->service->isMenuPending($alreadyChosen));
    }

    public function test_menu_is_pending_false_after_max_attempts(): void
    {
        $exhausted = new Conversation([
            'welcome_menu_shown' => true,
            'welcome_menu_attempts' => 3,
            'welcome_menu_choice' => null,
        ]);

        $this->assertFalse($this->service->isMenuPending($exhausted));
    }

    public function test_mark_offered_persists_pending_state(): void
    {
        $bot = Bot::create(['name' => 'Menu Bot']);
        $conversation = Conversation::create([
            'bot_id' => $bot->id,
            'bitrix_chat_id' => 'chat999',
            'welcome_menu_shown' => false,
            'welcome_menu_attempts' => 0,
            'welcome_menu_choice' => null,
        ]);

        $this->service->markOffered($conversation);

        $fresh = $conversation->fresh();

        $this->assertTrue($fresh->welcome_menu_shown);
        $this->assertSame(0, $fresh->welcome_menu_attempts);
        $this->assertNull($fresh->welcome_menu_choice);
    }

    public function test_perform_transfer_to_another_line_calls_operator_transfer(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('transferToLine')
            ->with('chat1234', 14)
            ->willReturn(['result' => true]);

        $service = new WelcomeMenuService($openChannel);
        $bot = $this->bot([], '');
        $conversation = new Conversation(['bitrix_chat_id' => 'chat1234']);

        $result = $service->performTransfer($bot, $conversation, [
            'label' => 'WhatsApp',
            'entity_id' => '14',
            'entity_type' => 'line',
        ]);

        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_perform_transfer_to_queue_member_uses_bot_transfer(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $openChannel->expects($this->once())
            ->method('transferToQueueMember')
            ->with('chat1234', 3723, 'user', 'bot-token')
            ->willReturn(['result' => true]);

        $service = new WelcomeMenuService($openChannel);
        $bot = $this->bot([], '');
        $bot->setAttribute('bot_token', 'bot-token');
        $conversation = new Conversation(['bitrix_chat_id' => 'chat1234']);

        $result = $service->performTransfer($bot, $conversation, [
            'label' => 'Soporte',
            'entity_id' => '3723',
            'entity_type' => 'user',
        ]);

        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_perform_transfer_rejects_missing_destination(): void
    {
        $openChannel = $this->createMock(OpenChannelService::class);
        $service = new WelcomeMenuService($openChannel);
        $bot = $this->bot([], '');
        $conversation = new Conversation(['bitrix_chat_id' => 'chat1234']);

        $result = $service->performTransfer($bot, $conversation, [
            'label' => 'Sin destino',
            'entity_id' => '0',
            'entity_type' => 'user',
        ]);

        $this->assertArrayHasKey('error', $result);
    }
}
