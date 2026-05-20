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
        Schema::table('unit_type_detail_ppns', function (Blueprint $table) {
            $table->string('nsfp_input')->nullable(true)->after('nsfp_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_type_detail_ppns', function (Blueprint $table) {
            $table->dropColumn('nsfp_input');
        });
    }
};
