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
        // 1. Kelompokkan SKU yang sama di memori
        $processed = [];
        foreach ($this->skus as $sku) {
            $key = $sku['seller_sku'] . '|' . ($sku['fulfillment_sku'] ?? '');
            if (isset($processed[$key])) {
                $processed[$key]['qty'] += (int)$sku['requested_quantity'];
            } else {
                $processed[$key] = [
                    'seller_sku' => $sku['seller_sku'],
                    'fulfillment_sku' => $sku['fulfillment_sku'] ?? null,
                    'qty' => (int)$sku['requested_quantity']
                ];
            }
        }

        // 2. Proses per batch (1000 SKU per batch) agar RAM tidak meledak
        $chunks = array_chunk(array_values($processed), 1000);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $item) {
                    DB::table('inbound_order_details')->updateOrInsert(
                        [
                            'inbound_order_id' => $this->inboundOrderId,
                            'seller_sku'       => $item['seller_sku'],
                            'fulfillment_sku'  => $item['fulfillment_sku'],
                        ],
                        [
                            'requested_quantity' => DB::raw("requested_quantity + " . $item['qty']),
                            'updated_at'         => now(),
                        ]
                    );
                }
            });
            unset($chunk); // Paksa hapus dari RAM
        }
    }
}
