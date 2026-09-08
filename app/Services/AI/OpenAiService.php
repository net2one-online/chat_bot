<?php

namespace App\Services\AI;

use App\Models\BitrixToken;
use App\Services\Bitrix\BitrixOAuthService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;
use OpenAI\Client;
use OpenAI\Factory;

class OpenAiService
{
    protected Client $client;

    protected string $model;

    public function __construct(
        protected SettingsService $settings,
        protected BitrixOAuthService $bitrixOAuth,
    ) {
        $this->configureForTenant();
    }

    /**
     * Build the OpenAI-compatible client using the Gemini configuration of the
     * active tenant (its own API key, model and base URL), so each client uses
     * its own Gemini account.
     */
    protected function configureForTenant(): void
    {
        $token = $this->bitrixOAuth->tenantToken();

        $baseUrl = (string) $this->valueFor($token, 'gemini_base_url', 'gemini.base_url');
        $apiKey = (string) $this->valueFor($token, 'gemini_api_key', 'gemini.api_key');
        $organization = (string) $this->settings->get('gemini.organization');
        $this->model = (string) $this->valueFor($token, 'gemini_model', 'gemini.model', 'gpt-4o');

        $factory = new Factory;

        if ($baseUrl) {
            $factory->withBaseUri($baseUrl);
        }

        $this->client = $factory
            ->withApiKey($apiKey)
            ->withOrganization($organization ?: null)
            ->make();
    }

    /**
     * Prefer a per-tenant value stored on the token, falling back to the global
     * settings/env value.
     */
    protected function valueFor(?BitrixToken $token, string $tokenField, string $settingsKey, ?string $default = null): ?string
    {
        if ($token && trim((string) $token->{$tokenField}) !== '') {
            return (string) $token->{$tokenField};
        }

        $v = $this->settings->get($settingsKey, $default);

        return $v !== null ? (string) $v : null;
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $params = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1024,
        ];

        if (! empty($tools)) {
            $params['tools'] = $tools;
            $params['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        try {
            $response = $this->client->chat()->create($params);
        } catch (\Throwable $e) {
            Log::warning("Error llamando a la API de IA: {$e->getMessage()}");

            return [
                'content' => '',
                'tool_calls' => [],
                'finish_reason' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        $choice = $response->choices[0] ?? null;

        if (! $choice) {
            return [
                'content' => '',
                'tool_calls' => [],
                'finish_reason' => 'error',
            ];
        }

        $result = [
            'content' => $choice->message->content ?? '',
            'tool_calls' => [],
            'finish_reason' => $choice->finishReason,
        ];

        if ($choice->message->toolCalls) {
            foreach ($choice->message->toolCalls as $toolCall) {
                $entry = [
                    'id' => $toolCall->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $toolCall->function->name,
                        'arguments' => $toolCall->function->arguments,
                    ],
                ];

                if ($toolCall->extraContent) {
                    $entry['extra_content'] = $toolCall->extraContent;
                }

                $result['tool_calls'][] = $entry;
            }
        }

        return $result;
    }

    public function getTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_contact',
                    'description' => 'Buscar un contacto en Bitrix24 por telefono, correo electronico o nombre',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Telefono, correo electronico o nombre del contacto',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_deals',
                    'description' => 'Buscar negociaciones o deals relacionados con un contacto',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'contact_id' => [
                                'type' => 'string',
                                'description' => 'ID del contacto en Bitrix24',
                            ],
                        ],
                        'required' => ['contact_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_company',
                    'description' => 'Buscar informacion de una empresa en Bitrix24',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Nombre de la empresa a buscar',
                            ],
                            'company_id' => [
                                'type' => 'string',
                                'description' => 'ID de la empresa en Bitrix24',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_activity',
                    'description' => 'Crear una actividad o tarea para un responsable en Bitrix24',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'owner_id' => [
                                'type' => 'string',
                                'description' => 'ID del registro propietario (negocio o contacto)',
                            ],
                            'subject' => [
                                'type' => 'string',
                                'description' => 'Asunto de la actividad',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Descripcion detallada de la actividad',
                            ],
                            'responsible_id' => [
                                'type' => 'string',
                                'description' => 'ID del responsable en Bitrix24',
                            ],
                            'deadline' => [
                                'type' => 'string',
                                'description' => 'Fecha limite en formato YYYY-MM-DD HH:MM:SS',
                            ],
                        ],
                        'required' => ['owner_id', 'subject', 'responsible_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'send_file',
                    'description' => 'Enviar un archivo adjunto (PDF, imagen u otro) al cliente en la conversacion. Usar exclusivamente cuando la base de conocimiento lo especifica explicitamente (por ejemplo: "enviar manual.pdf" o "adjuntar catalogo.pdf").',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'file_name' => [
                                'type' => 'string',
                                'description' => 'Nombre del archivo con extension que se debe enviar, tal como figura en la base de conocimiento (ej: manual.pdf, catalogo.png).',
                            ],
                            'message' => [
                                'type' => 'string',
                                'description' => 'Mensaje breve que acompanara al archivo',
                            ],
                        ],
                        'required' => ['file_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'transfer_to_human',
                    'description' => 'Transferir la conversacion a un operador humano cuando la IA no puede resolver la consulta o el cliente solicita hablar con una persona',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Razon de la transferencia',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
