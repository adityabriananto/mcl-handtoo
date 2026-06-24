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

    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function handle()
    {
        $sellerSkus = [];

        // Bungkus dalam transaksi agar lebih cepat dan aman
        DB::transaction(function () use (&$sellerSkus) {
            foreach ($this->rows as $data) {
                // Pastikan index 3 (fulfillment_sku) tidak kosong
                if (empty($data[3])) continue;

                $sellerSku = !empty($data[4]) ? trim($data[4]) : null;
                if ($sellerSku) {
                    $sellerSkus[] = $sellerSku;
                }

                /**
                 * Menggunakan updateOrCreate:
                 * Jika fulfillment_sku sudah ada, maka brand_code dll akan di-update (edit).
                 * Jika belum ada, maka akan dibuat data baru (create).
                 */
                MbMaster::updateOrCreate(
                    [
                        'brand_code'      => $data[0],
                        'fulfillment_sku' => $data[3]
                    ], // Key unik
                    [
                        'brand_name'          => $data[1],
                        'manufacture_barcode' => $data[2],
                        'seller_sku'          => $sellerSku,
                    ]
                );
            }
        });

        // Setelah chunk master data di-import, re-check inbound details
        // yang memiliki flag missing master data dan seller_sku yang cocok.
        if (!empty($sellerSkus)) {
            RecheckInboundMasterDataJob::dispatch(array_unique($sellerSkus))->onQueue('mb-master-import');
        }
    }
}
