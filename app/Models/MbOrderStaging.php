<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MbOrderStaging extends Model
{
    use HasFactory;

    // Nama tabel disesuaikan dengan konsep "Order Staging"
    protected $table = 'mb_order_staging';

    /**
     * Kolom yang dapat diisi secara massal (Mass Assignment).
     */
    protected $fillable = [
        'batch_id',
        'package_no',
        'waybill_no',
        'external_order_no',
        'order_status',
        'transaction_number',
        'order_code',
        'courier_name',
        'manufacture_barcode',
        'source_format',
        'full_payload',
        'upload_batch_id',
    ];

    /**
     * Casting atribut.
     * Ini akan mengubah kolom 'full_payload' menjadi array secara otomatis
     * saat dipanggil dari database.
     */
    protected $casts = [
        'full_payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Helper untuk mengambil data spesifik dari payload mentah.
     * Contoh: $order->getRawValue(10) untuk mengambil berat (Gross Weight) pada Format 2.
     */
    public function getRawValue($index)
    {
        return $this->full_payload[$index] ?? null;
    }
}
