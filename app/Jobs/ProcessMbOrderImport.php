<?php

namespace App\Jobs;

use App\Models\MbOrderStaging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\ImportBatch;

class ProcessMbOrderImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $rows;
    protected $header;
    protected $formatType;
    protected $batchId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $rows, array $header, $formatType, $batchId)
    {
        $this->rows = $rows;
        $this->header = $header;
        $this->formatType = $formatType;
        $this->batchId = $batchId;
    }

    public function handle()
    {
        // Gunakan $this->rows (dengan tanda $)
        if (empty($this->rows)) return;

        DB::beginTransaction();
        try {
            foreach ($this->rows as $row) {
                // Logic mapping header ke row
                $cleanRow = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);

                // Pastikan jumlah kolom row sama dengan header agar tidak error array_combine
                $fullPayload = array_combine(
                    $this->header,
                    array_pad($cleanRow, count($this->header), null)
                );

                if ($this->formatType == 'order_management') {
                    $this->processOrderManagement($cleanRow, $fullPayload);
                } else {
                    $this->processPackageManagement($cleanRow, $fullPayload);
                }
            }

            // Update Progress
            $batch = ImportBatch::find($this->batchId);
            if ($batch) {
                $batch->increment('processed_rows', count($this->rows));
                if ($batch->processed_rows >= $batch->total_rows) {
                    $batch->update(['status' => 'completed']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Import Error: " . $e->getMessage());
            ImportBatch::where('id', $this->batchId)->update([
                'status' => 'failed',
                'notes' => $e->getMessage()
            ]);
        }
    }

    private function processOrderManagement($row, $fullPayload)
    {
        $packageNo = $row[3] ?? null;
        if (!$packageNo) return;

        $existingRecords = MbOrderStaging::where('package_no', $packageNo)->get();

        if ($existingRecords->isNotEmpty()) {
            // Update SEMUA baris yang memiliki package_no ini (multi-brand support)
            MbOrderStaging::where('package_no', $packageNo)->update([
                'order_code'         => $row[0] ?? null,
                'external_order_no'  => $row[1] ?? null,
                'waybill_no'         => $row[2] ?? null,
                'order_status'       => $row[21] ?? null,
                'transaction_number' => $row[39] ?? null,
                'source_format'      => 'order_management',
                'full_payload'       => json_encode($fullPayload),
                'batch_id'           => $this->batchId,
            ]);
        } else {
            MbOrderStaging::create([
                'package_no'         => $packageNo,
                'order_code'         => $row[0] ?? null,
                'external_order_no'  => $row[1] ?? null,
                'waybill_no'         => $row[2] ?? null,
                'order_status'       => $row[21] ?? null,
                'transaction_number' => $row[39] ?? null,
                'source_format'      => 'order_management',
                'full_payload'       => json_encode($fullPayload),
                'batch_id'           => $this->batchId,
                'manufacture_barcode' => null,
            ]);
        }
    }

    private function processPackageManagement($row, $fullPayload)
    {
        $packageNo = $row[1] ?? null;
        $barcode   = $row[29] ?? null;

        if (!$packageNo) return;

        // 1. CARI DATA MASTER (ORDER MANAGEMENT)
        // Ambil data pertama sebagai referensi informasi order
        $master = MbOrderStaging::where('package_no', $packageNo)
                    ->whereNotNull('transaction_number')
                    ->first();

        if (!$master) return;

        // 2. PROSES DUPLIKASI DATA UNTUK SETIAP MB
        // Gunakan updateOrCreate dengan kunci gabungan (Package No + Barcode)
        // Ini memastikan jika ada 3 barcode berbeda di 1 package_no, akan ada 3 baris di DB
        MbOrderStaging::updateOrCreate(
            [
                'package_no'          => $packageNo,
                'manufacture_barcode' => $barcode
            ],
            [
                // Informasi dari file Package Management (CSV Row)
                'waybill_no'          => $row[0] ?? null,
                'order_status'        => $row[2] ?? null,
                'external_order_no'   => $row[4] ?? null,
                'order_code'          => $row[25] ?? null,

                // INFORMASI DUPLIKASI: Mengambil dari data master (Order Management)
                // Semua barcode dalam package_no yang sama akan punya TXN yang sama
                'transaction_number'  => $master->transaction_number,

                // Meta data
                'source_format'       => 'package_management',
                'full_payload'        => json_encode($fullPayload),
                'batch_id'            => $this->batchId,
            ]
        );

        // 3. CLEANUP BARIS KOSONG
        // Hapus baris hasil import Order Management yang belum punya barcode
        // agar tidak muncul data double (satu baris isi barcode, satu baris kosong) di UI.
        MbOrderStaging::where('package_no', $packageNo)
            ->whereNull('manufacture_barcode')
            ->delete();
    }
}
