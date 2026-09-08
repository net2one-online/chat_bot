@extends('layouts.app')

@section('title', __('Editar Bot'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="mb-6">
        <a href="{{ route('bots.index') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
            &larr; {{ __('Volver a bots') }}
        </a>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Editar Bot') }}: {{ $bot->name }}</h1>

    <div class="bg-white shadow sm:rounded-lg">
        <form action="{{ route('bots.update', $bot->id) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Nombre del bot') }}</label>
                <input type="text" name="name" id="name" value="{{ old('name', $bot->name) }}" required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            {{-- Preserve the currently selected channel when not present in the list --}}
            @php $selected = old('openline_id', $bot->openline_id); $found = false @endphp
            <div>
                <label for="openline_id" class="block text-sm font-medium text-gray-700">{{ __('Open Channel') }}</label>
                @if(!empty($channels))
                    <select name="openline_id" id="openline_id"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">{{ __('Seleccionar Open Channel') }}</option>
                        @foreach($channels as $channel)
                            @if((string)$selected === (string)$channel['ID']) @php $found = true @endphp @endif
                            <option value="{{ $channel['ID'] }}" {{ (string)$selected === (string)$channel['ID'] ? 'selected' : '' }}>
                                {{ $channel['LINE_NAME'] ?? __('Open Channel :id', ['id' => $channel['ID']]) }}
                            </option>
                        @endforeach
                        @if($bot->openline_id && !$found)
                            <option value="{{ $bot->openline_id }}" selected>{{ __('Open Channel :id', ['id' => $bot->openline_id]) }}</option>
                        @endif
                    </select>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Canales abiertos detectados en tu Bitrix24') }}</p>
                @else
                    <input type="text" name="openline_id" id="openline_id" value="{{ $selected }}"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-yellow-600">{{ __('No se pudieron obtener los canales desde Bitrix24. Escribe el ID manualmente.') }}</p>
                @endif
            </div>

            @php $menuOptions = old('menu_options') ?? $bot->menuOptions(); @endphp
            <div class="border-t border-gray-200 pt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-medium text-gray-900">{{ __('Menu de canales') }}</h3>
                        <p class="text-sm text-gray-500">{{ __('Opcional. Cuando el bot decide transferir a una persona, muestra al cliente la lista de canales para que elija donde continuar.') }}</p>
                    </div>
                    <label class="inline-flex items-center space-x-2 text-sm text-gray-700">
                        <input type="checkbox" name="menu_enabled" value="1" id="menu_enabled"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('menu_enabled', $bot->menuEnabled() ? '1' : '') ? 'checked' : '' }}>
                        <span>{{ __('Activar menu') }}</span>
                    </label>
                </div>

                <div id="menu-panel" class="mt-4 {{ old('menu_enabled', $bot->menuEnabled() ? '1' : '') ? '' : 'hidden' }}">
                    <div>
                        <label for="menu_greeting" class="block text-sm font-medium text-gray-700">{{ __('Saludo del menu') }}</label>
                        <textarea name="menu_greeting" id="menu_greeting" rows="2"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                  placeholder="{{ __('Hola, para poder ayudarte mejor responde solo con el numero de una de las siguientes opciones:') }}">{{ old('menu_greeting', $bot->menuGreeting()) }}</textarea>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">{{ __('Opciones') }}</label>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Puede ser un canal de atencion o un operador de la cola del canal del bot. El cliente responde con el numero de la opcion.') }}</p>
                        <table class="mt-2 min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Etiqueta') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Cola / Operador destino') }}</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody id="menu-options-body" class="bg-white divide-y divide-gray-200">
                                @forelse($menuOptions as $index => $row)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <input type="text" name="menu_options[{{ $index }}][label]"
                                                   value="{{ old('menu_options.'.$index.'.label', $row['label'] ?? '') }}"
                                                   placeholder="{{ __('Ej: Soporte') }}"
                                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        </td>
                                        <td class="px-3 py-2">
                                            <select name="menu_options[{{ $index }}][entity_id]"
                                                    class="menu-queue-select block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                <option value="-">-- {{ __('Seleccionar') }} --</option>
                                                @include('bots.partials.menu-destination-options', [
                                                    'lineQueues' => $channelQueues[$selected] ?? [],
                                                    'channelsForTransfer' => $channels,
                                                    'currentLine' => $selected,
                                                    'selectedTarget' => old('menu_options.'.$index.'.entity_id', $row['entity_id'] ?? ''),
                                                ])
                                            </select>
                                            <input type="hidden" name="menu_options[{{ $index }}][entity_type]" value="{{ $row['entity_type'] ?? 'user' }}">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" onclick="this.closest('tr').remove()"
                                                    class="text-red-600 hover:text-red-900 text-sm font-medium">{{ __('Quitar') }}</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-2">
                                            <input type="text" name="menu_options[0][label]" value=""
                                                   placeholder="{{ __('Ej: Soporte') }}"
                                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        </td>
                                        <td class="px-3 py-2">
                                            <select name="menu_options[0][entity_id]"
                                                    class="menu-queue-select block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                <option value="-">-- {{ __('Seleccionar') }} --</option>
                                                @include('bots.partials.menu-destination-options', [
                                                    'lineQueues' => $channelQueues[$selected] ?? [],
                                                    'channelsForTransfer' => $channels,
                                                    'currentLine' => $selected,
                                                    'selectedTarget' => '',
                                                ])
                                            </select>
                                            <input type="hidden" name="menu_options[0][entity_type]" value="user">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" onclick="this.closest('tr').remove()"
                                                    class="text-red-600 hover:text-red-900 text-sm font-medium">{{ __('Quitar') }}</button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        <button type="button" id="add-option-btn"
                                class="mt-3 px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            + {{ __('Agregar opcion') }}
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <label for="system_prompt" class="block text-sm font-medium text-gray-700">{{ __('Instrucciones del agente') }}</label>
                <textarea name="system_prompt" id="system_prompt" rows="6"
                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">{{ old('system_prompt', $bot->system_prompt) }}</textarea>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">{{ __('Estado') }}</label>
                <select name="status" id="status"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="active" {{ old('status', $bot->status) === 'active' ? 'selected' : '' }}>{{ __('Activo') }}</option>
                    <option value="inactive" {{ old('status', $bot->status) === 'inactive' ? 'selected' : '' }}>{{ __('Inactivo') }}</option>
                </select>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('bots.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ __('Cancelar') }}
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                    {{ __('Actualizar Bot') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const channelQueues = @json($channelQueues);
    const channelLines = @json($channels);
    const openlineSelect = document.getElementById('openline_id');
    const menuEnabled = document.getElementById('menu_enabled');
    const menuPanel = document.getElementById('menu-panel');
    const menuBody = document.getElementById('menu-options-body');
    const addBtn = document.getElementById('add-option-btn');
    let menuRowIndex = @json(count($menuOptions));
    if (menuRowIndex === 0) { menuRowIndex = 1; }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function currentLineId() {
        return (openlineSelect && openlineSelect.value) || '';
    }

    function queueOptions(selectedId) {
        const members = channelQueues[currentLineId()] || [];
        const lines = channelLines || [];
        const current = currentLineId();
        let html = '<option value="-">-- {{ __('Seleccionar') }} --</option>';
        if (members.length) {
            html += '<optgroup label="{{ __('Cola del canal actual') }}">';
            members.forEach((q) => {
                const selected = selectedId !== '' && String(q.entity_id) === String(selectedId) ? ' selected' : '';
                html += `<option value="${escapeHtml(q.entity_id)}" data-type="${escapeHtml(q.entity_type)}"${selected}>${escapeHtml(q.label)}</option>`;
            });
            html += '</optgroup>';
        }
        html += '<optgroup label="{{ __('Otro Open Channel') }}">';
        lines.forEach((l) => {
            if (current !== '' && String(l.ID) === current) { return; }
            const selected = selectedId !== '' && String(l.ID) === String(selectedId) ? ' selected' : '';
            html += `<option value="${escapeHtml(l.ID)}" data-type="line"${selected}>${escapeHtml(l.LINE_NAME || 'Open Channel ' + l.ID)}</option>`;
        });
        html += '</optgroup>';
        return html;
    }

    function renderQueueSelects() {
        document.querySelectorAll('#menu-options-body select[name$="[entity_id]"]').forEach((sel) => {
            const current = (sel.value && sel.value !== '-') ? sel.value : '';
            sel.innerHTML = queueOptions(current);
        });
    }

    function addMenuRow() {
        const tr = document.createElement('tr');
        const id = menuRowIndex++;
        tr.innerHTML = `
            <td class="px-3 py-2">
                <input type="text" name="menu_options[${id}][label]" value=""
                       placeholder="{{ __('Ej: Soporte') }}"
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </td>
            <td class="px-3 py-2">
                <select name="menu_options[${id}][entity_id]"
                        class="menu-queue-select block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">${queueOptions('')}</select>
                <input type="hidden" name="menu_options[${id}][entity_type]" value="user">
            </td>
            <td class="px-3 py-2 text-right">
                <button type="button" onclick="this.closest('tr').remove()"
                        class="text-red-600 hover:text-red-900 text-sm font-medium">{{ __('Quitar') }}</button>
            </td>`;
        menuBody.appendChild(tr);
    }

    if (addBtn) { addBtn.addEventListener('click', addMenuRow); }
    if (menuEnabled) {
        menuEnabled.addEventListener('change', () => {
            menuPanel.classList.toggle('hidden', !menuEnabled.checked);
        });
    }
    if (openlineSelect) { openlineSelect.addEventListener('change', renderQueueSelects); }

    menuBody.addEventListener('change', (e) => {
        if (e.target.classList.contains('menu-queue-select')) {
            const option = e.target.selectedOptions[0];
            const typeInput = e.target.closest('td').querySelector('input[type="hidden"]');
            if (typeInput) { typeInput.value = option && option.dataset.type ? option.dataset.type : 'user'; }
        }
    });
</script>
@endsection
