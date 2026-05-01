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
        Schema::table('cash_flows', function (Blueprint $table) {
            // Remove previous turn's relation
            if (Schema::hasColumn('cash_flows', 'finance_billing_item_id')) {
                $table->dropForeign(['finance_billing_item_id']);
                $table->dropColumn('finance_billing_item_id');
            }
            
            $table->foreignId('unit_transaction_billing_history_id')
                ->nullable()
                ->after('account_id')
                ->constrained('unit_transaction_billing_histories')
                ->cascadeOnDelete();
        });

        Schema::table('finance_billings', function (Blueprint $table) {
            $table->foreignId('cash_flow_id')
                ->nullable()
                ->after('unit_transaction_billing_id')
                ->constrained('cash_flows')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->dropForeign(['cash_flow_id']);
            $table->dropColumn('cash_flow_id');
        });

        Schema::table('cash_flows', function (Blueprint $table) {
            $table->dropForeign(['unit_transaction_billing_history_id']);
            $table->dropColumn('unit_transaction_billing_history_id');
        });
    }
};
