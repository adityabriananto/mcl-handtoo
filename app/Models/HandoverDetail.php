<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverDetail extends Model
{
    use HasFactory;

    protected $table = 'handover_details';

    protected $fillable = [
        'handover_id',
        'airwaybill',
        'scanned_at',
        'is_sent_api',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    /**
     * Mendapatkan batch yang memiliki detail AWB ini.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(HandoverBatch::class, 'handover_id', 'handover_id');
    }
}
