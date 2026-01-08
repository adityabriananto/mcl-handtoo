@extends('layouts.app')

@section('title', 'Inbound Management')

@section('content')
@php
    $statusColors = [
        'Pending'   => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'Completed' => 'bg-green-100 text-green-700 border-green-200',
    ];
@endphp

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
            <h1 class="text-2xl font-black text-gray-800 dark:text-white uppercase tracking-tighter">📥 Inbound Management</h1>
            <p class="text-sm text-gray-500 font-medium">Monitoring dan pemrosesan dokumen inbound.</p>
        </div>
    </div>

    {{-- 2. Dashboard Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border-b-4 border-gray-400">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Request</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">{{ number_format($stats->total) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border-b-4 border-amber-500">
            <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest">Pending</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">{{ number_format($stats->pending) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border-b-4 border-green-500">
            <p class="text-[10px] font-bold text-green-600 uppercase tracking-widest">Completed</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">{{ number_format($stats->completed) }}</p>
        </div>
    </div>

    {{-- 3. Filter Section --}}
    <div class="bg-white dark:bg-gray-900 p-4 rounded-xl shadow-sm border border-gray-200">
        <form action="{{ route('inbound.index') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 tracking-widest">Reference</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="REF-..." class="w-full rounded-lg border-gray-300 dark:bg-gray-800 text-sm focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 tracking-widest">Warehouse</label>
                <select name="warehouse" class="w-full rounded-lg border-gray-300 dark:bg-gray-800 text-sm">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh }}" {{ ($filters['warehouse'] ?? '') == $wh ? 'selected' : '' }}>{{ $wh }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 tracking-widest">Status</label>
                <select name="status" class="w-full rounded-lg border-gray-300 dark:bg-gray-800 text-sm">
                    <option value="">All Status</option>
                    <option value="Pending" {{ ($filters['status'] ?? '') == 'Pending' ? 'selected' : '' }}>Pending</option>
                    <option value="Completed" {{ ($filters['status'] ?? '') == 'Completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg text-sm transition">Apply Filter</button>
                <a href="{{ route('inbound.index', ['reset' => 1]) }}" class="flex-1 bg-gray-50 text-center py-2 rounded-lg text-sm font-bold text-gray-600 border border-gray-200 leading-8">Reset</a>
            </div>
        </form>
    </div>

    {{-- 4. Main Data Table --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-2xl overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left">
                <thead class="bg-gray-50 dark:bg-gray-800 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-4 py-4 w-10 text-center">#</th>
                        <th class="px-6 py-4">Inbound Ref / Full Qty</th>
                        <th class="px-6 py-4">Timeline</th>
                        <th class="px-6 py-4">Warehouse</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($requests->whereNull('parent_id') as $item)
                        @php
                            $childCount = $item->children->count();
                            $hasChildren = $childCount > 0;
                            $fullQty = $hasChildren
                                ? $item->children->flatMap->details->sum('requested_quantity') + $item->details->sum('requested_quantity')
                                : $item->details->sum('requested_quantity');

                            $allChildrenDone = $hasChildren && $item->children->where('status', '!=', 'Completed')->count() === 0;

                            $estimateTime = $item->created_at->addDays(2);
                        @endphp

                        {{-- PARENT ROW --}}
                        <tr class="bg-white dark:bg-gray-900 hover:bg-blue-50/20 transition duration-150">
                            <td class="px-4 py-4 text-center">
                                @if($hasChildren)
                                    <button @click="expandedRows.includes({{ $item->id }}) ? expandedRows = expandedRows.filter(id => id !== {{ $item->id }}) : expandedRows.push({{ $item->id }})"
                                        class="p-1 hover:bg-blue-600 hover:text-white rounded-full border border-gray-200 transition-all"
                                        :class="{ 'rotate-90 bg-blue-600 text-white border-blue-600': expandedRows.includes({{ $item->id }}) }">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-black text-gray-900 dark:text-white tracking-tight">{{ $item->reference_number }}</div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-[10px] text-blue-700 font-extrabold uppercase tracking-tighter">{{ number_format($fullQty) }} Full Qty</span>
                                    @if($hasChildren)
                                        <span class="text-[10px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded border border-blue-100 font-black uppercase">
                                            {{ $childCount }} Split Parts
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-[10px] font-black text-blue-400 uppercase mb-0.5">Created: <span class="font-black text-blue-600">{{ $item->created_at->format('d M Y') }}</span></div>
                                <div class="text-[10px] font-black text-gray-400 uppercase">Est. Finish: <span class="font-black text-red-500">{{ $estimateTime->format('d M Y') }}</span></div>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-600">{{ $item->warehouse_code }}</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase border {{ $statusColors[$item->status] ?? 'bg-gray-100' }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center space-x-2">
                                    @if($fullQty > 200 && !$hasChildren)
                                        <form action="{{ route('inbound.split', $item->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="bg-orange-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">Split</button>
                                        </form>
                                    @endif

                                    <button @click="exportId = {{ $item->id }}; exportType = '{{ $hasChildren ? 'batch' : 'single' }}'; exportModal = true"
                                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition">
                                        {{ $hasChildren ? 'Batch Export' : 'Export' }}
                                    </button>

                                    @if($item->status !== 'Completed')
                                        <form action="{{ route('inbound.complete', $item->id) }}" method="POST" onsubmit="return confirm('Selesaikan dokumen?')">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $hasChildren ? 'batch' : 'single' }}">
                                            <button type="submit"
                                                @if($hasChildren && !$allChildrenDone) disabled @endif
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm {{ ($hasChildren && !$allChildrenDone) ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white' }}">
                                                {{ $hasChildren ? 'Batch Done' : 'Complete' }}
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('inbound.show', $item->id) }}" class="bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold border">View</a>
                                </div>
                            </td>
                        </tr>

                        {{-- CHILD ROWS (SUB-IO) --}}
                        @if($hasChildren)
                            @foreach($item->children as $child)
                                @php
                                    $childQty = $child->details->sum('requested_quantity');
                                    $progress = $child->status === 'Completed' ? 100 : 0;
                                @endphp
                                <tr x-show="expandedRows.includes({{ $item->id }})"
                                    x-transition.opacity
                                    class="bg-slate-100 dark:bg-gray-800/80 border-l-8 border-blue-600">
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-blue-600 font-bold">↳</span>
                                    </td>
                                    <td class="px-10 py-3">
                                        <div class="text-xs font-black text-gray-700 dark:text-gray-300 italic">{{ $child->reference_number }}</div>
                                        <div class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">{{ number_format($childQty) }} Qty</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-[9px] font-bold text-gray-400 uppercase italic">Part of Split Data</div>
                                    </td>
                                    <td class="px-6 py-3 text-xs text-gray-500 font-bold">{{ $child->warehouse_code }}</td>
                                    <td class="px-6 py-3">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black border {{ $statusColors[$child->status] ?? 'bg-gray-50' }}">
                                            {{ $child->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex justify-end items-center space-x-4">
                                            @if($child->status !== 'Completed')
                                                <form action="{{ route('inbound.complete', $child->id) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="type" value="single">
                                                    <button type="submit" class="text-blue-600 hover:underline text-[10px] font-black uppercase tracking-widest">Mark Done</button>
                                                </form>
                                            @endif
                                            <button @click="exportId = {{ $child->id }}; exportType = 'single'; exportModal = true" class="text-green-600 hover:underline text-[10px] font-black uppercase">CSV</button>
                                            <a href="{{ route('inbound.show', $child->id) }}" class="text-gray-400 hover:text-gray-700 text-[10px] font-black uppercase">Details</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-6 bg-gray-50 dark:bg-gray-900 border-t border-gray-100">
            {{ $requests->links() }}
        </div>
    </div>

    {{-- Export Modal --}}
    <div x-show="exportModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="exportModal = false"></div>
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl z-50 w-full max-w-md p-8 relative">
                <h3 class="text-xl font-black mb-1 text-gray-800 dark:text-white uppercase tracking-tighter">
                    <span x-text="exportType === 'batch' ? 'Batch Export (ZIP)' : 'Export CSV File'"></span>
                </h3>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-6 tracking-widest">Pilih Layanan VAS</p>

                <form :action="'{{ url('/inbound/export') }}/' + exportId" method="GET">
                    <input type="hidden" name="type" :value="exportType">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-gray-400 mb-1">Vas Needed</label>
                            <select name="vas_needed" x-model="vasNeeded" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm focus:ring-blue-500">
                                <option value="">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase text-gray-400 mb-1">Repacking</label>
                                <select name="repacking" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm"><option value="No">No</option><option value="Yes">Yes</option></select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase text-gray-400 mb-1">Labeling</label>
                                <select name="labeling" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm"><option value="No">No</option><option value="Yes">Yes</option></select>
                            </div>
                        </div>
                        <div x-show="vasNeeded === 'Yes'" x-transition>
                            <label class="block text-[10px] font-black uppercase text-gray-400 mb-1">Vas Instruction</label>
                            <textarea name="vas_instruction" rows="3" class="w-full rounded-xl border-gray-200 dark:bg-gray-800 text-sm" placeholder="Instruksi khusus..."></textarea>
                        </div>
                    </div>
                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="exportModal = false" class="flex-1 px-4 py-3 text-sm font-bold text-gray-500 hover:bg-gray-100 rounded-xl">Cancel</button>
                        <button type="submit" @click="setTimeout(() => exportModal = false, 500)" class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-lg">Download</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
