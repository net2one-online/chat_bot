@extends('layouts.app')

@section('title', $bot->name)

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="mb-6">
        <a href="{{ route('bots.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
            &larr; {{ __('Volver a bots') }}
        </a>
    </div>

    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $bot->name }}</h1>
            <p class="text-sm text-gray-500">{{ __('Open Channel') }}: {{ $bot->openline_id ?? __('No configurado') }}</p>
        </div>
        <div class="flex space-x-2">
            <a href="{{ route('bots.edit', $bot->id) }}"
               class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-sm font-medium">
                {{ __('Editar') }}
            </a>
            <form action="{{ route('bots.destroy', $bot->id) }}" method="POST"
                  onsubmit="return confirm('{{ __('Esta seguro de eliminar este bot?') }}')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                    {{ __('Eliminar') }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Informacion') }}</h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Estado') }}</dt>
                        <dd class="mt-1">
                            @if($bot->status === 'active')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    {{ __('Activo') }}
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                    {{ __('Inactivo') }}
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Conversaciones') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $bot->conversations_count ?? 0 }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500">{{ __('Menu de canales') }}</dt>
                        <dd class="mt-1 text-sm text-gray-700">
                            @if($bot->menuEnabled())
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    {{ __('Activo') }}
                                </span>
                                <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                    @foreach($bot->menuOptions() as $index => $option)
                                        @if(($option['entity_type'] ?? '') === 'line')
                                            <li>{{ $index + 1 }}. {{ $option['label'] ?? '' }} &rarr; {{ __('Open Channel :id', ['id' => $option['entity_id'] ?? '']) }}</li>
                                        @else
                                            <li>{{ $index + 1 }}. {{ $option['label'] ?? '' }} &rarr; {{ __('Operador :id', ['id' => $option['entity_id'] ?? '']) }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-sm text-gray-500">{{ __('Desactivado') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500">{{ __('Instrucciones') }}</dt>
                        <dd class="mt-1 text-sm text-gray-700 whitespace-pre-wrap">{{ $bot->system_prompt ?? __('Sin instrucciones configuradas') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div>
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Webhook') }}</h2>
                <p class="text-xs text-gray-500 mb-2">{{ __('URL del webhook para Bitrix24:') }}</p>
                <code class="block p-2 bg-gray-50 rounded text-xs text-gray-700 break-all">
                    {{ url('/api/bitrix/webhook') }}
                </code>
            </div>

            <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Conversaciones Recientes') }}</h2>
                @if($recentConversations->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('No hay conversaciones aun.') }}</p>
                @else
                    <div class="space-y-3">
                        @foreach($recentConversations as $conv)
                        <a href="{{ route('conversations.show', $conv->id) }}"
                           class="block p-3 bg-gray-50 rounded-md hover:bg-gray-100">
                            <div class="text-sm font-medium text-gray-900">
                                {{ __('Chat') }} {{ $conv->bitrix_chat_id }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $conv->updated_at->diffForHumans() }}
                            </div>
                        </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
