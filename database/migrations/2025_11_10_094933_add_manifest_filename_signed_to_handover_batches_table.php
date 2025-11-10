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
        Schema::table('handover_batches', function (Blueprint $table) {
            //
            $table->string('manifest_name_signed')->nullable()->after('manifest_filename');
            $table->timestamp('signed_at')->nullable()->after('manifest_name_signed'); // Waktu Sign
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handover_batches', function (Blueprint $table) {
            //
            $table->dropColumn('manifest_name_signed');
            $table->dropColumn('signed_at');
        });
    }
};
