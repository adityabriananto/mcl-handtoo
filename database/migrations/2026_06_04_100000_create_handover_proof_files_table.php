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
        Schema::create('handover_proof_files', function (Blueprint $table) {
            $table->id();
            $table->string('handover_id');
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->timestamps();

            $table->foreign('handover_id')
                  ->references('handover_id')
                  ->on('handover_batches')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_proof_files');
    }
};
