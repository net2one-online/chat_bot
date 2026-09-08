@extends('layouts.app')

@section('title', __('Probar Chat'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Probar Chat') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Chatea con el bot directamente para probar como responde.') }}</p>
        </div>
        <a href="{{ route('chat.reset') }}"
           class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
            {{ __('Reiniciar conversacion') }}
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <label for="bot-select" class="block text-sm font-medium text-gray-700">{{ __('Seleccionar bot') }}</label>
                @if($bots->isEmpty())
                    <p class="mt-2 text-sm text-yellow-600">
                        {{ __('No hay bots creados.') }} <a href="{{ route('bots.create') }}" class="text-indigo-600 hover:text-indigo-500">{{ __('Crea uno primero') }}</a>.
                    </p>
                @else
                    <select id="bot-select"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        @foreach($bots as $bot)
                            <option value="{{ $bot->id }}" {{ $bot->isActive() ? 'selected' : '' }}>
                                {{ $bot->name }} {{ $bot->isActive() ? '' : '('. __('inactivo') .')' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-3 text-xs text-gray-500">{{ __('El bot procesa los mensajes con IA y consulta la base de conocimiento.') }}</p>
                @endif
            </div>

            @if(session('success'))
                <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-md">
                    <p class="text-green-700 text-sm">{{ session('success') }}</p>
                </div>
            @endif
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white shadow sm:rounded-lg flex flex-col" style="height: 520px;">
                <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-4">
                    <div class="flex justify-center">
                        <div class="text-center text-gray-400 text-sm bg-gray-50 rounded-lg px-4 py-3">
                            {{ __('Envia un mensaje para comenzar la conversacion.') }}<br>
                            {{ __('Prueba preguntando por los planes de servicio o pidiendo hablar con una persona.') }}
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 p-4">
                    <form id="chat-form" class="flex gap-3">
                        <input type="text" id="message-input"
                               placeholder="{{ __('Escribe tu mensaje...') }}"
                               autocomplete="off"
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               {{ $bots->isEmpty() ? 'disabled' : 'autofocus' }}>
                        <button type="submit" id="send-btn"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium disabled:opacity-50"
                                {{ $bots->isEmpty() ? 'disabled' : '' }}>
                            {{ __('Enviar') }}
                        </button>
                    </form>
                    <button id="quick-plans" class="mt-2 text-xs text-indigo-600 hover:text-indigo-500 mr-2">{{ __('Guion: que planes tienen?') }}</button>
                    <button id="quick-basic" class="text-xs text-indigo-600 hover:text-indigo-500">{{ __('Guion: que incluye el plan basico?') }}</button>
                    <button id="quick-human" class="text-xs text-indigo-600 hover:text-indigo-500 ml-2">{{ __('Guion: quiero hablar con una persona') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const botSelect = document.getElementById('bot-select');
    const i18n = {
        you: {!! json_encode(__('Tu')) !!},
        bot: {!! json_encode(__('Bot')) !!},
        typing: {!! json_encode(__('Escribiendo...')) !!},
        notJson: function(status) {
            return {!! json_encode(__('El servidor respondio con estado') . ' ' . __('respuesta no JSON')) !!} + ' ' + status + '. ' + {!! json_encode(__('Recarga la pagina e intentalo de nuevo.')) !!};
        },
        error: {!! json_encode(__('Ocurrio un error (estado')) !!},
        connError: {!! json_encode(__('Error de conexion. Asegurate de que el servidor este corriendo.')) !!},
    };

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function addBubble(role, text, meta) {
        const isUser = role === 'user';
        const wrap = document.createElement('div');
        wrap.className = 'flex ' + (isUser ? 'justify-end' : 'justify-start');

        const bubble = document.createElement('div');
        bubble.className = 'max-w-xs lg:max-w-md px-4 py-2 rounded-lg whitespace-pre-wrap ' +
            (isUser ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-900');

        const label = document.createElement('div');
        label.className = 'text-xs font-medium mb-1 ' + (isUser ? 'text-indigo-200' : 'text-gray-500');
        label.textContent = isUser ? i18n.you : i18n.bot;
        bubble.appendChild(label);

        const textEl = document.createElement('div');
        textEl.className = 'text-sm';
        textEl.textContent = text;
        bubble.appendChild(textEl);

        wrap.appendChild(bubble);
        chatMessages.appendChild(wrap);
        scrollToBottom();
    }

    function addTyping() {
        const wrap = document.createElement('div');
        wrap.id = 'typing-indicator';
        wrap.className = 'flex justify-start';
        wrap.innerHTML = '<div class="bg-gray-100 text-gray-500 text-sm px-4 py-3 rounded-lg">' + i18n.typing + '</div>';
        chatMessages.appendChild(wrap);
        scrollToBottom();
    }

    function removeTyping() {
        const t = document.getElementById('typing-indicator');
        if (t) t.remove();
    }

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = messageInput.value.trim();
        if (!message) return;

        messageInput.value = '';
        addBubble('user', message);

        const botId = botSelect ? botSelect.value : null;
        if (!botId) return;

        sendBtn.disabled = true;
        addTyping();

        try {
            const res = await fetch('{{ route('chat.send', [], false) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ bot_id: botId, message }),
            });

            let data = {};
            try {
                data = await res.json();
            } catch (e) {
                data = { error: i18n.notJson(res.status) };
            }

            removeTyping();

            if (!res.ok) {
                if (res.status === 419 && !window.__csrfRefreshed) {
                    window.__csrfRefreshed = true;
                    location.reload();
                    return;
                }
                addBubble('assistant', data.error || i18n.error + ' ' + res.status + '.', { error: true });
            } else {
                addBubble('assistant', data.message);
            }
        } catch (err) {
            removeTyping();
            addBubble('assistant', i18n.connError, { error: true });
        } finally {
            sendBtn.disabled = false;
            messageInput.focus();
        }
    });

    function fillAndSend(text) {
        messageInput.value = text;
        chatForm.requestSubmit();
    }

    const quickButtons = {
        'quick-plans': 'Hola, que planes de servicio tienen?',
        'quick-basic': 'Que incluye el plan basico?',
        'quick-human': 'Quiero hablar con una persona',
    };

    for (const [id, text] of Object.entries(quickButtons)) {
        document.getElementById(id).addEventListener('click', () => fillAndSend(text));
    }
</script>
@endsection
