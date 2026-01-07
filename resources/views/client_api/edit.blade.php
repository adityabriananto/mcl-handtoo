@extends('layouts.app')

@section('title', 'Edit Client API')

@section('content')
<div class="space-y-6">
    <h1 class="text-3xl font-extrabold text-gray-800 dark:text-gray-100 mb-6">⚙️ Edit Client API</h1>

    {{-- Form Card --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800">
        <div class="p-4 bg-yellow-500 text-white flex justify-between items-center">
            <h4 class="text-xl font-semibold">Modify Connection: {{ $client->client_name }}</h4>
            <a href="{{ route('client_api.index') }}" class="text-sm bg-yellow-600 hover:bg-yellow-700 px-3 py-1 rounded transition">Cancel</a>
        </div>

        <div class="p-8">
            <form action="{{ route('client_api.update', $client->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="md:col-span-1">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase">Client Name</label>
                        <input type="text" name="client_name" value="{{ old('client_name', $client->client_name) }}"
                               class="mt-2 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-4 focus:ring-yellow-500/20 focus:border-yellow-500 text-lg" required>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase">Client Code</label>
                        <input type="text" name="client_code" value="{{ old('client_code', $client->client_code) }}"
                               class="mt-2 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-4 focus:ring-yellow-500/20 focus:border-yellow-500 text-lg font-mono uppercase" required>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase">Base URL</label>
                        <input type="url" name="client_url" value="{{ old('client_url', $client->client_url) }}"
                               class="mt-2 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-4 focus:ring-yellow-500/20 focus:border-yellow-500 text-lg" required>
                    </div>

                    <div class="md:col-span-2">
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border-2 border-dashed border-yellow-200 dark:border-yellow-900 rounded-lg">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase mb-2">Current API Token</label>
                            <input type="text" name="client_token" value="{{ old('client_token', $client->client_token) }}"
                                   class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-yellow-500 rounded-md font-mono text-lg">
                            <p class="mt-2 text-sm text-gray-500 italic">"Caution: Updating the token will revoke access for the current client."</p>
                        </div>
                    </div>
                </div>

                <div class="mt-10">
                    <button type="submit" class="w-full py-4 bg-yellow-500 text-white rounded-xl text-xl font-extrabold shadow-lg hover:bg-yellow-600 transition duration-150">
                        💾 UPDATE CLIENT CONFIGURATION
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
