<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AiAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatTesterController extends Controller
{
    public function index()
    {
        $bots = Bot::all();
        return view('chat.index', compact('bots'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'bot_id' => 'required|exists:bots,id',
            'message' => 'required|string',
        ]);

        $bot = Bot::findOrFail($request->bot_id);

        if (!$bot->isActive()) {
            return response()->json(['error' => 'El bot esta inactivo'], 422);
        }

        $chatId = $request->input('chat_id', session('tester_chat_id', Str::uuid()->toString()));
        session(['tester_chat_id' => $chatId]);

        $conversation = Conversation::where('bot_id', $bot->id)
            ->where('bitrix_chat_id', $chatId)
            ->where('status', 'active')
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'bitrix_chat_id' => $chatId,
                'bitrix_session_id' => null,
                'contact_id' => null,
                'status' => 'active',
                'human_mode' => false,
            ]);
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        if ($conversation->isHumanMode()) {
            return response()->json([
                'message' => 'La conversacion esta en modo humano. El bot ya no responde.',
            ]);
        }

        try {
            $agent = app(AiAgentService::class);
            $response = $agent->processMessage($conversation, $request->message);
            $responseContent = $response;
        } catch (\Exception $e) {
            Log::error('Error en chat de prueba', ['error' => $e->getMessage()]);
            $responseContent = 'Ocurrio un error al procesar el mensaje. Revisa el servidor de colas.';
        }

        return response()->json([
            'message' => $responseContent,
            'conversation_id' => $conversation->id,
        ]);
    }

    public function reset(Request $request)
    {
        $request->session()->forget('tester_chat_id');

        return redirect()->route('chat.index')
            ->with('success', 'Conversacion de prueba reiniciada');
    }
}
