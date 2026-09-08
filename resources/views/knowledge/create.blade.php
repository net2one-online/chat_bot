@extends('layouts.app')

@section('title', __('Agregar Documento de Conocimiento'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="mb-6">
        <a href="{{ route('knowledge.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
            &larr; {{ __('Volver a base de conocimiento') }}
        </a>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Agregar Documento de Conocimiento') }}</h1>

    <div class="bg-white shadow sm:rounded-lg">
        <form action="{{ route('knowledge.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf

            <div>
                <label for="bot_id" class="block text-sm font-medium text-gray-700">{{ __('Bot') }}</label>
                <select name="bot_id" id="bot_id" required
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">{{ __('Seleccionar bot') }}</option>
                    @foreach($bots as $bot)
                        <option value="{{ $bot->id }}" {{ old('bot_id') == $bot->id ? 'selected' : '' }}>
                            {{ $bot->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">{{ __('Titulo') }}</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="{{ __('Ej: Planes comerciales') }}">
            </div>

            <div>
                <label for="content" class="block text-sm font-medium text-gray-700">{{ __('Contenido') }}</label>
                <textarea name="content" id="content" rows="10" required
                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                          placeholder="{{ __('Escribe aqui la informacion que el bot debe conocer.') }}&#10;&#10;{{ __('Ejemplo:') }}&#10;{{ __('El plan basico incluye...') }}&#10;{{ __('El plan profesional incluye...') }}">{{ old('content') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('Incluye toda la informacion relevante que el bot necesite para responder preguntas.') }}
                </p>
            </div>

            <div>
                <label for="source" class="block text-sm font-medium text-gray-700">{{ __('Fuente (opcional)') }}</label>
                <input type="text" name="source" id="source" value="{{ old('source') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="{{ __('Ej: Manual de ventas, FAQ, Politicas') }}">
            </div>

            <div>
                <label for="file" class="block text-sm font-medium text-gray-700">{{ __('Archivo adjunto (opcional)') }}</label>
                <input type="file" name="file" id="file"
                       class="mt-1 block w-full text-sm text-gray-700 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('PDF, imagen u otro archivo que el bot deba enviar al cliente. Para que el bot lo envie, indica en el contenido el nombre exacto del archivo (ej: "enviar manual.pdf").') }}
                </p>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('knowledge.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ __('Cancelar') }}
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                    {{ __('Guardar Documento') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
