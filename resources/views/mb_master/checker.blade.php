@extends('layouts.app')

@section('content')
<style>
    [x-cloak] { display: none !important; }

    /* FIX: Pastikan Root Stacking Context bersih */
    .page-container {
        position: relative;
        isolation: isolate;
    }

    /* FIX: Header harus memiliki z-index tertinggi dan overflow terlihat */
    .sticky-header {
        position: sticky;
        top: 0;
        z-index: 1000 !important; /* Sangat tinggi untuk mengalahkan elemen tabel */
        padding-top: 1rem;
        padding-bottom: 0.5rem;
    }

    .header-card {
        background-color: #1e293b;
        border: 1px solid #334155;
        border-radius: 1rem;
        padding: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        position: relative;
        z-index: 1001;
        overflow: visible !important; /* FIX: Agar dropdown select tidak terpotong */
    }

    /* Input Grouping */
    .custom-group {
        display: flex;
        align-items: stretch;
        width: 100%;
        border: 2px solid #4f46e5;
        border-radius: 0.75rem;
        background: #ffffff;
        position: relative;
        overflow: visible !important; /* FIX: Penting untuk elemen input/select interior */
    }

    .select-box {
        background-color: #0f172a;
        color: #ffffff;
        padding: 0 1.5rem 0 1rem;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        border: none;
        outline: none;
        min-width: 150px;
        border-radius: 0.65rem 0 0 0.65rem;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.5rem center;
        background-size: 1rem;
        position: relative;
        z-index: 1002;
    }

    .input-box {
        flex-grow: 1;
        padding: 0.75rem 1rem;
        color: #0f172a;
        font-weight: 700;
        border: none;
        outline: none;
        background: white;
    }

    .btn-indigo {
        background-color: #4f46e5;
        color: white;
        padding: 0 1.5rem;
        font-weight: 900;
        font-size: 10px;
        text-transform: uppercase;
        transition: 0.2s;
        border-radius: 0 0.65rem 0.65rem 0;
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
        border: none;
    }
    .btn-green:hover { transform: translateY(-1px); filter: brightness(1.1); }

    /* Table Styling */
    .dark-table {
        background-color: #1e293b;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 1rem;
        overflow: hidden;
        position: relative;
        z-index: 1; /* Pastikan tabel berada di bawah header */
    }
    .balanced-td { padding: 1.25rem 1.5rem !important; }

    .row-multi-brand {
        background-color: rgba(153, 27, 27, 0.4) !important;
        border-left: 8px solid #ef4444 !important;
    }
</style>

<div class="max-w-[1800px] mx-auto space-y-6 page-container" x-data="{ loading: false, exporting: false }">

    {{-- HEADER & SEARCH AREA --}}
    <div class="sticky-header px-2">
        <div class="header-card">
            <div class="flex flex-col lg:flex-row items-center gap-4">

                {{-- Logo --}}
                <div class="shrink-0 flex items-center gap-3">
                    <div class="p-2 bg-indigo-600 rounded-lg shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-black text-white italic tracking-tighter uppercase">Brand<span class="text-indigo-500">Checker</span></h2>
                </div>

                {{-- SEARCH FORM --}}
                <div class="flex-grow flex flex-col md:flex-row gap-3 w-full">
                    <form action="{{ route('mb-checker.verify') }}" method="GET" @submit="loading = true" class="flex-grow flex">
                        <div class="custom-group">
                            <select name="search_type" required class="select-box">
                                <option value="" disabled {{ !isset($searchType) ? 'selected' : '' }}>SELECT TYPE</option>
                                <option value="package_no" {{ ($searchType ?? '') == 'package_no' ? 'selected' : '' }}>PACKAGE NO</option>
                                <option value="waybill_no" {{ ($searchType ?? '') == 'waybill_no' ? 'selected' : '' }}>WAYBILL</option>
                                <option value="transaction_number" {{ ($searchType ?? '') == 'transaction_number' ? 'selected' : '' }}>TRANSACTION NO</option>
                                <option value="manufacture_barcode" {{ ($searchType ?? '') == 'manufacture_barcode' ? 'selected' : '' }}>MB</option>
                                <option value="external_order_no" {{ ($searchType ?? '') == 'external_order_no' ? 'selected' : '' }}>EXT ORDER NO</option>
                            </select>

                            <input type="text" name="search_query" value="{{ $searchQuery ?? '' }}" required class="input-box" placeholder="Scan or Type Query...">

                            <button type="submit" class="btn-indigo">
                                <span x-show="!loading">VERIFY</span>
                                <svg x-show="loading" class="animate-spin h-4 w-4 mx-auto" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>

                    {{-- EXPORT BUTTON --}}
                    <form action="{{ route('mb-checker.export') }}" method="GET" @submit="exporting = true">
                        <button type="submit" :disabled="exporting" class="btn-green h-full min-h-[46px] py-2">
                            <svg x-show="!exporting" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 16v1a3 3 0 003 3h10a3 3 0 003 3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <svg x-show="exporting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="exporting ? 'WAIT...' : 'EXPORT ZIP'"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- TABLE RESULTS --}}
    @if(isset($results))
    <div class="px-2 pb-10">
        <div class="dark-table overflow-x-auto">
            <table class="w-full border-separate border-spacing-0">
                <thead>
                    <tr class="text-left bg-slate-900/80">
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-wider">Barcode</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-wider">References</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-wider">Brand Identity</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-wider">Inventory (SKU)</th>
                        <th class="balanced-td text-[11px] font-black text-slate-500 uppercase tracking-wider text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-bold text-slate-200">
                    @forelse($results as $barcode => $orders)
                        @php
                            $uniqueBrandCount = $orders->unique('brand_name')->count();
                        @endphp

                        @foreach($orders as $item)
                        <tr class="border-b border-slate-700 transition-colors {{ $uniqueBrandCount > 1 ? 'row-multi-brand' : 'hover:bg-white/5' }}">
                            <td class="balanced-td">
                                <span class="text-indigo-400 font-mono text-lg block leading-none">{{ $barcode }}</span>
                                @if($uniqueBrandCount > 1)
                                    <span class="text-[9px] bg-red-600 text-white px-2 py-0.5 rounded mt-2 inline-block animate-pulse">
                                        MULTI BRAND ({{ $uniqueBrandCount }})
                                    </span>
                                @endif
                            </td>
                            <td class="balanced-td font-mono text-[10px] space-y-1">
                                <div class="flex items-center"><span class="text-slate-500 w-12 italic">TXN:</span><span class="text-slate-300">{{ $item->transaction_number }}</span></div>
                                <div class="flex items-center"><span class="text-slate-500 w-12 italic">EXT:</span><span class="text-slate-300">{{ $item->external_order_no }}</span></div>
                                <div class="flex items-center"><span class="text-slate-500 w-12 italic">WBL:</span><span class="text-slate-300">{{ $item->waybill_no }}</span></div>
                            </td>
                            <td class="balanced-td">
                                <div class="text-white text-lg font-black italic tracking-tight">{{ $item->brand_name }}</div>
                                <div class="text-indigo-400 text-xs font-mono tracking-widest uppercase">{{ $item->brand_code }}</div>
                            </td>
                            <td class="balanced-td text-[11px] space-y-1">
                                <div class="flex items-center"><span class="bg-slate-700 px-1 rounded text-slate-400 mr-2 text-[9px] font-black">SELLER</span><span class="font-mono">{{ $item->seller_sku }}</span></div>
                                <div class="flex items-center"><span class="bg-slate-700 px-1 rounded text-slate-400 mr-2 text-[9px] font-black">FULFIL</span><span class="font-mono">{{ $item->fulfillment_sku }}</span></div>
                            </td>
                            <td class="balanced-td text-right">
                                <span class="px-3 py-1.5 rounded-lg text-[10px] uppercase font-black tracking-widest shadow-lg {{ $item->is_disabled ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white' }}">
                                    {{ $item->is_disabled ? 'Inactive' : 'Verified' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" class="py-24 text-center">
                                <div class="text-slate-600 font-black uppercase tracking-[0.5em] italic opacity-50">No Records Found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
