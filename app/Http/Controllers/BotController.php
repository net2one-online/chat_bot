<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\Bitrix\BitrixBotProvisioningService;
use App\Services\Bitrix\BitrixOAuthService;
use App\Services\Bitrix\OpenChannelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BotController extends Controller
{
    protected OpenChannelService $openChannelService;

    public function __construct(
        OpenChannelService $openChannelService,
        protected BitrixOAuthService $oauth,
        protected BitrixBotProvisioningService $provisioning,
    ) {
        $this->openChannelService = $openChannelService;
    }

    public function index()
    {
        $bots = Bot::withCount('conversations')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('bots.index', compact('bots'));
    }

    public function create()
    {
        $channels = $this->openChannelService->listChannels();
        $channelQueues = $this->openChannelService->channelQueues();

        return view('bots.create', compact('channels', 'channelQueues'));
    }

    public function store(Request $request)
    {
        $this->mergeFilteredMenuOptions($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'openline_id' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'menu_enabled' => 'nullable|boolean',
            'menu_greeting' => 'nullable|string|max:2000',
            'menu_options' => 'nullable|array',
            'menu_options.*.label' => 'nullable|string|max:150',
            'menu_options.*.entity_id' => 'nullable|integer',
            'menu_options.*.entity_type' => 'nullable|string|in:user,department,line',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        if ($request->boolean('menu_enabled')) {
            $menu = $this->buildWelcomeMenu($request);

            if ($menu === null) {
                $validator->errors()->add('menu_options', 'Agrega al menos una opcion con destino al menu de bienvenida.');

                return redirect()->back()->withErrors($validator)->withInput();
            }
        } else {
            $menu = null;
        }

        $data['welcome_menu'] = $menu;
        unset($data['menu_enabled'], $data['menu_greeting'], $data['menu_options']);

        $bot = Bot::create($data);

        $registration = $this->registerInBitrix($bot);

        if (isset($registration['error'])) {
            return redirect()->route('bots.index')
                ->with('error', 'Bot creado, pero NO se registró en Bitrix24: '.($registration['message'] ?? 'Error desconocido'));
        }

        return redirect()->route('bots.index')
            ->with('success', 'Bot creado correctamente'.(isset($registration['bot_id']) ? ' (registrado en Bitrix24 como Chatbot '.$registration['bot_id'].')' : ''));
    }

    /**
     * Drop half-empty menu rows (blank label or target) before validation so a
     * spare input row never fails the whole form.
     */
    protected function mergeFilteredMenuOptions(Request $request): void
    {
        $options = collect($request->input('menu_options', []))
            ->filter(fn (array $row) => trim((string) ($row['label'] ?? '')) !== ''
                && ! in_array(trim((string) ($row['entity_id'] ?? '')), ['', '-'], true))
            ->values()
            ->all();

        $request->merge(['menu_options' => $options]);
    }

    protected function buildWelcomeMenu(Request $request): ?array
    {
        $options = [];

        foreach ($request->input('menu_options', []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $entityId = trim((string) ($row['entity_id'] ?? ''));

            if ($label === '' || in_array($entityId, ['', '-'], true)) {
                continue;
            }

            $options[] = [
                'label' => $label,
                'entity_id' => $entityId,
                'entity_type' => in_array($row['entity_type'] ?? 'user', ['user', 'department', 'line'], true) ? $row['entity_type'] : 'user',
            ];
        }

        if (empty($options)) {
            return null;
        }

        return [
            'enabled' => true,
            'greeting' => trim((string) $request->input('menu_greeting', '')),
            'options' => $options,
        ];
    }

    /**
     * Register the bot as a Chatbot 2.0 in the active portal so it shows up in
     * the Contact Center bot selector. Non-fatal: the bot is kept locally even
     * if the remote registration fails, so the user can retry later.
     */
    protected function registerInBitrix(Bot $bot): array
    {
        $token = $this->oauth->tenantToken();

        if (! $token) {
            Log::warning('BotController: sin portal configurado para registrar chatbot', [
                'bot_id' => $bot->id,
            ]);

            return ['error' => true, 'message' => 'No hay portal Bitrix24 configurado'];
        }

        $code = 'asistente_'.substr($token->member_id, 0, 8).'_'.$bot->id;

        $result = $this->provisioning->registerChatbot($token, $bot->name, $code);

        if (isset($result['error'])) {
            Log::warning('BotController: no se pudo registrar el chatbot en Bitrix', [
                'bot_id' => $bot->id,
                'reason' => $result['message'] ?? 'unknown',
            ]);

            return ['error' => true, 'message' => $result['message'] ?? 'Error desconocido'];
        }

        $bot->update([
            'bitrix_bot_id' => $result['bot_id'],
            'bot_token' => $result['bot_token'],
        ]);

        return ['success' => true, 'bot_id' => $result['bot_id']];
    }

    /**
     * Retry the Bitrix Chatbot 2.0 registration for an existing bot.
     */
    public function register(Bot $bot)
    {
        $registration = $this->registerInBitrix($bot);

        if (isset($registration['error'])) {
            return redirect()->route('bots.index')
                ->with('error', 'No se pudo registrar "'.$bot->name.'" en Bitrix24: '.($registration['message'] ?? 'Error desconocido'));
        }

        return redirect()->route('bots.index')
            ->with('success', 'Bot "'.$bot->name.'" registrado en Bitrix24 como Chatbot '.$registration['bot_id']);
    }

    public function show(Bot $bot)
    {
        $bot->loadCount('conversations');
        $recentConversations = $bot->conversations()
            ->with('contact')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $channels = $this->openChannelService->listChannels();

        return view('bots.show', compact('bot', 'recentConversations', 'channels'));
    }

    public function edit(Bot $bot)
    {
        $channels = $this->openChannelService->listChannels();
        $channelQueues = $this->openChannelService->channelQueues();

        return view('bots.edit', compact('bot', 'channels', 'channelQueues'));
    }

    public function update(Request $request, Bot $bot)
    {
        $this->mergeFilteredMenuOptions($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'openline_id' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'menu_enabled' => 'nullable|boolean',
            'menu_greeting' => 'nullable|string|max:2000',
            'menu_options' => 'nullable|array',
            'menu_options.*.label' => 'nullable|string|max:150',
            'menu_options.*.entity_id' => 'nullable|integer',
            'menu_options.*.entity_type' => 'nullable|string|in:user,department,line',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        if ($request->boolean('menu_enabled')) {
            $menu = $this->buildWelcomeMenu($request);

            if ($menu === null) {
                $validator->errors()->add('menu_options', 'Agrega al menos una opcion con destino al menu de bienvenida.');

                return redirect()->back()->withErrors($validator)->withInput();
            }
        } else {
            $menu = null;
        }

        $data['welcome_menu'] = $menu;
        unset($data['menu_enabled'], $data['menu_greeting'], $data['menu_options']);

        $oldName = $bot->name;
        $bot->update($data);

        if ($bot->bitrix_bot_id && $bot->bot_token && $oldName !== $bot->name) {
            $token = $this->oauth->tenantToken();

            if ($token) {
                $result = $this->provisioning->updateChatbotName($token, $bot->bitrix_bot_id, $bot->bot_token, $bot->name);

                if (isset($result['error'])) {
                    Log::warning('BotController: no se pudo actualizar el nombre del chatbot', [
                        'bot_id' => $bot->id,
                        'reason' => $result['message'] ?? 'unknown',
                    ]);
                }
            }
        }

        return redirect()->route('bots.index')
            ->with('success', 'Bot actualizado correctamente');
    }

    public function destroy(Bot $bot)
    {
        if ($bot->bitrix_bot_id && $bot->bot_token) {
            $token = $this->oauth->tenantToken();

            if ($token) {
                $result = $this->provisioning->unregisterChatbot($token, $bot->bitrix_bot_id, $bot->bot_token);

                if (isset($result['error'])) {
                    Log::warning('BotController: no se pudo eliminar el chatbot en Bitrix', [
                        'bot_id' => $bot->id,
                        'reason' => $result['message'] ?? 'unknown',
                    ]);
                }
            }
        }

        $bot->delete();

        return redirect()->route('bots.index')
            ->with('success', 'Bot eliminado correctamente');
    }
}
