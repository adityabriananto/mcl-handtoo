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
        Schema::table('mb_masters', function (Blueprint $table) {
            $table->index('brand_code');
            $table->index('manufacture_barcode');
            $table->index('fulfillment_sku');
            $table->index('seller_sku');
            $table->index('is_disabled');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mb_masters', function (Blueprint $table) {
            $table->dropIndex(['brand_code']);
            $table->dropIndex(['manufacture_barcode']);
            $table->dropIndex(['fulfillment_sku']);
            $table->dropIndex(['seller_sku']);
            $table->dropIndex(['is_disabled']);
            $table->dropIndex(['created_at']);
        });
    }
};
