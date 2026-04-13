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
        $inbound = DB::table('inbound_orders')->where('id', $this->inboundOrderId)->first();
        $brandCode = $inbound ? trim(explode('_', $inbound->comment)[0]) : null;

        $uniqueSellerSkus = collect($this->skus)->pluck('seller_sku')->unique()->toArray();

        $mbMasterMap = DB::table('mb_masters')
            ->where('brand_code', $brandCode)
            ->whereIn('seller_sku', $uniqueSellerSkus)
            ->pluck('fulfillment_sku', 'seller_sku');

        $processed = [];
        foreach ($this->skus as $sku) {
            $sellerSku = $sku['seller_sku'];
            $fulfillmentSku = $mbMasterMap->get($sellerSku) ?? '-';

            // Pastikan key PHP sama persis dengan kombinasi kolom unik di DB
            $key = $this->inboundOrderId . '|' . $sellerSku . '|' . $fulfillmentSku;

            if (isset($processed[$key])) {
                $processed[$key]['requested_quantity'] += (int)$sku['requested_quantity'];
            } else {
                $processed[$key] = [
                    'inbound_order_id'   => $this->inboundOrderId,
                    'seller_sku'         => $sellerSku,
                    'fulfillment_sku'    => $fulfillmentSku,
                    'requested_quantity' => (int)$sku['requested_quantity'],
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            }
        }

        // 4. Eksekusi Upsert
        // PENTING: Pastikan di tabel inbound_order_details ada UNIQUE INDEX
        // untuk kombinasi (inbound_order_id, seller_sku, fulfillment_sku)
        $chunks = array_chunk(array_values($processed), 1000);

        foreach ($chunks as $chunk) {
            DB::table('inbound_order_details')->upsert(
                $chunk,
                ['inbound_order_id', 'seller_sku', 'fulfillment_sku'], // Unique Keys
                ['requested_quantity', 'updated_at'] // Kolom yang diupdate jika kunci cocok
            );
        }
    }
}
