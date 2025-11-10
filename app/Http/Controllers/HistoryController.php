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

        // Menggunakan eager loading 'details'
        $batchesQuery = HandoverBatch::with('details');

        // --- Statistik Global ---
        $globalStats = [
            'total_batches' => HandoverBatch::count(),
            'completed_batches' => HandoverBatch::where('status', 'completed')->count(),
            'staging_batches' => HandoverBatch::where('status', 'staging')->count(),
            'manifest_signed' => HandoverBatch::where('status', 'completed')->whereNotNull('manifest_name_signed')->count(),
            'manifest_pending' => HandoverBatch::where('status', 'completed')->whereNull('manifest_name_signed')->count(),
        ];

        // --- Logika Filter ---
        if ($request->filled('handover_id')) {
            $batchesQuery->where('handover_id', 'like', '%' . $request->handover_id . '%');
        }

        if ($request->filled('airwaybill')) {
            // Menggunakan JOIN eksplisit untuk filtering AWB
            $batchesQuery
                ->join('handover_details', 'handover_batches.handover_id', '=', 'handover_details.handover_id')
                ->where('handover_details.airwaybill', 'like', '%' . $request->airwaybill . '%')
                ->select('handover_batches.*')
                ->distinct();
        }

        if ($request->filled('three_pl')) {
            $batchesQuery->where('three_pl', $request->three_pl);
        }

        if ($request->filled('date_start')) {
            $batchesQuery->whereDate('created_at', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $batchesQuery->whereDate('created_at', '<=', $request->date_end);
        }

        if ($request->filled('status')) {
            $status = $request->input('status');

            if ($status === 'staging') {
                $batchesQuery->where('status', 'staging');
            } elseif ($status === 'pending_handover') {
                $batchesQuery->where('status', 'completed')
                            ->whereNull('manifest_name_signed');
            } elseif ($status === 'completed') {
                $batchesQuery->where('status', 'completed')
                            ->whereNotNull('manifest_name_signed');
            }
        }
        // Jika tidak ada status yang dipilih, semua batch yang relevan (staging/completed) akan ditampilkan.


        $history = $batchesQuery->orderBy('updated_at', 'desc')->get();

        $uploadedManifests = $this->getUploadedManifests();
        $groupedHistory = $history->map(function ($batch) use ($uploadedManifests) {
            return [
                'handoverId' => $batch->handover_id,
                'threePlName' => $batch->three_pl,
                'awbs' => $batch->details,
                'createdTs' => $batch->created_at,
                'latestTs' => $batch->finalized_at,
                'signedTs' => $batch->signed_at,
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
        // --- 1. Persiapan Query Batch dengan Filter ---
        // Kita mulai dengan query pada HandoverBatch
        $batchesQuery = HandoverBatch::query();

        // Pastikan kita hanya mengekspor batch yang sudah diselesaikan (status: completed) secara default.
        // Namun, jika filter status ada, kita ikuti filter tersebut (yang mungkin menyertakan 'staging').

        // --- Logika Filter HandoverController::index diulang di sini ---

        if ($request->filled('handover_id')) {
            $batchesQuery->where('handover_id', 'like', '%' . $request->handover_id . '%');
        }

        if ($request->filled('airwaybill')) {
            // Menggunakan JOIN untuk filtering AWB
            $batchesQuery
                ->join('handover_details', 'handover_batches.handover_id', '=', 'handover_details.handover_id')
                ->where('handover_details.airwaybill', 'like', '%' . $request->airwaybill . '%')
                ->select('handover_batches.*')
                ->distinct();
        }

        if ($request->filled('three_pl')) {
            $batchesQuery->where('three_pl', $request->three_pl);
        }

        if ($request->filled('date_start')) {
            $batchesQuery->whereDate('created_at', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $batchesQuery->whereDate('created_at', '<=', $request->date_end);
        }

        // --- Filter Status ---
        // Logika Status yang sama dari index() diterapkan di sini
        if ($request->filled('status')) {
            $status = $request->input('status');

            if ($status === 'staging') {
                $batchesQuery->where('status', 'staging');
            } elseif ($status === 'pending_handover') {
                $batchesQuery->where('status', 'completed')
                            ->whereNull('manifest_name_signed');
            } elseif ($status === 'completed') {
                $batchesQuery->where('status', 'completed')
                            ->whereNotNull('manifest_name_signed');
            }
        } else {
            // Jika tidak ada filter status yang dipilih,
            // kita tetap membatasi hasil ke batch yang sudah selesai (atau sesuai kebutuhan bisnis Anda).
            // Saya asumsikan ekspor hanya relevan untuk status 'completed' jika filter kosong.
            $batchesQuery->where('status', 'completed');
        }

        // Ambil semua ID batch yang telah difilter
        $filteredBatchIds = $batchesQuery->pluck('handover_id');

        // --- 2. Ambil Detail AWB Berdasarkan ID Batch yang Difilter ---

        // Ambil semua detail AWB yang ada di dalam ID batch yang sudah difilter
        $allDetails = HandoverDetail::whereIn('handover_id', $filteredBatchIds)
                                    ->with('batch') // Eager load batch relationship
                                    ->get();

        // --- 3. Proses Export CSV ---

        $fileName = 'handover_data_' . Carbon::now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($allDetails) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['handover_id', 'awb', '3pl', 'scanned_at', 'created_at', 'finalized_at', 'signed_at']);

            foreach ($allDetails as $detail) {
                $batch = $detail->batch; // Menggunakan eager loaded relationship

                // Pastikan batch ada (walaupun seharusnya selalu ada karena difilter berdasarkan batch ID)
                $carrier = $batch->three_pl ?? 'N/A';
                $finalizedAt = $batch->finalized_at ? $batch->finalized_at->format('Y-m-d H:i:s') : 'N/A';
                $createdAt = $batch->created_at ?? 'N/A';
                $signedAt = $batch->signed_at ?? 'N/A';

                fputcsv($file, [
                    $detail->handover_id,
                    $detail->airwaybill,
                    $carrier,
                    $detail->scanned_at->format('Y-m-d H:i:s'),
                    $createdAt,
                    $finalizedAt,
                    $signedAt,
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
                'manifest_name_signed' => $fileName, // Asumsi kolom ini ada
                'status' => 'completed',
                'signed_at' => Carbon::now()
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

        if ($batch->status !== 'completed') {
            return redirect()->route('history.index')->with('error', 'Manifest Belum Selesai.');
        }
        // PENTING: Memastikan library milon/barcode terinstal untuk DNS1D
        $data = [
            'batch' => $batch,
            'details' => $batch->details,
            'printDate' => Carbon::now()->format('d M Y H:i:s'),
        ];

        $pdf = Pdf::loadView('handover.manifest-pdf', $data);

        $fileName = strtoupper($handoverId) . '_' . $batch->three_pl . '_MANIFEST.pdf';

        return $pdf->download($fileName);
    }
}
