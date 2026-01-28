<?php

namespace App\Http\Controllers;

use App\Models\MbMaster;
use App\Models\MbOrderStaging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MbCheckerController extends Controller
{
    public function index()
    {
        return view('mb_master.checker');
    }

    public function verify(Request $request)
    {
        $request->validate(['search_query' => 'required|string']);

        // 1. Bersihkan input user
        $rawQueries = explode(',', $request->search_query);
        $cleanQueries = collect($rawQueries)->map(function ($item) {
            return str_replace(['`', ' '], '', trim($item));
        })->filter()->values()->toArray();

        // 2. Ambil data lengkap dari Staging terlebih dahulu
        $stagingData = MbOrderStaging::whereIn('package_no', $cleanQueries)
                    ->orWhereIn('waybill_no', $cleanQueries)
                    ->orWhereIn('transaction_number', $cleanQueries)
                    ->orWhereIn('external_order_no', $cleanQueries)
                    ->orWhere(function($q) use ($cleanQueries) {
                        foreach($cleanQueries as $query) {
                            $q->orWhereRaw("REPLACE(manufacture_barcode, '`', '') = ?", [$query]);
                        }
                    })
                    ->get(['manufacture_barcode', 'transaction_number', 'external_order_no', 'waybill_no']);

        // Ambil semua barcode unik dari staging dan bersihkan backtick-nya
        $barcodesFromStaging = $stagingData->map(fn($s) => str_replace('`', '', $s->manufacture_barcode))->toArray();

        // 3. Gabungkan dengan query asli (siapa tahu user input barcode langsung)
        $allTargetBarcodes = array_unique(array_merge($barcodesFromStaging, $cleanQueries));

        // 4. Cari di Master Data
        $masterData = MbMaster::whereIn('manufacture_barcode', $allTargetBarcodes)
                    ->where('is_disabled', 0)
                    ->get();

        // 5. Mapping data Staging ke Master agar detailnya muncul di table
        $finalResults = $masterData->map(function($master) use ($stagingData) {
            $cleanMasterBC = str_replace('`', '', $master->manufacture_barcode);

            // Cari info staging yang cocok dengan barcode ini
            $match = $stagingData->first(function($s) use ($cleanMasterBC) {
                return str_replace('`', '', $s->manufacture_barcode) === $cleanMasterBC;
            });

            // Tempelkan info staging ke objek master
            $master->transaction_number = $match->transaction_number ?? '-';
            $master->external_order_no = $match->external_order_no ?? '-';
            $master->waybill_no = $match->waybill_no ?? '-';

            return $master;
        })->groupBy(function($item) {
            return str_replace('`', '', $item->manufacture_barcode);
        });

        return view('mb_master.checker', [
            'results' => $finalResults,
            'searchQuery' => $request->search_query
        ]);
    }

    public function exportBrandCheck()
{
    // 1. Ambil semua data staging
    $groupedPackages = MbOrderStaging::whereNotNull('manufacture_barcode')
        ->get()
        ->groupBy('package_no');

    $inventoryData = [];
    $outboundData = [];

    // 2. Ambil semua barcode unik & bersihkan tanda petik/backtick
    $uniqueBarcodes = MbOrderStaging::whereNotNull('manufacture_barcode')
        ->pluck('manufacture_barcode')
        ->unique()
        ->map(fn($bc) => str_replace(["`", "`"], "", $bc))
        ->toArray();

    // 3. Ambil Detail Master (Map Barcode ke Koleksi Brand)
    $masterData = MbMaster::whereIn('manufacture_barcode', $uniqueBarcodes)
        ->get()
        ->groupBy('manufacture_barcode');

    foreach ($groupedPackages as $packageNo => $items) {

        // CEK: Apakah paket ini mengandung minimal satu barcode multibrand?
        $packageHasMultiBrand = false;
        foreach ($items as $item) {
            $cleanBc = str_replace(["'", "`"], "", $item->manufacture_barcode);
            if (isset($masterData[$cleanBc]) && $masterData[$cleanBc]->count() > 1) {
                $packageHasMultiBrand = true;
                break;
            }
        }

        // Tentukan tim tujuan berdasarkan kondisi paket
        $targetTeam = $packageHasMultiBrand ? 'inventory_team' : 'outbound_team';

        foreach ($items as $item) {
            $cleanBc = str_replace(["'", "`"], "", $item->manufacture_barcode);
            $brands = $masterData[$cleanBc] ?? collect();

            // Jika barcode tidak ada di master, buat satu baris kosong
            if ($brands->isEmpty()) {
                $row = $this->formatExportRow($item, null, $targetTeam, false);
                $this->pushToTeam($row, $targetTeam, $inventoryData, $outboundData);
                continue;
            }

            // LOGIKA DUPLIKASI: Jika barcode punya 2 brand, loop ini akan jalan 2x
            $isBarcodeMulti = $brands->count() > 1;

            foreach ($brands as $brand) {
                $row = $this->formatExportRow($item, $brand, $targetTeam, $isBarcodeMulti);
                $this->pushToTeam($row, $targetTeam, $inventoryData, $outboundData);
            }
        }
    }

    return $this->processZipAndDownload($inventoryData, $outboundData);
}

    /**
     * Helper method untuk memproses ZIP dan Download
     */
    private function processZipAndDownload(array $inventoryData, array $outboundData)
    {
        // Pastikan direktori exports ada
        if (!Storage::disk('local')->exists('exports')) {
            Storage::disk('local')->makeDirectory('exports');
        }

        $fileInventory = 'exports/brand_check_inventory_team.csv';
        $fileOutbound  = 'exports/brand_check_outbound_team.csv';

        // Tulis ke CSV
        $this->writeToCsv($inventoryData, $fileInventory);
        $this->writeToCsv($outboundData, $fileOutbound);

        $zipName = 'brand_check_export_' . now()->format('Ymd_His') . '.zip';
        $zipPath = Storage::disk('local')->path('exports/' . $zipName);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFile(Storage::disk('local')->path($fileInventory), 'brand_check_inventory_team.csv');
            $zip->addFile(Storage::disk('local')->path($fileOutbound), 'brand_check_outbound_team.csv');
            $zip->close();
        }

        // Hapus file CSV mentah setelah dimasukkan ke ZIP agar storage tidak penuh
        Storage::disk('local')->delete([$fileInventory, $fileOutbound]);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function writeToCsv(array $data, $relativeStorePath)
    {
        $fullPath = Storage::disk('local')->path($relativeStorePath);
        $handle = fopen($fullPath, 'w');

        if (!empty($data)) {
            fputcsv($handle, array_keys($data[0])); // Header
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        } else {
            fputcsv($handle, ['No Data Found']);
        }
        fclose($handle);
    }

    private function formatExportRow($item, $brand, $team, $isMulti)
    {
        return [
            'external_order_no'   => $item->external_order_no,
            'transaction_number'  => $item->transaction_number,
            'waybill_no'          => $item->waybill_no,
            'manufacture_barcode' => $item->manufacture_barcode,
            'order_status'        => $item->order_status,
            'brand_name'          => $brand->brand_name ?? 'N/A',
            'brand_code'          => $brand->brand_code ?? 'N/A',
            'is_multibrand'       => $isMulti ? 'true' : 'false', // Flagging spesifik per barcode
            'operator'            => $team,
        ];
    }

    private function pushToTeam($row, $team, &$inventory, &$outbound)
    {
        if ($team === 'inventory_team') {
            $inventory[] = $row;
        } else {
            $outbound[] = $row;
        }
    }
}
