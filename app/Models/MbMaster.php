<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbMaster extends Model
{
    //
    protected $table = 'mb_masters';

    protected $fillable = [
        'brand_code',
        'brand_name',
        'manufacture_barcode',
        'seller_sku',
        'fulfillment_sku',
        'is_disabled'
    ];
}
