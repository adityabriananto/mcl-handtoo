<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundRequestDetail extends Model
{
    //
    protected $table = 'inbound_order_details';

    protected $fillable = [
        'inbound_order_id',
        'fulfillment_sku',
        'seller_sku',
        'product_name',
        'sku_status',
        'requested_quantity',     // Mapping dari # Items Requested
        'received_good',          // Mapping dari # Items Inbounded - Good
        'received_damaged',       // Mapping dari # Items Inbounded - Damaged
        'received_expired',       // Mapping dari # Items Inbounded - Expired
        'cogs',
        'cogs_currency',
        'seller_comment',
        'temperature',
        'product_type',
    ];
}
