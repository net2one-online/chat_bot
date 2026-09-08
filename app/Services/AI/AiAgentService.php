<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Agent\ToolRegistry;
use App\Services\Agent\WelcomeMenuService;
use App\Services\Bitrix\BitrixCrmService;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    protected OpenAiService $openAi;

    protected PromptService $promptService;

    protected ToolRegistry $toolRegistry;

    protected BitrixCrmService $crmService;

    public function __construct()
    {
        $this->openAi = app(OpenAiService::class);
        $this->promptService = app(PromptService::class);
        $this->toolRegistry = app(ToolRegistry::class);
        $this->crmService = app(BitrixCrmService::class);
    }

    public function processMessage(Conversation $conversation, string $userMessage): string
    {
        $bot = $conversation->bot;

        $context = $this->buildContext($conversation);
        $systemPrompt = $this->promptService->buildSystemPrompt($bot, $context, $userMessage);
        $recentMessages = $conversation->getRecentMessages(10);

        $messagesFormatted = $this->formatMessages($recentMessages, $userMessage);
        $messages = $this->promptService->buildMessages($messagesFormatted, $systemPrompt);

        $tools = $this->openAi->getTools();

        $maxIterations = 5;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;

            $response = $this->openAi->chat($messages, $tools);

            if (($response['finish_reason'] ?? '') === 'error') {
                $this->saveMessage(
                    $conversation,
                    'assistant',
                    'Lo siento, en este momento no puedo procesar tu solicitud por una sobrecarga del servicio. Por favor, intenta nuevamente en unos segundos.'
                );

                return 'Lo siento, en este momento no puedo procesar tu solicitud por una sobrecarga del servicio. Por favor, intenta nuevamente en unos segundos.';
            }

            if (! empty($response['tool_calls'])) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $response['content'] ?? null,
                    'tool_calls' => $response['tool_calls'],
                ];

                $toolResults = $this->executeToolCalls($response['tool_calls'], $conversation);

                foreach ($toolResults as $toolResult) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolResult['tool_call_id'],
                        'content' => json_encode($toolResult['result']),
                    ];
                }

                if ($this->shouldStopAfterToolExecution($toolResults, $conversation)) {
                    return $this->generateTransferMessage($conversation);
                }

                continue;
            }

            $finalResponse = $response['content'] ?? 'Lo siento, no pude generar una respuesta.';

            if (! empty($response['content'])) {
                $this->saveMessage($conversation, 'assistant', $response['content']);
            }

            return $finalResponse;
        }

        return 'Lo siento, estoy teniendo dificultades para procesar tu solicitud. Por favor, intenta nuevamente.';
    }

    protected function buildContext(Conversation $conversation): array
    {
        $context = [];

        if ($conversation->contact_id) {
            $contact = $this->crmService->getContact($conversation->contact_id);
            if ($contact && ! isset($contact['error'])) {
                $context['contact'] = $contact;
            }
        }

        if (! empty($context['contact'])) {
            $deals = $this->crmService->searchDeals($conversation->contact_id);
            if (! empty($deals)) {
                $context['deals'] = $deals;
            }
        }

        return $context;
    }

    protected function formatMessages($recentMessages, string $currentMessage): array
    {
        $messages = [];

        foreach ($recentMessages as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $currentMessage,
        ];

        return $messages;
    }

    protected function executeToolCalls(array $toolCalls, Conversation $conversation): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'];
            $parameters = json_decode($toolCall['function']['arguments'], true) ?? [];

            if ($toolName === 'transfer_to_human' || $toolName === 'send_file') {
                $parameters['chat_id'] = $conversation->bitrix_chat_id;
                $parameters['bot_id'] = $conversation->bot_id;
            }

            Log::info("Ejecutando herramienta: {$toolName}", [
                'parameters' => $parameters,
                'conversation_id' => $conversation->id,
            ]);

            $result = $this->toolRegistry->execute($toolName, $parameters);

            $results[] = [
                'tool_call_id' => $toolCall['id'],
                'tool_name' => $toolName,
                'result' => $result,
            ];

            Log::info("Resultado de herramienta: {$toolName}", ['result' => $result]);
        }

        return $results;
    }

    protected function shouldStopAfterToolExecution(array $toolResults, Conversation $conversation): bool
    {
        foreach ($toolResults as $result) {
            if ($result['tool_name'] === 'transfer_to_human' && ! empty($result['result']['transfer'])) {
                $bot = $conversation->bot;
                $menuService = app(WelcomeMenuService::class);

                if ($menuService->isEnabled($bot)) {
                    $menuService->markOffered($conversation);

                    return true;
                }

                $conversation->update([
                    'human_mode' => true,
                    'status' => 'transferred',
                ]);

                return true;
            }
        }

        return false;
    }

    protected function generateTransferMessage(Conversation $conversation): string
    {
        $bot = $conversation->bot;
        $menuService = app(WelcomeMenuService::class);

        if ($menuService->isEnabled($bot)) {
            return $menuService->greeting($bot);
        }

        return 'Estare transfiriendote con un asistente humano. En un momento estara contigo. Gracias por tu paciencia.';
    }

    protected function saveMessage(Conversation $conversation, string $role, string $content): void
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
        ]);
    }
}
