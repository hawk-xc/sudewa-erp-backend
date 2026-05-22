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
        if (!Schema::hasColumn('cash_flows', 'transaction_category')) {
            Schema::table('cash_flows', function (Blueprint $table) {
                $table->enum('transaction_category', [
                    'general',
                    'operational',
                    'director_receivable',
                    'shareholder_receivable',
                    'receivable',
                    'inventory'
                ])
                ->default('general')
                ->nullable(false)
                ->after('unit_transaction_billing_history_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('cash_flows', 'transaction_category')) {
            Schema::table('cash_flows', function (Blueprint $table) {
                $table->dropColumn('transaction_category');
            });
        }
    }
};
