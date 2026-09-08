@extends('layouts.app')

@section('title', __('Conversacion'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="mb-6">
        <a href="{{ route('conversations.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
            &larr; {{ __('Volver a conversaciones') }}
        </a>
    </div>

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Conversacion') }} #{{ $conversation->bitrix_chat_id }}</h1>
            <p class="text-sm text-gray-500">
                {{ __('Bot') }}: {{ $conversation->bot->name ?? 'N/A' }} |
                {{ __('Estado') }}:
                @if($conversation->status === 'active')
                    <span class="text-green-600">{{ __('Activo') }}</span>
                @elseif($conversation->status === 'transferred')
                    <span class="text-yellow-600">{{ __('Transferido') }}</span>
                @else
                    <span class="text-gray-600">{{ $conversation->status }}</span>
                @endif
                @if($conversation->human_mode)
                    | <span class="text-blue-600">{{ __('Modo Humano') }}</span>
                @endif
            </p>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="p-6">
            <div class="space-y-4 max-h-[600px] overflow-y-auto">
                @forelse($conversation->messages as $message)
                    <div class="flex {{ $message->role === 'user' ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg
                                    {{ $message->role === 'user'
                                        ? 'bg-gray-100 text-gray-900'
                                        : ($message->role === 'assistant'
                                            ? 'bg-indigo-100 text-indigo-900'
                                            : 'bg-yellow-100 text-yellow-900') }}">
                            <div class="text-xs font-medium mb-1
                                        {{ $message->role === 'user'
                                            ? 'text-gray-500'
                                            : ($message->role === 'assistant'
                                                ? 'text-indigo-500'
                                                : 'text-yellow-500') }}">
                                @if($message->role === 'user')
                                    {{ __('Cliente') }}
                                @elseif($message->role === 'assistant')
                                    {{ __('Bot') }}
                                @else
                                    {{ ucfirst($message->role) }}
                                @endif
                            </div>
                            <div class="text-sm whitespace-pre-wrap">{{ $message->content }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                {{ $message->created_at->format('H:i') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-500 py-8">
                        {{ __('No hay mensajes en esta conversacion.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
