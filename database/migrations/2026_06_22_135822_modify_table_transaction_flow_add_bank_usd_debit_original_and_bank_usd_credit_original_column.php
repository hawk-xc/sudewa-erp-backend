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
        if (!Schema::hasColumn('transaction_flows', 'bank_usd_debit_original')) {
            Schema::table('transaction_flows', function (Blueprint $table) {
                $table->decimal('bank_usd_debit_original', 15, 2)->nullable(false)->default(0)->after('bank_usd_debit');
                $table->decimal('bank_usd_credit_original', 15, 2)->nullable(false)->default(0)->after('bank_usd_credit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('transaction_flows', 'bank_usd_debit_original')) {
            Schema::table('transaction_flows', function (Blueprint $table) {
                $table->dropColumn('bank_usd_debit_original');
                $table->dropColumn('bank_usd_credit_original');
            });
        }
    }
};
