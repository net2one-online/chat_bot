<?php

namespace App\Http\Controllers;

use App\Models\BitrixToken;
use App\Models\Bot;
use App\Services\Bitrix\BitrixOAuthService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;

class SetupController extends Controller
{
    public function __construct(
        protected BitrixOAuthService $oauth,
        protected SettingsService $settings,
    ) {}

    public function index()
    {
        $token = $this->oauth->tenantToken()
            ?? BitrixToken::first();

        if (! $token) {
            return redirect()->route('dashboard')
                ->with('error', 'No hay conexion con Bitrix24. Instala la aplicacion primero.');
        }

        $domain = $token->domain
            ?: $this->oauth->portalDomainFromEndpoint($token->client_endpoint)
            ?: $this->settings->get('bitrix.domain', '');

        $channels = [];

        if ($domain) {
            $accessToken = $this->oauth->getAccessToken($token->member_id);

            if ($accessToken) {
                $response = Http::timeout(30)
                    ->post("https://{$domain}/rest/imopenlines.config.list.get", [
                        'auth' => $accessToken,
                        'PARAMS' => [
                            'select' => ['ID', 'LINE_NAME', 'ACTIVE'],
                            'order' => ['ID' => 'ASC'],
                            'limit' => 50,
                        ],
                    ]);

                if ($response->successful()) {
                    $channels = $response->json('result', []);
                }
            }
        }

        $setupCompleted = $token->setup_completed ?? false;
        $contactCenterUrl = $domain ? "https://{$domain}/contact-center/" : '#';
        $botId = $token->file_bot_id;
        $appBots = Bot::query()
            ->orderBy('id')
            ->get(['name', 'bitrix_bot_id', 'openline_id']);

        return view('setup.index', compact(
            'token',
            'domain',
            'channels',
            'setupCompleted',
            'contactCenterUrl',
            'botId',
            'appBots',
        ));
    }

    public function complete()
    {
        $token = $this->oauth->tenantToken() ?? BitrixToken::first();

        if ($token) {
            $token->update(['setup_completed' => true]);
        }

        return redirect()->route('setup.index')
            ->with('success', 'Configuracion completada.');
    }

    public function reset()
    {
        $token = $this->oauth->tenantToken() ?? BitrixToken::first();

        if ($token) {
            $token->update(['setup_completed' => false]);
        }

        return redirect()->route('setup.index')
            ->with('success', 'Configuracion reiniciada.');
    }
}
