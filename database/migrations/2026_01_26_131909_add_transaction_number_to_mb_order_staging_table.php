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
        Schema::table('mb_order_staging', function (Blueprint $table) {
            //
            $table->string('transaction_number')->after('waybill_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mb_order_staging', function (Blueprint $table) {
            //
        });
    }
};
