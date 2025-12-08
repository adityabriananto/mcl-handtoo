@extends('layouts.app')

@section('title', 'Edit 3PL Prefix')

@section('content')
{{-- Kontainer utama menggunakan dark:bg-gray-900 untuk kontras --}}
<div class="py-12 dark:bg-gray-900">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        {{-- Main Heading --}}
        <header class="mb-8">
            <h1 class="text-3xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                ✏️ Edit 3PL Prefix Configuration: **{{ $config->tpl_name }}**
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
        <div class="bg-white dark:bg-gray-900 shadow-xl sm:rounded-lg p-6 lg:p-8">
            {{-- Aksi form diarahkan ke route update dan menggunakan metode PUT --}}
            <form method="POST" action="{{ route('tpl.config.update', $config->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- 3PL Name Input --}}
                <div>
                    <label for="tpl_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">3PL Name:</label>
                    <input
                        type="text"
                        id="tpl_name"
                        name="tpl_name"
                        {{-- Memuat data yang sudah ada --}}
                        value="{{ old('tpl_name', $config->tpl_name) }}"
                        required
                        placeholder="e.g., JNT Express, ShopeeXpress"
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
                        class="input-field @error('prefixes_input') border-red-500 @enderror border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                    >{{ old('prefixes_input', $config->prefixes_input) }}</textarea>
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
                            {{-- Memuat status aktif yang sudah ada --}}
                            {{ old('is_active', $config->is_active) ? 'checked' : '' }}
                            class="h-4 w-4 text-blue-600 dark:text-blue-500 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-400 bg-white dark:bg-gray-700 dark:border-gray-600"
                        >
                        <label for="is_active" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Active Status
                        </label>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="flex justify-between pt-6">
                    {{-- Tombol Batal --}}
                    <a href="{{ route('tpl.config.index') }}"
                       class="py-2.5 px-6 rounded-md shadow-lg transition duration-150 font-semibold flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200">
                        Cancel
                    </a>

                    {{-- Tombol Submit --}}
                    <button
                        type="submit"
                        class="btn-primary bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500 dark:bg-yellow-500 dark:hover:bg-yellow-600 dark:focus:ring-yellow-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 inline-block" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zm-7.586 7.586a1 1 0 000 1.414l3.05 3.05 4.14-4.14-1.414-1.414-2.323 2.323-2.323-2.323z" />
                        </svg>
                        Update Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Style Helper for consistent components (Sama seperti halaman create) --}}
<style>
    .input-field {
        @apply block w-full rounded-md shadow-sm p-2.5 text-base focus:ring-blue-500 focus:border-blue-500 transition duration-150;
    }

    .input-field::placeholder {
        color: #9ca3af;
    }

    .dark .input-field::placeholder {
        color: #6b7280;
    }

    .btn-primary {
        @apply text-white py-2.5 px-6 rounded-md shadow-lg transition duration-150 font-semibold flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-900;
    }
</style>
@endsection
