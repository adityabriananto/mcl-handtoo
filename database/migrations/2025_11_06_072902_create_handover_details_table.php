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
        Schema::create('handover_details', function (Blueprint $table) {
            $table->id();
            $table->string('handover_id');
            $table->foreign('handover_id')->references('handover_id')->on('handover_batches')->onDelete('cascade');
            $table->string('airwaybill')->unique();
            $table->timestamp('scanned_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_details');
    }
};
