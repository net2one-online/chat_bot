@extends('layouts.app')

@section('title', __('Configuracion'))

@section('content')
<div class="px-4 py-6 sm:px-0">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Configuracion') }}</h1>

    @if($token)
    <div class="max-w-3xl bg-white shadow sm:rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">{{ __('Conexion con este portal') }}</h2>
        </div>
        <div class="px-6 py-4 grid grid-cols-1 gap-3 text-sm">
            <div>
                <span class="text-gray-500">{{ __('Dominio') }}:</span>
                <span class="font-medium text-gray-900">{{ $token->domain ?? __('Sin dominio') }}</span>
            </div>
            <div>
                <span class="text-gray-500">{{ __('Member ID') }}:</span>
                <span class="font-mono text-gray-900">{{ $token->member_id }}</span>
            </div>
        </div>
    </div>
    @endif

    <div class="max-w-3xl bg-white shadow sm:rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">{{ __('Configuracion de Gemini (IA)') }}</h2>
            <p class="text-xs text-gray-500 mt-1">{{ __('Claves para el proveedor de inteligencia artificial.') }}</p>
        </div>
        <form action="{{ route('settings.gemini.update') }}" method="POST" class="p-6 space-y-5">
            @csrf

            <div>
                <label for="api_key" class="block text-sm font-medium text-gray-700">{{ __('API Key de la IA') }}</label>
                <input type="password" name="api_key" id="api_key" autocomplete="new-password"
                       placeholder="{{ $gemini['api_key_present'] ? __('(Guardada. Deja vacio para conservar)') : __('Escribe la API Key') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="model" class="block text-sm font-medium text-gray-700">{{ __('Modelo') }}</label>
                <input type="text" name="model" id="model" value="{{ old('model', $gemini['model']) }}" required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="{{ __('Ej: gemini-2.5-flash') }}">
            </div>

            <div>
                <label for="base_url" class="block text-sm font-medium text-gray-700">{{ __('URL base (opcional)') }}</label>
                <input type="text" name="base_url" id="base_url" value="{{ old('base_url', $gemini['base_url']) }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="https://generativelanguage.googleapis.com/v1beta/openai/v1">
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" data-test="gemini"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ __('Probar conexion') }}
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                    {{ __('Guardar') }}
                </button>
            </div>
        </form>
    </div>

    <div id="test-result" class="mt-6 hidden"></div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('[data-test]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const target = btn.dataset.test;
            const result = document.getElementById('test-result');
            const csrf = document.querySelector('meta[name="csrf-token"]');

            btn.disabled = true;
            const original = btn.textContent;
            btn.textContent = {!! json_encode(__('Probando...')) !!};
            result.classList.add('hidden');

            try {
                const res = await fetch('/settings/test-' + target, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': {!! json_encode(csrf_token()) !!},
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await res.json();

                result.classList.remove('hidden');
                result.className = 'mt-6 p-4 rounded-md ' + (data.ok ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200');
                result.innerHTML = '<p class="text-sm ' + (data.ok ? 'text-green-700' : 'text-red-700') + '">' + (data.message || '') + '</p>';
            } catch (e) {
                result.classList.remove('hidden');
                result.className = 'mt-6 p-4 rounded-md bg-red-50 border border-red-200';
                result.innerHTML = '<p class="text-sm text-red-700">{!! json_encode(__('Error al probar la conexion.')) !!}</p>';
            } finally {
                btn.disabled = false;
                btn.textContent = original;
            }
        });
    });
</script>
@endsection