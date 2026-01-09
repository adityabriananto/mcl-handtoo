@extends('layouts.app')

@section('title', 'Inbound Management')

@section('content')
<style>
    [x-cloak] { display: none !important; }
</style>

@php
    $statusColors = [
        'Pending'   => 'bg-amber-100 text-amber-700 border-amber-200',
        'Completed' => 'bg-green-100 text-green-700 border-green-200',
    ];

    $summary = $stats;
    $warehouses = $requests->pluck('warehouse_code')->unique()->filter()->sort();
    $clients = $requests->pluck('client_name')->unique()->filter()->sort();
@endphp

<div class="space-y-6" x-data="{
    search: '',
    filterStatus: '',
    filterDate: '',
    filterWh: '',
    filterClient: '',
    expandedRows: [],
    exportModal: false,
    exportId: null,
    exportType: 'single',
    vasNeeded: 'N',
    vasInstruction: '',
    bundling: 'N',
    bundleItems: '',
    repacking: 'N',
    labeling: 'N',

    shouldShow(ref, status, date, wh, client) {
        const matchSearch = ref.toLowerCase().includes(this.search.toLowerCase());
        const matchStatus = this.filterStatus === '' || status === this.filterStatus;
        const matchDate = this.filterDate === '' || date === this.filterDate;
        const matchWh = this.filterWh === '' || wh === this.filterWh;
        const matchClient = this.filterClient === '' || (client || '').includes(this.filterClient);
        return matchSearch && matchStatus && matchDate && matchWh && matchClient;
    }
}">
    {{-- 1. Header & Filters --}}
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input x-model="search" type="text" placeholder="Search Ref..." class="w-full pl-10 pr-4 py-2 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 dark:text-white">
        </div>

        <select x-model="filterClient" class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl text-xs px-4 py-2 dark:text-white">
            <option value="">All Clients</option>
            @foreach($clients as $client)
                <option value="{{ $client }}">{{ $client }}</option>
            @endforeach
        </select>

        <select x-model="filterWh" class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl text-xs px-4 py-2 dark:text-white">
            <option value="">All Warehouse</option>
            @foreach($warehouses as $wh)
                <option value="{{ $wh }}">{{ $wh }}</option>
            @endforeach
        </select>

        <select x-model="filterStatus" class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl text-xs px-4 py-2 dark:text-white">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="Completed">Completed</option>
        </select>

        <input x-model="filterDate" type="date" class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl text-xs px-4 py-2 dark:text-white">

        <button @click="search = ''; filterStatus = ''; filterDate = ''; filterWh = ''; filterClient = ''" class="text-[10px] font-black text-red-600 hover:text-red-800 uppercase tracking-widest bg-red-50 dark:bg-red-900/10 rounded-xl px-4 py-2 transition border border-red-100 dark:border-red-900/30">
            Clear Filters
        </button>
    </div>

    {{-- 2. Dashboard Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border-b-4 border-blue-500">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Active Units</p>
            <p class="text-3xl font-black text-gray-800 dark:text-white">{{ number_format($summary->total) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border-b-4 border-amber-500">
            <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest">Pending</p>
            <p class="text-3xl font-black text-gray-800 dark:text-white">{{ number_format($summary->pending) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border-b-4 border-green-500">
            <p class="text-[10px] font-bold text-green-600 uppercase tracking-widest">Completed</p>
            <p class="text-3xl font-black text-gray-800 dark:text-white">{{ number_format($summary->completed) }}</p>
        </div>
    </div>

    {{-- 3. Data Table --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-3xl overflow-hidden border border-gray-200 dark:border-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-4 py-5 w-10 text-center">#</th>
                        <th class="px-6 py-5">Reference / Qty</th>
                        <th class="px-6 py-5 text-center">Client</th>
                        <th class="px-6 py-5">Warehouse</th>
                        <th class="px-6 py-5 text-center">Status</th>
                        <th class="px-6 py-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($requests->whereNull('parent_id') as $item)
                        @php
                            $childCount = $item->children->count();
                            $hasChildren = $childCount > 0;
                            $fullQty = $hasChildren ? $item->children->flatMap->details->sum('requested_quantity') : $item->details->sum('requested_quantity');
                            $formattedDate = $item->created_at->format('Y-m-d');
                            $childRefs = $item->children->pluck('reference_number')->join(' ');
                            $isDisabled = ($fullQty > 200 && !$hasChildren);
                        @endphp

                        <tr x-show="shouldShow('{{ $item->reference_number . ' ' . $childRefs }}', '{{ $item->status }}', '{{ $formattedDate }}', '{{ $item->warehouse_code }}', '{{ $item->client_name }}')"
                            class="bg-white dark:bg-gray-900 hover:bg-blue-50/20 transition duration-150">
                            <td class="px-4 py-4 text-center">
                                @if($hasChildren)
                                    <button @click="expandedRows.includes({{ $item->id }}) ? expandedRows = expandedRows.filter(id => id !== {{ $item->id }}) : expandedRows.push({{ $item->id }})"
                                        class="p-1.5 hover:bg-blue-600 hover:text-white rounded-full border border-gray-200 dark:border-gray-700 transition focus:outline-none"
                                        :class="{ 'rotate-90 bg-blue-600 text-white border-blue-600': expandedRows.includes({{ $item->id }}) }">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-black text-gray-900 dark:text-white uppercase tracking-tight">{{ $item->reference_number }}</div>
                                <div class="text-[10px] text-blue-700 font-extrabold uppercase">{{ number_format($fullQty) }} Total Qty</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs font-black text-blue-600 dark:text-blue-400 uppercase tracking-tighter">{{ $item->client_name ?? 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-widest">{{ $item->warehouse_code ?? 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase border {{ $statusColors[$item->status] ?? 'bg-gray-100' }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center space-x-2">
                                    <a href="{{ route('inbound.show', $item->id) }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-xl hover:bg-gray-200 transition text-[10px] font-black uppercase tracking-tighter">Details</a>

                                    @if($fullQty > 200 && !$hasChildren)
                                        <form action="{{ route('inbound.split', $item->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1.5 rounded-xl text-[10px] font-black uppercase animate-pulse">⚠️ Split</button>
                                        </form>
                                    @endif

                                    <button @click="exportId = {{ $item->id }}; exportType = '{{ $hasChildren ? 'batch' : 'single' }}'; vasInstruction = ''; exportModal = true;" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-xl text-[10px] font-black uppercase shadow-md transition">Export</button>

                                    @if($item->status !== 'Completed')
                                        <form action="{{ route('inbound.complete', $item->id) }}" method="POST" onsubmit="return confirm('Selesaikan dokumen ini?')">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $hasChildren ? 'batch' : 'single' }}">
                                            <button type="submit" @if($isDisabled) disabled @endif class="px-3 py-2 rounded-xl text-[10px] font-black uppercase transition {{ $isDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700' }}">
                                                {{ $hasChildren ? 'Batch Complete' : 'Complete' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Child Rows --}}
                        @if($hasChildren)
                            @foreach($item->children as $child)
                                <tr x-show="expandedRows.includes({{ $item->id }}) && shouldShow('{{ $item->reference_number . ' ' . $child->reference_number }}', '{{ $child->status }}', '{{ $formattedDate }}', '{{ $child->warehouse_code }}', '{{ $child->client_name ?? $item->client_name }}')"
                                    x-transition class="bg-slate-50 dark:bg-gray-800/50 border-l-8 border-blue-600">
                                    <td class="px-4 py-3 text-center text-blue-600 font-bold italic font-mono">↳</td>
                                    <td class="px-10 py-3">
                                        <div class="text-xs font-black text-gray-700 dark:text-gray-300 italic uppercase">{{ $child->reference_number }}</div>
                                        <div class="text-[9px] font-bold text-gray-500 uppercase">{{ number_format($child->details->sum('requested_quantity')) }} Qty</div>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="text-[10px] font-bold text-blue-500/70 uppercase">{{ $child->client_name ?? $item->client_name ?? 'N/A' }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ $child->warehouse_code }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-lg text-[9px] font-black border {{ $statusColors[$child->status] ?? 'bg-gray-50' }}">{{ $child->status }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex justify-end items-center space-x-3">
                                            <a href="{{ route('inbound.show', $child->id) }}" class="text-[10px] font-black text-gray-400 hover:text-blue-600 uppercase tracking-tighter transition">View Details</a>
                                            <button @click="exportId = {{ $child->id }}; exportType = 'single'; vasInstruction = ''; exportModal = true;" class="text-green-600 font-black text-[10px] uppercase hover:underline">Export</button>

                                            @if($child->status !== 'Completed')
                                                <form action="{{ route('inbound.complete', $child->id) }}" method="POST" onsubmit="return confirm('Selesaikan pecahan ini?')">
                                                    @csrf
                                                    <input type="hidden" name="type" value="single">
                                                    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-[10px] font-black uppercase hover:bg-blue-700 transition">Complete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-400 font-bold uppercase tracking-widest text-xs italic">-- No Inbound Data Found --</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Export Modal --}}
    <div x-show="exportModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-sm" @click="exportModal = false"></div>
            <div class="bg-gray-900 rounded-3xl shadow-2xl z-50 w-full max-w-md p-8 relative border border-gray-800">
                <div class="mb-6 text-center">
                    <h3 class="text-xl font-black text-white uppercase tracking-tighter">SKU Based Export</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Metode: Unique SKU Aggregation</p>
                </div>

                <form :action="'{{ url('/inbound/export') }}/' + exportId" method="GET">
                    <input type="hidden" name="type" :value="exportType">
                    <input type="hidden" name="export_mode" value="unique_sku">

                    <div class="space-y-4 text-left">
                        {{-- Bundling Service --}}
                        <div class="p-4 bg-pink-950/20 rounded-2xl border border-pink-900/30">
                            <label class="block text-[10px] font-black uppercase text-pink-500 mb-2">Bundling Service</label>
                            <select name="bundling" x-model="bundling" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-sm">
                                <option value="">No</option>
                                <option value="Y">Yes</option>
                            </select>
                            <div x-show="bundling === 'Y'" class="mt-4">
                                <input type="number" name="bundle_items" x-model="bundleItems" min="2" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-sm" placeholder="Items per Bundle">
                            </div>
                        </div>

                        {{-- VAS Instruction --}}
                        <div class="p-4 bg-purple-950/20 rounded-2xl border border-purple-900/30">
                            <label class="block text-[10px] font-black uppercase text-purple-500 mb-2">VAS Instruction</label>
                            <select name="vas_needed" x-model="vasNeeded" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-sm">
                                <option value="">No</option>
                                <option value="Y">Yes</option>
                            </select>
                            <div x-show="vasNeeded === 'Y'" class="mt-4">
                                <textarea name="vas_instruction" x-model="vasInstruction" maxlength="256" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-sm" placeholder="Instruction details..."></textarea>
                                <p class="text-[9px] text-right text-gray-500 mt-1"><span x-text="(vasInstruction || '').length"></span>/256</p>
                            </div>
                        </div>

                        {{-- Repacking & Labeling --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-800/40 rounded-2xl border border-gray-700">
                                <label class="block text-[10px] font-black text-gray-500 mb-2 uppercase">Repacking</label>
                                <select name="repacking" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-sm">
                                    <option value="">No</option>
                                    <option value="Y">Yes</option>
                                </select>
                            </div>
                            <div class="p-4 bg-gray-800/40 rounded-2xl border border-gray-700">
                                <label class="block text-[10px] font-black text-gray-500 mb-2 uppercase">Labeling</label>
                                <select name="labeling" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-sm">
                                    <option value="">No</option>
                                    <option value="Y">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="exportModal = false" class="flex-1 py-4 text-xs font-black text-gray-500 hover:text-white uppercase transition tracking-widest">Cancel</button>
                        <button type="submit" class="flex-1 py-4 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl text-xs font-black shadow-xl shadow-blue-900/20 uppercase transition tracking-widest">Generate CSV</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
