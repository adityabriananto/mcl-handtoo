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
        Schema::create('fdcams', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number');
            $table->string('order_number')->nullable();
            $table->string('parcel_type'); // FD or CR
            $table->string('recording')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fdcams');
    }
};
