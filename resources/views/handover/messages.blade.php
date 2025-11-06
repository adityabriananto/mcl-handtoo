@if (session('success') || session('error') || session('info') || $errors->any())
    {{-- Container utama menggunakan x-data untuk Alpine.js/JS --}}
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mb-4">

        {{-- SUCCESS Message --}}
        @if (session('success'))
            <div class="p-4 rounded-lg bg-green-100 border border-green-400 text-green-700 flex justify-between items-center shadow-md" role="alert">
                <p class="font-medium">{!! session('success') !!}</p>
                <button @click="show = false" class="text-green-700 hover:text-green-900 font-bold ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        @endif

        {{-- ERROR Message --}}
        @if (session('error'))
            <div class="p-4 rounded-lg bg-red-100 border border-red-400 text-red-700 flex justify-between items-center shadow-md" role="alert">
                <p class="font-medium">{!! session('error') !!}</p>
                <button @click="show = false" class="text-red-700 hover:text-red-900 font-bold ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        @endif

        {{-- Validation Errors (Tailwind style) --}}
        @if ($errors->any())
            <div class="p-4 rounded-lg bg-red-100 border border-red-400 text-red-700 shadow-md" role="alert">
                <strong class="font-bold">Validation Error:</strong>
                <ul class="mt-2 list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif

{{-- Catatan: Untuk menjalankan @click dan x-data, pastikan Alpine.js diimpor di app.js --}}
