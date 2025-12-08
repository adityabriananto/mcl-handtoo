@extends('layouts.app')

@section('title', '3PL Prefix Dashboard')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Main Header --}}
        <header class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl font-bold leading-tight text-gray-900 dark:text-gray-100 flex items-center">
                📊 3PL Prefix Management Dashboard
            </h1>
            {{-- Tombol Aksi --}}
            <a href="{{ route('tpl.config.create') }}" class="btn-primary bg-blue-600 hover:bg-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                </svg>
                Add New 3PL Prefix
            </a>
        </header>

        ---

        {{-- Ringkasan Statistik --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="stat-card dark:bg-gray-900">
                <p class="stat-label">Total Configurations</p>
                <p class="stat-value">{{ $totalConfigs ?? 0 }}</p>
            </div>
            <div class="stat-card dark:bg-gray-900">
                <p class="stat-label">Active Configurations</p>
                <p class="stat-value text-green-500">{{ $activeConfigs ?? 0 }}</p>
            </div>
            <div class="stat-card dark:bg-gray-900">
                <p class="stat-label">Last Updated</p>
                <p class="stat-value text-sm">{{ $lastUpdated ?? 'N/A' }}</p>
            </div>
        </div>

        ---

        {{-- Tabel Daftar Konfigurasi --}}
        <div class="bg-white dark:bg-gray-900 shadow-xl sm:rounded-lg overflow-hidden">
            <div class="p-6">

                {{-- 📝 PENCARIAN & FILTER FORM --}}
                <form method="GET" action="{{ route('tpl.config.index') }}" class="mb-4 flex space-x-4 items-end">

                    {{-- 1. Search Input --}}
                    <div class="flex-grow">
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search (3PL Name or Prefix)</label>
                        <input
                            type="text"
                            id="search"
                            name="search"
                            placeholder="e.g., JNT, CM, TG"
                            value="{{ request('search') }}"
                            class="input-field w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                        />
                    </div>

                    {{-- 2. Status Filter --}}
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select
                            id="status"
                            name="status"
                            class="input-field w-40 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                        >
                            <option value="">All Status</option>
                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    {{-- 3. Filter Button --}}
                    <button type="submit"
                        class="px-4 py-2.5 bg-blue-600 text-white rounded-md shadow-sm hover:bg-blue-700 transition duration-150 flex items-center h-[42px]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                        Filter
                    </button>

                    {{-- Tombol Reset --}}
                    @if(request('search') || request('status'))
                    <a href="{{ route('tpl.config.index') }}"
                        class="px-4 py-2.5 bg-gray-200 text-gray-700 rounded-md shadow-sm hover:bg-gray-300 transition duration-150 flex items-center h-[42px] dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                        Reset
                    </a>
                    @endif
                </form>

                {{-- Tabel Konten --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="table-header">3PL Name</th>
                                <th scope="col" class="table-header">Prefixes Count</th>
                                <th scope="col" class="table-header hidden sm:table-cell">Prefix</th>
                                <th scope="col" class="table-header">Status</th>
                                <th scope="col" class="table-header text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($configurations as $config)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition duration-150 dark:bg-gray-900">

                                <td class="table-data font-medium dark:text-gray-200">{{ $config->tpl_name }}</td>
                                <td class="table-data text-sm dark:text-gray-300">{{ count($config->prefixes) }}</td>
                                <td class="table-data hidden sm:table-cell text-sm text-gray-500 dark:text-gray-400">
                                    {{ implode(', ', $config->prefixes) }}
                                </td>

                                <td class="table-data">
                                    @if ($config->is_active)
                                        <span class="status-badge bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200">Active</span>
                                    @else
                                        <span class="status-badge bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-200">Inactive</span>
                                    @endif
                                </td>

                                <td class="table-data text-right space-x-2">
                                    <a href="{{ route('tpl.config.edit', $config->id) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 transition">
                                        Edit
                                    </a>
                                    <form action="{{ route('tpl.config.destroy', $config->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 transition">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                    No 3PL Prefix configurations found. Please add a new one.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Style Helper Components --}}
<style>
    .btn-primary {
        @apply text-white py-2 px-4 rounded-lg shadow-md transition duration-150 font-semibold flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-900;
    }
    .input-field {
        /* Konsisten dengan dark mode field yang sudah disetel */
        @apply border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150;
    }
    .input-field::placeholder {
        color: #9ca3af; /* gray-400 */
    }
    .dark .input-field::placeholder {
        color: #6b7280; /* gray-500 */
    }
    .stat-card {
        @apply bg-white dark:bg-gray-900 p-5 rounded-xl shadow-md border border-gray-100 dark:border-gray-700;
    }
    .stat-label {
        @apply text-sm font-medium text-gray-500 dark:text-gray-400;
    }
    .stat-value {
        @apply mt-1 text-3xl font-extrabold text-gray-900 dark:text-gray-100;
    }
    .table-header {
        @apply px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300;
    }
    .table-data {
        @apply px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200 text-center;
    }
    .status-badge {
        @apply px-2 inline-flex text-xs leading-5 font-semibold rounded-full;
    }
</style>
@endsection
