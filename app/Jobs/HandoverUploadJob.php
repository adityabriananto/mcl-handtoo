<?php

namespace App\Jobs;

use App\Models\DataUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandoverUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if (!file_exists($this->filePath)) {
            Log::error("File tidak ditemukan: {$this->filePath}");
            return;
        }

        $handle = fopen($this->filePath, 'r');
        fgetcsv($handle); // Skip header

        $chunkSize = 500; // Simpan per 500 data untuk efisiensi database
        $batch = [];

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty($data[39])) continue;

            $batch[] = [
                'airwaybill'    => $data[2],
                'order_number'  => $data[39] ?? null,
                'owner_code'    => $data[20] ?? null,
                'owner_name'    => $data[21] ?? null,
                'qty'           => (int) ($data[30] ?? 0),
                'platform_name' => $data[7] ?? null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            // Jika batch sudah mencapai 500, lakukan UPSERT
            if (count($batch) >= $chunkSize) {
                $this->processUpsert($batch);
                $batch = [];
            }
        }

        // Proses sisa data yang belum mencapai 500
        if (!empty($batch)) {
            $this->processUpsert($batch);
        }

        fclose($handle);

        // Hapus file temporary agar storage tidak penuh
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    private function processUpsert(array $data)
    {
        // Upsert akan Update jika airwaybill sudah ada, atau Insert jika belum ada.
        // Sangat cepat karena hanya mengirim 1 query untuk 500 baris.
        DataUpload::upsert(
            $data,
            ['order_number'], // Kolom unik penentu
            ['airwaybill', 'owner_code', 'owner_name', 'qty', 'platform_name', 'updated_at'] // Kolom yang diupdate
        );
    }
}
