@extends('layouts.app')

@section('title', 'Inbound Requests')

@section('content')
{{-- Global State menggunakan Alpine.js --}}
<div class="space-y-6" x-data="{
    expandedRows: [],
    exportModal: false,
    exportId: null,
    exportType: 'single',
    vasNeeded: ''
}">
    {{-- 1. Header --}}
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">📥 Inbound Requests</h1>
            <p class="text-sm text-gray-500">Kelola dokumen inbound, proses split, dan ekspor data (CSV/ZIP).</p>
        </div>
    </div>

    {{-- 2. Dashboard Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border-b-4 border-gray-400">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-tighter">Total Dokumen</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">{{ number_format($stats->total) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border-b-4 border-yellow-500">
            <p class="text-xs font-bold text-yellow-600 uppercase tracking-tighter">Created / Pending</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">{{ number_format($stats->pending) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border-b-4 border-green-500">
            <p class="text-xs font-bold text-green-600 uppercase tracking-tighter">Sent</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">{{ number_format($stats->sent) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border-b-4 border-blue-500">
            <p class="text-xs font-bold text-blue-600 uppercase tracking-tighter">Completed</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">{{ number_format($stats->completed) }}</p>
        </div>
    </div>

    {{-- 3. Filter Form --}}
    <div class="bg-white dark:bg-gray-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
        <form action="{{ route('inbound.index') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Search Ref</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="REF-..." class="w-full rounded-lg border-gray-300 dark:bg-gray-800 text-sm focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Warehouse</label>
                <select name="warehouse" class="w-full rounded-lg border-gray-300 dark:bg-gray-800 text-sm">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh }}" {{ ($filters['warehouse'] ?? '') == $wh ? 'selected' : '' }}>{{ $wh }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border-gray-300 dark:bg-gray-800 text-sm">
                    <option value="">All Status</option>
                    <option value="Pending" {{ ($filters['status'] ?? '') == 'Pending' ? 'selected' : '' }}>Created</option>
                    <option value="Sent" {{ ($filters['status'] ?? '') == 'Sent' ? 'selected' : '' }}>Sent</option>
                    <option value="Completed" {{ ($filters['status'] ?? '') == 'Completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg text-sm transition">Filter</button>
                <a href="{{ route('inbound.index', ['reset' => 1]) }}" class="flex-1 bg-gray-100 dark:bg-gray-800 text-center py-2 rounded-lg text-sm font-bold text-gray-600 dark:text-gray-300 border border-gray-200 leading-8">Reset</a>
            </div>
        </form>
    </div>

    {{-- 4. Inbound Table --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left">
                <thead class="bg-gray-50 dark:bg-gray-800 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-4 py-4 w-10 text-center">#</th>
                        <th class="px-6 py-4">Inbound Ref</th>
                        <th class="px-6 py-4">Warehouse</th>
                        <th class="px-6 py-4">Total Qty</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($requests->whereNull('parent_id') as $item)
                        @php
                            $mainQty = $item->details->sum('requested_quantity');
                            $hasChildren = $item->children->count() > 0;
                        @endphp

                        {{-- PARENT ROW --}}
                        <tr class="bg-white dark:bg-gray-900 hover:bg-blue-50/30 transition duration-150">
                            <td class="px-4 py-4 text-center">
                                @if($hasChildren)
                                    <button @click="expandedRows.includes({{ $item->id }}) ? expandedRows = expandedRows.filter(id => id !== {{ $item->id }}) : expandedRows.push({{ $item->id }})"
                                        class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full border border-gray-200 transition-transform"
                                        :class="{ 'rotate-90 bg-blue-50': expandedRows.includes({{ $item->id }}) }">
                                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-black text-blue-600 dark:text-blue-400 tracking-tight">{{ $item->reference_number }}</div>
                                <div class="text-[10px] text-gray-400 font-bold uppercase">{{ $item->created_at->format('d M Y') }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-600">{{ $item->warehouse_code }}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold {{ $mainQty > 200 ? 'text-red-500' : 'text-gray-700' }}">
                                        {{ number_format($mainQty) }} Units
                                    </span>
                                    @if($hasChildren)
                                        <span class="text-[9px] text-blue-500 font-black uppercase tracking-tighter">{{ $item->children->count() }} Split Files Connected</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $statusColors = [
                                        'Pending'   => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                        'Completed' => 'bg-green-100 text-green-700 border-green-200',
                                        'Sent'      => 'bg-blue-100 text-blue-700 border-blue-200',
                                    ];
                                    $colorClass = $statusColors[$item->status] ?? 'bg-gray-50 text-gray-500 border-gray-100';
                                @endphp
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border {{ $colorClass }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center space-x-2">
                                    @if($mainQty > 200)
                                        <form action="{{ route('inbound.split', $item->id) }}" method="POST" onsubmit="return confirm('Sistem akan otomatis membagi dokumen ini menjadi kelipatan maks 200. Lanjutkan?')">
                                            @csrf
                                            <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition transform active:scale-95 shadow-sm">
                                                Auto Split
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Tombol Export (Batch ZIP jika ada split, CSV jika tidak) --}}
                                    <button @click="exportId = {{ $item->id }}; exportType = '{{ $hasChildren ? 'batch' : 'single' }}'; exportModal = true"
                                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition">
                                        {{ $hasChildren ? 'Batch Export' : 'Export' }}
                                    </button>

                                    <a href="{{ route('inbound.show', $item->id) }}" class="bg-gray-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition">View</a>
                                </div>
                            </td>
                        </tr>

                        {{-- CHILDREN ROWS (SUB-IO) --}}
                        @if($hasChildren)
                            @foreach($item->children as $child)
                                <tr x-show="expandedRows.includes({{ $item->id }})" x-transition class="bg-gray-50/50 dark:bg-gray-800/40 italic border-l-4 border-blue-500/30">
                                    <td class="px-4 py-3"></td>
                                    <td class="px-12 py-3 text-sm text-gray-500">
                                        <span class="text-gray-300 mr-2 font-normal">└─</span> {{ $child->reference_number }}
                                    </td>
                                    <td class="px-6 py-3 text-sm opacity-50">{{ $child->warehouse_code }}</td>
                                    <td class="px-6 py-3 text-sm font-bold text-gray-500">{{ number_format($child->details->sum('requested_quantity')) }} Units</td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="text-[9px] font-black text-blue-500 border border-blue-100 px-2 py-0.5 rounded uppercase">SUB-IO</span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex justify-end items-center space-x-3">
                                            <button @click="exportId = {{ $child->id }}; exportType = 'single'; exportModal = true"
                                                    class="text-green-600 hover:text-green-800 text-[10px] font-black uppercase tracking-wider">
                                                Export
                                            </button>
                                            <a href="{{ route('inbound.show', $child->id) }}" class="text-blue-500 hover:text-blue-700 text-[10px] font-black uppercase">Details</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-6 bg-gray-50 dark:bg-gray-800 border-t border-gray-100">
            {{ $requests->links() }}
        </div>
    </div>

    {{-- 5. MODAL EXPORT (Alpine.js Controlled) --}}
    <div x-show="exportModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-900 opacity-60" @click="exportModal = false"></div>
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl z-50 w-full max-w-md p-8 relative">
                <h3 class="text-xl font-black mb-1 text-gray-800 dark:text-white uppercase tracking-tighter">
                    <span x-text="exportType === 'batch' ? 'Batch Export (ZIP Package)' : 'Export Service Settings'"></span>
                </h3>
                <p class="text-[10px] font-bold uppercase mb-6" :class="exportType === 'batch' ? 'text-blue-600' : 'text-gray-400'">
                    <span x-text="exportType === 'batch' ? 'Setiap Sub-IO akan diekspor sebagai file CSV terpisah di dalam ZIP.' : 'Data akan diekspor sebagai file CSV tunggal.'"></span>
                </p>

                <form :action="'{{ url('/inbound/export') }}/' + exportId" method="GET">
                    <input type="hidden" name="type" :value="exportType">

                    <div class="space-y-5">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-gray-400 mb-1.5 tracking-widest">Vas Needed</label>
                            <select name="vas_needed" x-model="vasNeeded" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm focus:ring-blue-500">
                                <option value="">No</option>
                                <option value="Y">Yes</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase text-gray-400 mb-1.5 tracking-widest">Repacking</label>
                                <select name="repacking" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm">
                                    <option value="">No</option>
                                    <option value="Y">Yes</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase text-gray-400 mb-1.5 tracking-widest">Labeling</label>
                                <select name="labeling" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm">
                                    <option value="">No</option>
                                    <option value="Y">Yes</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase text-gray-400 mb-1.5 tracking-widest">Bundling</label>
                                <select name="bundling" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm">
                                    <option value="">No</option>
                                    <option value="Y">Yes</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase text-gray-400 mb-1.5 tracking-widest">Items per Bundle</label>
                                <input type="number" name="bundle_qty" value="1" min="1" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm">
                            </div>
                        </div>

                        <div x-show="vasNeeded === 'Y'" x-transition>
                            <label class="block text-[10px] font-black uppercase text-gray-400 mb-1.5 tracking-widest">Vas Instruction</label>
                            <textarea name="vas_instruction" rows="3" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm" placeholder="Masukkan instruksi jika Vas Needed dipilih Yes..."></textarea>
                        </div>
                    </div>

                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="exportModal = false" class="flex-1 px-4 py-3 text-sm font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition">Cancel</button>
                        <button type="submit" @click="setTimeout(() => exportModal = false, 500)" class="flex-1 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-blue-200 transition">Download</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
