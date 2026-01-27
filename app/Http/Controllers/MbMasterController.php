<?php

namespace App\Http\Controllers;

use App\Jobs\ImportMbMasterJob;
use App\Models\MbMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MbMasterController extends Controller
{
    public function index(Request $request)
    {
        // Ambil data unik untuk dropdown filter
        $filterOptions = [
            'brands' => MbMaster::select('brand_code', 'brand_name')
                ->groupBy('brand_code', 'brand_name')
                ->orderBy('brand_name')
                ->get()
        ];

        $query = MbMaster::query();

        // Filter Brand (bisa berupa code atau name dari dropdown)
        if ($request->filled('brand')) {
            $query->where(function($q) use ($request) {
                $q->where('brand_code', $request->brand)
                ->orWhere('brand_name', 'like', "%{$request->brand}%");
            });
        }

        if ($request->filled('barcode')) {
            $query->where('manufacture_barcode', 'like', "%{$request->barcode}%");
        }

        if ($request->filled('f_sku')) {
            $query->where('fulfillment_sku', 'like', "%{$request->f_sku}%");
        }

        if ($request->filled('s_sku')) {
            $query->where('seller_sku', 'like', "%{$request->s_sku}%");
        }

        // Filter Status
        if ($request->filled('status')) {
            $statusValue = $request->status == 'active' ? 0 : 1;
            $query->where('is_disabled', $statusValue);
        }

        $masters = $query->latest()->paginate(10)->withQueryString();

        return view('mb_master.index', compact('masters', 'filterOptions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'brand_code'          => 'required|unique:mb_masters',
            'brand_name'          => 'required',
            'manufacture_barcode' => 'required',
            'fulfillment_sku'     => 'required|unique:mb_masters',
        ]);

        $data = $request->all();
        $data['is_disabled'] = $request->has('is_disabled') ? 0 : 1;

        MbMaster::create($data);
        return back()->with('success', 'Master Data created successfully.');
    }

    public function update(Request $request, MbMaster $mbMaster)
    {
        $data = $request->all();

        // Logic khusus untuk toggle dari tabel (jika is_disabled dikirim via hidden input)
        if ($request->has('is_disabled')) {
            $data['is_disabled'] = $request->is_disabled;
        }

        $mbMaster->update($data);
        return back()->with('success', 'Master Data updated.');
    }

    public function destroy(MbMaster $mbMaster)
    {
        $mbMaster->delete();
        return back()->with('success', 'Master Data removed.');
    }

    public function importCsv(Request $request)
    {
        $request->validate(['csv_file' => 'required|mimes:csv,txt|max:5120']);

        // HITUNG TOTAL BARIS (Header tidak dihitung)

        $file = $request->file('csv_file');
        $fileName = time() . '_' . $file->getClientOriginalName();

        // Simpan secara eksplisit ke disk 'local' (storage/app)
        $path = $file->storeAs('uploads', $fileName, 'local');

        // Ambil path absolut menggunakan facade Storage
        $fullPath = Storage::disk('local')->path($path);
        $totalRows = count(file($fullPath)) - 1;

        // SIMPAN KE DATABASE DULU
        \DB::table('import_statuses')->insert([
            'filename' => $fileName,
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kirim ke Queue
        ImportMbMasterJob::dispatch($fullPath, $fileName)->onQueue('mb-master-import');

        return back()->with('success', 'Import started!')->with('importing', true);
    }

    public function checkImportStatus() {
        $status = \DB::table('import_statuses')->latest()->first();
        return response()->json($status);
    }
}
