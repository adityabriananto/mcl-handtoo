<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationRequest extends Model
{
    //
    protected $table = 'cancellation_requests';

    /**
     * Atribut yang dapat diisi secara massal (Mass Assignment).
     */
    protected $fillable = [
        'tracking_number',
        'cancel_reason',
        'status',
        'reason',
    ];
}
