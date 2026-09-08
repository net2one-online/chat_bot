@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Dashboard') }}</h1>

    @if(! $setupCompleted)
        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">{{ __('Pendiente: conecta el bot de archivos a tus canales de atencion.') }}</p>
                    </div>
                </div>
                <a href="{{ route('setup.index', $embedMode ? ['embed' => 1] : []) }}"
                   class="ml-4 inline-flex items-center px-3 py-1.5 bg-yellow-600 text-white text-xs font-medium rounded-md hover:bg-yellow-700">
                    {{ __('Configurar') }}
                </a>
            </div>
        </div>
    @endif

    <div class="bg-white shadow rounded-lg p-4 mb-8">
        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="embed" value="{{ $embedMode ? '1' : '' }}">
            <div class="flex flex-wrap gap-1.5">
                @foreach($ranges as $key => $cfg)
                    @php
                        $baseQuery = ['range' => $key] + ($embedMode ? ['embed' => 1] : []);
                        $query = $key === 'custom'
                            ? ($baseQuery + ['from' => request()->query('from', ''), 'to' => request()->query('to', '')])
                            : $baseQuery;
                    @endphp
                    <a href="{{ route('dashboard', $query) }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium border
                              {{ $range === $key
                                  ? 'bg-indigo-600 text-white border-indigo-600'
                                  : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' }}">
                        {{ $cfg['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-end gap-2">
                <div>
                    <label for="from" class="block text-xs text-gray-500 mb-1">{{ __('Desde') }}</label>
                    <input type="date" name="from" id="from" value="{{ request()->query('from', '') }}"
                           class="px-2 py-1.5 text-sm rounded-md border border-gray-300">
                </div>
                <div>
                    <label for="to" class="block text-xs text-gray-500 mb-1">{{ __('Hasta') }}</label>
                    <input type="date" name="to" id="to" value="{{ request()->query('to', '') }}"
                           class="px-2 py-1.5 text-sm rounded-md border border-gray-300">
                </div>
                @if($embedMode)
                    <input type="hidden" name="embed" value="1">
                @endif
                <button type="submit" class="px-4 py-1.5 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                    {{ __('Aplicar') }}
                </button>
                <a href="{{ route('dashboard', $embedMode ? ['embed' => 1] : []) }}"
                   class="px-3 py-1.5 text-sm font-medium text-gray-500 hover:text-gray-700">
                    {{ __('Limpiar') }}
                </a>
            </div>
        </form>
        @if($range !== '')
            <div class="mt-2 text-xs text-gray-500">
                {{ __('Mostrando del') }}
                <span class="font-mono">{{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y H:i') }}</span>
                {{ __('al') }}
                <span class="font-mono">{{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y H:i') }}</span>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('Total de Conversaciones') }}</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $totalConversations }}</div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('Conversaciones Activas') }}</div>
            <div class="mt-2 text-3xl font-bold text-green-600">{{ $activeConversations }}</div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('Conversaciones Transferidas') }}</div>
            <div class="mt-2 text-3xl font-bold text-yellow-600">{{ $transferredConversations }}</div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('Mensajes Procesados') }}</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $totalMessages }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Bots') }}</h2>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">{{ __('Total de bots:') }}</span>
                    <span class="font-medium">{{ $totalBots }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">{{ __('Bots activos:') }}</span>
                    <span class="font-medium text-green-600">{{ $activeBots }}</span>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('bots.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                    {{ __('Ver bots') }}
                </a>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Acciones Rapidas') }}</h2>
            <div class="space-y-3">
                <a href="{{ route('bots.create') }}"
                   class="block w-full text-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                    {{ __('Crear nuevo bot') }}
                </a>
                <a href="{{ route('knowledge.create') }}"
                   class="block w-full text-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm font-medium">
                    {{ __('Agregar conocimiento') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
