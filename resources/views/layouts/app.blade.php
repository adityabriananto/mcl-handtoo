<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script>
        (function() {
            const appearance = '{{ $appearance ?? "system" }}';
            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) { document.documentElement.classList.add('dark'); }
            }
        })();
    </script>

    <style>
        html { background-color: #f3f4f6; }
        html.dark { background-color: #1f2937; }
        [x-cloak] { display: none !important; }
    </style>

    <title>{{ config('app.name', 'MCL - HandToo') }} | @yield('title', 'Handover Visibility Tool')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-gray-800">
    <header>
        <nav class="bg-white dark:bg-gray-900 shadow-lg fixed top-0 w-full z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                   <a class="text-xl font-bold text-gray-800 dark:text-white transition duration-150" href="{{ route('handover.index') }}">
                        📦 MCL - HandToo
                    </a>
                    <div class="flex space-x-2 items-center">
                        @guest
                        {{-- Menu Umum --}}
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open"
                                    class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition duration-150 focus:outline-none
                                        @if(request()->routeIs('handover.*') || request()->routeIs('history.*'))
                                            bg-blue-600 text-white
                                        @else
                                            text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                        @endif">
                                <span>🚚 Handover</span>
                                <svg class="ml-1 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>

                            <div x-show="open" x-cloak x-transition.opacity.scale.95
                                class="absolute right-0 mt-2 w-56 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20">
                                <a href="{{ route('handover.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('handover.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    Handover Station
                                </a>
                                <a href="{{ route('history.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('history.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    Handover Dashboard
                                </a>
                                 <a href="{{ route('handover.upload.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('handover.upload.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    Document Upload Data
                                </a>
                            </div>
                        </div>
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open"
                                    class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition duration-150 focus:outline-none
                                        @if(request()->routeIs('tpl.config.*') || request()->routeIs('client_api.index'))
                                            bg-blue-600 text-white
                                        @else
                                            text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                        @endif">
                                <span>⚙️ Configuration</span>
                                <svg class="ml-1 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>

                            <div x-show="open" x-cloak x-transition.opacity.scale.95
                                class="absolute right-0 mt-2 w-56 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20">
                                <a href="{{ route('tpl.config.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('tpl.config.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    3PL Configuration
                                </a>
                            </div>
                        </div>
                        @endguest
                    @auth
                        {{-- Menu Admin --}}

                        @if(auth()->user()->role !== 'admin')
                        @else
                        {{-- Menu Handover --}}
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open"
                                    class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition duration-150 focus:outline-none
                                        @if(request()->routeIs('handover.*') || request()->routeIs('history.*'))
                                            bg-blue-600 text-white
                                        @else
                                            text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                        @endif">
                                <span>🚚 Handover</span>
                                <svg class="ml-1 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>

                            <div x-show="open" x-cloak x-transition.opacity.scale.95
                                class="absolute right-0 mt-2 w-56 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20">
                                <a href="{{ route('handover.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('handover.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    Handover Station
                                </a>
                                <a href="{{ route('history.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('history.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    Handover Dashboard
                                </a>
                                <a href="{{ route('handover.upload.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('handover.upload.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    Document Upload Data
                                </a>
                            </div>
                        </div>

                        {{-- Menu Inbound --}}
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open"
                                    class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition duration-150 focus:outline-none
                                        @if(request()->routeIs('inbound.*')))
                                            bg-blue-600 text-white
                                        @else
                                            text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                        @endif">
                                <span>📥  Inbound</span>
                                <svg class="ml-1 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>

                            <div x-show="open" x-cloak x-transition.opacity.scale.95
                                class="absolute right-0 mt-2 w-56 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20">
                                <a href="{{ route('inbound.index') }}"
                               class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition duration-150 focus:outline-none
                                    @if(request()->routeIs('inbound.*'))
                                        bg-blue-600 text-white
                                    @else
                                        text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                    @endif">
                                <span>Inbound Order Data</span>
                            </a>
                            </div>
                        </div>

                        {{-- Menu MB Master --}}
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open"
                                    class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition duration-150 focus:outline-none
                                        @if(request()->routeIs('mb-master.*'))
                                            bg-blue-600 text-white shadow-md
                                        @else
                                            text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                        @endif">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                    MB Master
                                </span>
                                <svg class="ml-1 h-4 w-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div x-show="open" x-cloak x-transition.opacity.scale.95
                                class="absolute right-0 mt-2 w-52 rounded-xl shadow-xl py-2 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20 border border-gray-100 dark:border-gray-700">
                                <a href="{{ route('mb-master.index') }}"
                                class="group flex items-center px-4 py-2.5 text-sm transition-all
                                    {{ request()->routeIs('mb-master.index')
                                        ? 'bg-blue-50 text-blue-600 font-bold dark:bg-blue-900/20'
                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-2 {{ request()->routeIs('mb-master.index') ? 'bg-blue-600' : 'bg-gray-300' }}"></span>
                                    MB Master Data
                                </a>
                            </div>
                        </div>

                        {{-- Menu Configuration --}}
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open"
                                    class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition duration-150 focus:outline-none
                                        @if(request()->routeIs('tpl.config.*') || request()->routeIs('client_api.index'))
                                            bg-blue-600 text-white
                                        @else
                                            text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                        @endif">
                                <span>⚙️ Configuration</span>
                                <svg class="ml-1 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>

                            <div x-show="open" x-cloak x-transition.opacity.scale.95
                                class="absolute right-0 mt-2 w-56 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20">
                                <a href="{{ route('tpl.config.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('tpl.config.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    3PL Configuration
                                </a>
                                <a href="{{ route('client_api.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('client_api.index') ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    Client API Configuration
                                </a>
                            </div>
                        </div>
                        @endif
                    @endauth
                        {{-- AUTHENTICATION SECTION --}}
                        <div class="ml-4 border-l border-gray-200 dark:border-gray-700 pl-4">
                            @auth
                                {{-- Tampilan jika sudah Login --}}
                                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                                    <button @click="open = !open" class="flex items-center space-x-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 transition">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold border border-blue-200">
                                            {{ substr(auth()->user()->name, 0, 1) }}
                                        </div>
                                        <span class="hidden md:block">{{ auth()->user()->name }}</span>
                                    </button>

                                    <div x-show="open" x-cloak x-transition
                                        class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20">
                                        <div class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                                            Role: <span class="font-bold uppercase text-blue-600">{{ auth()->user()->role }}</span>
                                        </div>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-gray-700 transition">
                                                Logout
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- Tampilan jika belum Login --}}
                                <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-black rounded-xl text-white bg-blue-600 hover:bg-blue-700 transition shadow-md shadow-blue-200 dark:shadow-none uppercase tracking-widest">
                                    Login
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main class="pt-20 pb-8 min-h-screen">
        @if(session('success'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 shadow-sm" role="alert">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 shadow-sm" role="alert">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @yield('content')
        </div>
    </main>
</body>
</html>
