<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use HasFactory;

    protected $table = 'mb_import_batches';

    protected $fillable = [
        'file_name',
        'status',
        'total_rows',
        'processed_rows'
    ];
}
