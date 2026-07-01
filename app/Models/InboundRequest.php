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
        'is_arrived',
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
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $like = '%' . $search . '%';
            $query->where(function($q) use ($search, $like) {
                $q->where('reference_number', 'like', $like)
                  ->orWhere('inbound_order_no', 'like', $like)
                  ->orWhereIn('id', function($sub) use ($like) {
                      $sub->select('parent_id')
                          ->from('inbound_orders')
                          ->whereNotNull('parent_id')
                          ->where('reference_number', 'like', $like);
                  });
            });
        }

        // 2. Filter Warehouse
        if (!empty($filters['warehouse'])) {
            $query->where('warehouse_code', $filters['warehouse']);
        }

        // 3. Filter Status
        if (!empty($filters['status'])) {
            $status = $filters['status'];
            $query->where(function($q) use ($status) {
                $q->where('status', $status)
                  ->orWhereIn('id', function($sub) use ($status) {
                      $sub->select('parent_id')
                          ->from('inbound_orders')
                          ->whereNotNull('parent_id')
                          ->where('status', $status);
                  });
            });
        }

        // 4. Filter Date
        // Gunakan whereBetween agar index pada created_at bisa digunakan
        if (!empty($filters['date'])) {
            $startOfDay = $filters['date'] . ' 00:00:00';
            $endOfDay   = $filters['date'] . ' 23:59:59';
            $query->whereBetween('created_at', [$startOfDay, $endOfDay]);
        }

        if (!empty($filters['inbound_order_no'])) {
            $io = $filters['inbound_order_no'];
            $like = '%' . $io . '%';
            $query->where(function($q) use ($io, $like) {
                $q->where('inbound_order_no', 'like', $like)
                  ->orWhereIn('id', function($sub) use ($like) {
                      $sub->select('parent_id')
                          ->from('inbound_orders')
                          ->whereNotNull('parent_id')
                          ->where('inbound_order_no', 'like', $like);
                  });
            });
        }

        // 5. Filter Client (BARU)
        if (!empty($filters['client'])) {
            $query->where('client_name', $filters['client']);
        }

        return $query;
    }
}
