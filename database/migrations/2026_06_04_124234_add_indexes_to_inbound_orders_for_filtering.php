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
        Schema::table('inbound_orders', function (Blueprint $table) {
            $table->index('status', 'idx_inbound_orders_status');
            $table->index('warehouse_code', 'idx_inbound_orders_warehouse');
            $table->index('client_name', 'idx_inbound_orders_client');
            $table->index('created_at', 'idx_inbound_orders_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_orders', function (Blueprint $table) {
            $table->dropIndex('idx_inbound_orders_status');
            $table->dropIndex('idx_inbound_orders_warehouse');
            $table->dropIndex('idx_inbound_orders_client');
            $table->dropIndex('idx_inbound_orders_created_at');
        });
    }
};
