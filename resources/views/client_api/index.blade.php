@extends('layouts.app')

@section('title', 'Client API Management')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Main Header --}}
        <header class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl font-bold leading-tight text-gray-900 dark:text-gray-100 flex items-center">
                🔌 Client API Management
            </h1>
            <a href="{{ route('client_api.create') }}" class="btn-primary bg-green-600 hover:bg-green-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                </svg>
                Register New Client
            </a>
        </header>

        {{-- Success Alert --}}
        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Table Card --}}
        <div class="bg-white dark:bg-gray-900 shadow-xl sm:rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="table-header">Client Name</th>
                                <th class="table-header">Code</th>
                                <th class="table-header">App Key</th>
                                <th class="table-header">Base URL</th>
                                {{-- KOLOM BARU --}}
                                <th class="table-header">Access Token</th>
                                <th class="table-header text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($clients as $client)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition duration-150">
                                <td class="table-data font-semibold">{{ $client->client_name }}</td>
                                <td class="table-data">
                                    <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">
                                        {{ $client->client_code }}
                                    </span>
                                </td>
                                <td class="table-data">
                                    <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">
                                        {{ $client->app_key }}
                                    </span>
                                </td>
                                <td class="table-data text-xs text-gray-500">{{ $client->client_url }}</td>

                                {{-- KOLOM ACCESS TOKEN DENGAN FITUR COPY --}}
                                <td class="table-data">
                                    <div class="flex items-center justify-center space-x-2">
                                        <code class="px-3 py-1 bg-gray-100 dark:bg-gray-800 rounded-lg text-[10px] font-mono text-gray-600 dark:text-gray-400 max-w-[150px] truncate">
                                            {{ $client->access_token ?? 'No Token' }}
                                        </code>
                                        @if($client->access_token)
                                        <button onclick="copyToClipboard('{{ $client->access_token }}')"
                                                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition"
                                                title="Copy Token">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </button>
                                        @endif
                                    </div>
                                </td>

                                <td class="table-data text-right space-x-3">
                                    <a href="{{ route('client_api.edit', $client->id) }}"
                                    class="text-blue-600 hover:text-blue-900 text-xs font-bold uppercase">
                                    Edit
                                    </a>
                                    <form action="#" method="POST" class="inline-block">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 text-xs font-bold uppercase" onclick="return confirm('Delete this client?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="table-data text-gray-500 py-10 text-center">No clients registered yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Notification Toast for Copy --}}
<div id="copyToast" class="fixed bottom-5 right-5 bg-gray-900 text-white px-6 py-3 rounded-2xl shadow-2xl transform translate-y-32 transition-transform duration-300 flex items-center space-x-3 z-50 border border-gray-700">
    <span class="bg-green-500 rounded-full p-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
        </svg>
    </span>
    <span class="text-sm font-bold uppercase tracking-tight">Token Copied!</span>
</div>

<script>
    function copyToClipboard(text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            const toast = document.getElementById('copyToast');
            toast.classList.remove('translate-y-32');
            setTimeout(() => {
                toast.classList.add('translate-y-32');
            }, 3000);
        });
    }
</script>

<style>
    .table-header { @apply px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300; }
    .table-data { @apply px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200 text-center; }
    .btn-primary { @apply text-white py-2 px-4 rounded-lg shadow-md transition duration-150 font-semibold flex items-center justify-center; }
</style>
@endsection
