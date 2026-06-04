@extends('layouts.app')

@section('title', 'Handover Station')

@section('content')
<style>
    @keyframes pulse-red {
        0%, 100% { background-color: rgba(220, 38, 38, 0.1); }
        50% { background-color: rgba(220, 38, 38, 0.3); }
    }
    .row-cancelled {
        animation: pulse-red 2s infinite;
        border-left: 4px solid #dc2626;
    }
</style>
<div class="space-y-6">
    <h1 class="text-3xl font-extrabold text-gray-800 dark:text-gray-100 mb-6">📦 Handover Station</h1>

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

    {{-- 1. Setup Batch & 3PL --}}
    <div class="bg-white dark:bg-gray-900 shadow-xl rounded-xl overflow-hidden">
        <div class="p-4 bg-blue-600 text-white dark:bg-blue-700 flex justify-between items-center">
            <h4 class="text-xl font-semibold">1. Setup Batch & Carrier Selection</h4>

            {{-- TOMBOL CLEAR BATCH (HANYA MUNCUL JIKA SESSION AKTIF) --}}
            @if (session('batch_status') == 'staged')
                <form action="{{ route('handover.clear-batch') }}" method="POST" id="clear-batch-form">
                    @csrf
                    <button type="submit"
                            class="py-1 px-3 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-md transition duration-150"
                            onclick="event.preventDefault(); showConfirmModal('clear-batch-form', '{{ session('current_batch_id') }}');">
                        Clear Batch
                    </button>
                </form>
            @endif
        </div>
        <div class="p-6">
            <form id="setup-form" action="{{ route('handover.set-batch') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    {{-- Auto-Generated Handover ID Display --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Batch Handover Document ID</label>
                        @if (session('batch_status') == 'staged')
                            <div class="h-[50px] w-full px-4 border border-green-500 dark:border-green-400 bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 rounded-md shadow-sm text-lg font-mono font-bold flex items-center">
                                {{ session('current_batch_id') }}
                            </div>
                        @else
                            <div id="handover-id-preview" class="h-[50px] w-full px-4 border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md shadow-sm text-lg font-mono flex items-center">
                                <span id="handover-id-text">Select 3PL to preview ID</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-generated on Start.</p>
                        @endif
                    </div>

                    {{-- Select Carrier --}}
                    <div>
                        <label for="three_pl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select 3PL</label>
                        <select
                               class="h-[50px] w-full px-4 py-0 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-lg appearance-none"
                               id="three_pl" name="three_pl" required
                               {{ session('batch_status') == 'staged' ? 'disabled' : '' }}
                        >
                            <option value="">-- Choose 3PL --</option>
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
                    <div>
                        <label class="block text-sm font-medium text-transparent mb-1 select-none">&nbsp;</label>
                        <div class="h-[50px] flex">
                            @if (session('batch_status') != 'staged')
                                <button type="submit" class="h-full w-full border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 flex items-center justify-center">
                                    Start
                                </button>
                            @else
                                <button type="button" class="h-full w-full rounded-md text-lg font-medium text-gray-700 dark:text-gray-400 bg-gray-200 dark:bg-gray-700 cursor-not-allowed flex items-center justify-center">
                                    Active
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            <script>
                (function() {
                    const select = document.getElementById('three_pl');
                    const previewBox = document.getElementById('handover-id-preview');
                    const previewText = document.getElementById('handover-id-text');

                    if (select && previewBox && previewText) {
                        function slugify(str) {
                            return str.toUpperCase().replace(/\s+/g, '_').replace(/[^A-Z0-9_]/g, '');
                        }

                        function formatDate() {
                            const now = new Date();
                            const y = now.getFullYear();
                            const m = String(now.getMonth() + 1).padStart(2, '0');
                            const d = String(now.getDate()).padStart(2, '0');
                            return y + m + d;
                        }

                        select.addEventListener('change', function() {
                            const val = this.value.trim();
                            if (val && val !== '-- Choose 3PL --') {
                                const slug = slugify(val);
                                const date = formatDate();
                                previewText.textContent = 'HO-' + date + '-' + slug + '-01';
                                previewBox.classList.remove('bg-gray-100', 'dark:bg-gray-800', 'text-gray-500', 'dark:text-gray-400', 'border-gray-300', 'dark:border-gray-600');
                                previewBox.classList.add('bg-blue-50', 'dark:bg-blue-900/30', 'text-blue-800', 'dark:text-blue-200', 'border-blue-300', 'dark:border-blue-400');
                            } else {
                                previewText.textContent = 'Select 3PL to preview ID';
                                previewBox.classList.add('bg-gray-100', 'dark:bg-gray-800', 'text-gray-500', 'dark:text-gray-400', 'border-gray-300', 'dark:border-gray-600');
                                previewBox.classList.remove('bg-blue-50', 'dark:bg-blue-900/30', 'text-blue-800', 'dark:text-blue-200', 'border-blue-300', 'dark:border-blue-400');
                            }
                        });
                    }
                })();
            </script>
        </div>
    </div>

    {{-- 2. AWB Scanning & List (Hanya muncul jika sesi aktif: 'staged') --}}
    @if (session('batch_status') == 'staged')
        <div class="bg-white dark:bg-gray-900 shadow-xl rounded-xl overflow-hidden border-2 border-green-500">
            <div class="p-4 bg-green-600 text-white dark:bg-green-700 flex justify-between items-center">
                <h4 class="text-xl font-semibold">2. AWB Scanning (3PL: {{ session('current_three_pl') }})</h4>
                <span class="text-sm font-light">AWBs Staged: {{ count($stagedAwbs) }}</span>
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
                <div id="table-container" class="overflow-y-auto max-h-96 border border-gray-200 dark:border-gray-700 rounded-lg">
                    @include('handover.partials.table', ['stagedAwbs' => $stagedAwbs])
                </div>

                {{-- 4. Finalize Button --}}
                @php
                    $hasCancelledInTable = $stagedAwbs->contains('is_cancelled', true);
                @endphp

                @if ($stagedAwbs->count() > 0)
                    <form action="{{ route('handover.finalize') }}" method="POST" class="mt-6" id="finalize-form">
                        @csrf

                        @if($hasCancelledInTable)
                            <div class="mb-4 p-3 bg-red-600 text-white text-center rounded-lg animate-bounce font-bold">
                                ⚠️ PERINGATAN: Hapus AWB yang "CANCELLED" sebelum melakukan Finalize!
                            </div>
                        @endif

                        <button type="submit"
                            @if($hasCancelledInTable) disabled @endif
                            class="w-full py-4 rounded-lg text-xl font-extrabold shadow-lg transition duration-150
                            {{ $hasCancelledInTable
                                ? 'bg-gray-400 cursor-not-allowed opacity-50'
                                : 'bg-yellow-400 text-gray-800 hover:bg-yellow-500 focus:ring-4 focus:ring-yellow-300' }}">
                            ✅ **FINALIZE HANDOVER** ({{ $stagedAwbs->count() }} AWBs)
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>

{{-- Custom Modal for Confirmation (Menggantikan alert/confirm) --}}
<div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50 transition-opacity duration-300">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 w-11/12 max-w-lg transform transition-transform duration-300 scale-95">
        <h3 class="text-2xl font-bold text-red-600 dark:text-red-400 mb-4 border-b pb-2">Konfirmasi Penghapusan Batch</h3>
        <p class="text-gray-700 dark:text-gray-300 mb-6">Anda yakin ingin **menghapus total** batch <strong id="batch-id-display" class="font-mono text-lg"></strong>?</p>
        <p class="text-sm text-red-700 dark:text-red-300 mb-6">Tindakan ini akan menghapus semua AWB yang tersimpan di database untuk batch ini, dan sesi Anda akan diatur ulang.</p>
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="hideConfirmModal()" class="py-2 px-4 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 transition duration-150">
                Batal
            </button>
            <button type="button" id="confirm-submit-button" class="py-2 px-4 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition duration-150">
                Ya, Hapus Sekarang
            </button>
        </div>
    </div>
</div>

{{-- Script untuk Modal Konfirmasi --}}
<script>
    let formToSubmit = null;

    function showConfirmModal(formId, batchId) {
        formToSubmit = document.getElementById(formId);
        document.getElementById('batch-id-display').textContent = batchId;
        const modal = document.getElementById('confirm-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function hideConfirmModal() {
        const modal = document.getElementById('confirm-modal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        formToSubmit = null;
    }

    // Listener untuk tombol "Ya, Hapus Sekarang"
    document.getElementById('confirm-submit-button').addEventListener('click', function() {
        if (formToSubmit) {
            formToSubmit.submit();
        }
        hideConfirmModal();
    });
</script>

<script>
    // Gunakan json_encode untuk menghindari error "toJson() on array"
    let currentHash = "{{ md5(json_encode($stagedAwbs)) }}";

    function refreshTable() {
        fetch("{{ route('handover.table-fragment') }}")
            .then(response => response.json())
            .then(data => {
                // 1. Update isi tabel tanpa refresh halaman
                document.getElementById('table-container').innerHTML = data.html;

                // 2. Jika ada paket yang dibatalkan, putar suara error
                if (data.has_cancelled) {
                    playErrorSound();
                }

                // 3. Update jumlah statistik di UI (Opsional)
                const stagedCountText = document.querySelector('.text-sm.font-light');
                if(stagedCountText) {
                    stagedCountText.innerText = "AWBs Staged: " + data.count;
                }

                // 4. Cek tombol Finalize (Refresh halaman jika perlu mengunci tombol)
                // Karena tombol Finalize ada di luar container tabel,
                // cara termudah adalah reload jika status has_cancelled berubah.
                if (data.has_cancelled) {
                    // Opsional: window.location.reload();
                    // Atau manipulasi DOM tombol secara manual di sini.
                }
            })
            .catch(error => console.error('Error refreshing table:', error));
    }

    function checkDataChanges() {
        // Hanya jalan jika sesi scan sedang aktif
        @if (session('batch_status') === 'staged')
            fetch("{{ route('handover.check-count') }}")
                .then(response => response.json())
                .then(data => {
                    if (data.hash !== currentHash) {
                        console.log('Perubahan terdeteksi (Hapus/Cancel). Mengupdate tabel...');
                        currentHash = data.hash;
                        refreshTable();
                    }
                })
                .catch(error => console.error('Error checking updates:', error));
        @endif
    }

    // Jalankan pengecekan setiap 2 detik
    setInterval(checkDataChanges, 2000);
</script>

{{-- ========================================================================= --}}
{{-- 🔊 TAMBAHAN UNTUK SUARA (Success & Error Scan) --}}
{{-- ========================================================================= --}}

{{-- Pastikan Anda menempatkan file success.mp3 dan error.mp3 di folder public/sounds/ --}}
<audio id="successSound" src="{{ asset('sounds/success.mp3') }}" preload="auto"></audio>
<audio id="errorSound" src="{{ asset('sounds/error.mp3') }}" preload="auto"></audio>

<script>
    // Ambil elemen audio dan input
    const successAudio = document.getElementById('successSound');
    const errorAudio = document.getElementById('errorSound');
    const scanInput = document.querySelector('input[name="awb_number"]');

    function playSuccessSound() {
        successAudio.currentTime = 0;
        successAudio.play().catch(e => console.error("Error playing success sound:", e));
    }

    function playErrorSound() {
        errorAudio.currentTime = 0;
        errorAudio.play().catch(e => console.error("Error playing error sound:", e));
    }

    function refocusScanInput() {
        if (scanInput) {
            setTimeout(() => {
                scanInput.focus();
                scanInput.value = '';
            }, 100);
        }
    }

    // Cek Session Flash untuk memutar suara setelah form submit
    document.addEventListener('DOMContentLoaded', (event) => {
        // 1. Cek notifikasi sukses dari server
        @if (session('success'))
            playSuccessSound();
            refocusScanInput();
        @endif

        // 2. Cek notifikasi error dari server
        @if (session('error') || $errors->any())
            playErrorSound();
            refocusScanInput();
        @endif

        // Pastikan input AWB selalu fokus saat halaman dimuat
        refocusScanInput();
    });
</script>
@endsection
