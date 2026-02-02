<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanStagingData extends Command
{
    // Nama perintah yang akan dijalankan
    protected $signature = 'staging:clean';
    protected $description = 'Hapus data staging order yang lebih dari 3 hari';

    public function handle()
    {
        $days = 3;
        $threshold = now()->subDays($days);

        $this->info("Memulai pembersihan data staging sebelum: " . $threshold);

        // 1. Hapus data staging mentah
        $deletedOrders = DB::table('mb_order_staging')
            ->where('created_at', '<', $threshold)
            ->delete();

        // 2. Hapus log batch upload
        $deletedBatches = DB::table('mb_import_batches')
            ->where('created_at', '<', $threshold)
            ->delete();

        $this->info("Selesai! Berhasil menghapus $deletedOrders baris data dan $deletedBatches log batch.");
    }
}
