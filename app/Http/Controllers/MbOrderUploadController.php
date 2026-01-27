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
            $query->where('manufacture_barcode', 'like', '%' . $request->external_order_no . '%');
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

        $path = $request->file('order_file')->store('temp_imports');
        $fullPath = Storage::disk('local')->path($path);

        // Hitung total baris (dikurangi 1 untuk header)
        $totalRows = 0;
        if (($handle = fopen($fullPath, "r")) !== FALSE) {
            // Cara cepat hitung baris tanpa load semua ke RAM
            while (!feof($handle)) {
                if (fgets($handle) !== false) {
                    $totalRows++;
                }
            }
            fclose($handle);
            $totalRows = max(0, $totalRows - 1); // Kurangi header
        }

        $batch = ImportBatch::create([
            'file_name' => $request->file('order_file')->getClientOriginalName(),
            'status' => 'queued',
            'total_rows' => $totalRows, // Sekarang sudah terisi
            'processed_rows' => 0,
            'format_type' => $request->format // Simpan format agar di log terlihat
        ]);

        ProcessMbOrderImport::dispatch($fullPath, $request->format, $batch->id)->onQueue('mb-order-upload');

        return back()->with('success', "File #{$batch->id} queued. Total {$totalRows} rows detected.");
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
