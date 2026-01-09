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
        Schema::create('inbound_orders', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('warehouse_code');
            $table->string('delivery_type')->nullable();
            $table->string('seller_warehouse_code')->nullable();
            $table->timestamp('estimate_time');
            $table->string('comment');
            $table->string('reference_number')->nullable();
            $table->string('inbound_order_no')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_orders');
    }
};
