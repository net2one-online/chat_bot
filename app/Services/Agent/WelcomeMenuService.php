<?php

namespace App\Services\Agent;

use App\Models\Bot;
use App\Models\Conversation;
use App\Services\Bitrix\OpenChannelService;
use Illuminate\Support\Facades\Log;

/**
 * Welcomes a customer with a numbered menu on their first message and hands
 * the dialog off to the chosen queue/operator. Resolution is purely
 * deterministic (regex), so it never depends on the AI's tool loop.
 */
class WelcomeMenuService
{
    protected const MAX_ATTEMPTS = 3;

    public function __construct(protected OpenChannelService $openChannelService) {}

    public function isEnabled(Bot $bot): bool
    {
        return $bot->openline_id !== null && $bot->menuEnabled();
    }

    public function isMenuPending(Conversation $conversation): bool
    {
        return $conversation->welcome_menu_shown
            && $conversation->welcome_menu_choice === null
            && $conversation->welcome_menu_attempts < self::MAX_ATTEMPTS;
    }

    public function markOffered(Conversation $conversation): void
    {
        $conversation->update([
            'welcome_menu_shown' => true,
            'welcome_menu_attempts' => 0,
            'welcome_menu_choice' => null,
        ]);
    }

    public function greeting(Bot $bot): string
    {
        $greeting = $bot->menuGreeting();

        if ($greeting === '') {
            $greeting = 'Hola, para poder ayudarte mejor responde solo con el numero de una de las siguientes opciones:';
        }

        return $greeting."\n".$this->menuText($bot);
    }

    public function menuText(Bot $bot): string
    {
        $lines = [];

        foreach ($bot->menuOptions() as $index => $option) {
            $lines[] = ($index + 1).'. '.($option['label'] ?? 'Opcion '.($index + 1));
        }

        return implode("\n", $lines);
    }

    public function resolveOption(Bot $bot, string $message): ?array
    {
        $options = $bot->menuOptions();

        if (empty($options)) {
            return null;
        }

        $trimmed = trim($message);

        if (preg_match('/^\s*(\d+)\s*[.)]?\s*$/u', $trimmed, $matches)) {
            $position = (int) $matches[1];

            if (isset($options[$position - 1])) {
                return $options[$position - 1];
            }

            return null;
        }

        foreach ($options as $option) {
            if (mb_strtolower(trim((string) ($option['label'] ?? ''))) === mb_strtolower($trimmed)) {
                return $option;
            }
        }

        return null;
    }

    public function confirmation(Bot $bot, array $option): string
    {
        $label = trim((string) ($option['label'] ?? ''));

        $prefix = $label !== '' ? "Perfecto, te estoy derivando con {$label}. " : 'Perfecto, te estoy derivando con un agente especializado. ';

        return $prefix.'En un momento estara contigo. Gracias por tu paciencia.';
    }

    public function retryText(Bot $bot): string
    {
        return 'No he entendido tu eleccion. Responde solo con el numero de una de las siguientes opciones:'."\n".$this->menuText($bot);
    }

    public function performTransfer(Bot $bot, Conversation $conversation, array $option): array
    {
        $entityId = (int) ($option['entity_id'] ?? 0);

        if ($entityId <= 0) {
            Log::error('Opcion de menu sin destino de transferencia', [
                'bot_id' => $bot->id,
                'option' => $option,
            ]);

            return ['error' => true, 'message' => 'La opcion no tiene una cola de atencion configurada'];
        }

        $entityType = (string) ($option['entity_type'] ?? 'user');

        if ($entityType === 'line') {
            return $this->openChannelService->transferToLine($conversation->bitrix_chat_id, $entityId);
        }

        return $this->openChannelService->transferToQueueMember(
            $conversation->bitrix_chat_id,
            $entityId,
            $entityType,
            (string) ($bot->bot_token ?? ''),
        );
    }
}
