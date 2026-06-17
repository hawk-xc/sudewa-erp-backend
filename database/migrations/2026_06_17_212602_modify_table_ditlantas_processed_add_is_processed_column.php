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
        Schema::table('ditlantas_processed', function (Blueprint $table) {
            $table->boolean('is_processed')->default(false)->after('process_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ditlantas_processed', function (Blueprint $table) {
            $table->dropColumn('is_processed');
        });
    }
};
