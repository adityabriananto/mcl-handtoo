<?php

namespace App\Jobs;

use App\Models\MbMaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ImportMbMasterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath, $filename;

    public function __construct($filePath, $filename)
    {
        $this->filePath = $filePath;
        $this->filename = $filename;
    }

    public function handle()
    {
        $statusRecord = DB::table('import_statuses')->where('filename', $this->filename)->first();
        if (!$statusRecord) return;

        $handle = fopen($this->filePath, 'r');
        fgetcsv($handle); // Skip header

        try {
            $processed = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                MbMaster::updateOrCreate(
                    ['fulfillment_sku' => $data[3]],
                    [
                        'brand_code' => $data[0],
                        'brand_name' => $data[1],
                        'manufacture_barcode' => $data[2],
                        'seller_sku' => $data[4] ?? null,
                    ]
                );
                $processed++;
                if ($processed % 10 == 0) {
                    DB::table('import_statuses')->where('id', $statusRecord->id)
                        ->update(['processed_rows' => $processed, 'updated_at' => now()]);
                }
            }
            DB::table('import_statuses')->where('id', $statusRecord->id)
                ->update(['processed_rows' => $processed, 'status' => 'completed', 'updated_at' => now()]);
            if (file_exists($this->filePath)) unlink($this->filePath);
        } catch (\Exception $e) {
            DB::table('import_statuses')->where('id', $statusRecord->id)->update(['status' => 'error']);
        }
    }
}
