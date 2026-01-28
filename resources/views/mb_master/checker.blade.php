@extends('layouts.app')

@section('content')
<style>
    [x-cloak] { display: none !important; }

    /* Layout Wrapper */
    .header-card {
        background-color: #1e293b;
        border: 1px solid #334155;
        border-radius: 1rem;
        padding: 1rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
    }

    /* Input Grouping */
    .custom-group {
        display: flex;
        align-items: stretch;
        width: 100%;
        border: 2px solid #4f46e5;
        border-radius: 0.75rem;
        overflow: hidden;
        background: #ffffff;
    }

    .select-box {
        background-color: #0f172a;
        color: #ffffff;
        padding: 0 1rem;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        border: none;
        outline: none;
        min-width: 130px;
    }

    .input-box {
        flex-grow: 1;
        padding: 0.75rem 1rem;
        color: #0f172a;
        font-weight: 700;
        border: none;
        outline: none;
    }

    .btn-indigo {
        background-color: #4f46e5;
        color: white;
        padding: 0 1.5rem;
        font-weight: 900;
        font-size: 10px;
        text-transform: uppercase;
        transition: 0.2s;
    }
    .btn-indigo:hover { background-color: #4338ca; }

    .btn-green {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: white;
        padding: 0 1.5rem;
        font-weight: 900;
        font-size: 10px;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: 0.3s;
    }
    .btn-green:hover { transform: translateY(-1px); filter: brightness(1.1); }

    /* Table Styling */
    .dark-table { background-color: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 1rem; overflow: hidden; }
    .balanced-td { padding: 1.25rem 1.5rem !important; }
    .row-multi-brand { background-color: rgba(153, 27, 27, 0.4) !important; border-left: 8px solid #ef4444 !important; }
</style>

<div class="max-w-[1800px] mx-auto space-y-6" x-data="{ loading: false, exporting: false }">

    {{-- HEADER & SEARCH AREA --}}
    <div class="sticky top-0 z-50 pt-4 px-2">
        <div class="header-card">
            <div class="flex flex-col lg:flex-row items-center gap-4">

                {{-- Logo --}}
                <div class="shrink-0 flex items-center gap-3">
                    <div class="p-2 bg-indigo-600 rounded-lg shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    </div>
                    <h2 class="text-xl font-black text-white italic tracking-tighter">BRAND<span class="text-indigo-500">CHECKER</span></h2>
                </div>

                {{-- SEARCH FORM --}}
                <div class="flex-grow flex flex-col md:flex-row gap-3 w-full">
                    <form action="{{ route('mb-checker.verify') }}" method="GET" @submit="loading = true" class="flex-grow flex">
                        <div class="custom-group">
                            <select name="search_type" required class="select-box">
                                <option value="" disabled {{ !isset($searchType) ? 'selected' : '' }}>TYPE</option>
                                <option value="package_no" {{ ($searchType ?? '') == 'package_no' ? 'selected' : '' }}>PACKAGE NO</option>
                                <option value="waybill_no" {{ ($searchType ?? '') == 'waybill_no' ? 'selected' : '' }}>WAYBILL</option>
                                <option value="transaction_number" {{ ($searchType ?? '') == 'transaction_number' ? 'selected' : '' }}>TRANSACTION NO</option>
                                <option value="manufacture_barcode" {{ ($searchType ?? '') == 'manufacture_barcode' ? 'selected' : '' }}>MB</option>
                                <option value="external_order_no" {{ ($searchType ?? '') == 'external_order_no' ? 'selected' : '' }}>EXT ORDER NO</option>
                            </select>

                            <input type="text" name="search_query" value="{{ $searchQuery ?? '' }}" required class="input-box" placeholder="Scan/Type...">

                            <button type="submit" class="btn-indigo">
                                <span x-show="!loading">VERIFY</span>
                                <svg x-show="loading" class="animate-spin h-4 w-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </button>
                        </div>
                    </form>

                    {{-- EXPORT BUTTON (Diluar Form Verify agar tidak konflik) --}}
                    <form action="{{ route('mb-checker.export') }}" method="GET" @submit="exporting = true">
                        <button type="submit" :disabled="exporting" class="btn-green h-full py-3 md:py-0">
                            <svg x-show="!exporting" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 16v1a3 3 0 003 3h10a3 3 0 003 3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            <svg x-show="exporting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span x-text="exporting ? 'WAIT...' : 'EXPORT ZIP'"></span>
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    {{-- TABLE RESULTS --}}
    @if(isset($results))
    <div class="px-2">
        <div class="dark-table overflow-x-auto">
            <table class="w-full border-separate border-spacing-0">
                <thead>
                    <tr class="text-left bg-slate-900/80">
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase">Barcode</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase">References</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase">Brand</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase">Inventory</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-bold text-slate-200">
                    @forelse($results as $barcode => $brands)
                        @foreach($brands as $item)
                        <tr class="border-b border-slate-700 {{ $brands->count() > 1 ? 'row-multi-brand' : 'hover:bg-white/5' }}">
                            <td class="balanced-td">
                                <span class="text-indigo-400 font-mono text-lg block">{{ $barcode }}</span>
                                @if($brands->count() > 1)
                                    <span class="text-[9px] bg-red-600 text-white px-2 py-0.5 rounded mt-1 inline-block">MULTI BRAND</span>
                                @endif
                            </td>
                            <td class="balanced-td font-mono text-[10px] space-y-1">
                                <div><span class="text-slate-500 mr-2">TXN:</span>{{ $item->transaction_number }}</div>
                                <div><span class="text-slate-500 mr-2">EXT:</span>{{ $item->external_order_no }}</div>
                                <div><span class="text-slate-500 mr-2">WBL:</span>{{ $item->waybill_no }}</div>
                            </td>
                            <td class="balanced-td">
                                <div class="text-white text-lg font-black italic">{{ $item->brand_name }}</div>
                                <div class="text-indigo-400 text-xs">{{ $item->brand_code }}</div>
                            </td>
                            <td class="balanced-td text-[11px] space-y-1">
                                <div><span class="bg-slate-700 px-1 rounded text-slate-400 mr-2">SELLER</span>{{ $item->seller_sku }}</div>
                                <div><span class="bg-slate-700 px-1 rounded text-slate-400 mr-2">FULFIL</span>{{ $item->fulfillment_sku }}</div>
                            </td>
                            <td class="balanced-td text-right">
                                <span class="px-3 py-1 rounded-lg text-[10px] uppercase font-black {{ $item->is_disabled ? 'bg-red-600' : 'bg-emerald-600' }}">
                                    {{ $item->is_disabled ? 'Inactive' : 'Verified' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="py-20 text-center text-slate-500 font-black uppercase tracking-widest italic">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
