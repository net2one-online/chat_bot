<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $query = KnowledgeDocument::with('bot');

        if ($request->filled('bot_id')) {
            $query->where('bot_id', $request->bot_id);
        }

        $documents = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        $bots = Bot::all();

        return view('knowledge.index', compact('documents', 'bots'));
    }

    public function create()
    {
        $bots = Bot::all();

        return view('knowledge.create', compact('bots'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'source' => 'nullable|string|max:255',
            'file' => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $data['file_name'] = $uploaded->getClientOriginalName();
            $data['file_mime'] = $uploaded->getMimeType();
            $data['file_data'] = file_get_contents($uploaded->getRealPath());
        }

        KnowledgeDocument::create($data);

        return redirect()->route('knowledge.index')
            ->with('success', 'Documento creado correctamente');
    }

    public function show($knowledgeDocument)
    {
        $knowledgeDocument = KnowledgeDocument::findOrFail($knowledgeDocument);
        $knowledgeDocument->load('bot');

        return view('knowledge.show', compact('knowledgeDocument'));
    }

    public function edit($knowledgeDocument)
    {
        $knowledgeDocument = KnowledgeDocument::findOrFail($knowledgeDocument);
        $bots = Bot::all();

        return view('knowledge.edit', compact('knowledgeDocument', 'bots'));
    }

    public function update(Request $request, $knowledgeDocument)
    {
        $knowledgeDocument = KnowledgeDocument::findOrFail($knowledgeDocument);
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'source' => 'nullable|string|max:255',
            'file' => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $data['file_name'] = $uploaded->getClientOriginalName();
            $data['file_mime'] = $uploaded->getMimeType();
            $data['file_data'] = file_get_contents($uploaded->getRealPath());
        } elseif ($request->boolean('remove_file')) {
            $data['file_name'] = null;
            $data['file_mime'] = null;
            $data['file_data'] = null;
        }

        $knowledgeDocument->update($data);

        return redirect()->route('knowledge.index')
            ->with('success', 'Documento actualizado correctamente');
    }

    public function destroy($knowledgeDocument)
    {
        $knowledgeDocument = KnowledgeDocument::findOrFail($knowledgeDocument);
        $knowledgeDocument->delete();

        return redirect()->route('knowledge.index')
            ->with('success', 'Documento eliminado correctamente');
    }
}
