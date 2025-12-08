<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TplPrefix extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'tpl_name',
        'prefixes',
        'is_active',
    ];

    // Mengubah kolom 'prefixes' dari JSON ke array/object PHP secara otomatis
    protected $casts = [
        'prefixes' => 'array',
        'is_active' => 'boolean',
    ];
}
