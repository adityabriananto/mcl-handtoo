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
        Schema::create('mb_masters', function (Blueprint $table) {
            $table->id();
            $table->string('brand_code');
            $table->string('brand_name');
            $table->string('manufacture_barcode')->nullable();
            $table->string('seller_sku')->nullable();
            $table->string('fulfillment_sku')->nullable();
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mb_masters');
    }
};
