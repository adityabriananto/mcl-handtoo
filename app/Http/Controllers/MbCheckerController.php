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
        $request->validate([
            'search_query' => 'required|string',
            'search_type' => 'required|string|in:all,package_no,waybill_no,transaction_number,external_order_no,manufacture_barcode'
        ]);

        $type = $request->search_type;
        $rawQueries = explode(',', $request->search_query);
        $cleanQueries = collect($rawQueries)->map(function ($item) {
            return str_replace(['`', ' '], '', trim($item));
        })->filter()->values()->toArray();

        $finalResults = collect();

        if ($type === 'manufacture_barcode') {
            /**
             * SKENARIO A: Murni Cek Data Master (Tanpa Staging)
             */
            $masterData = MbMaster::whereIn('manufacture_barcode', $cleanQueries)
                        ->where('is_disabled', 0)
                        ->get();

            foreach ($masterData as $brand) {
                $newRow = new \stdClass(); // Buat objek kosong agar struktur sama dengan model staging
                $newRow->manufacture_barcode = $brand->manufacture_barcode;
                $newRow->brand_name          = $brand->brand_name;
                $newRow->brand_code          = $brand->brand_code;
                $newRow->seller_sku          = $brand->seller_sku;
                $newRow->fulfillment_sku     = $brand->fulfillment_sku;
                $newRow->is_disabled         = $brand->is_disabled;
                $newRow->is_multi_brand      = $masterData->where('manufacture_barcode', $brand->manufacture_barcode)->count() > 1;

                // Set null untuk kolom order staging karena tidak dicari
                $newRow->transaction_number  = '-';
                $newRow->external_order_no   = '-';
                $newRow->waybill_no          = '-';

                $finalResults->push($newRow);
            }
        } else {
            /**
             * SKENARIO B: Pencarian Berdasarkan Info Order (Tetap Pakai Staging)
             */
            $stagingData = MbOrderStaging::whereIn($type, $cleanQueries)
                        ->get(['manufacture_barcode', 'transaction_number', 'external_order_no', 'waybill_no']);

            $allTargetBarcodes = $stagingData->map(fn($s) => str_replace('`', '', $s->manufacture_barcode))->unique()->toArray();

            $masterDataGrouped = MbMaster::whereIn('manufacture_barcode', $allTargetBarcodes)
                        ->where('is_disabled', 0)
                        ->get()
                        ->groupBy(fn($m) => str_replace('`', '', $m->manufacture_barcode));

            foreach ($stagingData as $order) {
                $cleanStagingBC = str_replace('`', '', $order->manufacture_barcode);
                $relatedBrands = $masterDataGrouped->get($cleanStagingBC);

                if ($relatedBrands && $relatedBrands->count() > 0) {
                    foreach ($relatedBrands as $brand) {
                        $newRow = clone $order;
                        $newRow->brand_name      = $brand->brand_name;
                        $newRow->brand_code      = $brand->brand_code;
                        $newRow->seller_sku      = $brand->seller_sku;
                        $newRow->fulfillment_sku = $brand->fulfillment_sku;
                        $newRow->is_disabled     = $brand->is_disabled;
                        $newRow->is_multi_brand  = $relatedBrands->count() > 1;
                        $finalResults->push($newRow);
                    }
                } else {
                    $order->brand_name = 'NOT FOUND';
                    $order->brand_code = '-';
                    $order->seller_sku = 'N/A';
                    $order->fulfillment_sku = 'N/A';
                    $order->is_disabled = 1;
                    $order->is_multi_brand = false;
                    $finalResults->push($order);
                }
            }
        }

        // Grouping berdasarkan Barcode agar tampilan tabel tetap konsisten
        $groupedResults = $finalResults->groupBy(fn($item) => str_replace('`', '', $item->manufacture_barcode));

        return view('mb_master.checker', [
            'results' => $groupedResults,
            'searchQuery' => $request->search_query,
            'searchType' => $type
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
