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
        if (Schema::hasTable('unit_type_detail_ppns')) {
            Schema::table('unit_type_detail_ppns', function (Blueprint $table) {
                if (!Schema::hasColumn('unit_type_detail_ppns', 'nsfp_number')) {
                    $table->string('nsfp_number')->nullable(true)->after('nsfp_amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('unit_type_detail_ppns')) {
            Schema::table('unit_type_detail_ppns', function (Blueprint $table) {
                if (Schema::hasColumn('unit_type_detail_ppns', 'nsfp_number')) {
                    $table->dropColumn('nsfp_number');
                }
            });
        }
    }
};
