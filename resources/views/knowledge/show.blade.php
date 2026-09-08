@extends('layouts.app')

@section('title', $knowledgeDocument->title)

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="mb-6">
        <a href="{{ route('knowledge.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
            &larr; {{ __('Volver a base de conocimiento') }}
        </a>
    </div>

    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $knowledgeDocument->title }}</h1>
            <p class="text-sm text-gray-500">
                {{ __('Bot') }}: {{ $knowledgeDocument->bot->name ?? 'N/A' }}
                @if($knowledgeDocument->source)
                    | {{ __('Fuente') }}: {{ $knowledgeDocument->source }}
                @endif
            </p>
        </div>
        <div class="flex space-x-2">
            <a href="{{ route('knowledge.edit', $knowledgeDocument->id) }}"
               class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-sm font-medium">
                {{ __('Editar') }}
            </a>
            <form action="{{ route('knowledge.destroy', $knowledgeDocument->id) }}" method="POST"
                  onsubmit="return confirm('{{ __('Esta seguro de eliminar este documento?') }}')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                    {{ __('Eliminar') }}
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="p-6">
            @if($knowledgeDocument->file_name)
                <div class="mb-4 inline-flex items-center px-3 py-1.5 rounded-md bg-gray-100 text-gray-700 text-sm">
                    {{ __('Archivo adjunto:') }} <span class="ml-1 font-medium">{{ $knowledgeDocument->file_name }}</span>
                </div>
            @endif
            <div class="prose max-w-none">
                {!! nl2br(e($knowledgeDocument->content)) !!}
            </div>
        </div>
    </div>
</div>
@endsection
