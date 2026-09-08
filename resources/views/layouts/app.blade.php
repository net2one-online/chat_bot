<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bot Chat Platform')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
@php $embedMode = session('iframe_mode') || request()->query('embed') === '1'; @endphp
<body class="bg-gray-50 min-h-screen">
    @if($embedMode)
        @include('partials.embed-nav')
    @else
        <nav class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="{{ route('dashboard') }}" class="text-xl font-bold text-gray-900">
                                Bot Chat Platform
                            </a>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="{{ route('dashboard') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium
                                      {{ request()->routeIs('dashboard') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                {{ __('Dashboard') }}
                            </a>
                            <a href="{{ route('chat.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium
                                      {{ request()->routeIs('chat.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                {{ __('Chat') }}
                            </a>
                            <a href="{{ route('bots.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium
                                      {{ request()->routeIs('bots.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                {{ __('Bots') }}
                            </a>
                            <a href="{{ route('conversations.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium
                                      {{ request()->routeIs('conversations.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                {{ __('Conversaciones') }}
                            </a>
                            <a href="{{ route('knowledge.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium
                                      {{ request()->routeIs('knowledge.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                {{ __('Base de Conocimiento') }}
                            </a>
                            <a href="{{ route('settings.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium
                                      {{ request()->routeIs('settings.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                {{ __('Configuracion') }}
                            </a>
                            <a href="{{ route('setup.index') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium
                                      {{ request()->routeIs('setup.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                {{ __('Setup') }}
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">{{ __('Bot Chat Platform') }}</span>
                    </div>
                </div>
            </div>
        </nav>
    @endif

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                <p class="text-green-700 text-sm">{{ session('success') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                <ul class="list-disc list-inside text-red-700 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @yield('scripts')
</body>
</html>