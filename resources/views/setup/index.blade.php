@extends('layouts.app')

@section('title', __('Configuracion Inicial'))

@section('content')
<div class="px-4 py-6 sm:px-0">

    @if($setupCompleted)
        <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
            <div class="flex items-center justify-between">
                <p class="text-green-700 text-sm font-medium">{{ __('Configuracion completada correctamente.') }}</p>
                <form method="POST" action="{{ route('setup.reset') }}">
                    @csrf
                    <button type="submit" class="text-xs text-green-600 underline hover:text-green-800">
                        {{ __('Reiniciar configuracion') }}
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">{{ __('Aun no se ha completado la configuracion inicial.') }}</p>
                </div>
            </div>
        </div>
    @endif

    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Configuracion Inicial') }}</h1>

    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Paso 1: Bot de archivos') }}</h2>
        <p class="text-sm text-gray-600 mb-4">
            {{ __('El bot de archivos se ha registrado automaticamente con ID:') }}
            <span class="font-mono font-bold text-indigo-600">{{ $botId }}</span>
        </p>
        @if($domain)
            <p class="text-sm text-gray-600">
                {{ __('Portal:') }}
                <span class="font-mono text-gray-800">{{ $domain }}</span>
            </p>
        @endif
    </div>

    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Paso 2: Conectar a canales de atencion') }}</h2>
        <p class="text-sm text-gray-600 mb-4">
            {{ __('Para que el bot pueda enviar archivos, debes asignarlo como chatbot en tus canales de atencion.') }}
        </p>

        @if(empty($channels))
            <p class="text-sm text-gray-500 mb-4">{{ __('No se encontraron canales de atencion abiertos en tu portal.') }}</p>
        @else
            <div class="overflow-x-auto mb-4">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('ID') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Nombre') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Estado') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($channels as $channel)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $channel['ID'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $channel['LINE_NAME'] ?? 'Sin nombre' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(($channel['ACTIVE'] ?? 'N') === 'Y')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ __('Activo') }}</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ __('Inactivo') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="bg-gray-50 rounded-md p-4 mb-4">
            <h3 class="text-sm font-medium text-gray-900 mb-2">{{ __('Instrucciones') }}</h3>
            <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                <li>{{ __('Abre la configuracion de Contact Center en tu portal de Bitrix24.') }}</li>
                <li>{{ __('Selecciona el canal de atencion que deseas configurar.') }}</li>
                <li>{{ __('Busca la seccion "Chatbot" y selecciona el bot con ID:') }} <span class="font-mono font-bold">{{ $botId }}</span></li>
                <li>{{ __('Guarda los cambios.') }}</li>
                <li>{{ __('Haz clic en "Completar configuracion" abajo.') }}</li>
            </ol>
        </div>

        <a href="{{ $contactCenterUrl }}" target="_blank"
           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium mb-4">
            {{ __('Abrir Contact Center') }}
            <svg class="ml-2 -mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
        </a>

        @if(! $setupCompleted)
            <form method="POST" action="{{ route('setup.complete') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium">
                    {{ __('Completar configuracion') }}
                </button>
            </form>
        @endif
    </div>
</div>
@endsection