@extends('layouts.app')

@section('title', 'Inbound Management')

@section('content')
<style>
    [x-cloak] { display: none !important; }
    .table-compact td { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
    .status-badge { display: inline-block; min-width: 85px; text-align: center; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 10px; }

    /* Animasi tambahan untuk loading */
    .pulse-blue { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
</style>

@php
    $statusColors = [
        'Created'             => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'Inbound in Process'  => 'bg-blue-50 text-blue-700 border-blue-200',
        'Partially'           => 'bg-orange-50 text-orange-700 border-orange-200',
        'Completely'          => 'bg-green-50 text-green-700 border-green-200',
        'Cancelled by Seller' => 'bg-red-50 text-red-700 border-red-200',
    ];
    $summary = $stats;
    $warehouses = $requests->pluck('warehouse_code')->unique()->filter()->sort();
    $clients = $requests->pluck('client_name')->unique()->filter()->sort();
@endphp

<div class="space-y-4" x-data="{
    uploadModal: false,
    exportModal: false,
    loading: false,
    splitLoading: false,
    statusLoading: false,
    expandedRows: [],
    fileName: '',
    fileSize: '',
    exportId: null,
    exportType: 'single',
    search: '',
    filterStatus: '',
    filterDate: '',
    filterWh: '',
    filterClient: '',
    filterIo: '',

    shouldShow(ref, status, date, wh, client, io) {
        const s = this.search.toLowerCase();
        const matchSearch = (ref || '').toLowerCase().includes(s);
        const matchStatus = this.filterStatus === '' || status === this.filterStatus;
        const matchDate = this.filterDate === '' || date === this.filterDate;
        const matchWh = this.filterWh === '' || wh === this.filterWh;
        const matchClient = this.filterClient === '' || (client || '').includes(this.filterClient);
        const matchIo = this.filterIo === '' || (io || '').toLowerCase().includes(this.filterIo.toLowerCase());
        return matchSearch && matchStatus && matchDate && matchWh && matchClient && matchIo;
    }
}">

    {{-- 1. Header Section --}}
    <div class="flex flex-col lg:flex-row gap-4 items-center justify-between bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
        <div class="flex items-center gap-6">
            <div>
                <h1 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic">Inbound <span class="text-blue-600">Portal</span></h1>
                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest italic">Compact View v3.0</p>
            </div>
            <div class="flex gap-4 border-l pl-6 border-gray-100 dark:border-gray-800 overflow-x-auto custom-scrollbar">
                <div>
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest text-center">Total</p>
                    <p class="text-xl font-black leading-none text-blue-600 text-center">{{ number_format($summary->total) }}</p>
                </div>
                {{-- Hanya tampilkan stats Completed jika user login --}}
                <div>
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest text-center">Completed</p>
                    <p class="text-xl font-black leading-none text-blue-600 text-center">{{ number_format($summary->completed) }}</p>
                </div>
                <div>
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest text-center">Cancel</p>
                    <p class="text-xl font-black leading-none text-blue-600 text-center">{{ number_format($summary->cancelled) }}</p>
                </div>
                <div>
                    <p class="text-[9px] font-bold text-amber-500 uppercase tracking-widest text-center">Processing</p>
                    <p class="text-xl font-black leading-none text-gray-800 dark:text-white text-center">{{ number_format($summary->processing) }}</p>
                </div>
                <div>
                    <p class="text-[9px] font-bold text-amber-500 uppercase tracking-widest text-center">Pending</p>
                    <p class="text-xl font-black leading-none text-gray-800 dark:text-white text-center">{{ number_format($summary->pending) }}</p>
                </div>
            </div>
        </div>

        <button @click="uploadModal = true"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-indigo-500/20 active:scale-95">
            Update Actual Good
        </button>
    </div>

    {{-- 2. Filter Bar (Tetap Sama) --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-2">
        <input x-model="search" type="text" placeholder="Ref No..." class="px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-[10px] focus:ring-2 focus:ring-blue-500 dark:text-white">
        <input x-model="filterIo" type="text" placeholder="IO No..." class="px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-[10px] focus:ring-2 focus:ring-blue-500 dark:text-white">
        <select x-model="filterClient" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-[10px] px-2 py-2 dark:text-white font-bold">
            <option value="">All Clients</option>
            @foreach($clients as $client) <option value="{{ $client }}">{{ $client }}</option> @endforeach
        </select>
        <select x-model="filterStatus" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-[10px] px-2 py-2 dark:text-white font-bold">
            <option value="">All Status</option>
            <option value="Created">Created</option>
            <option value="Inbound in Process">Inbound in Process</option>
            <option value="Cancelled by Seller">Cancelled by Seller</option>
            <option value="Partially">Partially</option>
            <option value="Completely">Completely</option>
        </select>
        <input x-model="filterDate" type="date" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-[10px] px-2 py-2 dark:text-white">
        <button @click="search=''; filterIo=''; filterStatus=''; filterDate=''; filterWh=''; filterClient=''" class="text-[10px] font-black text-red-600 bg-red-50 rounded-xl px-2 py-2 uppercase border border-red-100">Clear</button>
    </div>

    {{-- 3. Data Table --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 table-compact">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-[9px] font-black text-gray-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-3 py-4 w-10 text-center">#</th>
                        <th class="px-4 py-4 text-left">Inbound Info</th>
                        <th class="px-4 py-4 text-left">Timeline</th>
                        <th class="px-4 py-4 text-center">WH</th>
                        <th class="px-4 py-4 text-center">Status</th>
                        <th class="px-4 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($requests->whereNull('parent_id') as $item)
                        @php
                            $hasChildren = $item->children->count() > 0;
                            $skuCount = $item->details->count();
                            $fullQty = $hasChildren ? $item->children->flatMap->details->sum('requested_quantity') : $item->details->sum('requested_quantity');
                            $formattedDate = $item->created_at->format('Y-m-d');
                            $childRefs = $item->children->pluck('reference_number')->join(' ');
                            $isLocked = in_array($item->status, ['Completed']);
                        @endphp

                        <tr x-show="shouldShow('{{ $item->reference_number . ' ' . $childRefs }}', '{{ $item->status }}', '{{ $formattedDate }}', '{{ $item->warehouse_code }}', '{{ $item->client_name }}', '{{ $item->inbound_order_no }}')"
                            class="hover:bg-blue-50/20 transition-colors">

                            <td class="px-3 py-2 text-center">
                                @if($hasChildren)
                                    <button @click="expandedRows.includes({{ $item->id }}) ? expandedRows = expandedRows.filter(id => id !== {{ $item->id }}) : expandedRows.push({{ $item->id }})"
                                        class="p-1 rounded border border-gray-200 transition transform"
                                        :class="expandedRows.includes({{ $item->id }}) ? 'rotate-90 bg-blue-600 text-white' : ''">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-black text-gray-900 dark:text-white uppercase leading-none tracking-tight text-sm">{{ $item->reference_number }}</span>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[9px] text-blue-600 font-extrabold uppercase bg-blue-50 dark:bg-blue-900/30 px-1.5 py-0.5 rounded">{{ $item->client_name }}</span>
                                        <span class="text-[9px] text-gray-400 font-bold">• {{ number_format($fullQty) }} Units</span>
                                    </div>
                                    <span class="mt-1.5 text-[10px] text-indigo-600 dark:text-indigo-400 font-black uppercase italic">IO: {{ $item->inbound_order_no ?? 'WAITING...' }}</span>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-[10px] leading-tight text-gray-500">
                                <div>Created: {{ $item->created_at->format('d/m H:i') }}</div>
                                @if($item->estimate_time)
                                <div class="text-amber-600 font-bold mt-1 italic uppercase">Est: {{ \Carbon\Carbon::parse($item->estimate_time)->format('d/m H:i') }}</div>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center text-[10px] font-bold text-gray-400 uppercase">{{ $item->warehouse_code }}</td>

                            <td class="px-4 py-3 text-center">
                                <span class="status-badge px-3 py-1 rounded-full text-[9px] font-black uppercase border {{ $statusColors[$item->status] ?? 'bg-gray-100' }}">
                                    {{ $item->status }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    {{-- RESET (If Locked) --}}
                                    @if($isLocked || $item->status === 'Partial Completed')
                                        <form action="{{ route('inbound.reset_status', $item->id) }}" method="POST" onsubmit="return confirm('Unlock data ini?')">
                                            @csrf
                                            <button type="submit" class="p-2 bg-orange-50 text-orange-600 rounded-xl hover:bg-orange-600 hover:text-white transition border border-orange-200 shadow-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2z"></path></svg>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- SPLIT --}}
                                    @if($skuCount > 100 && !$hasChildren && !$isLocked)
                                        <form action="{{ route('inbound.split', $item->id) }}" method="POST" @submit="splitLoading = true">
                                            @csrf
                                            <button type="submit" :disabled="splitLoading" class="px-3 py-2 bg-orange-100 text-orange-700 rounded-xl border border-orange-200 hover:bg-orange-600 hover:text-white transition flex items-center gap-2 shadow-sm">
                                                <span x-show="!splitLoading" class="text-[9px] font-black uppercase">Split</span>
                                                <svg x-show="splitLoading" class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- COMPLETE (Parent/Single) --}}
                                    @if(!$isLocked && !$hasChildren && $skuCount <= 100)
                                        <form action="{{ route('inbound.complete', $item->id) }}" method="POST" @submit="statusLoading = true">
                                            @csrf
                                            <button type="submit" :disabled="statusLoading" class="p-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition active:scale-90 shadow-md shadow-blue-900/20">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('inbound.show', $item->id) }}" class="p-2 bg-gray-100 dark:bg-gray-800 text-gray-600 rounded-xl border border-gray-200" title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    </a>

                                    <button @click="exportId = {{ $item->id }}; exportType = '{{ $hasChildren ? 'batch' : 'single' }}'; exportModal = true;" class="p-2 bg-green-600 text-white rounded-xl shadow-md active:scale-95 transition" title="Export">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- CHILD ROWS --}}
                        @if($hasChildren)
                            @foreach($item->children as $child)
                                @php $isChildLocked = ($child->status === 'Completed'); @endphp
                                <tr x-show="expandedRows.includes({{ $item->id }})" x-transition class="bg-slate-50 dark:bg-gray-800/40 border-l-4 border-blue-600 shadow-inner">
                                    <td class="px-3 py-3 text-center text-blue-600 font-black text-xs italic">↳</td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-black text-slate-700 dark:text-gray-200 uppercase leading-none">{{ $child->reference_number }}</span>
                                            <span class="mt-1 text-[10px] text-indigo-500 font-extrabold italic uppercase">IO: {{ $child->inbound_order_no ?? 'PENDING' }}</span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 text-[10px] leading-tight text-gray-500 font-bold">
                                        {{ $child->created_at->format('d/m H:i') }}
                                    </td>

                                    <td class="px-4 py-3 text-center text-[10px] font-black text-gray-400 uppercase">
                                        {{ $child->warehouse_code }}
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        <span class="status-badge px-3 py-1 rounded-full text-[9px] font-black uppercase border shadow-sm {{ $statusColors[$child->status] ?? 'bg-gray-100' }}">
                                            {{ $child->status }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            {{-- CHILD RESET --}}
                                            @if($isChildLocked)
                                                <form action="{{ route('inbound.reset_status', $child->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="p-2 bg-orange-50 text-orange-600 rounded-xl hover:bg-orange-600 hover:text-white transition border border-orange-200 shadow-sm" title="Reset Child">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2z"></path></svg>
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- CHILD COMPLETE MANUAL --}}
                                            @if(!$isChildLocked)
                                                <form action="{{ route('inbound.complete', $child->id) }}" method="POST" @submit="statusLoading = true">
                                                    @csrf
                                                    <button type="submit" :disabled="statusLoading" class="p-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition active:scale-90 shadow-md shadow-blue-900/20" title="Complete Child">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- CHILD VIEW & EXPORT --}}
                                            <a href="{{ route('inbound.show', $child->id) }}" class="p-2 bg-white dark:bg-gray-700 text-gray-600 rounded-xl border border-gray-200 active:scale-95 shadow-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            </a>

                                            <button @click="exportId = {{ $child->id }}; exportType = 'single'; exportModal = true;" class="p-2 bg-green-600 text-white rounded-xl shadow-md active:scale-95 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 font-bold uppercase tracking-widest text-[10px] italic">-- No Data Found --</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL UPLOAD --}}
    <div x-show="uploadModal" class="fixed inset-0 z-[100] overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-950/90 backdrop-blur-sm" @click="if(!loading) uploadModal = false"></div>
            <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] shadow-2xl z-[110] w-full max-w-md p-8 relative border border-gray-200">
                <div class="text-center mb-6">
                    <div class="inline-flex p-3 bg-indigo-100 rounded-2xl text-indigo-600 mb-3"><svg class="w-6 h-6" :class="loading ? 'animate-bounce' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg></div>
                    <h3 class="text-lg font-black text-gray-900 dark:text-white uppercase italic">Upload Actual Data</h3>
                </div>
                <form action="{{ route('inbound.upload') }}" method="POST" enctype="multipart/form-data" @submit="loading = true">
                    @csrf
                    <div class="relative group mb-6" x-show="!loading">
                        <input type="file" name="csv_file" required accept=".csv, .xls, .xlsx" @change="const file = $event.target.files[0]; if(file) { fileName = file.name; fileSize = (file.size/1024).toFixed(1) + ' KB'; }" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="border-2 border-dashed rounded-3xl p-8 text-center transition-all" :class="fileName ? 'border-green-500 bg-green-50/5' : 'border-gray-200 group-hover:border-indigo-500'">
                            <p x-show="!fileName" class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Select File</p>
                            <div x-show="fileName" x-cloak><p class="text-[10px] font-black text-gray-900 dark:text-white truncate" x-text="fileName"></p><p class="text-[9px] font-bold text-green-600 mt-1" x-text="fileSize"></p></div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" x-show="!loading" @click="uploadModal = false; fileName=''" class="flex-1 py-3 text-[10px] font-black text-gray-400 uppercase">Cancel</button>
                        <button type="submit" :disabled="loading || !fileName" class="flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition shadow-lg" :class="loading ? 'bg-gray-100 text-gray-400' : 'bg-indigo-600 text-white'"><span x-text="loading ? 'Uploading...' : 'Confirm'"></span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- EXPORT MODAL --}}
    <div x-show="exportModal" class="fixed inset-0 z-[150] flex items-center justify-center p-6" x-cloak x-transition>
        <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-[2px]" @click="exportModal = false"></div>
        <div class="bg-gray-900 rounded-[2rem] p-6 z-[160] w-[240px] flex flex-col items-center border border-gray-800 shadow-2xl relative">
            <h3 class="text-xs font-black text-white uppercase tracking-widest italic mb-4">Export XLSX</h3>
            <form :action="'{{ url('/inbound/export') }}/' + exportId" method="GET" class="w-full">
                <input type="hidden" name="type" :value="exportType">
                <button type="submit" @click="setTimeout(() => exportModal = false, 500)" class="w-full py-3 bg-green-600 hover:bg-green-500 text-white rounded-xl text-[10px] font-black uppercase transition-all shadow-lg">Download</button>
                <button type="button" @click="exportModal = false" class="w-full mt-2 py-2 text-[8px] font-bold text-gray-500 uppercase tracking-widest">Cancel</button>
            </form>
        </div>
    </div>
</div>
@endsection
