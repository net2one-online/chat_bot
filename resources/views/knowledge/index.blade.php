@extends('layouts.app')

@section('title', __('Base de Conocimiento'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Base de Conocimiento') }}</h1>
        <a href="{{ route('knowledge.create') }}"
           class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
            {{ __('Agregar Documento') }}
        </a>
    </div>

    <div class="mb-4">
        <form action="{{ route('knowledge.index') }}" method="GET" class="flex gap-4">
            <select name="bot_id" class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('Todos los bots') }}</option>
                @foreach($bots as $bot)
                    <option value="{{ $bot->id }}" {{ request('bot_id') == $bot->id ? 'selected' : '' }}>
                        {{ $bot->name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                {{ __('Filtrar') }}
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($documents as $document)
        <div class="bg-white shadow sm:rounded-lg p-6">
            <div class="flex justify-between items-start mb-2">
                <h3 class="text-lg font-medium text-gray-900">{{ $document->title }}</h3>
                @if($document->source)
                    <span class="text-xs text-gray-500">{{ $document->source }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-600 mb-4 line-clamp-3">{{ Str::limit($document->content, 150) }}</p>
            @if($document->file_name)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 mb-4">
                    {{ $document->file_name }}
                </span>
            @endif
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-500">{{ __('Bot') }}: {{ $document->bot->name ?? 'N/A' }}</span>
                <div class="space-x-2">
                    <a href="{{ route('knowledge.show', $document->id) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">{{ __('Ver') }}</a>
                    <a href="{{ route('knowledge.edit', $document->id) }}" class="text-yellow-600 hover:text-yellow-900 text-sm">{{ __('Editar') }}</a>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-8 text-gray-500">
            {{ __('No hay documentos de conocimiento. Agrega el primero para que tu bot pueda responder preguntas.') }}
        </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $documents->withQueryString()->links() }}
    </div>
</div>
@endsection
