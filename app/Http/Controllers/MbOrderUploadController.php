<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMbOrderImport;
use Illuminate\Http\Request;
use App\Models\MbOrderStaging;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MbOrderUploadController extends Controller
{
    public function index(Request $request)
    {
        $query = MbOrderStaging::query();

        // Logika Filter
        if ($request->filled('package_no')) {
            $query->where('package_no', 'like', '%' . $request->package_no . '%');
        }
        if ($request->filled('waybill_no')) {
            $query->where('waybill_no', 'like', '%' . $request->waybill_no . '%');
        }
        if ($request->filled('transaction_number')) {
            $query->where('transaction_number', 'like', '%' . $request->transaction_number . '%');
        }
        if ($request->filled('external_order_no')) {
            $query->where('external_order_no', 'like', '%' . $request->external_order_no . '%');
        }
        if ($request->filled('manufacture_barcode')) {
            // $query->where('manufacture_barcode', 'like', '%' . $request->manufacture_barcode . '%');
            $search = str_replace('`', '', $request->manufacture_barcode);
            $query->where(DB::raw("REPLACE(manufacture_barcode, '`', '')"), 'like', '%' . $search . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(25);

        // Ambil data log untuk dropdown navigasi
        $batches = ImportBatch::orderBy('created_at', 'desc')->take(5)->get();

        return view('mb_master.order_upload', compact('orders', 'batches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'order_file' => 'required|mimes:csv,txt',
            'format' => 'required'
        ]);

        // 1. Simpan file sementara
        $path = $request->file('order_file')->store('temp_imports');
        $fullPath = Storage::disk('local')->path($path);

        // 2. Hitung total baris untuk Batch record
        $totalRows = 0;
        if (($handle = fopen($fullPath, "r")) !== FALSE) {
            while (fgetcsv($handle) !== false) { $totalRows++; }
            fclose($handle);
            $totalRows = max(0, $totalRows - 1); // Kurangi header
        }

        // 3. Buat record Batch
        $batch = ImportBatch::create([
            'file_name' => $request->file('order_file')->getClientOriginalName(),
            'status' => 'processing',
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'format_type' => $request->format
        ]);

        // 4. CHUNKING LOGIC: Pecah menjadi 1000 baris per Job
        $header = null;
        $chunkSize = 1000;
        $currentChunk = [];

        if (($handle = fopen($fullPath, "r")) !== FALSE) {
            $header = fgetcsv($handle, 5000, ","); // Ambil Header

            while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                $currentChunk[] = $row;

                if (count($currentChunk) == $chunkSize) {
                    ProcessMbOrderImport::dispatch($currentChunk, $header, $request->format, $batch->id)
                        ->onQueue('mb-order-upload');
                    $currentChunk = []; // Reset chunk
                }
            }

            // Kirim sisa baris jika ada
            if (!empty($currentChunk)) {
                ProcessMbOrderImport::dispatch($currentChunk, $header, $request->format, $batch->id)
                    ->onQueue('mb-order-upload');
            }
            fclose($handle);
        }

        // 5. Hapus file fisik setelah di-chunk ke Queue (Optional, karena data sudah dikirim sebagai Array)
        if (file_exists($fullPath)) { unlink($fullPath); }

        return back()->with('success', "Proses dimulai. {$totalRows} baris dibagi menjadi beberapa bagian untuk efisiensi.");
    }

    public function clean()
    {
        // Tentukan batas waktu (3 hari yang lalu dari sekarang)
        $limitDate = now()->subDays(3);

        // 1. Hapus data di Staging yang lebih tua dari 3 hari
        $deletedOrders = MbOrderStaging::where('created_at', '<', $limitDate)->delete();

        // 2. Hapus data di Import Batch yang lebih tua dari 3 hari
        $deletedBatches = ImportBatch::where('created_at', '<', $limitDate)->delete();

        // Jika ingin menghapus file fisik di storage yang tersisa (opsional)
        // Anda bisa menambahkan logika pengecekan file di sini jika diperlukan.

        return back()->with('success', "Pembersihan berhasil: {$deletedOrders} data staging dan {$deletedBatches} log batch (lebih dari 3 hari) telah dihapus.");
    }

    public function logs()
    {
        // Mengambil data log upload dengan pagination agar tidak berat
        $logs = ImportBatch::orderBy('created_at', 'desc')->paginate(15);

        return view('mb_master.logs', compact('logs'));
    }
}
