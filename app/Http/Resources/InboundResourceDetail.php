<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboundResourceDetail extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Menggunakan pemetaan warehouse dari config yang kita buat sebelumnya
        $warehouseMap = config('warehouses.list');
        $warehouseName = $warehouseMap[$this->warehouse_code] ?? $this->inbound_warehouse;

        return [
            "inbound_warehouse"        => $warehouseName,
            "skus" => $this->details->map(function($detail) {
                return [
                    "shelf_life_flag"        => "false",
                    "comments"               => $this->comment ?? "",
                    "item_inbounded_damaged" => (string) ($detail->received_damaged ?? 0),
                    "requested_quantity"     => (string) $detail->requested_quantity,
                    "serial_number_flag"     => "false",
                    "fulfillment_sku"        => $detail->seller_sku, // Sesuaikan jika ada field fulfillment_sku
                    "seller_sku"             => [
                        $detail->seller_sku
                    ],
                    "item_inbounded_expired" => "0",
                    "item_inbounded_good"    => (string) ($detail->received_good ?? 0),
                    "sku_status"             => $this->status,
                    "fulfillment_sku_name"   => $detail->product_name ?? "-",
                    "barcodes"               => [
                        $detail->barcode ?? $detail->seller_sku
                    ]
                ];
            }),
            // Format waktu UTC ISO8601
            "inbound_time"             => $this->created_at ? $this->created_at->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z') : null,
            "inbound_warehouse_code"   => $this->warehouse_code,
            "created_at"               => $this->created_at ? $this->created_at->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z') : null,
            "seller_mobile"            => "-", // Sesuaikan field jika ada
            "seller_country"           => "-",
            "fulfillment_order_number" => $this->inbound_order_no,
            "need_reservation"         => "false",
            "seller_postcode"          => "-",
            "seller_warehouse_name"    => $this->inbound_warehouse,
            "updated_at"               => $this->updated_at ? $this->updated_at->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z') : null,
            "estimate_time"            => $this->estimate_time ? \Illuminate\Support\Carbon::parse($this->estimate_time)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z') : null,
            "delivery_type"            => "Dropoff",
            "seller_contact"           => "-",
            "io_status"                => $this->status,
            "comments"                 => $this->comment,
            "marketplace"              => "LAZADA_ID",
            "warehouse_address"        => "-",
            "reservation_order"        => "-",
            "shop_name"                => "-",
            "reference_number"         => $this->reference_number,
            "seller_address"           => "-",
            "seller_city"              => "-",
            "reservation_status"       => "-",
            "warehouse_name"           => $warehouseName,
            "io_type"                  => "normal",
            "io_number"                => $this->inbound_order_no
        ];
    }
}
