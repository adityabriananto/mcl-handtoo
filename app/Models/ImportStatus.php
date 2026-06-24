<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportStatus extends Model
{
    protected $table = 'import_statuses';

    protected $fillable = [
        'filename',
        'total_rows',
        'processed_rows',
        'status',
    ];
}
