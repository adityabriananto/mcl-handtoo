<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HandoverAwb extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_uuid',
        'airwaybill',
        'three_pl_name',
        'handover_id',
        'scanned_at',
        'is_committed',
        'committed_at',
    ];

    // Casts untuk memastikan tipe data
    protected $casts = [
        'is_committed' => 'boolean',
        'scanned_at' => 'datetime',
        'committed_at' => 'datetime',
    ];

    // Menentukan kolom yang merupakan kunci
    protected $primaryKey = 'id';
}
