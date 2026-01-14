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
        // Hapus dd($this) agar data bisa mengalir ke frontend/export

        return [
            'reference_number' => $this->reference_number,
            'brand_os'         => $this->comment,
            'io_number'        => $this->inbound_order_no,
            'io_type'          => $this->parent_id ? 'Child' : ($this->children->count() > 0 ? 'Parent' : 'Single'),
            'status'           => $this->status,
            'io_status'        => $this->io_status,
            'warehouse_code'   => $this->warehouse_code,
            'warehouse_name'   => $this->inbound_warehouse,

            // Relasi Dokumen (Hanya muncul jika relevan menggunakan whenLoaded)
            'relation' => [
                'is_parent' => $this->children->count() > 0,
                'is_child'  => $this->parent_id !== null,

                // Jika ini Parent, tampilkan daftar anak-anaknya
                'children' => $this->children->map(function($child) {
                    return [
                        'id'               => $child->id,
                        'reference_number' => $child->reference_number,
                        'io_number'        => $child->inbound_order_no,
                        'status'           => $child->status,
                        'io_status'        => $child->io_status,
                        'total_qty'        => $child->details->sum('requested_quantity'),
                    ];
                }),

                // Jika ini Child, tampilkan referensi bapaknya
                'parent_ref' => $this->parent ? $this->parent->reference_number : null,
            ],

            // Data Barang (Details)
            'summary' => [
                'total_sku'   => $this->details->count(),
                'total_units' => (int) $this->details->sum('requested_quantity'),
            ],

            'items' => $this->details->map(function($detail) {
                return [
                    'sku'              => $detail->seller_sku,
                    'product_name'     => $detail->product_name ?? '-',
                    'qty'              => (int) $detail->requested_quantity,
                    'received_good'    => (int) $detail->received_good
                ];
            }),

            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
