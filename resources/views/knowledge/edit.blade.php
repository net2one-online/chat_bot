@extends('layouts.app')

@section('title', __('Editar Documento'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="mb-6">
        <a href="{{ route('knowledge.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
            &larr; {{ __('Volver a base de conocimiento') }}
        </a>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Editar Documento') }}: {{ $knowledgeDocument->title }}</h1>

    <div class="bg-white shadow sm:rounded-lg">
        <form action="{{ route('knowledge.update', $knowledgeDocument->id) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label for="bot_id" class="block text-sm font-medium text-gray-700">{{ __('Bot') }}</label>
                <select name="bot_id" id="bot_id" required
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    @foreach($bots as $bot)
                        <option value="{{ $bot->id }}" {{ old('bot_id', $knowledgeDocument->bot_id) == $bot->id ? 'selected' : '' }}>
                            {{ $bot->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">{{ __('Titulo') }}</label>
                <input type="text" name="title" id="title" value="{{ old('title', $knowledgeDocument->title) }}" required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="content" class="block text-sm font-medium text-gray-700">{{ __('Contenido') }}</label>
                <textarea name="content" id="content" rows="10" required
                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">{{ old('content', $knowledgeDocument->content) }}</textarea>
            </div>

            <div>
                <label for="source" class="block text-sm font-medium text-gray-700">{{ __('Fuente (opcional)') }}</label>
                <input type="text" name="source" id="source" value="{{ old('source', $knowledgeDocument->source) }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="file" class="block text-sm font-medium text-gray-700">{{ __('Archivo adjunto (opcional)') }}</label>
                @if($knowledgeDocument->file_name)
                    <p class="mt-2 text-sm text-gray-700">
                        {{ __('Archivo actual:') }} <span class="font-medium">{{ $knowledgeDocument->file_name }}</span>
                        ({{ number_format(strlen((string) $knowledgeDocument->file_data) / 1024, 1, '.', ',') }} KB)
                    </p>
                    <label class="mt-3 inline-flex items-center">
                        <input type="checkbox" name="remove_file" value="1" class="h-4 w-4 border-gray-300 rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">{{ __('Quitar archivo') }}</span>
                    </label>
                @endif
                <input type="file" name="file" id="file"
                       class="mt-1 block w-full text-sm text-gray-700 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('PDF, imagen u otro archivo que el bot deba enviar al cliente. Para que el bot lo envie, indica en el contenido el nombre exacto del archivo (ej: "enviar manual.pdf"). Si subes uno nuevo reemplazara el actual.') }}
                </p>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('knowledge.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ __('Cancelar') }}
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                    {{ __('Actualizar Documento') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
