<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Services\AI\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromptService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PromptService;
    }

    private function bot(): Bot
    {
        return Bot::create(['name' => 'Bot Consultas']);
    }

    public function test_system_prompt_renders_registered_client_data(): void
    {
        $prompt = $this->service->buildSystemPrompt($this->bot(), [
            'client' => [
                'name' => 'María',
                'last_name' => 'Gonzalez',
                'phone' => '1122334455',
                'email' => 'maria@example.com',
                'locality' => 'Rosario',
            ],
        ]);

        $this->assertStringContainsString('Registro del cliente', $prompt);
        $this->assertStringContainsString('María', $prompt);
        $this->assertStringContainsString('1122334455', $prompt);
        $this->assertStringContainsString('maria@example.com', $prompt);
    }

    public function test_system_prompt_does_not_render_client_data_when_absent(): void
    {
        $prompt = $this->service->buildSystemPrompt($this->bot());

        $this->assertStringNotContainsString('Registro del cliente (datos ya obtenidos)', $prompt);
    }

    public function test_system_prompt_forbids_asking_registered_client_data(): void
    {
        $prompt = $this->service->buildSystemPrompt($this->bot(), [
            'client' => ['name' => 'María'],
        ]);

        $this->assertStringContainsString('no vuelvas a pedirlos', $prompt);
    }
}
