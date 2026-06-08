<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    {{ __("You're logged in!") }}
                </div>
            </div>

            {{-- Data Upload Cleanup Card --}}
            <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Data Upload Cleanup
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Hapus data upload dan file yang sudah lebih dari 1 bulan.
                            </p>
                            <div id="cleanup-stats" class="mt-3 flex gap-4 text-sm">
                                <span class="text-gray-600 dark:text-gray-400">
                                    Record lama: <span id="old-records" class="font-medium text-gray-900 dark:text-white">-</span>
                                </span>
                                <span class="text-gray-600 dark:text-gray-400">
                                    File lama: <span id="old-files" class="font-medium text-gray-900 dark:text-white">-</span>
                                </span>
                            </div>
                        </div>
                        <form id="cleanup-form" action="{{ route('admin.data-upload.clear') }}" method="POST" class="ml-4">
                            @csrf
                            <button type="submit"
                                onclick="return confirm('Yakin ingin menghapus data upload dan file yang lebih dari 1 bulan? Tindakan ini tidak bisa dibatalkan.')"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Clear Data Lama
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('{{ route('admin.data-upload.summary') }}')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('old-records').textContent = data.old_records.toLocaleString();
                    document.getElementById('old-files').textContent = data.old_files.toLocaleString();

                    const btn = document.querySelector('#cleanup-form button');
                    if (data.old_records === 0 && data.old_files === 0) {
                        btn.disabled = true;
                        btn.classList.add('opacity-50', 'cursor-not-allowed');
                        btn.classList.remove('hover:bg-red-500');
                    }
                })
                .catch(err => {
                    console.error('Failed to load cleanup stats:', err);
                });
        });
    </script>
    @endpush
</x-app-layout>
