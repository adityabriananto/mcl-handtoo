<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessInboundSkusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $inboundOrderId;
    protected $skus;

    // Timeout job 1 jam agar aman untuk 100K data
    public $timeout = 3600;

    public function __construct($inboundOrderId, $skus)
    {
        $this->inboundOrderId = $inboundOrderId;
        $this->skus = $skus;
    }

    public function handle()
    {
        $processed = [];
        foreach ($this->skus as $sku) {
            $key = $sku['seller_sku'] . '|' . ($sku['fulfillment_sku'] ?? '');

            if (isset($processed[$key])) {
                $processed[$key]['requested_quantity'] += (int)$sku['requested_quantity'];
            } else {
                $processed[$key] = [
                    'inbound_order_id'   => $this->inboundOrderId,
                    'seller_sku'         => $sku['seller_sku'],
                    'fulfillment_sku'    => $sku['fulfillment_sku'] ?? null,
                    'requested_quantity' => (int)$sku['requested_quantity'],
                    'created_at'         => now(), // Isi eksplisit untuk insert
                    'updated_at'         => now(),
                ];
            }
        }

        $chunks = array_chunk(array_values($processed), 1000);

        foreach ($chunks as $chunk) {
            // UPSERT jauh lebih efisien dan menangani created_at dengan benar
            DB::table('inbound_order_details')->upsert(
                $chunk,
                ['inbound_order_id', 'seller_sku', 'fulfillment_sku'], // Unique key
                ['requested_quantity', 'updated_at'] // Kolom yang diupdate jika sudah ada
            );

            unset($chunk);
        }
    }
}
