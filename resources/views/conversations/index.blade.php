@extends('layouts.app')

@section('title', __('Conversaciones'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Conversaciones') }}</h1>

    <div class="mb-4 flex flex-wrap gap-4">
        <form action="{{ route('conversations.index') }}" method="GET" class="flex gap-4">
            <select name="bot_id" class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('Todos los bots') }}</option>
                @foreach($bots as $bot)
                    <option value="{{ $bot->id }}" {{ request('bot_id') == $bot->id ? 'selected' : '' }}>
                        {{ $bot->name }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('Todos los estados') }}</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('Activo') }}</option>
                <option value="waiting" {{ request('status') == 'waiting' ? 'selected' : '' }}>{{ __('Esperando') }}</option>
                <option value="transferred" {{ request('status') == 'transferred' ? 'selected' : '' }}>{{ __('Transferido') }}</option>
                <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('Cerrado') }}</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                {{ __('Filtrar') }}
            </button>
        </form>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Chat ID') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Bot') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Estado') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Modo Humano') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Ultima Actualizacion') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Acciones') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($conversations as $conversation)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ $conversation->bitrix_chat_id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $conversation->bot->name ?? 'N/A' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($conversation->status === 'active')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ __('Activo') }}</span>
                        @elseif($conversation->status === 'transferred')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">{{ __('Transferido') }}</span>
                        @elseif($conversation->status === 'closed')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ __('Cerrado') }}</span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">{{ $conversation->status }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $conversation->human_mode ? __('Si') : __('No') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $conversation->updated_at->diffForHumans() }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('conversations.show', $conversation->id) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Ver') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                        {{ __('No hay conversaciones.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $conversations->withQueryString()->links() }}
    </div>
</div>
@endsection
