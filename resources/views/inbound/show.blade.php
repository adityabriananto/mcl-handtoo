@extends('layouts.app')

@section('title', 'Detail Inbound - ' . $inbound->reference_number)

@section('content')
<div class="space-y-6" x-data="{
    searchSKU: '',
    items: {{ $inbound->details->map(function($d) {
        return [
            'sku' => $d->seller_sku,
            'qty' => $d->requested_quantity,
            'name' => $d->product_name ?? '-' {{-- Asumsi ada field product_name --}}
        ];
    })->toJson() }}
}">
    {{-- 1. Navigation & Actions --}}
    <div class="flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <a href="{{ route('inbound.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-black text-gray-800 dark:text-white uppercase tracking-tighter">
                    {{ $inbound->reference_number }}
                </h1>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Detail Inbound Request</p>
            </div>
        </div>

        <div class="flex space-x-3">
            <span class="px-4 py-2 rounded-xl text-xs font-black uppercase border
                {{ $inbound->status === 'Completed' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-blue-100 text-blue-700 border-blue-200' }}">
                Status: {{ $inbound->status }}
            </span>
        </div>
    </div>

    {{-- 2. Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border border-gray-100">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Warehouse Location</p>
            <p class="text-lg font-bold text-gray-800 dark:text-white">{{ $inbound->warehouse_code }}</p>
        </div>

        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border border-gray-100">
            <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-1">Total Unique SKU</p>
            <p class="text-2xl font-black text-blue-600 dark:text-blue-400">
                {{ number_format($inbound->details->count()) }} <span class="text-xs font-bold text-gray-400 uppercase">Items</span>
            </p>
        </div>

        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border border-gray-100">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Quantity Units</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">
                {{ number_format($inbound->details->sum('requested_quantity')) }} <span class="text-xs font-bold text-gray-400 uppercase">Pcs</span>
            </p>
        </div>
    </div>

    {{-- 3. SKU List with Search --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-2xl overflow-hidden border border-gray-200">
        {{-- Header Tabel & Search --}}
        <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex flex-col md:flex-row justify-between items-center gap-4">
            <h2 class="font-black text-gray-800 dark:text-white uppercase tracking-tighter">List Items SKU</h2>

            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input
                    type="text"
                    x-model="searchSKU"
                    placeholder="Cari Seller SKU..."
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-gray-50 dark:bg-gray-800 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
        </div>

        {{-- Table Body --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Seller SKU</th>
                        <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Product Name</th>
                        <th class="px-6 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Requested Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                    {{-- Loop menggunakan Alpine.js untuk pencarian instan --}}
                    <template x-for="item in items.filter(i => i.sku.toLowerCase().includes(searchSKU.toLowerCase()))" :key="item.sku">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-blue-600 dark:text-blue-400" x-text="item.sku"></span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" x-text="item.name"></td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <span class="font-black text-gray-800 dark:text-white" x-text="item.qty.toLocaleString()"></span>
                                <span class="text-[10px] text-gray-400 font-bold ml-1 uppercase">Units</span>
                            </td>
                        </tr>
                    </template>

                    {{-- Empty State jika pencarian tidak ketemu --}}
                    <tr x-show="items.filter(i => i.sku.toLowerCase().includes(searchSKU.toLowerCase())).length === 0">
                        <td colspan="3" class="px-6 py-10 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 9.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p class="text-sm text-gray-400 font-bold uppercase">SKU "<span x-text="searchSKU"></span>" tidak ditemukan</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
