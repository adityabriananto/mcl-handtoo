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
        Schema::create('fdcam_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Fdcam::class);
            $table->string('manufacture_barcode');
            $table->string('sku')->nullable();
            $table->string('quality'); // (Mandatory | Goods or Defective or Reject to 3pl)
            $table->string('recording')->nullable();
            $table->text('notes')->nullable();
            $table->string('tpl')->nullable();
            $table->string('owner')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fdcam_items');
    }
};
