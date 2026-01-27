@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-black italic text-white uppercase tracking-tighter">Ingestion Logs</h1>
            <p class="text-slate-500 text-sm">Riwayat upload dan status pemrosesan background job.</p>
        </div>
        <a href="{{ route('mb-orders.index') }}" class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-700 transition">
            ← Kembali ke Dashboard
        </a>
    </div>

    <div class="bg-[#0f172a] rounded-2xl border border-slate-800 overflow-hidden shadow-2xl">
        <table class="w-full text-left">
            <thead class="bg-[#020617] text-[10px] font-black text-slate-500 uppercase tracking-widest">
                <tr>
                    <th class="px-6 py-4">Batch ID</th>
                    <th class="px-6 py-4">File Name</th>
                    <th class="px-6 py-4">Format</th>
                    <th class="px-6 py-4 text-center">Progress</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Date Uploaded</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach($logs as $log)
                <tr class="hover:bg-slate-900/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-blue-400">#{{ $log->id }}</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-slate-200">{{ $log->file_name }}</div>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-400 font-mono uppercase">
                        {{ str_replace('_', ' ', $log->format_type ?? 'N/A') }}
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $percent = $log->total_rows > 0 ? ($log->processed_rows / $log->total_rows) * 100 : 0;
                        @endphp
                        <div class="w-32 bg-slate-800 rounded-full h-1.5 mx-auto">
                            <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $percent }}%"></div>
                        </div>
                        <div class="text-[9px] font-black text-slate-500 mt-1 text-center uppercase">
                            {{ number_format($log->processed_rows) }} / {{ number_format($log->total_rows) }}
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($log->status == 'completed')
                            <span class="px-2 py-0.5 bg-green-900/30 text-green-500 text-[9px] font-black rounded uppercase border border-green-800/50">Completed</span>
                        @elseif($log->status == 'processing')
                            <span class="px-2 py-0.5 bg-blue-900/30 text-blue-400 text-[9px] font-black rounded uppercase border border-blue-800/50 animate-pulse">Processing</span>
                        @elseif($log->status == 'failed')
                            <span class="px-2 py-0.5 bg-red-900/30 text-red-500 text-[9px] font-black rounded uppercase border border-red-800/50" title="{{ $log->notes }}">Failed</span>
                        @else
                            <span class="px-2 py-0.5 bg-slate-800 text-slate-400 text-[9px] font-black rounded uppercase border border-slate-700">Queued</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right text-[10px] font-bold text-slate-500 uppercase italic">
                        {{ $log->created_at->format('d M Y, H:i') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $logs->links() }}
    </div>
</div>
@endsection
