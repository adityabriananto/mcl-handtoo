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
        Schema::create('data_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('airwaybill');
            $table->string('order_number')->nullable();
            $table->string('owner_code')->nullable();
            $table->string('owner_name')->nullable();
            $table->integer('qty')->nullable();
            $table->string('platform_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_uploads');
    }
};
