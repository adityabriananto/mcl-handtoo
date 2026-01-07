@extends('layouts.app')

@section('title', 'Upload Data Handover')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Main Header --}}
        <header class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl font-bold leading-tight text-gray-900 dark:text-gray-100 flex items-center">
                📤 Import Data Order Management
            </h1>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Sisi Kiri: Instruksi & Informasi --}}
            <div class="lg:col-span-1">
                <div class="stat-card dark:bg-gray-900 h-full">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Upload Instructions</h3>
                    <ul class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <span class="bg-blue-100 text-blue-800 text-xs font-bold mr-2 px-2 py-1 rounded">1</span>
                            Ensure your file is in <strong>.csv</strong> format.
                        </li>
                        <li class="flex items-start">
                            <span class="bg-blue-100 text-blue-800 text-xs font-bold mr-2 px-2 py-1 rounded">2</span>
                            The first row must be the header (Name, Price, etc).
                        </li>
                        <li class="flex items-start">
                            <span class="bg-blue-100 text-blue-800 text-xs font-bold mr-2 px-2 py-1 rounded">3</span>
                            Maximum file size allowed is <strong>5MB</strong>.
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Sisi Kanan: Form Upload --}}
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-900 shadow-xl sm:rounded-lg overflow-hidden p-8">

                    {{-- Alert Success --}}
                    @if(session('success'))
                        <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 flex items-center shadow-sm">
                            <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Alert Error --}}
                    @if($errors->any())
                        <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 shadow-sm">
                            <ul class="list-disc ml-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('handover.upload.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="space-y-6">
                            <div>
                                <label for="csv_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Choose CSV File
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="csv_file" class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:hover:bg-gray-800 dark:bg-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:hover:border-gray-500 transition duration-150">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <svg class="w-10 h-10 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">CSV (MAX. 5MB)</p>
                                        </div>
                                        <input id="csv_file" name="csv_file" type="file" class="hidden" accept=".csv" required onchange="displayFileName(this)" />
                                    </label>
                                </div>
                                <p id="file_name_display" class="mt-3 text-sm text-blue-600 dark:text-blue-400 font-medium italic"></p>
                            </div>

                            <div class="pt-4">
                                <button type="submit" id="submitBtn" class="btn-primary w-full py-3 text-lg bg-blue-600 hover:bg-blue-700">
                                    🚀 Process & Import Data
                                </button>
                            </div>
                            <div id="progressContainer" class="hidden mt-4">
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">Uploading & Processing...</span>
                                    <span id="progressPercent" class="text-sm font-medium text-blue-700 dark:text-blue-300">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                    <div id="progressBar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2 italic">Jangan tutup halaman ini sampai proses selesai.</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const uploadForm = document.getElementById('uploadForm');
    const submitBtn = document.getElementById('submitBtn');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');

    uploadForm.onsubmit = function() {
        // Cek apakah file sudah dipilih
        const fileInput = document.getElementById('csv_file');
        if (fileInput.files.length === 0) return;

        // Nonaktifkan tombol agar tidak double click
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');

        // Tampilkan progress bar
        progressContainer.classList.remove('hidden');

        // Simulasi progress (karena PHP fgetcsv berjalan di server,
        // browser hanya bisa mendeteksi progress "upload" file saja)
        let width = 0;
        const interval = setInterval(() => {
            if (width >= 95) {
                clearInterval(interval);
            } else {
                width += 5;
                progressBar.style.width = width + '%';
                progressPercent.innerText = width + '%';
            }
        }, 300);
    };

    function displayFileName(input) {
        if (input.files.length > 0) {
            const fileName = input.files[0].name;
            document.getElementById('file_name_display').textContent = "Selected file: " + fileName;
        }
    }
</script>

<style>
    .btn-primary {
        @apply text-white py-2 px-4 rounded-lg shadow-md transition duration-150 font-semibold flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-900;
    }
    .btn-secondary {
        @apply bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 py-2 px-4 rounded-lg shadow-sm transition duration-150 font-semibold flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-700;
    }
    .stat-card {
        @apply bg-white dark:bg-gray-900 p-6 rounded-xl shadow-md border border-gray-100 dark:border-gray-700;
    }
</style>
@endsection
