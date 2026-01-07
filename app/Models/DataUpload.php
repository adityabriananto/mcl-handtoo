<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DataUpload extends Model
{
    //
    use HasFactory;

    protected $table = 'data_uploads';

    protected $fillable = [
        'airwaybill',
        'order_number',
        'owner_code',
        'owner_name',
        'qty',
        'platform_name'
    ];
}
