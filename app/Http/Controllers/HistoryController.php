<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use App\Models\HandoverBatch;
use App\Models\HandoverDetail;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class HistoryController extends Controller
{
    /**
     * Menampilkan daftar Handover Batch yang sudah diselesaikan (completed) beserta statistiknya.
     */
    public function index(Request $request)
    {
        $allCarriers = config('handover.all_carriers');

        $batchesQuery = HandoverBatch::with('details');

        // --- Statistik Global ---
        $globalStats = [
            'total_batches' => HandoverBatch::count(),
            'completed_batches' => HandoverBatch::where('status', 'completed')->count(),
            'staging_batches' => HandoverBatch::where('status', 'staging')->count(),
            'manifest_signed' => HandoverBatch::where('status', 'completed')->whereNotNull('manifest_filename')->count(),
            'manifest_pending' => HandoverBatch::where('status', 'completed')->whereNull('manifest_filename')->count(),
        ];

        // --- Logika Filter ---
        if ($request->filled('handover_id')) {
            $batchesQuery->where('handover_id', 'like', '%' . $request->handover_id . '%');
        }

        if ($request->filled('airwaybill')) {
            // Asumsi relasi HandoverBatch memiliki many AWBs
            $batchesQuery->whereHas('awbs', function ($q) use ($request) {
                $q->where('airwaybill', 'like', '%' . $request->airwaybill . '%');
            });
        }

        if ($request->filled('three_pl')) {
            $batchesQuery->where('three_pl', $request->three_pl);
        }

        if ($request->filled('date_start')) {
            $batchesQuery->whereDate('finalized_at', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $batchesQuery->whereDate('finalized_at', '<=', $request->date_end);
        }

        // Filter utama: hanya tampilkan yang sudah completed
        $batchesQuery->where('status', 'completed');

        $history = $batchesQuery->orderBy('finalized_at', 'desc')->get();

        $uploadedManifests = $this->getUploadedManifests();

        $groupedHistory = $history->map(function ($batch) use ($uploadedManifests) {
            return [
                'handoverId' => $batch->handover_id,
                'threePlName' => $batch->three_pl,
                'awbs' => $batch->details,
                'latestTs' => $batch->finalized_at,
                'batch' => $batch, // Kirim objek batch untuk mengakses user_id atau manifest_filename
            ];
        })->keyBy('handoverId');

        return view('handover.history', [
            'allCarriers' => $allCarriers,
            'groupedHistory' => $groupedHistory,
            'uploadedManifests' => $uploadedManifests,
            'globalStats' => $globalStats,
        ]);
    }

    /**
     * Helper untuk mendapatkan daftar manifest yang sudah diupload (simulasi/DB check).
     * Di sini kita pakai cara DB check (manifest_filename) untuk yang sudah di-upload.
     */
    private function getUploadedManifests()
    {
        $manifests = HandoverBatch::whereNotNull('manifest_filename')
                                  ->pluck('manifest_filename', 'handover_id')
                                  ->toArray();
        return $manifests;
    }

    /**
     * Menangani proses ekspor data AWB menjadi CSV.
     */
    public function exportCsv(Request $request)
    {
        // ... (Export Logic - Sama seperti sebelumnya) ...
        $batchIds = HandoverBatch::where('status', 'completed')->pluck('handover_id');
        $allDetails = HandoverDetail::whereIn('handover_id', $batchIds)->get();

        $fileName = 'handover_data_' . Carbon::now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($allDetails) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['handover_id', 'airwaybill', 'carrier', 'scanned_at', 'finalized_at']);

            foreach ($allDetails as $detail) {
                $batch = $detail->batch;
                fputcsv($file, [
                    $detail->handover_id,
                    $detail->airwaybill,
                    $batch->three_pl ?? 'N/A',
                    $detail->scanned_at->format('Y-m-d H:i:s'),
                    $batch->finalized_at ? $batch->finalized_at->format('Y-m-d H:i:s') : 'N/A',
                ]);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Menangani upload file signed manifest dan mengupdate status di DB.
     */
    public function uploadManifest(Request $request, $handoverId)
    {
        $request->validate([
            'signed_file' => 'required|file|mimes:pdf,jpg,png|max:5120',
        ]);

        $batch = HandoverBatch::where('handover_id', $handoverId)->first();

        if (!$batch) {
            return redirect()->route('history.index')->with('error', 'Batch ID tidak ditemukan.');
        }

        $file = $request->file('signed_file');
        $extension = $file->getClientOriginalExtension();
        $fileName = strtoupper($handoverId) . '_signed_manifest.' . $extension;

        try {
            // Simpan File ke Storage
            $path = $file->storeAs('manifests', $fileName, 'public');

            // UPDATE STATUS DI DATABASE
            $batch->update([
                'manifest_filename' => $fileName, // Asumsi kolom ini ada
            ]);

            return redirect()->route('history.index')->with('success', 'Manifest **' . $fileName . '** untuk Batch **' . $handoverId . '** berhasil diupload! Status manifest telah diperbarui.');

        } catch (\Exception $e) {
            return redirect()->route('history.index')->with('error', 'Gagal upload manifest dan update DB: ' . $e->getMessage());
        }
    }

    /**
     * Menangani download manifest dalam format PDF.
     */
    public function downloadManifest($handoverId)
    {
        $batch = HandoverBatch::with('details')->where('handover_id', $handoverId)->first();

        if (!$batch) {
             return redirect()->route('history.index')->with('error', 'Batch ID tidak ditemukan.');
        }

        // PENTING: Memastikan library milon/barcode terinstal untuk DNS1D
        $data = [
            'batch' => $batch,
            'details' => $batch->details,
            'printDate' => Carbon::now()->format('d M Y H:i:s'),
            'logoUrl' => public_path('images/logistics_logo.png'), // Asumsi path logo
        ];

        $pdf = Pdf::loadView('handover.manifest-pdf', $data);

        $fileName = strtoupper($handoverId) . '_' . $batch->three_pl . '_MANIFEST.pdf';

        return $pdf->download($fileName);
    }
}
