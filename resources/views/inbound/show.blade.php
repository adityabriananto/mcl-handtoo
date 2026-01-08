@extends('layouts.app')

@section('title', 'Detail Inbound - ' . $inbound->reference_number)

@section('content')
<div class="space-y-6">
    {{-- 1. Breadcrumbs & Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <nav class="flex text-gray-500 text-xs mb-2 uppercase font-bold tracking-widest">
                <a href="{{ route('inbound.index') }}" class="hover:text-blue-600">Inbound Requests</a>
                <span class="mx-2">/</span>
                <span class="text-gray-800 dark:text-gray-200">Detail</span>
            </nav>
            <h1 class="text-3xl font-black text-gray-900 dark:text-white flex items-center gap-3">
                {{ $inbound->reference_number }}
                @if($inbound->parent_id)
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded border border-blue-200 uppercase font-black">Sub-IO</span>
                @elseif($inbound->children->count() > 0)
                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded border border-purple-200 uppercase font-black">Main IO</span>
                @endif
            </h1>
        </div>

        <div class="flex items-center gap-2">
            {{-- Tombol Split jika masih ada SKU > 200 (Safety check) --}}
            @if($inbound->details->sum('requested_quantity') > 200)
                <form action="{{ route('inbound.split', $inbound->id) }}" method="POST">
                    @csrf
                    <button class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition">
                        Split This Document
                    </button>
                </form>
            @endif
            <a href="{{ route('inbound.index') }}" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-200 transition">
                Back to List
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- 2. Detail Information Card --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                    <h2 class="font-bold text-gray-800 dark:text-white uppercase tracking-tighter">Information Details</h2>
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-widest bg-blue-50 text-blue-700">
                        {{ $inbound->status }}
                    </span>
                </div>
                <div class="p-6 grid grid-cols-2 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Warehouse</p>
                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ $inbound->warehouse_code }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Delivery Type</p>
                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ $inbound->delivery_type }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Estimate Time</p>
                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ $inbound->estimate_time ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- 3. SKU Items Table --}}
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="font-bold text-gray-800 dark:text-white uppercase tracking-tighter">SKU Items (Total: {{ number_format($inbound->details->sum('requested_quantity')) }})</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-[10px] font-black text-gray-400 uppercase">
                            <tr>
                                <th class="px-6 py-4">Seller SKU</th>
                                <th class="px-6 py-4">Fulfillment SKU</th>
                                <th class="px-6 py-4 text-right">Quantity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($inbound->details as $detail)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-6 py-4 font-bold text-sm text-blue-600">{{ $detail->seller_sku }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $detail->fulfillment_sku }}</td>
                                <td class="px-6 py-4 text-right font-black text-gray-800 dark:text-white">
                                    {{ number_format($detail->requested_quantity) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 4. Sidebar: Relation & Timeline --}}
        <div class="space-y-6">
            {{-- Hierarchy Card --}}
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 p-6">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Document Hierarchy</h3>

                @if($inbound->parent_id)
                    <div class="mb-4">
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">Main Document (Parent)</p>
                        <a href="{{ route('inbound.show', $inbound->parent_id) }}" class="flex items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-800 group transition">
                            <div class="flex-1">
                                <p class="text-sm font-bold text-blue-700 dark:text-blue-400 group-hover:underline">{{ $inbound->parent->reference_number }}</p>
                                <p class="text-[10px] text-blue-600 opacity-70 italic">Click to view source</p>
                            </div>
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                @endif

                @if($inbound->children->count() > 0)
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">Related Splits (Children)</p>
                        <div class="space-y-2">
                            @foreach($inbound->children as $child)
                            <a href="{{ route('inbound.show', $child->id) }}" class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 hover:border-blue-300 transition group">
                                <span class="text-gray-300 mr-2">└─</span>
                                <div class="flex-1">
                                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover:text-blue-600">{{ $child->reference_number }}</p>
                                    <p class="text-[9px] text-gray-400">{{ number_format($child->details->sum('requested_quantity')) }} Units</p>
                                </div>
                                <span class="text-[10px] font-bold text-gray-400">{{ $child->status }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!$inbound->parent_id && $inbound->children->count() == 0)
                    <p class="text-xs italic text-gray-400">This document has no related split records.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
