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
        Schema::create('handover_batches', function (Blueprint $table) {
            $table->id(); // Primary Key ID

            // Kolom Kritis
            $table->string('handover_id')->unique(); // Untuk validasi Start Button
            $table->string('three_pl');              // Carrier (JNE, SICEPAT, dll.)
            $table->integer('total_awb')->default(0); // Jumlah AWB yang diselesaikan
            $table->timestamp('finalized_at')->nullable(); // Waktu Finalisasi

            // Kolom Audit (Jika perlu)
            $table->unsignedBigInteger('user_id')->nullable(); // User yang menyelesaikan
            $table->string('status')->default('completed');    // completed/canceled

            $table->timestamps(); // created_at (saat batch dibuat) dan updated_at

            // Optional: Foreign key ke tabel users (jika ada)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_batches');
    }
};
