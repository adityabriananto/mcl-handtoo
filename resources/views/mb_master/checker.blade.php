@extends('layouts.app')

@section('content')
<style>
    [x-cloak] { display: none !important; }

    /* Header Background Logic */
    .sticky-header-container {
        position: sticky;
        top: 0;
        z-index: 100;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.25rem 1.5rem;
        background: linear-gradient(to bottom, #0f172a 0%, rgba(15, 23, 42, 0.95) 80%, rgba(15, 23, 42, 0) 100%);
    }

    /* Input Field High Contrast */
    .search-input-white {
        background-color: #ffffff !important;
        color: #0f172a !important;
        border: 3px solid #4f46e5 !important;
    }

    /* Table & Scrollbar Styling */
    .custom-scrollbar::-webkit-scrollbar { height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #4f46e5; border-radius: 10px; }

    .dark-table {
        background-color: #1e293b; /* Slate 800 */
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .balanced-td { padding: 1.25rem 1.5rem !important; }

    /* Export Button Gradient */
    .btn-export {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        transition: all 0.3s ease;
    }
    .btn-export:hover {
        filter: brightness(1.1);
        transform: translateY(-1px);
    }
</style>

<div class="max-w-[1800px] mx-auto space-y-4" x-data="{ loading: false, exporting: false, searchQuery: '{{ $searchQuery ?? '' }}' }">

    {{-- 1. STICKY HEADER (FIXED & COMPACT) --}}
    <div class="sticky top-0 z-[40] -mx-4 px-4 pt-4 mb-4">
        {{-- Glassmorphism Effect untuk background agar tidak kaku --}}
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-md -z-10 border-b border-slate-700/50"></div>

        <div class="bg-slate-800 p-2.5 md:p-3 rounded-2xl shadow-2xl relative overflow-hidden border border-slate-700">
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-3">

                {{-- Branding & Export --}}
                <div class="flex items-center justify-between lg:justify-start gap-4">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0">
                            <h2 class="text-base font-black text-white uppercase italic tracking-tighter leading-none">
                                Brand <span class="text-indigo-500">Checker</span>
                            </h2>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-[7px] font-bold text-slate-500 uppercase tracking-widest">Live System</span>
                            </div>
                        </div>

                        {{-- TOMBOL EXPORT (Dibuat lebih mungil) --}}
                        <form action="{{ route('mb-checker.export') }}" method="GET" @submit="exporting = true" class="lg:ml-2">
                            <button type="submit" :disabled="exporting"
                                class="btn-export flex items-center gap-2 px-3 py-1.5 rounded-lg text-white text-[8px] font-black uppercase tracking-wider shadow-md disabled:opacity-50">
                                <template x-if="!exporting">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </template>
                                <template x-if="exporting">
                                    <svg class="animate-spin h-3 w-3 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </template>
                                <span class="hidden sm:inline" x-text="exporting ? 'Wait...' : 'Export ZIP'"></span>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- SEARCH FIELD (SUPER SLIM) --}}
                <div class="flex-grow max-w-2xl">
                    <form action="{{ route('mb-checker.verify') }}" method="GET" @submit="loading = true" x-init="$refs.searchInput.focus()">
                        <div class="relative group">
                            <input type="text" name="search_query" x-model="searchQuery" x-ref="searchInput" autocomplete="off"
                                class="w-full pl-4 pr-36 py-2 search-input-white rounded-xl text-sm font-bold shadow-inner transition-all outline-none border-2 border-indigo-600 focus:ring-4 focus:ring-indigo-500/20"
                                placeholder="Scan Barcode / Waybill...">

                            {{-- TOMBOL CLEAR (High Contrast & Posisi Presisi) --}}
                            <div class="absolute right-20 top-0 bottom-0 flex items-center">
                                <button type="button" x-show="searchQuery.length > 0"
                                    @click="searchQuery = ''; $refs.searchInput.focus()" x-cloak
                                    class="p-1 text-slate-900 hover:text-red-700 transition-all bg-slate-200 hover:bg-slate-300 rounded-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>

                            {{-- TOMBOL VERIFY (SLIM) --}}
                            <button type="submit" class="absolute right-1 top-1 bottom-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">
                                <span x-show="!loading">Verify</span>
                                <svg x-show="loading" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. DATA TABLE --}}
    @if(isset($results))
    <div class="px-6 animate-in fade-in slide-in-from-bottom-3 duration-400">
        <div class="overflow-x-auto custom-scrollbar dark-table rounded-2xl shadow-2xl">
            <table class="w-full border-separate border-spacing-0">
                <thead>
                    <tr class="text-left bg-slate-900/50">
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-700">Barcode</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-700">Order Reference</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-700">Brand Identity</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-700">Inventory SKU</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-700 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-bold">
                    @forelse($results as $barcode => $brands)
                        @foreach($brands as $index => $item)
                        <tr class="hover:bg-white/[0.03] transition-colors border-b border-slate-700 {{ $brands->count() > 1 ? 'bg-amber-500/10' : '' }}">
                            <td class="balanced-td align-middle">
                                <span class="text-indigo-400 font-mono text-lg block leading-none">{{ $barcode }}</span>
                                @if($brands->count() > 1)
                                    <span class="inline-block mt-2 bg-amber-600 text-white text-[9px] px-2 py-0.5 rounded font-black uppercase">Multi Brand Detected</span>
                                @endif
                            </td>
                            <td class="balanced-td align-middle border-l border-white/5">
                                <div class="space-y-1 font-mono text-[11px]">
                                    <div class="flex gap-2"><span class="text-slate-500 w-8 italic">TXN:</span><span class="text-slate-200">{{ $item->transaction_number ?? '-' }}</span></div>
                                    <div class="flex gap-2"><span class="text-slate-500 w-8 italic">EXT:</span><span class="text-slate-200">{{ $item->external_order_no ?? '-' }}</span></div>
                                    <div class="flex gap-2"><span class="text-slate-500 w-8 italic">WBL:</span><span class="text-slate-200">{{ $item->waybill_no ?? '-' }}</span></div>
                                </div>
                            </td>
                            <td class="balanced-td align-middle border-l border-white/5">
                                <span class="text-white text-xl font-black uppercase italic block leading-tight">{{ $item->brand_name }}</span>
                                <span class="text-indigo-400 text-xs font-mono tracking-widest">{{ $item->brand_code }}</span>
                            </td>
                            <td class="balanced-td align-middle border-l border-white/5">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3">
                                        <span class="text-[8px] bg-slate-700 text-slate-400 px-1.5 py-0.5 rounded uppercase">Seller</span>
                                        <span class="text-slate-200 font-mono text-xs">{{ $item->seller_sku ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-[8px] bg-slate-700 text-slate-400 px-1.5 py-0.5 rounded uppercase">Fulfil</span>
                                        <span class="text-slate-200 font-mono text-xs">{{ $item->fulfillment_sku }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="balanced-td align-middle text-right border-l border-white/5">
                                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl {{ $item->is_disabled ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white' }} font-black shadow-lg">
                                    <span class="w-2 h-2 rounded-full bg-white {{ $item->is_disabled ? '' : 'animate-pulse' }}"></span>
                                    <span class="text-[10px] uppercase italic">
                                        {{ $item->is_disabled ? 'Inactive' : 'Verified' }}
                                    </span>
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" class="py-24 text-center">
                                <div class="text-slate-600 font-black uppercase tracking-[0.5em] italic">No Data Scan Results</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<script>
    // Reset exporting state after a few seconds because file download doesn't trigger page refresh
    window.addEventListener('blur', function() {
        setTimeout(() => {
            if (typeof Alpine !== 'undefined') {
                document.querySelector('[x-data]').__x.$data.exporting = false;
            }
        }, 3000);
    });
</script>
@endsection
