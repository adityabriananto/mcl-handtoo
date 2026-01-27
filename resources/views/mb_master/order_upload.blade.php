@extends('layouts.app')

@section('content')
{{-- FORM ENGINE (Tersembunyi) --}}
<form id="ingestionForm" action="{{ route('mb-orders.import') }}" method="POST" enctype="multipart/form-data" @submit="isLoading = true"></form>

<div x-data="{ openImport: false, fileName: '', isLoading: false }"
     class="min-h-screen p-6"
     style="background-color: #020617; color: #f1f5f9;">

    {{-- HEADER AREA --}}
    <div class="flex justify-between items-center mb-8 border-b border-slate-800 pb-6">
        <div>
            <h1 class="text-3xl font-black italic tracking-tighter text-white uppercase">
                <span style="color: #2563eb;">MB</span> Order Staging
            </h1>
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.4em] mt-1">Status: System Operational</p>
        </div>

        <div class="flex items-center gap-3">
            <form action="{{ route('mb-orders.clean') }}" method="POST" onsubmit="return confirm('DANGER: Purge all staging data?')">
                @csrf
                <button type="submit" class="px-4 py-2 border border-red-900 text-red-500 hover:bg-red-600 hover:text-white text-[10px] font-black rounded uppercase transition-all">
                    Purge System
                </button>
            </form>
            <button @click="openImport = true" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black rounded-xl shadow-lg shadow-blue-900/40 uppercase tracking-widest transition-all active:scale-95">
                + Launch Injector
            </button>
        </div>
    </div>

    {{-- FILTER SECTION --}}
    <div class="mb-6 p-6 rounded-2xl border border-slate-800 shadow-xl" style="background-color: #1e293b;">
        <form action="{{ route('mb-orders.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">

            @php
                // Helper array untuk memudahkan mapping field
                $fields = [
                    ['name' => 'package_no', 'label' => 'Package No', 'placeholder' => 'HU260...'],
                    ['name' => 'waybill_no', 'label' => 'Waybill No', 'placeholder' => 'JX...'],
                    ['name' => 'transaction_number', 'label' => 'Transaction No', 'placeholder' => 'TXN...'],
                    ['name' => 'manufacture_barcode', 'label' => 'Barcode Ref', 'placeholder' => 'BAR...'],
                    ['name' => 'external_order_no', 'label' => 'External Order', 'placeholder' => 'EON...'],
                ];
            @endphp

            @foreach($fields as $field)
            <div>
                <label class="text-[10px] font-black uppercase text-slate-400 mb-2 block tracking-widest">{{ $field['label'] }}</label>

                @if($field['name'] !== 'external_order_no')
                    <input type="text" name="{{ $field['name'] }}" value="{{ request($field['name']) }}"
                        class="w-full border border-slate-700 rounded-lg px-4 py-2 text-sm outline-none transition"
                        style="background-color: #0f172a !important; color: #e2e8f0 !important;"
                        placeholder="{{ $field['placeholder'] }}">
                @else
                    {{-- Field terakhir dengan tombol submit --}}
                    <div class="flex gap-2">
                        <input type="text" name="external_order_no" value="{{ request('external_order_no') }}"
                            class="w-full border border-slate-700 rounded-lg px-4 py-2 text-sm outline-none transition"
                            style="background-color: #0f172a !important; color: #e2e8f0 !important;"
                            placeholder="EON...">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 rounded-lg transition shrink-0 shadow-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>
                    </div>
                @endif
            </div>
            @endforeach

        </form>
    </div>

    {{-- DARK TABLE CONTAINER --}}
    <div class="rounded-2xl overflow-hidden shadow-2xl border border-slate-800" style="background-color: #0f172a;">
        <div class="p-4 flex justify-between items-center border-b border-slate-800" style="background-color: #1e293b;">
            <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-300">Live Staging Records</h4>
            <div class="flex items-center gap-3">
               @if(request()->anyFilled(['package_no', 'waybill_no', 'transaction_number', 'external_order_no', 'manufacture_barcode']))
                    <a href="{{ route('mb-orders.index') }}"
                    class="flex items-center gap-1.5 px-2 py-1 bg-red-900/20 border border-red-800/50 rounded text-[9px] font-black text-red-400 hover:bg-red-900/40 transition-all uppercase tracking-tighter">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Clear Filter
                    </a>
                @endif
                <span class="text-[9px] font-bold text-blue-400 bg-blue-900/30 px-2 py-1 rounded">BUFFER_01</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead style="background-color: #020617;">
                    <tr class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 border-b border-slate-800 text-blue-500">Transaction No</th>
                        <th class="px-6 py-4 border-b border-slate-800">Package ID</th>
                        <th class="px-6 py-4 border-b border-slate-800">Waybill No</th>
                        <th class="px-6 py-4 border-b border-slate-800">External Order</th>
                        <th class="px-6 py-4 border-b border-slate-800">Manufacture Barcode</th>
                        <th class="px-6 py-4 border-b border-slate-800 text-right">Captured</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($orders as $order)
                    <tr class="hover:bg-blue-900/20 transition-colors">
                        <td class="px-6 py-4 font-mono text-xs text-blue-400">{{ $order->transaction_number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 font-bold text-white tracking-tight italic">{{ $order->package_no }}</td>
                        <td class="px-6 py-4 text-xs text-slate-300 uppercase">{{ $order->waybill_no ?? '—' }}</td>
                        <td class="px-6 py-4 text-xs text-slate-400 font-mono">{{ $order->external_order_no ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm font-bold text-blue-400 px-3 py-1 rounded bg-black/50 border border-blue-900/50">
                                {{ $order->manufacture_barcode ?? '—' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-[10px] font-black text-slate-500 italic uppercase">
                                {{ $order->created_at->format('H:i:s') }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-24 text-center text-slate-600 font-black uppercase italic tracking-[0.4em]">
                            Buffer Empty: Data Not Found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="p-4 border-t border-slate-800 dark-pagination" style="background-color: #0f172a;">
            {{ $orders->appends(request()->query())->links() }}
        </div>
    </div>

    {{-- MODAL UPLOAD --}}
    <div x-show="openImport" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        {{-- Backdrop Pekat --}}
        <div class="absolute inset-0 bg-black/95 backdrop-blur-sm" @click="!isLoading && (openImport = false)"></div>

        {{-- Modal Box --}}
        <div class="relative w-full max-w-md rounded-2xl shadow-3xl overflow-hidden border border-slate-700"
             style="background-color: #0f172a;">

            {{-- Modal Header --}}
            <div class="p-5 bg-blue-600 text-white flex justify-between items-center">
                <h4 class="text-xl font-black uppercase italic tracking-tighter">1. Batch Ingestion</h4>
                <button type="button" @click="openImport = false" x-show="!isLoading" class="text-white hover:rotate-90 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-8">
                <div class="space-y-6">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" form="ingestionForm">

                    {{-- Mapping Logic --}}
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-2 ml-1 italic tracking-widest">Logic Schema</label>
                        <div class="relative">
                            <select name="format" form="ingestionForm" required :disabled="isLoading"
                                class="block w-full px-4 py-4 border-2 border-slate-800 text-white rounded-xl text-lg font-black appearance-none focus:border-blue-600 outline-none cursor-pointer"
                                style="background-color: #020617;">
                                <option value="order_management" style="background-color: #020617; color: white;">ORDER MANAGEMENT</option>
                                <option value="package_management" style="background-color: #020617; color: white;">PACKAGE MANAGEMENT</option>
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-blue-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>

                    {{-- CSV Upload --}}
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-2 ml-1 italic tracking-widest">CSV Resource</label>
                        <div class="relative group mt-2">
                            <input type="file" name="order_file" form="ingestionForm" required :disabled="isLoading"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20"
                                @change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''">

                            <div class="p-6 border-2 border-dashed border-slate-800 rounded-2xl flex flex-col items-center justify-center transition-all"
                                 style="background-color: #020617;"
                                 :class="fileName ? 'border-green-600 bg-green-950/20' : ''">
                                <span class="text-xs font-black uppercase tracking-widest"
                                      :class="fileName ? 'text-green-500' : 'text-slate-600'"
                                      x-text="fileName ? fileName : 'SELECT DATA SOURCE'"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Action Button --}}
                    <div class="mt-8">
                        <button type="submit" form="ingestionForm" :disabled="isLoading || !fileName"
                                class="w-full py-5 bg-green-600 text-white rounded-2xl text-xl font-black shadow-xl hover:bg-green-700 transition duration-200 disabled:opacity-50 disabled:bg-slate-800">
                            <span x-show="!isLoading">🚀 START INGEST</span>
                            <span x-show="isLoading" class="flex items-center justify-center gap-2 italic">
                                <svg class="animate-spin h-6 w-6" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                BUSY...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    /* Fix untuk Pagination Laravel agar tetap gelap */
    .dark-pagination nav div div span,
    .dark-pagination nav div div a {
        background-color: #020617 !important;
        border-color: #1e293b !important;
        color: #475569 !important;
        font-weight: 900 !important;
    }
    .dark-pagination nav div div span.cursor-default {
        background-color: #2563eb !important;
        color: white !important;
    }
</style>
@endsection
