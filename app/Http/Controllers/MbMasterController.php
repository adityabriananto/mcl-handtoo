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
        // 1. Ambil data unik untuk dropdown filter
        $filterOptions = [
            'brands' => MbMaster::select('brand_code', 'brand_name')
                ->groupBy('brand_code', 'brand_name')
                ->orderBy('brand_name')
                ->get()
        ];

        $query = MbMaster::query();

        // 2. Terapkan Filter (Logic yang sama untuk View & Export)
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
        if ($request->filled('status')) {
            $statusValue = $request->status == 'active' ? 0 : 1;
            $query->where('is_disabled', $statusValue);
        }

       if ($request->has('export')) {
            // 1. Bersihkan semua output buffer agar file tidak korup
            if (ob_get_contents()) ob_end_clean();
            ob_start();

            // 2. Gunakan get() jika cursor() masih bermasalah dengan fastexcel di env Anda
            // atau gunakan generator jika data sangat banyak
            $exportData = $query->get();

            // 3. Pastikan return fastexcel langsung dikembalikan
            return (new \Rap2hpoutre\FastExcel\FastExcel($exportData))
                ->download('MB_Master_Export_'.date('YmdHis').'.csv', function ($item) {
                    return [
                        'Brand Code'          => $item->brand_code,
                        'Brand Name'          => $item->brand_name,
                        'Manufacture Barcode' => $item->manufacture_barcode,
                        'Fulfillment SKU'     => $item->fulfillment_sku,
                        'Seller SKU'          => $item->seller_sku ?? '-',
                        'Status'              => $item->is_disabled ? 'Disabled' : 'Active',
                    ];
                });
        }

        // 4. Final Query untuk View
        $masters = $query->latest()->paginate(50)->withQueryString();

        return view('mb_master.index', compact('masters', 'filterOptions'));
    }

    public function store(Request $request)
    {
        // dd($request->all())
        // $request->validate([
        //     'brand_code'          => 'required|unique:mb_masters',
        //     'brand_name'          => 'required',
        //     'manufacture_barcode' => 'required',
        //     'fulfillment_sku'     => 'required|unique:mb_masters',
        // ]);

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
            $data['is_disabled'] = (int)$request->is_disabled;
        }

        $mbMaster->update($request->all());
        return back()->with('success', 'Master Data updated.');
    }

    public function destroy(MbMaster $mbMaster)
    {
        $mbMaster->delete();
        return back()->with('success', 'Master Data removed.');
    }

    public function importCsv(Request $request)
    {
        $request->validate(['csv_file' => 'required|mimes:csv,txt|max:10240']);

        $file = $request->file('csv_file');
        $fullPath = $file->getRealPath(); // Ambil path sementara

        $chunkSize = 1000;
        $currentChunk = [];

        if (($handle = fopen($fullPath, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header

            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $currentChunk[] = $row;

                if (count($currentChunk) >= $chunkSize) {
                    ImportMbMasterJob::dispatch($currentChunk)->onQueue('mb-master-import');
                    $currentChunk = [];
                }
            }

            if (!empty($currentChunk)) {
                ImportMbMasterJob::dispatch($currentChunk)->onQueue('mb-master-import');
            }
            fclose($handle);
        }

        return back()->with('success', 'Import sedang diproses di background.');
    }
}
