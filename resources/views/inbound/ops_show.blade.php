@extends('layouts.app')

@section('title', 'Detail Inbound - ' . $inbound->reference_number)

@section('content')
{{-- Library untuk Generate Excel secara Client-Side --}}
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<style>
    [x-cloak] { display: none !important; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #fed7aa; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #fb923c; }
</style>

<div class="space-y-8 py-2"
    x-cloak
    x-data="{
        searchSKU: '',
        warehouse: '{{ $inbound->warehouse_code ?? '-' }}',
        client: '{{ $inbound->client_name ?? '-' }}',
        refNumber: '{{ $inbound->reference_number }}',
        {{-- Mapping data dengan fallback string kosong agar tidak undefined --}}
        items: {{ $inbound->details->map(function($d) {
            return [
                'seller_sku' => $d->seller_sku ?? '',
                'fulfillment_sku' => $d->fulfillment_sku ?? '-',
                'good_qty' => (int)$d->received_good,
                'requested_qty' => (int)$d->requested_quantity,
            ];
        })->toJson() }},

        exportToExcel() {
            const timestamp = new Date().toISOString().split('T')[0];
            const fileName = `Export_Details_${this.refNumber}_${timestamp}.xlsx`;

            const dataForExport = this.items.map(item => ({
                'Warehouse': this.warehouse,
                'Client Name': this.client,
                'Reference No': this.refNumber,
                'Seller SKU': item.seller_sku,
                'Fulfillment SKU': item.fulfillment_sku,
                'Received Good': item.good_qty,
                'Requested Qty': item.requested_qty
            }));

            const ws = XLSX.utils.json_to_sheet(dataForExport);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Inbound Details');
            XLSX.writeFile(wb, fileName);
        }
    }">

    {{-- 1. Header Section --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div class="flex items-center space-x-5">
            <a href="{{ route('ops.inbound.index') }}" class="p-3 bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 hover:bg-gray-50 transition transform active:scale-95">
                <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div>
                <h1 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic leading-tight">
                    {{ $inbound->reference_number }}
                </h1>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-[0.2em]">Inbound Request Details</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <span class="px-5 py-2.5 rounded-2xl text-[11px] font-black uppercase border tracking-widest
                {{ $inbound->status === 'Completed' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-blue-100 text-blue-700 border-blue-200' }}">
                Status: {{ $inbound->status }}
            </span>
        </div>
    </div>

    {{-- 2. Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-900 p-7 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-800">
            <p class="text-[11px] font-black text-gray-400 uppercase tracking-widest mb-2">Warehouse</p>
            <p class="text-xl font-bold text-gray-800 dark:text-white uppercase leading-none">{{ $inbound->warehouse_code }}</p>
        </div>

        <div class="bg-white dark:bg-gray-900 p-7 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-800">
            <p class="text-[11px] font-black text-blue-500 uppercase tracking-widest mb-2">Unique SKU</p>
            <p class="text-3xl font-black text-blue-600 dark:text-blue-400 tracking-tighter leading-none">
                {{ number_format($inbound->details->count()) }} <span class="text-sm font-bold text-gray-400 uppercase">Items</span>
            </p>
        </div>

        <div class="bg-white dark:bg-gray-900 p-7 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-800">
            <p class="text-[11px] font-black text-gray-400 uppercase tracking-widest mb-2">Total Units</p>
            <p class="text-3xl font-black text-gray-800 dark:text-white tracking-tighter leading-none">
                {{ number_format($inbound->details->sum('requested_quantity')) }} <span class="text-sm font-bold text-gray-400 uppercase">Pcs</span>
            </p>
        </div>
    </div>

    {{-- 3. MAIN CONTENT --}}
    <div class="flex flex-col lg:flex-row gap-8 items-start">

        {{-- KOLOM KIRI: SKU Table --}}
        <div class="w-full {{ $inbound->children->count() > 0 ? 'lg:w-2/3' : 'lg:w-full' }}">
            <div class="bg-white dark:bg-gray-900 shadow-xl rounded-[2.5rem] overflow-hidden border border-gray-100 dark:border-gray-800">
                <div class="p-7 border-b border-gray-100 dark:border-gray-800 flex flex-col lg:flex-row justify-between items-center gap-4">
                    <h2 class="font-black text-gray-800 dark:text-white uppercase tracking-tighter italic text-l leading-none">Verification SKU List</h2>

                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                        {{-- Tombol Export --}}
                        <button @click="exportToExcel()"
                                class="flex items-center gap-2 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-green-500/20 active:scale-95 w-full sm:w-auto justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Export Excel
                        </button>

                        {{-- Input Search dengan Proteksi --}}
                        <div class="relative w-full sm:w-80 group">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-500 group-focus-within:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </span>
                            <input type="text"
                                x-model="searchSKU"
                                placeholder="TYPE SKU TO FILTER..."
                                class="block w-full pl-12 pr-12 py-3 bg-gray-900 dark:bg-black border-2 border-gray-800 rounded-2xl text-blue-400 placeholder-gray-600 text-[10px] font-black tracking-widest uppercase focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition-all duration-300 shadow-2xl">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Seller SKU</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Fulfillment SKU</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Product Name</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            {{-- Logic Filter yang lebih aman --}}
                            <template x-for="item in items.filter(i => {
                                const term = searchSKU.toLowerCase();
                                return (i.fulfillment_sku || '').toLowerCase().includes(term) ||
                                       (i.seller_sku || '').toLowerCase().includes(term);
                            })" :key="item.fulfillment_sku + Math.random()">
                                <tr class="hover:bg-blue-50/30 dark:hover:bg-gray-800/50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-bold text-blue-600 dark:text-blue-400" x-text="item.seller_sku"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-bold text-blue-600 dark:text-blue-400" x-text="item.fulfillment_sku"></span>
                                    </td>
                                    <td class="px-6 py-4 text-[13px] text-gray-600 dark:text-gray-400 uppercase tracking-tight" x-text="item.name"></td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <span class="font-black text-gray-800 dark:text-white" x-text="item.good_qty.toLocaleString()"></span>
                                        /
                                        <span class="font-black text-gray-800 dark:text-white" x-text="item.requested_qty.toLocaleString()"></span>
                                        <span class="text-[9px] text-gray-400 font-bold ml-1 uppercase">Pcs</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: Child Documents --}}
        @if($inbound->children->count() > 0)
        <div class="w-full lg:w-1/3 space-y-4 sticky top-4">
            <div class="bg-orange-50/50 dark:bg-orange-950/10 border border-orange-100 dark:border-orange-900/50 rounded-[2.5rem] p-7 shadow-sm">
                <div class="mb-6">
                    <p class="text-[11px] font-black text-orange-600 uppercase tracking-[0.2em] mb-1">Child Documents</p>
                    <p class="text-[10px] text-orange-400 font-bold italic">Associated Split Orders</p>
                </div>

                <div class="max-h-[500px] overflow-y-auto pr-2 space-y-3 custom-scrollbar">
                    @foreach($inbound->children as $child)
                        <a href="{{ route('ops.inbound.show', $child->id) }}"
                           class="flex items-center justify-between px-5 py-4 bg-white dark:bg-gray-800 border border-orange-100 dark:border-orange-900/30 rounded-2xl hover:border-orange-500 hover:shadow-md transition-all group overflow-hidden">
                            <div class="flex flex-col min-w-0">
                                <span class="text-[9px] font-black text-gray-400 uppercase mb-1 leading-none">ORDER REF</span>
                                <span class="text-xs font-black text-gray-700 dark:text-gray-200 group-hover:text-orange-600 truncate uppercase italic leading-none">
                                    {{ $child->reference_number }}
                                </span>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-orange-500 transform group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
