<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index untuk tabel Header (inbound_requests)
        Schema::table('inbound_orders', function (Blueprint $table) {
            // Index untuk pencarian berdasarkan Reference Number (dari Job)
            $table->index('reference_number');

            // Index untuk pencarian berdasarkan IO Number (dari Dashboard/Filter)
            $table->index('inbound_order_no');
        });

        // Index untuk tabel Detail (inbound_order_details)
        Schema::table('inbound_order_details', function (Blueprint $table) {
            // Index pada seller_sku karena sering di-query di dalam loop Job
            $table->index('seller_sku');

            // Index gabungan (Composite Index) untuk optimasi UpdateOrCreate
            // Ini mempercepat pencarian spesifik: "Cari SKU X milik Order ID Y"
            $table->index(['inbound_order_id', 'seller_sku'], 'idx_order_sku');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_orders', function (Blueprint $table) {
            $table->dropIndex(['reference_number']);
            $table->dropIndex(['inbound_order_no']);
        });

        Schema::table('inbound_order_details', function (Blueprint $table) {
            $table->dropIndex(['seller_sku']);
            $table->dropIndex('idx_order_sku');
        });
    }
};
