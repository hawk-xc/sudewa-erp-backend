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
        Schema::table('cash_unit_transaction_billing_history', function (Blueprint $table) {
            $table->decimal('original_amount', 15, 2)->default(0)->nullable(false)->after('amount');
            $table->decimal('exchange_amount', 15, 2)->default(0)->nullable(false)->after('original_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_unit_transaction_billing_history', function (Blueprint $table) {
            if (Schema::hasColumn('cash_unit_transaction_billing_history', 'original_amount')) {
                $table->dropColumn('original_amount');
            }
            if (Schema::hasColumn('cash_unit_transaction_billing_history', 'exchange_amount')) {
                $table->dropColumn('exchange_amount');
            }
        });
    }
};
