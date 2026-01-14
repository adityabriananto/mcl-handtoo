<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan kolom ke table detail
        Schema::table('inbound_order_details', function (Blueprint $table) {
            $table->string('product_name')->nullable();
            $table->string('sku_status')->nullable();
            $table->decimal('cogs', 15, 2)->nullable();
            $table->string('cogs_currency', 10)->nullable();
            $table->text('seller_comment')->nullable();
            $table->string('temperature')->nullable();
            $table->string('product_type')->nullable();
        });

        // 2. Hapus kolom dari table utama (inbound_requests)
        Schema::table('inbound_orders', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_sku', 'seller_sku', 'product_name', 'sku_status',
                'items_requested', 'items_inbounded_good', 'items_inbounded_damaged',
                'items_inbounded_expired', 'cogs', 'cogs_currency', 'seller_comment',
                'temperature', 'product_type'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
