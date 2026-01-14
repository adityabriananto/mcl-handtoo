@extends('layouts.app')

@section('title', 'Inbound Management')

@section('content')
<style>
    [x-cloak] { display: none !important; }

    .btn-indigo {
        @apply bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] shadow-lg shadow-indigo-500/20 transition transform active:scale-95 flex items-center;
    }
</style>

@php
    $statusColors = [
        'Pending'    => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'Processing' => 'bg-blue-100 text-blue-700 border-blue-200',
        'Completed'  => 'bg-green-100 text-green-700 border-green-200',
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
    fileName: '',
    fileSize: '',
    expandedRows: [],
    exportModal: false,
    uploadModal: false,
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
    {{-- 1. Header & Quick Actions --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic">
                Inbound <span class="text-blue-600">Requests</span>
            </h1>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Inbound Data Processing</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Button Container --}}
            <button @click="uploadModal = true"
                class="group relative flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl transition-all duration-300 shadow-lg shadow-indigo-500/25 active:scale-95 border border-indigo-500/50">

                {{-- Icon dengan efek hover --}}
                <div class="mr-3 p-1 bg-indigo-500/50 rounded-lg group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                </div>

                {{-- Text --}}
                <div class="flex flex-col items-start leading-none">
                    <span class="text-[10px] font-black uppercase tracking-[0.15em] opacity-80">Action</span>
                    <span class="text-xs font-bold uppercase tracking-tighter mt-0.5">Update IO Number</span>
                </div>

                {{-- Glow Effect on Hover --}}
                <div class="absolute inset-0 rounded-2xl bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            </button>
        </div>
    </div>

    {{-- 2. Filters Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input x-model="search" type="text" placeholder="Search Ref..." class="w-full pl-10 pr-4 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 dark:text-white">
        </div>

        <select x-model="filterClient" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-xs px-4 py-2 dark:text-white">
            <option value="">All Clients</option>
            @foreach($clients as $client) <option value="{{ $client }}">{{ $client }}</option> @endforeach
        </select>

        <select x-model="filterWh" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-xs px-4 py-2 dark:text-white">
            <option value="">All Warehouse</option>
            @foreach($warehouses as $wh) <option value="{{ $wh }}">{{ $wh }}</option> @endforeach
        </select>

        <select x-model="filterStatus" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-xs px-4 py-2 dark:text-white">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="Completed">Completed</option>
        </select>

        <input x-model="filterDate" type="date" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-xs px-4 py-2 dark:text-white">

        <button @click="search = ''; filterStatus = ''; filterDate = ''; filterWh = ''; filterClient = ''" class="text-[10px] font-black text-red-600 hover:text-red-800 uppercase tracking-widest bg-red-50 dark:bg-red-950 border border-red-100 dark:border-red-900/30 rounded-xl px-4 py-2 transition">
            Reset
        </button>
    </div>

    {{-- 3. Stats Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border-b-4 border-blue-500">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Active</p>
            <p class="text-3xl font-black text-gray-800 dark:text-white tracking-tighter">{{ number_format($summary->total) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border-b-4 border-amber-500">
            <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest">Pending Work</p>
            <p class="text-3xl font-black text-gray-800 dark:text-white tracking-tighter">{{ number_format($summary->pending) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border-b-4 border-green-500">
            <p class="text-[10px] font-bold text-green-600 uppercase tracking-widest">Completed</p>
            <p class="text-3xl font-black text-gray-800 dark:text-white tracking-tighter">{{ number_format($summary->completed) }}</p>
        </div>
    </div>

    {{-- 4. Main Table --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-[2rem] overflow-hidden border border-gray-200 dark:border-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-4 py-5 w-10 text-center">#</th>
                        <th class="px-6 py-5">Reference / Qty</th>
                        {{-- KOLOM BARU --}}
                        <th class="px-6 py-5 text-left italic">IO Number</th>
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
                                        class="p-1.5 hover:bg-blue-600 hover:text-white rounded-full border border-gray-200 dark:border-gray-700 transition"
                                        :class="{ 'rotate-90 bg-blue-600 text-white border-blue-600': expandedRows.includes({{ $item->id }}) }">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
                                @endif
                            </td>

                            {{-- Kolom Reference --}}
                            <td class="px-6 py-4">
                                <div class="font-black text-gray-900 dark:text-white uppercase tracking-tight">{{ $item->reference_number }}</div>
                                <div class="text-[10px] text-blue-700 font-extrabold uppercase">{{ number_format($fullQty) }} Total Qty</div>
                            </td>

                            {{-- Kolom IO Number (Parent) --}}
                            <td class="px-6 py-4">
                                @if(!$hasChildren)
                                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 px-2 py-1 rounded-lg border border-indigo-100 dark:border-indigo-800">
                                        {{ $item->inbound_order_no ?? 'WAITING...' }}
                                    </span>
                                @else
                                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest italic italic">Multiple IO (Split)</span>
                                @endif
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
                                {{-- Actions Button (Sama seperti sebelumnya) --}}
                                <div class="flex justify-end items-center gap-2">
                                    <a href="{{ route('inbound.show', $item->id) }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-xl hover:bg-gray-200 transition text-[10px] font-black uppercase">Details</a>
                                    @if($fullQty > 200 && !$hasChildren)
                                        <form action="{{ route('inbound.split', $item->id) }}" method="POST">@csrf<button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-2 rounded-xl text-[10px] font-black uppercase animate-pulse shadow-lg shadow-orange-500/20 transition">⚠️ Split</button></form>
                                    @endif
                                    <button @click="exportId = {{ $item->id }}; exportType = '{{ $hasChildren ? 'batch' : 'single' }}'; exportModal = true;" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-xl text-[10px] font-black uppercase shadow-md transition">Export</button>
                                    @if($item->status !== 'Completed')
                                        <form action="{{ route('inbound.complete', $item->id) }}" method="POST" onsubmit="return confirm('Selesaikan dokumen ini?')">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $hasChildren ? 'batch' : 'single' }}">
                                            <button type="submit" @if($isDisabled) disabled @endif class="px-3 py-2 rounded-xl text-[10px] font-black uppercase transition {{ $isDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700' }}">Complete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Child Rows --}}
                        @if($hasChildren)
                            @foreach($item->children as $child)
                                <tr x-show="expandedRows.includes({{ $item->id }})" x-transition class="bg-slate-50 dark:bg-gray-800/50 border-l-8 border-blue-600">
                                    <td class="px-4 py-3 text-center text-blue-600 font-bold">↳</td>
                                    <td class="px-10 py-3">
                                        <div class="text-xs font-black text-gray-700 dark:text-gray-300 italic uppercase">{{ $child->reference_number }}</div>
                                    </td>
                                    {{-- IO Number untuk Child --}}
                                    <td class="px-6 py-3">
                                        <span class="text-[11px] font-black text-indigo-600 dark:text-indigo-400 italic">
                                            {{ $child->inbound_order_no ?? 'PENDING' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-center text-[10px] font-bold text-gray-400 uppercase tracking-tighter">{{ $child->client_name ?? $item->client_name }}</td>
                                    <td class="px-6 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ $child->warehouse_code }}</td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-lg text-[9px] font-black border {{ $statusColors[$child->status] ?? 'bg-gray-50' }}">{{ $child->status }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex justify-end items-center gap-3">
                                            <a href="{{ route('inbound.show', $child->id) }}" class="text-[10px] font-black text-gray-500 hover:text-blue-600 uppercase tracking-widest flex items-center">Details</a>
                                            <button @click="exportId = {{ $child->id }}; exportType = 'single'; exportModal = true;" class="text-green-600 font-black text-[10px] uppercase hover:underline">Export</button>
                                            @if($child->status !== 'Completed')
                                                <form action="{{ route('inbound.complete', $child->id) }}" method="POST">
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
                        <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400 font-bold uppercase tracking-widest text-xs italic">-- No Data Found --</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL: UPLOAD CSV/EXCEL --}}
    <div x-show="uploadModal" class="fixed inset-0 z-[60] overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-950/90 backdrop-blur-sm" @click="uploadModal = false; fileName = ''"></div>

            <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] shadow-2xl z-[70] w-full max-w-lg p-10 relative border border-gray-200 dark:border-gray-800">
                <div class="text-center mb-8">
                    <div class="inline-flex p-4 bg-indigo-100 dark:bg-indigo-900/30 rounded-3xl text-indigo-600 mb-4 transform -rotate-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic">Update Inbound Order Number</h3>
                </div>

                <form action="{{ route('inbound.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <div class="relative group">
                        <input type="file" name="csv_file" required
                            accept=".csv, .xls, .xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"
                            @change="
                                const file = $event.target.files[0];
                                if (file) {
                                    fileName = file.name;
                                    fileSize = (file.size / 1024).toFixed(2) > 1024
                                        ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                                        : (file.size / 1024).toFixed(2) + ' KB';
                                }
                            "
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                        <div class="border-2 border-dashed rounded-[2rem] p-12 text-center transition-all duration-300"
                            :class="fileName ? 'border-green-500 bg-green-50/10' : 'border-gray-300 dark:border-gray-700 group-hover:border-indigo-500 group-hover:bg-indigo-50/10'">

                            {{-- Tampilan sebelum pilih file --}}
                            <div x-show="!fileName">
                                <p class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest group-hover:text-indigo-500">
                                    Drop CSV, XLSX or Click to Browse
                                </p>
                                <p class="text-[9px] text-gray-400 mt-2 italic uppercase tracking-tighter">Supported: .csv, .xls, .xlsx (Max 5MB)</p>
                            </div>

                            {{-- Tampilan sesudah pilih file --}}
                            <div x-show="fileName" x-cloak class="flex flex-col items-center">
                                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-2xl text-green-600 mb-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <p class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-tight" x-text="fileName" style="word-break: break-all;"></p>
                                <p class="text-[9px] font-bold text-green-600 dark:text-green-400 mt-1 uppercase" x-text="fileSize"></p>
                                <button type="button" @click.stop="fileName = ''; $el.closest('form').reset()" class="mt-4 text-[9px] font-black text-red-500 hover:underline uppercase tracking-widest">Remove File</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="button" @click="uploadModal = false; fileName = ''" class="flex-1 py-4 text-xs font-black text-gray-400 hover:text-red-500 uppercase transition tracking-[0.2em]">Cancel</button>
                        <button type="submit" class="flex-1 py-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-2xl text-xs font-black shadow-xl shadow-indigo-900/20 uppercase transition tracking-[0.2em] transform active:scale-95">Start Processing</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: EXPORT CONFIG --}}
    <div x-show="exportModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-sm" @click="exportModal = false"></div>
            <div class="bg-gray-900 rounded-[2.5rem] shadow-2xl z-50 w-full max-w-md p-8 relative border border-gray-800">
                <div class="mb-6 text-center">
                    <h3 class="text-xl font-black text-white uppercase tracking-tighter italic">Export SKU Aggregation</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Unique SKU Consolidation Mode</p>
                </div>

                <form :action="'{{ url('/inbound/export') }}/' + exportId" method="GET">
                    <input type="hidden" name="type" :value="exportType">
                    <input type="hidden" name="export_mode" value="unique_sku">

                    <div class="space-y-4">
                        {{-- Bundling --}}
                        <div class="p-4 bg-gray-800/40 rounded-2xl border border-gray-700">
                            <label class="block text-[10px] font-black uppercase text-pink-500 mb-2 tracking-widest">Bundling Service</label>
                            <select name="bundling" x-model="bundling" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-xs">
                                <option value="">No Bundling</option>
                                <option value="Y">Active (Combine Items)</option>
                            </select>
                        </div>

                        {{-- VAS --}}
                        <div class="p-4 bg-gray-800/40 rounded-2xl border border-gray-700">
                            <label class="block text-[10px] font-black uppercase text-purple-500 mb-2 tracking-widest">VAS Instructions</label>
                            <select name="vas_needed" x-model="vasNeeded" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-xs">
                                <option value="">None</option>
                                <option value="Y">Include Special Info</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-800/40 rounded-2xl border border-gray-700">
                                <label class="block text-[10px] font-black text-gray-500 mb-2 uppercase tracking-widest">Repacking</label>
                                <select name="repacking" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-xs uppercase font-bold">
                                    <option value="">No</option>
                                    <option value="Y">Yes</option>
                                </select>
                            </div>
                            <div class="p-4 bg-gray-800/40 rounded-2xl border border-gray-700">
                                <label class="block text-[10px] font-black text-gray-500 mb-2 uppercase tracking-widest">Labeling</label>
                                <select name="labeling" class="w-full bg-gray-800 border-gray-700 text-white rounded-xl text-xs uppercase font-bold">
                                    <option value="">No</option>
                                    <option value="Y">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="exportModal = false" class="flex-1 py-4 text-xs font-black text-gray-500 hover:text-white uppercase transition tracking-widest">Close</button>
                        <button type="submit" class="flex-1 py-4 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl text-xs font-black shadow-xl shadow-blue-900/20 uppercase transition tracking-widest transform active:scale-95">Generate CSV</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
