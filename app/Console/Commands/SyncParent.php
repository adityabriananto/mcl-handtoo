<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InboundRequest;
use App\Models\InboundRequestDetail;
use Illuminate\Support\Facades\DB;

class SyncParent extends Command
{
    // Signature ditambahkan opsi status untuk fleksibilitas jika suatu saat ingin status lain
    protected $signature = 'inbound:sync-parent';
    protected $description = 'Sync received_good quantity from Child to Parent (Only for Partially/Completely orders)';

    public function handle()
    {
        $this->info('Starting Synchronization for Received Goods...');
        $this->comment('Filtering: Only status Partially or Completely');

        // 1. Ambil Parent yang memiliki Children
        // Filter: Hanya proses data yang statusnya sudah 'Partially' atau 'Completely'
        $parents = InboundRequest::whereHas('children')
            ->whereIn('status', ['Partially', 'Completely']) // <--- Filter Status di level Parent
            ->with(['details', 'children.details'])
            ->get();

        if ($parents->isEmpty()) {
            $this->warn('No Partially or Completely records found to sync.');
            return 0;
        }

        $bar = $this->output->createProgressBar($parents->count());
        $bar->start();

        foreach ($parents as $parent) {
            DB::transaction(function () use ($parent) {

                // 2. Loop setiap detail (SKU) yang ada di Parent
                foreach ($parent->details as $parentDetail) {

                    // 3. Hitung total received_good dari semua detail SKU yang sama di level Child
                    $totalReceived = InboundRequestDetail::whereHas('inboundRequest', function($q) use ($parent) {
                            $q->where('parent_id', $parent->id);
                        })
                        ->where('sku_code', $parentDetail->sku_code)
                        ->sum('received_good');

                    // 4. Update kolom received_good di Parent Detail
                    // Kita gunakan updateQuietly() jika Anda punya Observer agar tidak memicu loop
                    $parentDetail->update([
                        'received_good' => $totalReceived
                    ]);
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully synced " . $parents->count() . " parent records.");
    }
}
