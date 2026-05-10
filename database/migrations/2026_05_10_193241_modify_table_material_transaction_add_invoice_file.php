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
        Schema::table('material_transactions', function (Blueprint $table) {
            $table->string('invoice_file')->nullable(true)->after('is_refunded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_transactions', function (Blueprint $table) {
            $table->dropColumn('invoice_file');
        });
    }
};
