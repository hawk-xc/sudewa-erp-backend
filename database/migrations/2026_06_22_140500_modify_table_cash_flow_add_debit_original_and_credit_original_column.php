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
        if (!Schema::hasColumn('cash_flows', 'debet_original')) {
            Schema::table('cash_flows', function (Blueprint $table) {
                $table->decimal('debet_original', 15, 2)->nullable(false)->default(0)->after('debet');
                $table->decimal('credit_original', 15, 2)->nullable(false)->default(0)->after('credit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('cash_flows', 'debet_original')) {
            Schema::table('cash_flows', function (Blueprint $table) {
                $table->dropColumn('debet_original');
                $table->dropColumn('credit_original');
            });
        }
    }
};
