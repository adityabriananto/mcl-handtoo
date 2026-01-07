<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Inline script to detect system dark mode preference --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? "system" }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    {{-- Inline style untuk background color --}}
    <style>
        html {
            background-color: #f3f4f6; /* gray-100 */
        }

        html.dark {
            background-color: #1f2937; /* gray-800/900 */
        }
    </style>

    <title>{{ config('app.name', 'MCL - HandToo') }} | @yield('title', 'Handover Visibility Tool')</title>

    {{-- Panggilan Aset KRITIS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="font-sans antialiased text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-gray-800">
    <header>
        <nav class="bg-white dark:bg-gray-900 shadow-lg fixed top-0 w-full z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <a class="text-xl font-bold text-gray-900 dark:text-white hover:text-blue-500 transition duration-150" href="{{ route('handover.index') }}">
                        📦 MCL - HandToo
                    </a>
                    <div class="flex space-x-4 items-center">
                        <a href="{{ route('handover.index') }}"
                        class="px-3 py-2 rounded-md text-sm font-medium transition duration-150
                                @if(request()->routeIs('handover.index'))
                                    bg-blue-600 text-white dark:bg-blue-700
                                @else
                                    text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                @endif">
                            📥 Handover Station
                        </a>
                        <a href="{{ route('history.index') }}"
                        class="px-3 py-2 rounded-md text-sm font-medium transition duration-150
                                @if(request()->routeIs('history.index'))
                                    bg-blue-600 text-white dark:bg-blue-700
                                @else
                                    text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                @endif">
                            📊 Handover Dashboard
                        </a>

                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open"
                                    class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition duration-150 focus:outline-none
                                        @if(request()->routeIs('tpl.config.*') || request()->routeIs('handover.upload.*') || request()->routeIs('client.api.*'))
                                            bg-blue-600 text-white
                                        @else
                                            text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                        @endif">
                                <span>⚙️ Configuration</span>
                                <svg class="ml-1 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>

                            <div x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20"
                                style="display: none;">

                                <a href="{{ route('tpl.config.index') }}"
                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    3PL Configuration
                                </a>

                                <a href="{{ route('handover.upload.index') }}"
                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Document Upload Data
                                </a>

                                <a href="{{ route('client_api.index') }}"
                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Client API Configuration
                                </a>
                            </div>
                        </div>
                        </div>
                </div>
            </div>
        </nav>
    </header>

    <main class="pt-20 pb-8 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- @include('handover.messages') --}}
            @yield('content')
        </div>
    </main>
</body>
</html>
