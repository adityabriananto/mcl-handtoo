<?php

namespace App\Jobs;

use App\Models\MbOrderStaging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessMbOrderImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Ubah $rows menjadi $filePath (String)
    protected $filePath, $formatType, $batchId;

    public function __construct(string $filePath, $formatType, $batchId)
    {
        $this->filePath = $filePath;
        $this->formatType = $formatType;
        $this->batchId = $batchId;
    }

    public function handle()
    {
        if (($handle = fopen($this->filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 5000, ","); // Ambil Header

            DB::beginTransaction();
            try {
                while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                    // Clean data
                    $cleanRow = array_map(function($value) {
                        return is_string($value) ? trim($value) : $value;
                    }, $row);

                    // Buat payload untuk full_payload menggunakan headers
                    $fullPayload = array_combine($headers, array_pad($cleanRow, count($headers), null));

                    if ($this->formatType == 'order_management') {
                        $this->processOrderManagement($cleanRow, $fullPayload);
                    } else if ($this->formatType == 'package_management') {
                        $this->processPackageManagement($cleanRow, $fullPayload);
                    }

                    // Update progress setiap baris atau per 100 baris agar lebih efisien
                    DB::table('mb_import_batches')
                        ->where('id', $this->batchId)
                        ->increment('processed_rows');
                }

                DB::table('mb_import_batches')->where('id', $this->batchId)->update(['status' => 'completed']);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                DB::table('mb_import_batches')->where('id', $this->batchId)->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            }

            fclose($handle);

            // Hapus file temporary setelah selesai agar storage tidak penuh
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        }
    }

    private function processOrderManagement($row, $fullPayload)
    {
        MbOrderStaging::updateOrCreate(
            ['package_no' => $row[3] ?? null, 'manufacture_barcode' => null],
            [
                'order_code'         => $row[0] ?? null,
                'external_order_no'  => $row[1] ?? null,
                'waybill_no'         => $row[2] ?? null,
                'transaction_number' => $row[38] ?? null,
                'source_format'      => 'order_management',
                'full_payload'       => json_encode($fullPayload),
                'batch_id'    => $this->batchId,
            ]
        );
    }

    private function processPackageManagement($row, $fullPayload)
    {
        $packageNo = $row[1] ?? null;
        $barcode   = $row[29] ?? null;

        $master = MbOrderStaging::where('package_no', $packageNo)
                    ->whereNotNull('transaction_number')
                    ->first();

        if (!$master) return;

        $existingRows = MbOrderStaging::where('package_no', $packageNo)->get();

        if ($existingRows->count() === 1 && $existingRows->first()->manufacture_barcode === null) {
            $existingRows->first()->update([
                'waybill_no'          => $row[0] ?? null,
                'external_order_no'   => $row[4] ?? null,
                'order_code'          => $row[25] ?? null,
                'manufacture_barcode' => $barcode,
                'source_format'       => 'package_management',
                'full_payload'        => json_encode($fullPayload),
                'batch_id'     => $this->batchId,
            ]);
        } else {
            MbOrderStaging::updateOrCreate(
                ['package_no' => $packageNo, 'manufacture_barcode' => $barcode],
                [
                    'waybill_no'          => $row[0] ?? null,
                    'external_order_no'   => $row[4] ?? null,
                    'order_code'          => $row[25] ?? null,
                    'transaction_number'  => $master->transaction_number,
                    'source_format'       => 'package_management',
                    'full_payload'        => json_encode($fullPayload),
                    'batch_id'     => $this->batchId,
                ]
            );
        }
    }
}
