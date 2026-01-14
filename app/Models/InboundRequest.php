<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundRequest extends Model
{
    protected $table = 'inbound_orders';

    protected $fillable = [
        // Kolom Lama
        'client_name',
        'warehouse_code',
        'delivery_type',
        'seller_warehouse_code',
        'estimate_time',
        'comment',
        'reference_number',
        'inbound_order_no',
        'status',
        'parent_id',

        // List Baru (Mapping dari Ekspor)
        'fulfillment_order_no',
        'shop_name',
        'created_time',
        'estimated_inbound_time',
        'inbounded_time',
        'fulfillment_sku',
        'seller_sku',
        'product_name',
        'inbound_warehouse',
        'reservation_order',
        'sku_status',
        'io_status',
        'cainiao_consolidation_service',
        'items_requested',
        'items_inbounded_good',
        'lgf_quantity',
        'lgf_status',
        'lgf_date',
        'rep_planning_order_id',
        'rep_order_quantity',
        'rep_planning_order_date',
        'alert',
        'alert_detail',
        'items_inbounded_damaged',
        'items_inbounded_expired',
        'cogs',
        'cogs_currency',
        'seller_comment',
        'seller_address_details',
        'vas_needed',
        'vas_instruction',
        'vas_order',
        'ontime',
        'lmo_seller',
        'exception',
        'temperature',
        'product_type'
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
