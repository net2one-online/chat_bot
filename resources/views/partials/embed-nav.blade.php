<nav class="bg-white shadow px-4 py-3">
    <div class="max-w-7xl mx-auto flex flex-wrap items-center gap-2">
        <a href="{{ route('dashboard', ['embed' => 1]) }}" class="text-lg font-bold text-gray-900 mr-3">
            Bot Chat Platform
        </a>
        <a href="{{ route('dashboard', ['embed' => 1]) }}"
           class="px-3 py-1.5 text-sm rounded-md {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white' : 'text-gray-700 border border-gray-300 hover:bg-gray-100' }}">
            {{ __('Dashboard') }}
        </a>
        <a href="{{ route('chat.index', ['embed' => 1]) }}"
           class="px-3 py-1.5 text-sm rounded-md {{ request()->routeIs('chat.*') ? 'bg-indigo-600 text-white' : 'text-gray-700 border border-gray-300 hover:bg-gray-100' }}">
            {{ __('Chat') }}
        </a>
        <a href="{{ route('bots.index', ['embed' => 1]) }}"
           class="px-3 py-1.5 text-sm rounded-md {{ request()->routeIs('bots.*') ? 'bg-indigo-600 text-white' : 'text-gray-700 border border-gray-300 hover:bg-gray-100' }}">
            {{ __('Bots') }}
        </a>
        <a href="{{ route('conversations.index', ['embed' => 1]) }}"
           class="px-3 py-1.5 text-sm rounded-md {{ request()->routeIs('conversations.*') ? 'bg-indigo-600 text-white' : 'text-gray-700 border border-gray-300 hover:bg-gray-100' }}">
            {{ __('Conversaciones') }}
        </a>
        <a href="{{ route('knowledge.index', ['embed' => 1]) }}"
           class="px-3 py-1.5 text-sm rounded-md {{ request()->routeIs('knowledge.*') ? 'bg-indigo-600 text-white' : 'text-gray-700 border border-gray-300 hover:bg-gray-100' }}">
            {{ __('Base de Conocimiento') }}
        </a>
        <a href="{{ route('settings.index', ['embed' => 1]) }}"
           class="px-3 py-1.5 text-sm rounded-md {{ request()->routeIs('settings.*') ? 'bg-indigo-600 text-white' : 'text-gray-700 border border-gray-300 hover:bg-gray-100' }}">
            {{ __('Configuracion') }}
        </a>
        <a href="{{ route('setup.index', ['embed' => 1]) }}"
           class="px-3 py-1.5 text-sm rounded-md {{ request()->routeIs('setup.*') ? 'bg-indigo-600 text-white' : 'text-gray-700 border border-gray-300 hover:bg-gray-100' }}">
            {{ __('Setup') }}
        </a>
    </div>
</nav>