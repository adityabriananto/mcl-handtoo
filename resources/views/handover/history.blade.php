@extends('layouts.app')

@section('title', 'Handover History')

@section('content')
<div class="space-y-8 p-4 sm:p-6 lg:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 dark:text-gray-100 border-b pb-3 mb-6 border-gray-200 dark:border-gray-700">
        📊 Handover History Dashboard
    </h1>

    {{-- Notifikasi Flash --}}
    @if (session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg dark:bg-green-900 dark:border-green-400 dark:text-green-200" role="alert">
            <p class="font-bold">Success!</p>
            <p>{!! session('success') !!}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg dark:bg-red-900 dark:border-red-400 dark:text-red-200" role="alert">
            <p class="font-bold">Error!</p>
            <p>{!! session('error') !!}</p>
        </div>
    @endif

    {{-- STATISTIK DASHBOARD ANGKA --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        {{-- Card 1: Total Batches (GRAY/DEFAULT) --}}
        <div class="stat-card bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
            <p class="stat-label text-gray-700 dark:text-white">Total Batches Created</p>
            <p class="stat-value text-gray-800 dark:text-white">{{ $globalStats['total_batches'] }}</p>
        </div>

        {{-- Card 2: Batches in Staging (YELLOW) --}}
        <div class="stat-card bg-yellow-100 dark:bg-yellow-900 border-yellow-400 dark:border-yellow-700">
            <p class="stat-label text-yellow-800 dark:text-yellow-300">Currently Staging</p>
            <p class="stat-value text-yellow-800 dark:text-yellow-100">{{ $globalStats['staging_batches'] }}</p>
        </div>

        {{-- Card 3: Completed Batches (BLUE) --}}
        <div class="stat-card bg-blue-100 dark:bg-blue-900 border-blue-400 dark:border-blue-700">
            <p class="stat-label text-blue-800 dark:text-blue-300">Total Completed</p>
            <p class="stat-value text-blue-800 dark:text-blue-100">{{ $globalStats['completed_batches'] }}</p>
        </div>

        {{-- Card 4: Manifest Signed (GREEN) --}}
        <div class="stat-card bg-green-100 dark:bg-green-900 border-green-400 dark:border-green-700">
            <p class="stat-label text-green-800 dark:text-green-300">Manifest Signed</p>
            <p class="stat-value text-green-800 dark:text-green-100">{{ $globalStats['manifest_signed'] }}</p>
        </div>

        {{-- Card 5: Manifest Pending (RED) --}}
        <div class="stat-card bg-red-100 dark:bg-red-900 border-red-400 dark:border-red-700">
            <p class="stat-label text-red-800 dark:text-red-300">Manifest Pending</p>
            <p class="stat-value text-red-800 dark:text-red-100">{{ $globalStats['manifest_pending'] }}</p>
        </div>
    </div>

    {{-- 1. Filter Section --}}
    <div class="bg-white dark:bg-gray-800 shadow-2xl rounded-xl">
        <div class="p-5 bg-gray-50 dark:bg-gray-700 rounded-t-xl border-b border-gray-200 dark:border-gray-600">
            <h4 class="text-xl font-bold text-gray-800 dark:text-gray-200">Filter History</h4>
        </div>
        <div class="p-6">
            <form action="{{ route('history.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">

                    {{-- Handover ID --}}
                    <div>
                        <label for="handover_id" class="label-text">Handover ID</label>
                        <input type="text" class="input-field" name="handover_id" placeholder="HO-..." value="{{ request('handover_id') }}">
                    </div>

                    {{-- AWB Number (BARU) --}}
                    <div>
                        <label for="airwaybill" class="label-text">AWB Number</label>
                        <input type="text" class="input-field" name="airwaybill" placeholder="AWB12345..." value="{{ request('airwaybill') }}">
                    </div>

                    {{-- Carrier --}}
                    <div>
                        <label for="three_pl" class="label-text">Carrier</label>
                        <select class="input-field" name="three_pl">
                            <option value="">All Carriers</option>
                            @foreach($allCarriers as $carrier)
                                <option value="{{ $carrier }}" {{ request('three_pl') == $carrier ? 'selected' : '' }}>{{ $carrier }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Start Date --}}
                    <div>
                        <label for="date_start" class="label-text">Finalized After</label>
                        <input type="date" class="input-field" name="date_start" value="{{ request('date_start') }}">
                    </div>

                    {{-- End Date --}}
                    <div>
                        <label for="date_end" class="label-text">Finalized Before</label>
                        <input type="date" class="input-field" name="date_end" value="{{ request('date_end') }}">
                    </div>

                    {{-- Button --}}
                    <div class="pt-1 flex space-x-2">
                        <button type="submit" class="w-full btn-primary bg-blue-600 hover:bg-blue-700">
                            Apply Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 2. Summary & Export --}}
    <div class="flex justify-between items-center py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
            Committed Batches: <span class="text-blue-600 dark:text-blue-400">{{ $groupedHistory->count() }}</span> Found
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
                // Menggunakan manifest_filename dari objek $batchData['batch']
                $isSigned = $batchData['batch']->manifest_filename !== null;
                $signedFileName = $batchData['batch']->manifest_filename ?? 'Pending';
                $statusClass = $isSigned
                    ? 'bg-green-500 text-white font-bold'
                    : 'bg-yellow-500 text-gray-800 font-bold';
            @endphp

            <details class="group bg-white dark:bg-gray-800 shadow-2xl rounded-xl overflow-hidden transition duration-300 ease-in-out hover:shadow-blue-500/30">

                {{-- Summary Header --}}
                <summary class="flex justify-between items-center p-5 font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                    <span class="text-xl flex items-center space-x-3">
                        <span class="text-blue-600 dark:text-blue-400">#{{ $handoverId }}</span>
                        <span class="text-gray-500 dark:text-gray-400">|</span>
                        <span>Carrier: **{{ $batchData['threePlName'] }}** ({{ $batchData['awbs']->count() }} AWBs)</span>
                    </span>
                    <span class="px-4 py-1 text-sm rounded-full {{ $statusClass }} flex-shrink-0 ml-4">
                        {!! $isSigned ? 'SIGNED' : 'PENDING' !!}
                    </span>
                    <svg class="h-6 w-6 transform group-open:rotate-180 transition duration-200 text-gray-500 dark:text-gray-400 ml-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </summary>

                {{-- Detail Content --}}
                <div class="p-6 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

                        {{-- Manifest Actions --}}
                        <div class="lg:col-span-2 space-y-3 p-4 border rounded-lg dark:border-gray-700">
                            <h6 class="text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center space-x-2">
                                📑 Manifest Document
                            </h6>

                            {{-- Download Manifest (PDF) --}}
                            <a href="{{ route('history.download-manifest', $handoverId) }}" class="btn-secondary bg-blue-500 hover:bg-blue-600 w-full md:w-auto">
                                Download Manifest (.PDF)
                            </a>

                            @if (!$isSigned)
                                {{-- Upload Form --}}
                                <form action="{{ route('history.upload-manifest', $handoverId) }}" method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row items-start md:items-center space-y-2 md:space-y-0 md:space-x-3 pt-3 border-t border-gray-200 dark:border-gray-700 mt-3">
                                    @csrf
                                    <input type="file" name="signed_file" class="w-full md:w-auto text-sm file-input" required>
                                    <button type="submit" class="btn-primary bg-green-600 hover:bg-green-700 flex-shrink-0 w-full md:w-auto">
                                        Upload Signed Proof
                                    </button>
                                </form>
                            @else
                                {{-- Success Upload Status --}}
                                <div class="bg-green-50 text-green-700 p-3 rounded-md text-sm dark:bg-green-900 dark:text-green-200">
                                    ✅ Proof uploaded: <a href="{{ Storage::url('manifests/' . $signedFileName) }}" target="_blank" class="underline hover:text-green-900 dark:hover:text-green-100 font-medium">{{ $signedFileName }}</a>
                                </div>
                            @endif
                        </div>

                        {{-- Summary Info --}}
                        <div class="lg:col-span-1 space-y-2 p-4 bg-gray-50 dark:bg-gray-850 rounded-lg">
                            <p class="text-sm dark:text-gray-400">**Finalized At:** <br><span class="font-mono text-gray-700 dark:text-gray-300">{{ $batchData['latestTs'] ? $batchData['latestTs']->format('Y-m-d H:i:s') : 'N/A' }}</span></p>
                            <p class="text-sm dark:text-gray-400">**Processed By:** <br><span class="text-gray-700 dark:text-gray-300">User ID {{ $batchData['batch']->user_id ?? 'N/A' }}</span></p>
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
</div>

{{-- Style Helper for better readability --}}
<style>
    .label-text {
        @apply block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1;
    }
    .input-field {
        @apply mt-1 block w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm p-2 text-base;
    }
    .btn-primary {
        @apply text-white py-2 px-4 rounded-md shadow-md transition duration-150 font-semibold;
    }
    .btn-secondary {
        @apply py-2 px-4 rounded-md shadow-md transition duration-150 font-semibold text-white;
    }
    .table-th {
        @apply px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider;
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
