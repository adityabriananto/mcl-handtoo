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
            'id'               => $this->id,
            'reference_number' => $this->reference_number,
            'brand_os'         => $this->comment,
            'io_number'        => $this->inbound_order_no,
            'io_type'          => $this->parent_id ? 'Child' : ($this->children->count() > 0 ? 'Parent' : 'Single'),
            'status'           => $this->status,
            'warehouse'        => $this->warehouse_code,

            // Relasi Dokumen (Hanya muncul jika relevan menggunakan whenLoaded)
            'relation' => [
                'is_parent' => $this->children->count() > 0,
                'is_child'  => $this->parent_id !== null,

                // Jika ini Parent, tampilkan daftar anak-anaknya
                'children' => $this->children->map(function($child) {
                    return [
                        'id'               => $child->id,
                        'reference_number' => $child->reference_number,
                        'status'           => $child->status,
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
                    'sku'          => $detail->seller_sku,
                    'product_name' => $detail->product_name ?? '-',
                    'qty'          => (int) $detail->requested_quantity,
                ];
            }),

            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
