<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundRequestDetail extends Model
{
    //
    protected $table = 'inbound_order_details';

    protected $fillable = [
        'inbound_order_id',
        'seller_sku',
        'fulfillment_sku',
        'requested_quantity',
    ];
}
