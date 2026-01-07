<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="awb-table-content">
    <thead class="bg-gray-700 dark:bg-gray-800 sticky top-0">
        <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/12">No.</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-5/12">AWB Number</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-2/12">Scan Timestamp</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider w-2/12">Status</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-white uppercase tracking-wider w-2/12">Action</th>
        </tr>
    </thead>
    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
        @forelse ($stagedAwbs as $awbData)
            <tr class="hover:bg-blue-50 dark:hover:bg-gray-800 {{ $awbData->is_cancelled ? 'row-cancelled bg-red-50' : '' }}">
                <td class="px-6 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $loop->iteration }}</td>
                <td class="px-6 py-3 text-sm font-bold text-gray-800 dark:text-gray-200">{{ $awbData->airwaybill }}</td>
                <td class="px-6 py-3 text-sm text-gray-500">{{ \Carbon\Carbon::parse($awbData->scanned_at)->format('H:i:s') }}</td>
                <td class="px-6 py-3 text-center">
                    @if($awbData->is_cancelled)
                        <span class="px-3 py-1 text-xs font-bold rounded-full bg-red-600 text-white animate-pulse">CANCELLED</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">ACTIVE</span>
                    @endif
                </td>
                <td class="px-6 py-3 whitespace-nowrap text-right text-sm font-medium">
                    {{-- Form Remove --}}
                    <form action="{{ route('handover.remove') }}" method="POST" class="remove-awb-form">
                        @csrf
                        <input type="hidden" name="awb_to_remove" value="{{ $awbData->airwaybill }}">

                        {{-- Kita tambah onclick untuk amaran --}}
                        <button type="submit"
                                onclick="return confirm('Adakah anda pasti ingin menghapus AWB {{ $awbData->airwaybill }} dari session ini?')"
                                class="text-red-600 hover:text-red-900 font-semibold text-xs py-1 px-3 rounded bg-red-100 hover:bg-red-200 transition duration-150 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-800">
                            Remove
                        </button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada AWB.</td></tr>
        @endforelse
    </tbody>
</table>
