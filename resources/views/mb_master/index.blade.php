@extends('layouts.app')

@section('content')
<style>
    [x-cloak] { display: none !important; }
    @keyframes progress-shine {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    .animate-shine { animation: progress-shine 2s infinite; }
</style>

{{-- Satu root x-data untuk semua fungsi dashboard --}}
<div class="space-y-4" x-data="{
    openAdd: false,
    openUpload: false,
    openEdit: false,
    is_disabled: false,
    showProgress: false,
    percentage: 0,
    fileDetail: null,
    fileError: null,
    currentItem: { id: '', brand_code: '', brand_name: '', manufacture_barcode: '', fulfillment_sku: '', seller_sku: '', is_disabled: 0 },

    // 1. Handle File & Validation
    handleFile(e) {
        this.fileError = null;
        const file = e.target.files[0];
        if (!file) return;

        const extension = file.name.split('.').pop().toLowerCase();
        if (extension !== 'csv') {
            this.fileError = 'Invalid file type. Please select a .csv file.';
            this.fileDetail = null;
            e.target.value = '';
            return;
        }

        this.fileDetail = {
            name: file.name,
            size: (file.size / 1024).toFixed(2) + ' KB'
        };
    },

    // 2. Download Template CSV
    downloadTemplate() {
        const headers = ['brand_code', 'brand_name', 'manufacture_barcode', 'fulfillment_sku', 'seller_sku'];
        const exampleData = ['APL', 'Apple', '190198066511', 'IPH13-PRO-256', 'IPHONE-13-SELLER'];
        const content = [headers, exampleData].map(e => e.join(',')).join('\n');
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'template_master_brand.csv';
        link.click();
    },

    // 3. Real-time Progress Tracker
    checkImportStatus() {
        fetch('{{ url('mb-master/import-status') }}')
            .then(res => res.json())
            .then(data => {
                if(data && data.status === 'processing') {
                    this.showProgress = true;
                    this.percentage = Math.round((data.processed_rows / data.total_rows) * 100) || 0;
                    setTimeout(() => this.checkImportStatus(), 2000);
                } else if(data && data.status === 'completed') {
                    this.percentage = 100;
                    setTimeout(() => { this.showProgress = false; location.reload(); }, 1500);
                }
            });
    }
}" x-init="@if(session('importing')) checkImportStatus() @endif">

    {{-- 1. PROGRESS BAR AREA --}}
    <template x-if="showProgress">
        <div class="bg-gray-900 border border-emerald-500/30 p-5 rounded-[2rem] shadow-2xl mb-6 relative overflow-hidden">
            <div class="flex justify-between items-center mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></div>
                    <span class="text-[10px] font-black text-emerald-500 uppercase italic tracking-widest">Job Processor Active</span>
                </div>
                <span class="text-xs font-black text-white italic" x-text="percentage + '%'"></span>
            </div>
            <div class="w-full bg-gray-800 rounded-full h-3 overflow-hidden relative border border-gray-700">
                <div class="bg-emerald-500 h-full transition-all duration-700 ease-out relative" :style="`width: ${percentage}%` text-white">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-shine"></div>
                </div>
            </div>
        </div>
    </template>

    {{-- 2. COMPACT HEADER, ALL FILTERS & ACTIONS --}}
    <div class="bg-white dark:bg-gray-900 p-4 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
        <form action="{{ route('mb-master.index') }}" method="GET">
            <div class="flex flex-wrap lg:flex-nowrap items-end gap-2">

                <div class="w-full lg:w-48">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 mb-1 block tracking-tighter">Brand</label>
                    <input list="brand-options" name="brand" value="{{ request('brand') }}" placeholder="Search Brand..."
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border-none rounded-xl text-[10px] font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition">
                    <datalist id="brand-options">
                        @foreach($filterOptions['brands'] as $b)
                            <option value="{{ $b->brand_code }}">{{ $b->brand_name }}</option>
                        @endforeach
                    </datalist>
                </div>

                <div class="w-full lg:w-32">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 mb-1 block tracking-tighter">Barcode</label>
                    <input type="text" name="barcode" value="{{ request('barcode') }}" placeholder="BC..."
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border-none rounded-xl text-[10px] font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition">
                </div>

                <div class="w-full lg:w-32">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 mb-1 block tracking-tighter">F-SKU</label>
                    <input type="text" name="f_sku" value="{{ request('f_sku') }}" placeholder="Fulfillment"
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border-none rounded-xl text-[10px] font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition">
                </div>

                <div class="w-full lg:w-32">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 mb-1 block tracking-tighter">S-SKU</label>
                    <input type="text" name="s_sku" value="{{ request('s_sku') }}" placeholder="Seller"
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border-none rounded-xl text-[10px] font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition">
                </div>

                <div class="w-full lg:w-28">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 mb-1 block tracking-tighter">Status</label>
                    <select name="status" class="w-full px-2 py-2.5 bg-gray-50 dark:bg-gray-800 border-none rounded-xl text-[10px] font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition appearance-none cursor-pointer">
                        <option value="">All</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="disabled" {{ request('status') == 'disabled' ? 'selected' : '' }}>Disabled</option>
                    </select>
                </div>

                <div class="flex gap-1">
                    {{-- Button Submit Normal untuk Filter --}}
                    <button type="submit" class="bg-gray-900 dark:bg-indigo-600 text-white px-3 py-2.5 rounded-xl text-[9px] font-black uppercase transition hover:bg-indigo-700 active:scale-95">
                        Filter
                    </button>

                    {{-- Button Export (Menambah parameter 'export' ke URL) --}}
                    <a :href="'{{ route('mb-master.index') }}?export=1&' + new URLSearchParams(new FormData($el.closest('form'))).toString()"
                    class="bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 px-3 py-2.5 rounded-xl text-[9px] font-black uppercase transition hover:bg-emerald-600 hover:text-white active:scale-95 border border-emerald-200 dark:border-emerald-800/50 flex items-center">
                        Export CSV
                    </a>

                    <a href="{{ route('mb-master.index') }}" class="bg-gray-100 dark:bg-gray-800 text-gray-400 p-2.5 rounded-xl hover:text-red-500 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </a>
                </div>
                <div class="hidden lg:block w-px h-8 bg-gray-100 dark:bg-gray-800 mx-1"></div>

                <div class="flex gap-1 ml-auto">
                    <button type="button" @click="openUpload = true" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl text-[9px] font-black uppercase transition shadow-lg shadow-emerald-500/10 active:scale-95">
                        Import
                    </button>
                    <button type="button" @click="openAdd = true" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-[9px] font-black uppercase transition shadow-lg shadow-indigo-500/10 active:scale-95">
                        Add New
                    </button>
                </div>

            </div>
        </form>
    </div>

    {{-- 3. DATA TABLE --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden shadow-xl">
        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800/50 text-white">
                <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest italic">
                    <th class="px-6 py-4 text-left">Brand Detail</th>
                    <th class="px-6 py-4 text-left">Manufacture Barcode</th>
                    <th class="px-6 py-4 text-left">SKU Identification</th>
                    <th class="px-6 py-4 text-center w-32">Status</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-[11px]">
                @forelse($masters as $m)
                <tr class="hover:bg-indigo-50/30 dark:hover:bg-gray-800/40 transition">
                    <td class="px-6 py-4">
                        <div class="font-black text-gray-900 dark:text-white uppercase">{{ $m->brand_name }}</div>
                        <div class="text-[9px] text-indigo-600 font-bold uppercase italic tracking-tighter">{{ $m->brand_code }}</div>
                    </td>
                    <td class="px-6 py-4 font-mono font-bold text-gray-500 italic">
                        {{ $m->manufacture_barcode }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-col">
                            <span class="font-black text-gray-700 dark:text-gray-200">F-SKU: {{ $m->fulfillment_sku }}</span>
                            <span class="text-[9px] font-bold text-gray-400 italic text-white">S-SKU: {{ $m->seller_sku ?? '-' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <form action="{{ route('mb-master.update', $m->id) }}" method="POST">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_disabled" value="{{ $m->is_disabled ? 0 : 1 }}">
                            <button type="submit" class="flex items-center justify-center gap-2 px-3 py-1.5 mx-auto rounded-lg border font-black text-[8px] uppercase tracking-widest transition-all active:scale-90 {{ !$m->is_disabled ? 'bg-green-50 text-green-600 border-green-200' : 'bg-red-50 text-red-600 border-red-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ !$m->is_disabled ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                {{ !$m->is_disabled ? 'Active' : 'Disabled' }}
                            </button>
                        </form>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <button @click="currentItem = {{ $m->toJson() }}; is_disabled = {{ $m->is_disabled ? 'true' : 'false' }}; openEdit = true"
                                class="p-2 bg-amber-50 dark:bg-amber-900/20 text-amber-600 rounded-xl hover:bg-amber-600 hover:text-white transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>
                            <form action="{{ route('mb-master.destroy', $m->id) }}" method="POST" onsubmit="return confirm('Delete this record?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 bg-red-50 dark:bg-red-900/20 text-red-600 rounded-xl hover:bg-red-600 hover:text-white transition shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400 font-bold uppercase text-[10px]">-- No Master Data Found --</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 4. MODAL ADD NEW --}}
    <div x-show="openAdd" class="fixed inset-0 z-[110] overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" @click="openAdd = false"></div>
            <div class="relative bg-gray-900 rounded-[2.5rem] w-full max-w-md p-8 border border-gray-800 shadow-2xl text-left">
                <h3 class="text-xl font-black uppercase italic text-white tracking-tighter mb-6 italic">Add New <span class="text-indigo-500">Master</span></h3>
                <form action="{{ route('mb-master.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="brand_code" placeholder="Brand Code" required class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition">
                        <input type="text" name="brand_name" placeholder="Brand Name" required class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                    <input type="text" name="manufacture_barcode" placeholder="Manufacture Barcode" required class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="fulfillment_sku" placeholder="Fulfillment SKU" required class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition">
                        <input type="text" name="seller_sku" placeholder="Seller SKU" class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                    <input type="hidden" name="is_disabled" value="0">
                    <div class="flex gap-3 pt-4">
                        <button type="button" @click="openAdd = false" class="flex-1 text-[9px] font-black text-gray-600 uppercase">Cancel</button>
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-4 rounded-2xl text-[9px] font-black uppercase tracking-widest shadow-xl shadow-indigo-500/20 active:scale-95 transition-all">Save Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- 5. MODAL EDIT --}}
    <div x-show="openEdit" class="fixed inset-0 z-[110] overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" @click="openEdit = false"></div>
            <div class="relative bg-gray-900 rounded-[2.5rem] w-full max-w-md p-8 border border-gray-800 shadow-2xl text-left">
                <h3 class="text-xl font-black uppercase italic text-white tracking-tighter mb-6">Edit <span class="text-amber-500">Master</span></h3>
                <form :action="'{{ route('mb-master.index') }}/' + currentItem.id" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="brand_code" x-model="currentItem.brand_code" class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-amber-500">
                        <input type="text" name="brand_name" x-model="currentItem.brand_name" class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <input type="text" name="manufacture_barcode" x-model="currentItem.manufacture_barcode" class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-amber-900/50 text-white text-[11px] font-black outline-none focus:ring-2 focus:ring-amber-500">
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="fulfillment_sku" x-model="currentItem.fulfillment_sku" class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-amber-500">
                        <input type="text" name="seller_sku" x-model="currentItem.seller_sku" class="w-full px-4 py-3 rounded-2xl bg-gray-800 border border-gray-700 text-white text-[11px] font-bold outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div class="p-4 bg-gray-800/50 rounded-2xl border border-gray-700">
                        <input type="hidden" name="is_disabled" :value="is_disabled ? 1 : 0">
                        <div class="flex gap-2">
                            <button type="button" @click="is_disabled = false" class="flex-1 py-3 rounded-xl text-[9px] font-black uppercase" :class="!is_disabled ? 'bg-green-600 text-white shadow-lg' : 'bg-gray-800 text-gray-500'">Active</button>
                            <button type="button" @click="is_disabled = true" class="flex-1 py-3 rounded-xl text-[9px] font-black uppercase" :class="is_disabled ? 'bg-red-600 text-white shadow-lg' : 'bg-gray-800 text-gray-500'">Deactivate</button>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" @click="openEdit = false" class="flex-1 text-[9px] font-black text-gray-600 uppercase">Cancel</button>
                        <button type="submit" class="flex-1 bg-amber-600 text-white py-4 rounded-2xl text-[9px] font-black uppercase tracking-widest active:scale-95 shadow-xl shadow-amber-500/20 transition-all">Update Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- 6. MODAL UPLOAD --}}
    <div x-show="openUpload" class="fixed inset-0 z-[110] overflow-y-auto" x-cloak x-transition>
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" @click="openUpload = false; fileDetail = null; fileError = null"></div>

            <div class="relative bg-gray-900 rounded-[2.5rem] w-full max-w-md p-8 border border-gray-800 shadow-2xl text-left">
                <div class="flex justify-between items-start mb-6 text-white uppercase italic">
                    <div>
                        <h3 class="text-xl font-black italic uppercase tracking-tighter">Bulk <span class="text-emerald-500">Import</span></h3>
                        <p class="text-[9px] font-bold text-gray-500 uppercase mt-1 italic text-white">Ready for background job</p>
                    </div>
                    <button @click="downloadTemplate()" class="bg-gray-800 text-emerald-400 px-3 py-1.5 rounded-lg border border-gray-700 hover:bg-emerald-600 hover:text-white transition text-[9px] font-black uppercase shadow-lg shadow-emerald-500/10">Template</button>
                </div>

                <form action="{{ route('mb-master.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="border-2 border-dashed rounded-3xl p-8 text-center bg-gray-800/40 relative mb-6 transition-all duration-300"
                        :class="fileError ? 'border-red-500 bg-red-500/5' : (fileDetail ? 'border-emerald-500 bg-emerald-500/5' : 'border-gray-700 hover:border-gray-500')">

                        <input type="file" name="csv_file" required accept=".csv"
                            @change="handleFile"
                            class="absolute inset-0 w-full opacity-0 cursor-pointer z-20">

                        <div class="relative z-10">
                            <template x-if="!fileDetail && !fileError">
                                <div class="space-y-2">
                                    <div class="p-3 bg-gray-800 rounded-2xl inline-block text-gray-500 border border-gray-700 shadow-inner">
                                        <svg class="w-6 h-6 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    </div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic">Drop CSV file here</p>
                                    <p class="text-[8px] font-bold text-gray-600 uppercase tracking-tighter">Max: 5MB</p>
                                </div>
                            </template>

                            <template x-if="fileDetail">
                                <div class="flex items-center gap-4 text-left p-2 bg-gray-950/50 rounded-2xl border border-emerald-500/20 shadow-xl">
                                    <div class="p-3 bg-emerald-500 text-white rounded-xl shadow-lg shadow-emerald-500/20">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div>
                                    <div class="overflow-hidden">
                                        <p class="text-[10px] font-black text-white truncate" x-text="fileDetail.name"></p>
                                        <p class="text-[9px] font-bold text-emerald-500 uppercase italic tracking-tighter" x-text="fileDetail.size"></p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="fileError">
                                <div class="space-y-2">
                                    <div class="p-3 bg-red-500/20 text-red-500 rounded-2xl inline-block"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div>
                                    <p class="text-[9px] font-black text-red-500 uppercase italic tracking-tighter" x-text="fileError"></p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" @click="openUpload = false; fileDetail = null; fileError = null"
                                class="flex-1 py-4 text-[9px] font-black text-gray-600 uppercase tracking-widest">Discard</button>
                        <button type="submit"
                                class="flex-1 bg-emerald-600 text-white py-4 rounded-2xl text-[9px] font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 active:scale-95 transition-all disabled:opacity-30"
                                :disabled="!fileDetail">
                            Start background job
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
