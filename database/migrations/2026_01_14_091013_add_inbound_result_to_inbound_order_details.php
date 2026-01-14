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
    Schema::table('inbound_order_details', function (Blueprint $table) {
        $table->integer('received_good')->default(0)->after('requested_quantity');
        $table->integer('received_damaged')->default(0)->after('received_good');
        $table->integer('received_expired')->default(0)->after('received_damaged');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbound_order_details', function (Blueprint $table) {
            //
        });
    }
};
