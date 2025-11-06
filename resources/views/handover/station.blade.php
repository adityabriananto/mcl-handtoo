@extends('layouts.app')

@section('title', 'Handover Station')

@section('content')
<div class="space-y-6">
    <h1 class="text-3xl font-extrabold text-gray-800 dark:text-gray-100 mb-6">📦 Handover Station</h1>

    {{-- Notifikasi (Tambahkan di sini juga jika diperlukan, atau di layout.app) --}}
    @if (session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg" role="alert"><p>{!! session('success') !!}</p></div>
    @endif
    @if ($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert"><p>Validation Error: {{ $errors->first() }}</p></div>
    @endif
    @if (session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert"><p>{!! session('error') !!}</p></div>
    @endif

    {{-- 1. Setup Batch & 3PL --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-xl overflow-hidden">
        <div class="p-4 bg-blue-600 text-white dark:bg-blue-700">
            <h4 class="text-xl font-semibold">1. Setup Batch & Carrier Selection</h4>
        </div>
        <div class="p-6">
            <form id="setup-form" action="{{ route('handover.set-batch') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-6 gap-6 items-end">

                    {{-- Input Handover ID --}}
                    <div class="md:col-span-3">
                        <label for="handover_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batch Handover Document ID</label>
                        <input type="text"
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-lg"
                               id="handover_id" name="handover_id"
                               placeholder="Scan Batch ID (misal: HO-20251106-001)" required

                               @if (session('batch_status') == 'staged')
                                   value="{{ session('current_batch_id') }}"
                                   disabled
                               @endif
                        >
                    </div>

                    {{-- Select Carrier --}}
                    <div class="md:col-span-2">
                        <label for="three_pl" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select 3PL Carrier</label>
                        <select
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-lg"
                               id="three_pl" name="three_pl" required
                               {{ session('batch_status') == 'staged' ? 'disabled' : '' }}
                        >
                            <option value="">-- Choose Carrier --</option>
                            @foreach($allCarriers as $carrier)
                                <option value="{{ $carrier }}"
                                        @if (session('batch_status') == 'staged' && session('current_three_pl') == $carrier)
                                            selected
                                        @endif
                                >
                                    {{ $carrier }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Tombol Start/Active --}}
                    <div class="md:col-span-1">
                        @if (session('batch_status') != 'staged')
                            <button type="submit" class="w-full py-3 px-4 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150">
                                **Start**
                            </button>
                        @else
                            <button type="button" class="w-full py-3 px-4 rounded-md text-lg font-medium text-gray-700 dark:text-gray-400 bg-gray-200 dark:bg-gray-700 cursor-not-allowed">
                                Active
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 2. AWB Scanning & List (Hanya muncul jika sesi aktif: 'staged') --}}
    @if (session('batch_status') == 'staged')
        <div class="bg-white dark:bg-gray-900 shadow-xl rounded-xl overflow-hidden border-2 border-green-500">
            <div class="p-4 bg-green-600 text-white dark:bg-green-700 flex justify-between items-center">
                <h4 class="text-xl font-semibold">2. AWB Scanning (Carrier: **{{ session('current_three_pl') }}**)</h4>
                <span class="text-sm font-light">AWBs Staged: {{ count(session('staged_awbs', [])) }}</span>
            </div>

            <div class="p-6">
                {{-- Input Scanning (Fokus Utama) --}}
                <form action="{{ route('handover.scan') }}" method="POST" id="scan-form" class="mb-6">
                    @csrf
                    <div class="flex space-x-3">
                        <input type="text" class="flex-grow px-4 py-4 border-4 border-green-500 dark:border-green-400 dark:bg-gray-800 dark:text-white rounded-lg text-2xl font-bold uppercase shadow-inner focus:outline-none focus:ring-4 focus:ring-green-300 transition duration-150" name="awb_number" placeholder="SCAN AWB NUMBER HERE" required autofocus>
                        <button class="px-6 py-4 bg-green-500 text-white rounded-lg text-lg font-semibold hover:bg-green-600 transition duration-150 flex-shrink-0" type="submit">
                            Add
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Input ini otomatis fokus untuk pemindai barcode.</p>
                </form>

                {{-- 3. Staged AWB List --}}
                <h5 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-3">Staged AWBs (Tersimpan di Sesi & DB)</h5>
                <div class="overflow-y-auto max-h-96 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-700 dark:bg-gray-800 sticky top-0">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/12">No.</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-6/12">AWB Number</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-3/12">Scan Timestamp</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-2/12">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse (session('staged_awbs', []) as $awbData)
                                <tr class="hover:bg-blue-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $loop->iteration }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm font-bold text-gray-800 dark:text-gray-200">{{ $awbData['airwaybill'] }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($awbData['scanned_at'])->format('H:i:s') }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm font-medium">
                                        <form action="{{ route('handover.remove') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="awb_to_remove" value="{{ $awbData['airwaybill'] }}">
                                            <button type="submit" class="text-red-600 hover:text-red-900 font-semibold text-xs py-1 px-2 rounded bg-red-100 hover:bg-red-200 transition duration-150 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800">
                                                Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Belum ada AWB yang dipindai dalam sesi ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- 4. Finalize Button --}}
                @if (count(session('staged_awbs', [])) > 0)
                    <form action="{{ route('handover.finalize') }}" method="POST" class="mt-6" id="finalize-form">
                        @csrf
                        <button type="submit" class="w-full py-4 bg-yellow-400 text-gray-800 rounded-lg text-xl font-extrabold shadow-lg hover:bg-yellow-500 transition duration-150 focus:outline-none focus:ring-4 focus:ring-yellow-300">
                            ✅ **FINALIZE HANDOVER** (Commit {{ count(session('staged_awbs')) }} AWBs)
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
