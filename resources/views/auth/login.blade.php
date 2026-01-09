<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Login | {{ config('app.name', 'MCL HandToo') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #030712; }
        ::-webkit-scrollbar-thumb { background: #1f2937; border-radius: 10px; }

        .btn-primary {
            @apply text-white rounded-xl shadow-md transition duration-150 flex items-center justify-center font-bold;
        }

        .login-gradient {
            background: radial-gradient(circle at top right, rgba(37, 99, 235, 0.1), transparent),
                        radial-gradient(circle at bottom left, rgba(30, 58, 138, 0.05), transparent);
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-950 min-h-screen flex items-center justify-center p-4 login-gradient antialiased font-sans">

    <div class="w-full max-w-md" x-data="{ loading: false }">

        {{-- Main Header --}}
        <header class="mb-10 text-center">
            <div class="inline-flex items-center justify-center space-x-3 mb-4">
                <div class="bg-blue-600 text-white p-2 rounded-2xl shadow-lg shadow-blue-500/30 transform -rotate-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <h1 class="text-3xl font-black leading-tight text-gray-900 dark:text-white uppercase tracking-tighter">
                    MCL <span class="text-blue-500">HandToo</span>
                </h1>
            </div>
            <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.4em]">Hand Tools System for MCL</p>
        </header>

        {{-- Login Card --}}
        <div class="bg-white dark:bg-gray-900 shadow-2xl rounded-[2.5rem] overflow-hidden border border-gray-200 dark:border-gray-800 transition-all duration-500">

            <div class="bg-gray-50 dark:bg-gray-800/50 px-8 py-5 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
                <h2 class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center">
                    <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse mr-2"></span>
                    Secure Access Gateway
                </h2>
                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tighter">v2.0.0</span>
            </div>

            <div class="p-8">
                @if($errors->any())
                    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 text-red-700 dark:text-red-400 text-xs font-bold uppercase tracking-tight rounded-r-xl shadow-sm italic">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            {{ $errors->first() }}
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" @submit="loading = true" class="space-y-6">
                    @csrf

                    <div class="space-y-2">
                        <label for="email" class="block text-[10px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest ml-1">Work Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-2xl text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="example@mcl.com">
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="block text-[10px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest ml-1">Password Key</label>
                        <input type="password" name="password" id="password" required
                            class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-2xl text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="••••••••">
                    </div>

                    <div class="flex items-center justify-between px-1">
                        <label class="flex items-center cursor-pointer group">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-blue-600 focus:ring-blue-500 transition">
                            <span class="ml-2 text-[10px] font-bold text-gray-500 uppercase tracking-tight group-hover:text-blue-500 transition">Remember Session</span>
                        </label>
                    </div>

                    <button type="submit"
                        :disabled="loading"
                        class="w-full btn-primary bg-blue-600 hover:bg-blue-700 py-4 text-xs font-black uppercase tracking-[0.2em] shadow-xl shadow-blue-500/20 transform active:scale-95 transition-all disabled:opacity-70 disabled:cursor-not-allowed">

                        <span x-show="!loading" class="flex items-center">
                            Authorized Sign In
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </span>

                        <span x-show="loading" x-cloak class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </form>

                {{-- BACK TO HOME BUTTON (TAMBAHAN) --}}
                <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800 text-center">
                    <a href="{{ url('/') }}" class="inline-flex items-center text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-blue-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Homepage
                    </a>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <footer class="mt-12 text-center">
            <p class="text-[9px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-[0.5em] flex items-center justify-center gap-2">
                &copy; {{ date('Y') }} MCL LEX Indonesia
                <span class="h-1 w-1 bg-gray-300 rounded-full"></span>
                v2.0
            </p>
        </footer>
    </div>

</body>
</html>
