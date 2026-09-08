<?php

namespace App\Services\AI;

use App\Models\Bot;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Collection;

class PromptService
{
    public function buildSystemPrompt(Bot $bot, array $context = [], ?string $userMessage = null): string
    {
        $basePrompt = $bot->system_prompt ?? 'Eres un asistente virtual atento y profesional. Responde de forma clara y util.';

        $knowledgeText = $this->getKnowledgeContext($bot, $userMessage);

        $crmContext = '';
        if (! empty($context['contact'])) {
            $contact = $context['contact'];
            $crmContext .= "\n\nInformacion del contacto actual:\n";
            $crmContext .= '- Nombre: '.($contact['NAME'] ?? '').' '.($contact['LAST_NAME'] ?? '')."\n";
            $crmContext .= '- Telefono: '.($this->extractPhone($contact) ?? 'No disponible')."\n";
            $crmContext .= '- Email: '.($this->extractEmail($contact) ?? 'No disponible')."\n";
        }

        if (! empty($context['deals']) && count($context['deals']) > 0) {
            $crmContext .= "\nNegociaciones del contacto:\n";
            foreach ($context['deals'] as $deal) {
                $crmContext .= '- '.($deal['TITLE'] ?? 'Sin titulo').' (Estado: '.($deal['STAGE_ID'] ?? 'N/A').")\n";
            }
        }

        $systemPrompt = <<<PROMPT
{$basePrompt}

## Reglas importantes:
- No inventes informacion que no tengas.
- Si no tienes suficiente informacion, solicita mas detalles al cliente.
- No reveles informacion interna del sistema.
- No utilizes emojis en tus respuestas.
- Brinda al cliente toda la informacion importante que tengas sobre su consulta antes de considerar una transferencia.
- Si el cliente solicita hablar con una persona, te pide una derivacion ("me derivas", "pasame con un humano", "atencion al cliente"), o su consulta supera tu alcance, o ya resolviste lo principal del tema, utiliza la herramienta transfer_to_human de inmediato sin buscar mas datos: el sistema mostrara al cliente el menu de canales de atencion para elegir con quien seguir.
- Responde de forma natural y profesional.
- Si necesitas consultar informacion del CRM, utiliza las herramientas disponibles.

## Base de conocimiento:
{$knowledgeText}
{$crmContext}

Si el cliente pregunta algo que no esta en la base de conocimiento y no puedes responder con las herramientas disponibles, indica que no cuentas con esa informacion y ofrece transferirlo a un asistente humano.
PROMPT;

        return trim($systemPrompt);
    }

    public function buildMessages(array $recentMessages, string $systemPrompt): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($recentMessages as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        return $messages;
    }

    protected function getKnowledgeContext(Bot $bot, ?string $userMessage = null): string
    {
        $documents = KnowledgeDocument::where('bot_id', $bot->id)
            ->get();

        if ($documents->isEmpty()) {
            return 'No hay documentos de base de conocimiento disponibles.';
        }

        if ($userMessage) {
            $relevant = $this->findRelevantDocuments($documents, $userMessage);

            if ($relevant->isNotEmpty()) {
                $text = '';
                foreach ($relevant as $doc) {
                    $text .= "\n### {$doc->title}\n{$doc->content}\n";
                }

                return trim($text);
            }
        }

        $text = "Indice de servicios disponibles:\n";
        foreach ($documents as $doc) {
            $text .= "- {$doc->title}\n";
        }

        return trim($text);
    }

    protected function findRelevantDocuments($documents, string $userMessage): Collection
    {
        $query = mb_strtolower($userMessage);
        $queryWords = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $query)) ?: [];
        $queryWords = array_values(array_filter(array_map('trim', $queryWords), fn ($w) => mb_strlen($w) >= 4));

        if (empty($queryWords)) {
            return collect();
        }

        $scored = $documents->map(function ($doc) use ($queryWords) {
            $haystack = mb_strtolower($doc->title.' '.$doc->content);
            $score = 0;

            foreach ($queryWords as $word) {
                if (mb_strpos($haystack, $word) !== false) {
                    $score++;
                }
            }

            return ['doc' => $doc, 'score' => $score];
        })->filter(fn ($item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(8);

        return $scored->map(fn ($item) => $item['doc'])->values();
    }

    protected function extractPhone(array $contact): ?string
    {
        if (empty($contact['PHONE'])) {
            return null;
        }

        $phones = $contact['PHONE'];
        if (is_array($phones) && count($phones) > 0) {
            return $phones[0]['VALUE'] ?? null;
        }

        return null;
    }

    protected function extractEmail(array $contact): ?string
    {
        if (empty($contact['EMAIL'])) {
            return null;
        }

        $emails = $contact['EMAIL'];
        if (is_array($emails) && count($emails) > 0) {
            return $emails[0]['VALUE'] ?? null;
        }

        return null;
    }
}
