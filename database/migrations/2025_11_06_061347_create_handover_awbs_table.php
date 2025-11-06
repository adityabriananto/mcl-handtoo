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
        Schema::create('handover_awbs', function (Blueprint $table) {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->string('airwaybill')->index();
            $table->string('three_pl_name')->index();
            $table->string('handover_id')->index();
            $table->timestamp('scanned_at');
            $table->boolean('is_committed')->default(false);
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_awbs');
    }
};
