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
        Schema::table('tpl_prefixes', function (Blueprint $table) {
            $table->string('handover_prefix')->nullable();
            $table->integer('counter')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tpl_prefixes', function (Blueprint $table) {
            $table->dropColumn('handover_prefix');
            $table->dropColumn('counter');
        });
    }
};
