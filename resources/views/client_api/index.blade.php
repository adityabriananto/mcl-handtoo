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
                                <th class="table-header">Base URL</th>
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
                                <td class="table-data text-xs text-gray-500">{{ $client->client_url }}</td>
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
                                <td colspan="5" class="table-data text-gray-500 py-10">No clients registered yet.</td>
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
<div id="copyToast" class="fixed bottom-5 right-5 bg-gray-800 text-white px-4 py-2 rounded shadow-lg transform translate-y-20 transition-transform duration-300">
    Token copied to clipboard!
</div>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            const toast = document.getElementById('copyToast');
            toast.classList.remove('translate-y-20');
            setTimeout(() => {
                toast.classList.add('translate-y-20');
            }, 2000);
        });
    }
</script>

<style>
    .table-header { @apply px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300; }
    .table-data { @apply px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200 text-center; }
    .btn-primary { @apply text-white py-2 px-4 rounded-lg shadow-md transition duration-150 font-semibold flex items-center justify-center; }
</style>
@endsection
