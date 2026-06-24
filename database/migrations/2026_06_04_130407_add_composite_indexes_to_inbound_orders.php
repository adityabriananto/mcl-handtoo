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
            $table->index(['parent_id', 'status'], 'idx_inbound_orders_parent_status');
            $table->index(['parent_id', 'created_at'], 'idx_inbound_orders_parent_created');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_orders', function (Blueprint $table) {
            $table->dropIndex('idx_inbound_orders_parent_status');
            $table->dropIndex('idx_inbound_orders_parent_created');
        });
    }
};
