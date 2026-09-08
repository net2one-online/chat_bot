<?php

namespace App\Http\Controllers;

use App\Services\AI\OpenAiService;
use App\Services\Bitrix\BitrixOAuthService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected OpenAiService $openAi,
        protected BitrixOAuthService $bitrixOAuth,
    ) {}

    public function index(): View
    {
        $token = $this->bitrixOAuth->tenantToken();

        $gemini = [
            'api_key_present' => $token && trim((string) $token->gemini_api_key) !== '',
            'model' => $token?->gemini_model ?: $this->settings->get('gemini.model'),
            'base_url' => $token?->gemini_base_url ?: $this->settings->get('gemini.base_url'),
        ];

        return view('settings.index', compact('gemini', 'token'));
    }

    public function updateGemini(Request $request)
    {
        $data = $request->validate([
            'api_key' => 'nullable|string',
            'model' => 'required|string|max:255',
            'base_url' => 'nullable|string|max:255',
        ]);

        $token = $this->bitrixOAuth->tenantToken();

        if (! $token) {
            return redirect()->route('settings.index')
                ->with('error', 'No hay un portal Bitrix24 configurado. Instala la aplicacion primero.');
        }

        $updates = [
            'gemini_model' => $data['model'],
            'gemini_base_url' => $data['base_url'] ?: null,
        ];

        // Only overwrite the API key when a new one is provided.
        if (! empty($data['api_key'])) {
            $updates['gemini_api_key'] = $data['api_key'];
        }

        $token->update($updates);

        return redirect()->route('settings.index')
            ->with('success', 'Configuracion de Gemini guardada correctamente para este portal.');
    }

    public function testGemini(): JsonResponse
    {
        try {
            $response = $this->openAi->chat([
                ['role' => 'user', 'content' => 'Responde solo: OK'],
            ], [], ['max_tokens' => 10]);

            if (isset($response['error'])) {
                return response()->json([
                    'ok' => false,
                    'message' => $response['error'],
                ]);
            }

            $token = $this->bitrixOAuth->tenantToken();
            $model = $token?->gemini_model ?: $this->settings->get('gemini.model');

            return response()->json([
                'ok' => true,
                'message' => 'Conexion con la IA exitosa. Modelo: '.$model,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
