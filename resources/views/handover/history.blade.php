@extends('layouts.app')

@section('title', 'Handover History')

@section('content')
<div class="space-y-8 p-4 sm:p-6 lg:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 dark:text-gray-100 border-b pb-3 mb-6 border-gray-200 dark:border-gray-700">
        📊 Handover History Dashboard
    </h1>

    {{-- Notifikasi Flash --}}
    @if (session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg dark:bg-green-900 dark:border-green-400 dark:text-green-200 mb-4" role="alert"><p>{!! session('success') !!}</p></div>
    @endif
    @if (session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg dark:bg-red-900 dark:border-red-400 dark:text-red-200 mb-4" role="alert"><p>{!! session('error') !!}</p></div>
    @endif
    @if ($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg dark:bg-red-900 dark:border-red-400 dark:text-red-200 mb-4" role="alert"><p>Validation Error: {{ $errors->first() }}</p></div>
    @endif

    {{-- STATISTIK DASHBOARD ANGKA --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">

        {{-- Card 1: Total Batches (GRAY/DEFAULT) --}}
        <div class="stat-card bg-gray-50 dark:bg-gray-800 border-gray-300 dark:border-gray-700">
            <p class="stat-label text-gray-700 dark:text-gray-300">Total Batches Created</p>
            <p class="stat-value text-gray-800 dark:text-gray-100">{{ $globalStats['total_batches'] }}</p>
        </div>

        {{-- Card 2: Batches in Staging (DEFAULT) --}}
        <div class="stat-card bg-gray-50 dark:bg-gray-800 border-gray-300 dark:border-gray-700">
            <p class="stat-label text-gray-700 dark:text-gray-300">Currently Staging</p>
            <p class="stat-value text-yellow-600 dark:text-yellow-400">{{ $globalStats['staging_batches'] }}</p>
        </div>

        {{-- Card 3: Completed Batches (DEFAULT) --}}
        <div class="stat-card bg-gray-50 dark:bg-gray-800 border-gray-300 dark:border-gray-700">
            <p class="stat-label text-gray-700 dark:text-gray-300">Total Completed</p>
            <p class="stat-value text-blue-600 dark:text-blue-400">{{ $globalStats['completed_batches'] }}</p>
        </div>

        {{-- Card 4: Manifest Signed (DEFAULT) --}}
        <div class="stat-card bg-gray-50 dark:bg-gray-800 border-gray-300 dark:border-gray-700">
            <p class="stat-label text-gray-700 dark:text-gray-300">Manifest Signed</p>
            <p class="stat-value text-green-600 dark:text-green-400">{{ $globalStats['manifest_signed'] }}</p>
        </div>

        {{-- Card 5: Manifest Pending (DEFAULT) --}}
        <div class="stat-card bg-gray-50 dark:bg-gray-800 border-gray-300 dark:border-gray-700">
            <p class="stat-label text-gray-700 dark:text-gray-300">Manifest Pending</p>
            <p class="stat-value text-red-600 dark:text-red-400">{{ $globalStats['manifest_pending'] }}</p>
        </div>
    </div>

    {{-- 1. Filter Section --}}
    <div class="bg-white dark:bg-gray-900 shadow-2xl rounded-xl">
        <div class="p-5 bg-gray-50 dark:bg-gray-800 rounded-t-xl border-b border-gray-200 dark:border-gray-700">
            <h4 class="text-xl font-bold text-gray-800 dark:text-gray-200">Filter History</h4>
        </div>
        <div class="p-6">
            <form action="{{ route('history.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">

                    {{-- Handover ID --}}
                    <div class="md:col-span-2 lg:col-span-1">
                        <label for="handover_id" class="label-text">Handover ID</label>
                        {{-- Menggunakan input-field yang sudah dimodifikasi dark mode-nya --}}
                        <input type="text" class="input-field dark-input-field" name="handover_id" placeholder="HO-..." value="{{ request('handover_id') }}">
                    </div>

                    {{-- AWB Number --}}
                    <div class="md:col-span-2 lg:col-span-1">
                        <label for="airwaybill" class="label-text">AWB Number</label>
                        <input type="text" class="input-field dark-input-field" name="airwaybill" placeholder="AWB12345..." value="{{ request('airwaybill') }}">
                    </div>

                    {{-- Carrier --}}
                    <div class="md:col-span-2 lg:col-span-1">
                        <label for="three_pl" class="label-text">Carrier</label>
                        {{-- Field ini ASUMSI menerima daftar Carrier dari TplConfig --}}
                        <select class="input-field" name="three_pl">
                            <option value="">All Carriers</option>
                            {{-- PERBAIKAN: Menggunakan ?? [] untuk mencegah NULL --}}
                            @foreach($allCarriers ?? [] as $carrier)
                                <option value="{{ $carrier }}" {{ request('three_pl') == $carrier ? 'selected' : '' }}>{{ $carrier }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- NEW: Status Filter --}}
                    <div class="md:col-span-2 lg:col-span-1">
                        <label for="status" class="label-text">Status</label>
                        <select class="input-field dark-input-field" name="status">
                            <option value="">All Statuses</option>
                            {{-- Staging (status='staging') --}}
                            <option value="staging" {{ request('status') == 'staging' ? 'selected' : '' }}>
                                Staging
                            </option>
                            {{-- Pending Handover (status='completed' & manifest_name_signed IS NULL) --}}
                            <option value="pending_handover" {{ request('status') == 'pending_handover' ? 'selected' : '' }}>
                                Pending Handover
                            </option>
                            {{-- Completed (status='completed' & manifest_name_signed IS NOT NULL) --}}
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>
                                Completed
                            </option>
                        </select>
                    </div>

                    {{-- Start Date --}}
                    <div class="md:col-span-2 lg:col-span-1">
                        <label for="date_start" class="label-text">Created After</label>
                        <input type="date" class="input-field dark-input-field" name="date_start" value="{{ request('date_start') }}">
                    </div>

                    {{-- End Date --}}
                    <div class="md:col-span-2 lg:col-span-1">
                        <label for="date_end" class="label-text">Created Before</label>
                        <input type="date" class="input-field dark-input-field" name="date_end" value="{{ request('date_end') }}">
                    </div>
                </div>

                {{-- Button Group: Apply Filter & Clear Filter (Span 6 columns) --}}
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 pt-4">
                    <div class="md:col-span-6 flex space-x-3">
                        {{-- APPLY BUTTON --}}
                        <button type="submit" class="w-full btn-primary bg-blue-600 hover:bg-blue-700 flex-1">
                            Apply Filter
                        </button>

                        {{-- CLEAR FILTER BUTTON --}}
                        <a href="{{ route('history.index') }}" class="w-full btn-secondary bg-red-500 hover:bg-red-600 flex-1 text-center py-2 px-4 rounded-md shadow-md transition duration-150 font-semibold">
                            Clear Filter
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 2. Summary & Export --}}
    <div class="flex justify-between items-center py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
            Committed Batches: <span class="text-blue-600 dark:text-blue-400">{{ $historyPaginator->total() }}</span> Found
            <span class="text-sm text-gray-500 ml-2">(Page {{ $historyPaginator->currentPage() }} of {{ $historyPaginator->lastPage() }})</span>
        </h3>

        {{-- Export Button: Mempertahankan semua parameter query filter, termasuk AWB --}}
        <a href="{{ route('history.export-csv', array_merge(request()->query(), ['airwaybill' => request('airwaybill')])) }}" class="btn-secondary bg-gray-500 hover:bg-gray-600">
            Export All Data (.csv)
        </a>
    </div>

    {{-- 3. Daftar Batch Terkelompok (Accordion) --}}
    @if ($groupedHistory->isEmpty())
        <div class="p-6 text-center bg-yellow-50 dark:bg-yellow-950 text-yellow-800 dark:text-yellow-200 border-2 border-yellow-300 dark:border-yellow-700 rounded-lg">
            <p class="font-medium">⚠️ No batches matched the current filter criteria.</p>
        </div>
    @endif

    <div class="space-y-4" id="historyAccordion">
        @foreach ($groupedHistory as $handoverId => $batchData)
            @php
                // Tentukan status berdasarkan kolom 'status' dari HandoverBatch
                $batchStatus = $batchData['batch']->status;
                $isSigned = $batchData['batch']->manifest_name_signed !== null;
                $signedFileName = $batchData['batch']->manifest_name_signed ?? 'Pending';

                // Logika Penentuan Status Tampilan
                if ($batchStatus === 'completed' && $isSigned) {
                    $displayStatus = 'COMPLETED';
                    $statusClass = 'bg-green-500 text-white font-bold';
                } elseif ($batchStatus === 'completed' && !$isSigned) {
                    $displayStatus = 'PENDING HANDOVER';
                    $statusClass = 'bg-yellow-500 text-gray-800 font-bold';
                } else {
                    // Default/Status lainnya (misalnya 'staging')
                    $displayStatus = strtoupper($batchStatus);
                    $statusClass = 'bg-gray-500 text-white font-bold';
                }

                // Logika Status Signed/Unsigned untuk Badge Tambahan
                if ($isSigned) {
                    $signedLabel = 'SIGNED';
                    $signedLabelClass = 'bg-blue-600 text-white';
                } else {
                    $signedLabel = 'UNSIGNED';
                    $signedLabelClass = 'bg-red-500 text-white';
                }
            @endphp

            <details class="group bg-white dark:bg-gray-900 shadow-2xl rounded-xl overflow-hidden transition duration-300 ease-in-out hover:shadow-blue-500/30">

                {{-- Summary Header --}}
                <summary class="flex justify-between items-center p-5 font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition duration-150">
                    <span class="text-xl flex items-center space-x-3">
                        <span class="text-blue-600 dark:text-blue-400">#{{ $handoverId }}</span>
                        <span class="text-gray-500 dark:text-gray-400">|</span>
                        <span>3PL: {{ $batchData['threePlName'] }} ({{ $batchData['awbs']->count() }} AWBs)</span>

                        {{-- Manifest Signed Badge --}}
                        <span class="px-3 py-0.5 text-xs rounded-full {{ $signedLabelClass }} font-semibold ml-4">
                            {{ $signedLabel }}
                        </span>
                    </span>
                    <span class="px-4 py-1 text-sm rounded-full {{ $statusClass }} flex-shrink-0 ml-4">
                        {!! $displayStatus !!}
                    </span>
                    @if ($batchStatus === 'staging')
                        <a href="{{ route('handover.resume', $handoverId) }}" class="ml-4 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow transition duration-150 transform hover:scale-105">
                            ✏️ RESUME SCAN
                        </a>
                    @endif
                    <span>
                        Created : {{$batchData['createdTs'] }}
                    </span>
                    <svg class="h-6 w-6 transform group-open:rotate-180 transition duration-200 text-gray-500 dark:text-gray-400 ml-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </summary>

                {{-- Detail Content --}}
                <div class="p-6 bg-white dark:bg-gray-950 border-t border-gray-100 dark:border-gray-800">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

                        {{-- Manifest Actions --}}
                        <div class="lg:col-span-2 space-y-3 p-4 border rounded-lg dark:border-gray-700">
                            <h6 class="text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center space-x-2">
                                📑 Manifest Document
                            </h6>

                            {{-- Download Manifest (PDF) - Menggunakan manifest_filename yang generated --}}
                            <a href="{{ route('history.download-manifest', $handoverId) }}" class="btn-secondary bg-blue-500 hover:bg-blue-600 w-full md:w-auto">
                                Download Manifest (.PDF)
                            </a>

                            @php
                                $proofFiles = $batchData['batch']->proofFiles ?? collect();
                            @endphp

                            @php
                                $uploadKey = 'upload_' . $handoverId;
                            @endphp

                            {{-- File Upload dengan Dynamic Fields --}}
                            <div x-data="{ files: [{ id: 1, selected: false, name: '' }] }" class="pt-3 border-t border-gray-200 dark:border-gray-700 mt-3">

                                @if ($proofFiles->isNotEmpty())
                                    <p class="text-sm font-semibold text-green-700 dark:text-green-300 mb-2">
                                        ✅ {{ $proofFiles->count() }} Proof file(s) uploaded:
                                    </p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3">
                                        @foreach($proofFiles as $proof)
                                            <div class="flex items-center gap-2 p-2 bg-green-50 dark:bg-green-900/30 rounded-lg text-sm text-green-700 dark:text-green-200 hover:bg-green-100 dark:hover:bg-green-900/50 transition group">
                                                <a href="{{ Storage::url($proof->path) }}" target="_blank"
                                                   class="flex items-center gap-2 flex-1 min-w-0">
                                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                    </svg>
                                                    <span class="truncate">{{ $proof->original_name }}</span>
                                                </a>
                                                <a href="{{ route('history.download-proof', $proof->id) }}"
                                                   class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 p-1 rounded hover:bg-blue-100 dark:hover:bg-blue-900/50 transition"
                                                   title="Download">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <form action="{{ route('history.upload-manifest', $handoverId) }}" method="POST" enctype="multipart/form-data" class="space-y-2">
                                    @csrf

                                    <template x-for="(file, index) in files" :key="file.id">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 relative">
                                                <input type="file"
                                                       name="signed_files[]"
                                                       x-on:change="file.selected = true; file.name = $event.target.files[0] ? $event.target.files[0].name : ''"
                                                       class="w-full text-sm file-input"
                                                       accept=".jpg,.jpeg,.png,.pdf"
                                                       :required="index === 0 && {{ $proofFiles->isEmpty() ? 'true' : 'false' }}">
                                                <p x-show="file.name" x-text="file.name" class="text-xs text-green-600 mt-0.5 truncate"></p>
                                            </div>
                                            <button type="button"
                                                    x-on:click="files.splice(index, 1)"
                                                    x-show="files.length > 1"
                                                    class="text-red-500 hover:text-red-700 text-sm px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/30 transition"
                                                    title="Remove">
                                                ✕
                                            </button>
                                        </div>
                                    </template>

                                    <div class="flex items-center gap-3 pt-1">
                                        <button type="button"
                                                x-on:click="files.push({ id: Date.now(), selected: false, name: '' })"
                                                class="text-sm text-blue-600 hover:text-blue-800 font-medium px-3 py-1.5 border border-blue-300 rounded-lg hover:bg-blue-50 dark:border-blue-700 dark:text-blue-400 dark:hover:bg-blue-900/30 transition">
                                            + Tambah File
                                        </button>
                                        <button type="submit"
                                                x-show="files.some(f => f.selected)"
                                                x-cloak
                                                class="btn-primary bg-green-600 hover:bg-green-700 text-sm px-4 py-1.5">
                                            Upload Files
                                        </button>
                                    </div>
                                </form>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Allowed: .jpg, .jpeg, .png, .pdf (max 5MB each)</p>
                            </div>
                        </div>

                        {{-- Summary Info --}}
                        <div class="lg:col-span-1 space-y-2 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <p class="text-sm dark:text-gray-400">**Finalized At:** <br><span class="font-mono text-gray-700 dark:text-gray-300">{{ $batchData['latestTs'] ? $batchData['latestTs']->format('Y-m-d H:i:s') : 'N/A' }}</span></p>
                            <p class="text-sm dark:text-gray-400">**Signed At:** <br><span class="font-mono text-gray-700 dark:text-gray-300">{{ $batchData['signedTs'] ? $batchData['signedTs'] : 'N/A' }}</span></p>
                        </div>
                    </div>

                    {{-- Detail AWB Table --}}
                    <h6 class="text-xl font-bold text-gray-800 dark:text-gray-200 mt-6 mb-3 border-b pb-2">AWB Details</h6>
                    <div class="overflow-x-auto rounded-lg border dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="table-th w-1/12">No.</th>
                                    <th class="table-th w-5/12">AWB Number</th>
                                    <th class="table-th w-6/12">Handover Timestamp</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($batchData['awbs'] as $awb)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="table-td">{{ $loop->iteration }}</td>
                                    <td class="table-td font-medium text-blue-700 dark:text-blue-400">{{ $awb->airwaybill }}</td>
                                    <td class="table-td text-gray-600 dark:text-gray-400">{{ $awb->scanned_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        @endforeach
    </div>

    {{-- Pagination Links --}}
    @if ($historyPaginator->hasPages())
        <div class="mt-6">
            {{ $historyPaginator->links() }}
        </div>
    @endif
</div>

{{-- Style Helper for better readability and consistent Dark Mode input --}}
<style>
    .label-text {
        @apply block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1;
    }
    .input-field {
        /* Base styling */
        @apply mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-base;
    }
    .dark-input-field {
        /* Dark mode overrides for consistency with TplConfig dashboard */
        @apply dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:ring-blue-500 focus:border-blue-500;
    }
    .btn-primary {
        @apply text-white py-2 px-4 rounded-md shadow-md transition duration-150 font-semibold;
    }
    .btn-secondary {
        @apply py-2 px-4 rounded-md shadow-md transition duration-150 font-semibold text-white;
    }
    .table-th {
        /* Adjusted for better dark mode contrast */
        @apply px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider bg-gray-700 dark:bg-gray-700;
    }
    .table-td {
        @apply px-6 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300;
    }
    .file-input {
        @apply block w-full text-sm text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-800 p-2;
    }
    .stat-card {
        @apply p-4 rounded-xl border-2 shadow-lg transition duration-300;
    }
    .stat-label {
        @apply text-sm font-semibold mb-1;
    }
    .stat-value {
        @apply text-3xl font-extrabold;
    }
</style>
@endsection
