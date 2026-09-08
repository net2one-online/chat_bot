<?php

namespace App\Jobs;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Agent\WelcomeMenuService;
use App\Services\AI\AiAgentService;
use App\Services\Bitrix\BitrixBotService;
use App\Services\Bitrix\OpenChannelService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public int $botId,
        public string $chatId,
        public string $message,
        public ?string $sessionId = null,
        public ?string $contactId = null,
        public ?string $bitrixMessageId = null,
    ) {}

    public function handle(): void
    {
        $bot = Bot::withoutGlobalScope('tenant')->find($this->botId);

        if (! $bot || ! $bot->isActive()) {
            Log::warning('Bot no encontrado o inactivo', ['bot_id' => $this->botId]);

            return;
        }

        TenantContext::set($bot->member_id);

        if ($this->isMessageProcessed()) {
            Log::info('Mensaje ya procesado', ['message_id' => $this->bitrixMessageId]);

            return;
        }

        $conversation = $this->getOrCreateConversation($bot);
        $this->handleMessageGrouping($conversation);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $this->message,
            'bitrix_message_id' => $this->bitrixMessageId,
        ]);

        if ($conversation->isHumanMode()) {
            Log::info('Conversacion en modo humano, no procesando', ['conversation_id' => $conversation->id]);

            return;
        }

        $menuService = app(WelcomeMenuService::class);

        if ($menuService->isMenuPending($conversation)) {
            if ($this->handleMenuResolution($bot, $conversation, $menuService)) {
                return;
            }
        }

        $agentService = app(AiAgentService::class);
        $response = $agentService->processMessage($conversation, $this->message);

        $this->sendResponse($bot, $conversation, $response);
    }

    /**
     * Resolve a pending channel menu: a valid pick hands the dialog off to the
     * chosen channel/operator, anything else is retried (deterministically).
     * Returns true when the message was consumed by the menu.
     */
    protected function handleMenuResolution(Bot $bot, Conversation $conversation, WelcomeMenuService $menuService): bool
    {
        $option = $menuService->resolveOption($bot, $this->message);

        if ($option) {
            $conversation->update([
                'welcome_menu_choice' => (string) ($option['entity_id'] ?? ''),
            ]);

            $this->sendResponse($bot, $conversation, $menuService->confirmation($bot, $option));

            $result = $menuService->performTransfer($bot, $conversation, $option);

            if (isset($result['error'])) {
                Log::error('No se pudo transferir la conversacion tras elegir opcion del menu', [
                    'conversation_id' => $conversation->id,
                    'option' => $option,
                    'result' => $result,
                ]);

                return false;
            }

            $conversation->update([
                'human_mode' => true,
                'status' => 'transferred',
            ]);

            return true;
        }

        $conversation->increment('welcome_menu_attempts');
        $this->sendResponse($bot, $conversation, $menuService->retryText($bot));

        return true;
    }

    protected function isMessageProcessed(): bool
    {
        if (! $this->bitrixMessageId) {
            return false;
        }

        return Message::where('bitrix_message_id', $this->bitrixMessageId)->exists();
    }

    protected function getOrCreateConversation(Bot $bot): Conversation
    {
        $conversation = Conversation::where('bot_id', $bot->id)
            ->where('bitrix_chat_id', $this->chatId)
            ->where('status', 'active')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'bitrix_chat_id' => $this->chatId,
                'bitrix_session_id' => $this->sessionId,
                'contact_id' => $this->contactId,
                'status' => 'active',
                'human_mode' => false,
            ]);
        }

        return $conversation;
    }

    protected function handleMessageGrouping(Conversation $conversation): void
    {
        $lastMessage = $conversation->messages()
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastMessage) {
            return;
        }

        $timeDiff = now()->diffInSeconds($lastMessage->created_at);

        if ($timeDiff <= 5 && $lastMessage->content === $this->message) {
            Log::info('Mensaje duplicado detectado', [
                'conversation_id' => $conversation->id,
            ]);
        }
    }

    protected function sendResponse(Bot $bot, Conversation $conversation, string $response): void
    {
        if ($bot->bot_token) {
            $botService = app(BitrixBotService::class);
            $result = $botService->sendV2Message($bot, $this->chatId, $response);
        } elseif ($bot->openline_id) {
            $openChannelService = app(OpenChannelService::class);
            $result = $openChannelService->sendMessage($this->chatId, $response);
        } else {
            $botService = app(BitrixBotService::class);
            $result = $botService->sendMessage($this->chatId, $response);
        }

        if (isset($result['error'])) {
            Log::error('Error enviando respuesta a Bitrix24', [
                'chat_id' => $this->chatId,
                'error' => $result,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job ProcessIncomingMessage fallo', [
            'bot_id' => $this->botId,
            'chat_id' => $this->chatId,
            'message' => $exception->getMessage(),
        ]);
    }
}
