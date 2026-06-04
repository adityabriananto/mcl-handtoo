@extends('layouts.app')

@section('title', 'WMS Upload Logs')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">WMS UPLOAD LOGS</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">processed via background job</p>
        </div>
        <a href="{{ route('ops.inbound.index') }}" class="inline-flex items-center px-3 py-1.5 bg-gray-800 text-white text-xs font-medium rounded-lg hover:bg-gray-700 transition">
            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Dashboard
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-gray-900 rounded-xl p-3 mb-5">
        <form method="GET" action="{{ route('inbound.import-log') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div>
                <label class="block text-[9px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Filename</label>
                <input type="text" name="filename" value="{{ request('filename') }}"
                       class="w-full bg-gray-800 border-0 text-gray-200 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-indigo-500 placeholder-gray-500"
                       placeholder="Search filename...">
            </div>
            <div>
                <label class="block text-[9px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Status</label>
                <select name="status"
                        class="w-full bg-gray-800 border-0 text-gray-200 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-indigo-500 appearance-none">
                    <option value="">All</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="error" {{ request('status') === 'error' ? 'selected' : '' }}>Error</option>
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full bg-gray-800 border-0 text-gray-200 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-[9px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full bg-gray-800 border-0 text-gray-200 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-indigo-500">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-bold uppercase tracking-wide rounded-md transition">
                    Filter
                </button>
                <a href="{{ route('inbound.import-log') }}" class="inline-flex items-center justify-center w-8 h-8 bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white rounded-md transition" title="Clear">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-gray-900 dark:bg-gray-900 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Batch ID</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">File Name</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider w-48">Progress</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse ($logs as $log)
                        @php
                            $percent = $log->total_rows > 0 ? round(($log->processed_rows / $log->total_rows) * 100) : 0;
                            $percent = min($percent, 100);
                        @endphp
                        <tr class="hover:bg-gray-800/50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-300">#{{ $log->id }}</td>
                            <td class="px-6 py-4 text-sm text-gray-200 max-w-md truncate" title="{{ $log->filename }}">
                                {{ $log->filename }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500
                                            {{ $log->status === 'completed' ? 'bg-emerald-500' : ($log->status === 'error' ? 'bg-red-500' : 'bg-blue-500') }}"
                                             style="width: {{ $percent }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-400 w-12 text-right">{{ $percent }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($log->status === 'completed')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        COMPLETED
                                    </span>
                                @elseif ($log->status === 'error')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/20">
                                        ERROR
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        PROCESSING
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                {{ $log->created_at?->format('d M Y, H:i') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-sm">No upload logs found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="px-6 py-4 border-t border-gray-800">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
