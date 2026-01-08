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
        Schema::create('inbound_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_order_id')->references('id')->on('inbound_orders')->onDelete('cascade');
            $table->string('seller_sku')->nullable();
            $table->string('fulfillment_sku')->nullable();
            $table->integer('requested_quantity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_order_details');
    }
};
