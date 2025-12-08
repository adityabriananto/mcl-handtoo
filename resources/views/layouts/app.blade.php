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
                    <div class="flex space-x-4">
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
                            ⏱️ History Dashboard
                        </a>
                         <a href="{{ route('tpl.config.index') }}"
                           class="px-3 py-2 rounded-md text-sm font-medium transition duration-150
                                  @if(request()->routeIs('tpl.config.index'))
                                      bg-blue-600 text-white dark:bg-blue-700
                                  @else
                                      text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-500
                                  @endif">
                            ⚙️ 3PL Configuration
                        </a>
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
