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
        // 1. Ambil Brand Code dari Inbound (Comment)
        $inbound = DB::table('inbound_orders')->where('id', $this->inboundOrderId)->first();
        // Gunakan pembersihan _deffective seperti logic sebelumnya jika perlu
        $brandCode = $inbound ? trim(explode('_', $inbound->comment)[0]) : null;

        // 2. Kumpulkan semua seller_sku unik untuk mencari fulfillment_sku sekaligus
        $uniqueSellerSkus = collect($this->skus)->pluck('seller_sku')->unique()->toArray();

        // 3. Ambil data MbMaster dan jadikan Map [seller_sku => fulfillment_sku]
        $mbMasterMap = DB::table('mb_masters')
            ->where('brand_code', $brandCode)
            ->whereIn('seller_sku', $uniqueSellerSkus)
            ->pluck('fulfillment_sku', 'seller_sku');

        $processed = [];
        foreach ($this->skus as $sku) {
            $sellerSku = $sku['seller_sku'];

            // Cari fulfillment_sku dari map yang sudah kita buat
            $fulfillmentSku = $mbMasterMap->get($sellerSku) ?? '-';

            // Gunakan fulfillment_sku sebagai bagian dari Unique Key
            $key = $sellerSku . '|' . $fulfillmentSku;

            if (isset($processed[$key])) {
                $processed[$key]['requested_quantity'] += (int)$sku['requested_quantity'];
            } else {
                $processed[$key] = [
                    'inbound_order_id'   => $this->inboundOrderId,
                    'seller_sku'         => $sellerSku,
                    'fulfillment_sku'    => $fulfillmentSku, // Didapat dari MbMaster
                    'requested_quantity' => (int)$sku['requested_quantity'],
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            }
        }

        // 4. Proses Upsert dalam Chunk
        $chunks = array_chunk(array_values($processed), 1000);

        foreach ($chunks as $chunk) {
            DB::table('inbound_order_details')->upsert(
                $chunk,
                ['inbound_order_id', 'seller_sku', 'fulfillment_sku'],
                ['requested_quantity', 'updated_at']
            );

            unset($chunk);
        }
    }
}
