<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Handover Data Uploads') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Total Records
                    </div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white" id="total-records">
                        {{ number_format($totalRecordCount) }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Record Lama (> 1 Bulan)
                    </div>
                    <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400" id="old-records">
                        {{ number_format($oldRecordCount) }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        File Lama di Storage
                    </div>
                    <div class="mt-2 text-3xl font-bold text-orange-600 dark:text-orange-400" id="old-files">
                        {{ number_format($oldFileCount) }}
                    </div>
                </div>
            </div>

            {{-- Cleanup Action Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Data Upload Cleanup
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Hapus data upload dan file yang sudah lebih dari 1 bulan.
                                <span class="block mt-1 text-xs text-gray-500">
                                    Cutoff date: {{ $cutoffDate->format('d M Y H:i') }}
                                </span>
                            </p>
                        </div>
                        <form id="cleanup-form" action="{{ route('admin.data-upload.clear') }}" method="POST" class="ml-4">
                            @csrf
                            <button type="submit"
                                id="clear-btn"
                                onclick="return confirm('Yakin ingin menghapus data upload dan file yang lebih dari 1 bulan? Tindakan ini tidak bisa dibatalkan.')"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 {{ $oldRecordCount === 0 && $oldFileCount === 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                                {{ $oldRecordCount === 0 && $oldFileCount === 0 ? 'disabled' : '' }}>
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Clear Data Lama
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Recent Data Uploads Table (Optional - shows last 20 records) --}}
            @php
                $recentUploads = \App\Models\DataUpload::latest()->take(20)->get();
            @endphp

            @if($recentUploads->count() > 0)
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Recent Data Uploads
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Airwaybill</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Owner</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qty</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platform</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($recentUploads as $upload)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $upload->airwaybill }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $upload->order_number ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $upload->owner_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $upload->qty ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $upload->platform_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $upload->created_at?->format('d M Y H:i') ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function refreshStats() {
                fetch('{{ route('admin.data-upload.summary') }}')
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('old-records').textContent = data.old_records.toLocaleString();
                        document.getElementById('total-records').textContent = data.total_records.toLocaleString();
                        document.getElementById('old-files').textContent = data.old_files.toLocaleString();

                        const btn = document.getElementById('clear-btn');
                        if (data.old_records === 0 && data.old_files === 0) {
                            btn.disabled = true;
                            btn.classList.add('opacity-50', 'cursor-not-allowed');
                            btn.classList.remove('hover:bg-red-500');
                        } else {
                            btn.disabled = false;
                            btn.classList.remove('opacity-50', 'cursor-not-allowed');
                            btn.classList.add('hover:bg-red-500');
                        }
                    })
                    .catch(err => {
                        console.error('Failed to load cleanup stats:', err);
                    });
            }

            // Refresh every 30 seconds
            refreshStats();
            setInterval(refreshStats, 30000);
        });
    </script>
    @endpush
</x-app-layout>
