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
        if (Schema::hasTable('finance_assets')) {
            Schema::table('finance_assets', function (Blueprint $table) {
                if (Schema::hasColumn('finance_assets', 'depreciation')) {
                    $table->dropColumn('depreciation');
                }
                if (Schema::hasColumn('finance_assets', 'residual_value')) {
                    $table->dropColumn('residual_value');
                }
                if (Schema::hasColumn('finance_assets', 'final_value')) {
                    $table->dropColumn('final_value');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('finance_assets')) {
            Schema::table('finance_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('finance_assets', 'depreciation')) {
                    $table->decimal('depreciation', 15, 2)->nullable()->after('economic_age');
                }
                if (!Schema::hasColumn('finance_assets', 'residual_value')) {
                    $table->decimal('residual_value', 15, 2)->nullable()->after('depreciation');
                }
                if (!Schema::hasColumn('finance_assets', 'final_value')) {
                    $table->decimal('final_value', 15, 2)->nullable()->after('residual_value');
                }
            });
        }
    }
};
