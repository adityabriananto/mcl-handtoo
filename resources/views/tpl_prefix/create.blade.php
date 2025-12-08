@extends('layouts.app')

@section('title', 'Add 3PL Prefix')

@section('content')
{{-- 1. Background Kontainer Utama --}}
<div class="py-12 dark:bg-gray-900">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        {{-- Main Heading --}}
        <header class="mb-8">
            <h1 class="text-3xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                📦 Add 3PL Prefix Configuration
            </h1>
        </header>

        {{-- Notifikasi Flash --}}
        @if (session('success'))
            <div class="mb-4">
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md dark:bg-green-900/50 dark:border-green-400 dark:text-green-200" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            </div>
        @endif

        {{-- Error Messages --}}
        @if ($errors->any())
            <div class="mb-4">
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md dark:bg-red-900/50 dark:border-red-400 dark:text-red-200" role="alert">
                    <p class="font-bold mb-2">Please fix the following errors:</p>
                    <ul class="list-disc ml-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Form Card --}}
        {{-- 2. Background Card --}}
        <div class="bg-white dark:bg-gray-900 shadow-xl sm:rounded-lg p-6 lg:p-8">
            <form method="POST" action="{{ route('tpl.config.store') }}" class="space-y-6">
                @csrf

                {{-- 3PL Name Input --}}
                <div>
                    <label for="tpl_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">3PL Name:</label>
                    <input
                        type="text"
                        id="tpl_name"
                        name="tpl_name"
                        value="{{ old('tpl_name') }}"
                        required
                        placeholder="e.g., JNT Express, ShopeeXpress"
                        {{-- 3. Implementasi Gaya Dark Mode Field Konsisten --}}
                        class="input-field @error('tpl_name') border-red-500 @enderror border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                    >
                    @error('tpl_name')
                        <p class="text-red-500 text-xs italic mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Prefixes Input --}}
                <div>
                    <label for="prefixes_input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prefixes (use comma if prefix more than 1):</label>
                    <textarea
                        id="prefixes_input"
                        name="prefixes_input"
                        rows="4"
                        required
                        placeholder="e.g., CM, JT, TG, 881, JNEB"
                        {{-- 3. Implementasi Gaya Dark Mode Field Konsisten --}}
                        class="input-field @error('prefixes_input') border-red-500 @enderror border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                    >{{ old('prefixes_input') }}</textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Sample: CM, JT, TG, JNEB. (Prefixes will be converted to uppercase automatically for case-insensitive lookup)
                    </p>
                    @error('prefixes_input')
                        <p class="text-red-500 text-xs italic mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Active Status Checkbox --}}
                <div class="pt-4">
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            {{ old('is_active', 1) ? 'checked' : '' }}
                            {{-- Warna Checkbox sudah benar --}}
                            class="h-4 w-4 text-blue-600 dark:text-blue-500 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-400 bg-white dark:bg-gray-700 dark:border-gray-600"
                        >
                        <label for="is_active" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Active Status
                        </label>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="flex justify-start pt-6">
                    <button
                        type="submit"
                        class="btn-primary bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-blue-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 inline-block" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                        </svg>
                        Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Style Helper for consistent components --}}
<style>
    .input-field {
        /*
        Kelas ini sekarang hanya menampung properti default yang sama untuk semua field,
        sementara properti dark mode spesifik (bg, text, border) dipindahkan inline.
        */
        @apply block w-full rounded-md shadow-sm p-2.5 text-base focus:ring-blue-500 focus:border-blue-500 transition duration-150;
    }

    /* Penyesuaian Placeholder untuk Dark Mode (Sama seperti sebelumnya, ini sudah benar) */
    .input-field::placeholder {
        color: #9ca3af; /* gray-400 (Light Mode) */
    }

    .dark .input-field::placeholder {
        /* Menggunakan warna yang lebih gelap (gray-500) */
        color: #6b7280;
    }

    .btn-primary {
        @apply text-white py-2.5 px-6 rounded-md shadow-lg transition duration-150 font-semibold flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-900;
    }
</style>
@endsection
