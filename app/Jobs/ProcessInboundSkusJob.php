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
        // 1. Ambil Brand Code & Data Inbound
        $inbound = DB::table('inbound_orders')->where('id', $this->inboundOrderId)->first();
        $brandCode = $inbound ? trim(explode('_', $inbound->comment)[0]) : null;

        // 2. Mapping Fulfillment SKU dari MbMaster
        $uniqueSellerSkus = collect($this->skus)->pluck('seller_sku')->unique()->toArray();
        $mbMasterMap = DB::table('mb_masters')
            ->where('brand_code', $brandCode)
            ->whereIn('seller_sku', $uniqueSellerSkus)
            ->pluck('fulfillment_sku', 'seller_sku');

        // 3. AMBIL DATA EKSISTING DI DATABASE (Untuk Perbandingan)
        // Kita ambil semua detail yang sudah terdaftar untuk Inbound ini
        $existingDetails = DB::table('inbound_order_details')
            ->where('inbound_order_id', $this->inboundOrderId)
            ->get()
            ->keyBy(function($item) {
                return $item->seller_sku . '|' . $item->fulfillment_sku;
            });

        $toInsert = [];
        $toUpdate = [];

        // 4. Proses Grouping Request SKUs (Menangani jika dalam 1 request ada SKU yang sama berulang)
        $groupedRequest = [];
        foreach ($this->skus as $sku) {
            $sellerSku = $sku['seller_sku'];
            $fulfillmentSku = $mbMasterMap->get($sellerSku) ?? '-';
            $key = $sellerSku . '|' . $fulfillmentSku;

            if (isset($groupedRequest[$key])) {
                $groupedRequest[$key]['requested_quantity'] += (int)$sku['requested_quantity'];
            } else {
                $groupedRequest[$key] = [
                    'inbound_order_id'   => $this->inboundOrderId,
                    'seller_sku'         => $sellerSku,
                    'fulfillment_sku'    => $fulfillmentSku,
                    'requested_quantity' => (int)$sku['requested_quantity']
                ];
            }
        }

        // 5. PERBANDINGAN GRANULAR
        foreach ($groupedRequest as $key => $data) {
            if ($existingDetails->has($key)) {
                $existing = $existingDetails->get($key);

                // CEK: Jika Qty berbeda, baru masuk antrean Update
                if ((int)$existing->requested_quantity !== (int)$data['requested_quantity']) {
                    $toUpdate[] = array_merge($data, [
                        'id'         => $existing->id, // Pakai ID asli untuk performa update
                        'updated_at' => now()
                    ]);
                }
                // JIKA Qty sama, biarkan saja (Tidak perlu masuk insert/update)
            } else {
                // Data benar-benar baru
                $toInsert[] = array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // 6. EKSEKUSI KE DATABASE
        DB::transaction(function () use ($toInsert, $toUpdate) {
            // Proses Insert Baru
            if (!empty($toInsert)) {
                DB::table('inbound_order_details')->insert($toInsert);
            }

            // Proses Update yang Berubah saja
            // Menggunakan upsert untuk update massal yang efisien
            if (!empty($toUpdate)) {
                DB::table('inbound_order_details')->upsert(
                    $toUpdate,
                    ['id'], // Gunakan Primary Key untuk matching update
                    ['requested_quantity', 'updated_at']
                );
            }
        });
    }
}
