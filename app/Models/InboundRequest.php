<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundRequest extends Model
{
    protected $table = 'inbound_orders';

    protected $fillable = [
        'warehouse_code',
        'delivery_type',
        'seller_warehouse_code',
        'estimate_time',
        'comment',
        'reference_number',
        'status',
        'parent_id'
    ];

    /**
     * Relasi ke baris SKU (Detail)
     */
    public function details() {
        return $this->hasMany(InboundRequestDetail::class, 'inbound_order_id', 'id');
    }

    /**
     * Relasi ke hasil split (Sub-IO)
     */
    public function children() {
        return $this->hasMany(InboundRequest::class, 'parent_id', 'id');
    }

    /**
     * Relasi balik ke dokumen utama (Induk)
     */
    public function parent() {
        return $this->belongsTo(InboundRequest::class, 'parent_id', 'id');
    }

    /**
     * Scope Filter untuk pencarian di Dashboard
     */
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('reference_number', 'like', '%' . $search . '%');
        })->when($filters['warehouse'] ?? null, function ($query, $warehouse) {
            $query->where('warehouse_code', $warehouse);
        })->when($filters['status'] ?? null, function ($query, $status) {
            $query->where('status', $status);
        });
    }
}
