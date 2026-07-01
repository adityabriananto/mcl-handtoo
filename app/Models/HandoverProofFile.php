<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandoverProofFile extends Model
{
    protected $table = 'handover_proof_files';

    protected $fillable = [
        'handover_id',
        'filename',
        'original_name',
        'mime_type',
        'file_size',
        'disk',
        'path',
    ];

    public function batch()
    {
        return $this->belongsTo(HandoverBatch::class, 'handover_id', 'handover_id');
    }
}
