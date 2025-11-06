<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HandoverBatch extends Model
{
    use HasFactory;

    protected $table = 'handover_batches';

    protected $fillable = [
        'handover_id',
        'three_pl',
        'total_awb',
        'finalized_at',
        'user_id',
        'status',
        'manifest_filename', // Asumsi kolom ini ada
    ];

    protected $casts = [
        'finalized_at' => 'datetime',
    ];

    /**
     * Mendapatkan semua AWB detail yang terkait dengan batch ini.
     */
    public function details(): HasMany
    {
        return $this->hasMany(HandoverDetail::class, 'handover_id', 'handover_id');
    }

    public function awbs(): HasMany
    {
        return $this->hasMany(HandoverDetail::class, 'handover_id');
    }
}
