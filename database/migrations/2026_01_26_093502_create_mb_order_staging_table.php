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
        Schema::create('mb_order_staging', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->string('package_no')->index();
            $table->string('waybill_no')->nullable();
            $table->string('manufacture_barcode')->nullable(); // Khusus Package Mgmt
            $table->string('external_order_no')->nullable();   // ExternalOrderNo / External Order No.
            $table->string('order_code')->nullable();          // OrderCode / Sales Order Code
            $table->string('source_format')->nullable();       // order_management / package_management
            $table->timestamps();

            // Index gabungan untuk pencarian cepat data itemized
            $table->index(['package_no', 'manufacture_barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mb_order_staging');
    }
};
