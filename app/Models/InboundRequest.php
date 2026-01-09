<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundRequest extends Model
{
    protected $table = 'inbound_orders';

    protected $fillable = [
        'client_name',
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
        // 1. Filter Pencarian (Search)
        // Mencari di Parent atau di Child sekaligus
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('reference_number', 'like', '%' . $filters['search'] . '%')
                ->orWhereHas('children', function($childQuery) use ($filters) {
                    $childQuery->where('reference_number', 'like', '%' . $filters['search'] . '%');
                });
            });
        }

        // 2. Filter Warehouse
        if (!empty($filters['warehouse'])) {
            $query->where('warehouse_code', $filters['warehouse']);
        }

        // 3. Filter Status
        // Jika memfilter 'Pending', kita tampilkan Parent yang masih Pending
        // ATAU Parent yang punya Child dengan status Pending
        if (!empty($filters['status'])) {
            $query->where(function($q) use ($filters) {
                $q->where('status', $filters['status'])
                ->orWhereHas('children', function($childQuery) use ($filters) {
                    $childQuery->where('status', $filters['status']);
                });
            });
        }

        return $query;
    }
}
