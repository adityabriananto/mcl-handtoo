<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    // app/Http/Resources/InboundResource.php

    public function toArray($request)
    {
        return [
            'reference_number' => $this->reference_number,
            'io_type'             => $this->parent_id ? 'Child' : ($this->children->count() > 0 ? 'Parent' : 'Single'),
            'status'           => $this->status,
            'warehouse'        => $this->warehouse_code,

            // Jika ini Parent, tampilkan daftar referensi anak-anaknya
            'split_children'   => $this->children->map(function($child) {
                return [
                    'reference_number'    => $child->reference_number,
                    'status' => $child->status
                ];
            }),

            // Jika ini Child, tampilkan referensi bapaknya
            'parent_reference_number'       => $this->parent ? $this->parent->reference_number : null,

            'total_qty'        => $this->details->sum('requested_quantity'),
            'items'            => $this->details->map(function($detail) {
                return [
                    'sku'  => $detail->seller_sku,
                    'qty'  => $detail->requested_quantity,
                ];
            }),
            'created_at'       => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
