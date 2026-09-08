<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $query = Conversation::with(['bot', 'messages']);

        if ($request->filled('bot_id')) {
            $query->where('bot_id', $request->bot_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $conversations = $query->orderBy('updated_at', 'desc')
            ->paginate(20);

        $bots = Bot::all();

        return view('conversations.index', compact('conversations', 'bots'));
    }

    public function show(Conversation $conversation)
    {
        $conversation->load(['bot', 'messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }]);

        return view('conversations.show', compact('conversation'));
    }
}
