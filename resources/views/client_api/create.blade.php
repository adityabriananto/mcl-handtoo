@extends('layouts.app')

@section('title', 'Register Client API')

@section('content')
<div class="space-y-6">
    <h1 class="text-3xl font-extrabold text-gray-800 dark:text-gray-100 mb-6">🔑 API Management</h1>

    {{-- Notifikasi Flash --}}
    @if (session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg dark:bg-green-900 dark:border-green-400 dark:text-green-200 mb-4 shadow-sm" role="alert">
            <p>{!! session('success') !!}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg dark:bg-red-900 dark:border-red-400 dark:text-red-200 mb-4 shadow-sm" role="alert">
            <p class="font-bold">Validation Error:</p>
            <ul class="list-disc ml-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Card --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800">
        {{-- Header Card ala Handover Station --}}
        <div class="p-4 bg-blue-600 text-white dark:bg-blue-700 flex justify-between items-center">
            <h4 class="text-xl font-semibold text-white">1. Client API Registration Details</h4>
            <a href="{{ route('client_api.index') }}" class="text-sm bg-blue-500 hover:bg-blue-400 px-3 py-1 rounded transition font-medium">
                Back to List
            </a>
        </div>

        <div class="p-8">
            <form action="{{ route('client_api.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                    {{-- Client Name --}}
                    <div class="md:col-span-1">
                        <label for="client_name" class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Client Name</label>
                        <input type="text"
                               id="client_name" name="client_name"
                               class="mt-2 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 text-lg transition duration-150"
                               placeholder="e.g. Sirclo, Autokirim, E-zone, etc..."
                               value="{{ old('client_name') }}" required>
                    </div>

                    {{-- Client Code --}}
                    <div class="md:col-span-1">
                        <label for="client_code" class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Client Identifier Code</label>
                        <input type="text"
                               id="client_code" name="client_code"
                               class="mt-2 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 text-lg font-mono uppercase"
                               placeholder="owner code on dabao"
                               value="{{ old('client_code') }}" required>
                    </div>

                    {{-- App Key --}}
                    <div class="md:col-span-1">
                        <label for="app_key" class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Client API Key</label>
                        <input type="text"
                               id="app_key" name="app_key"
                               class="mt-2 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 text-lg font-mono uppercase"
                               placeholder="owner code on dabao"
                               value="{{ old('app_key') }}" required>
                    </div>

                    {{-- Client URL --}}
                    <div class="md:col-span-2">
                        <label for="client_url" class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Base URL / Callback URL</label>
                        <input type="url"
                               id="client_url" name="client_url"
                               class="mt-2 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 text-lg"
                               placeholder="https://api.clientdomain.com/v1"
                               value="{{ old('client_url') }}" required>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic font-medium">Used for system-to-system communication & authentication callback.</p>
                    </div>

                    {{-- API Token Section --}}
                    <div class="md:col-span-2">
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                            <label for="client_token" class="block text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">API Token (Optional)</label>
                            <input type="text"
                                   id="client_token" name="client_token"
                                   class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-blue-400 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-lg font-mono"
                                   placeholder="Leave blank if no need token"
                                   value="{{ old('client_token') }}">
                        </div>
                    </div>
                </div>

                {{-- Action Button ala Finalize Handover --}}
                <div class="mt-10">
                    <button type="submit" class="w-full py-4 bg-green-600 text-white rounded-xl text-xl font-extrabold shadow-lg hover:bg-green-700 transform hover:-translate-y-1 transition duration-150 focus:outline-none focus:ring-4 focus:ring-green-300">
                        ✅ REGISTER
                    </button>
                    <p class="text-center mt-4 text-sm text-gray-500 font-medium">By clicking register, you grant this client access to the specified API endpoints.</p>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
