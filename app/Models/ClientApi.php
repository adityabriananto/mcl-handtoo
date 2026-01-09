<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClientApi extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     * Secara default Laravel akan mencari 'client_apis',
     * jadi ini bersifat opsional namun baik untuk kejelasan.
     */
    protected $table = 'client_apis';

    /**
     * Atribut yang dapat diisi secara massal (Mass Assignment).
     */
    protected $fillable = [
        'client_name',
        'client_code',
        'client_url',
        'client_token',
        'access_token',
    ];

    /**
     * Opsi Tambahan: Boot method untuk otomatis membuat token
     * saat Client baru didaftarkan jika token kosong.
     */
}
