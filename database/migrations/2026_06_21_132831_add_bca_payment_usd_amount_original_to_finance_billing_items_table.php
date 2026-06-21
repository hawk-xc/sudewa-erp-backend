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
        Schema::table('finance_billing_items', function (Blueprint $table) {
            $table->decimal('bca_payment_usd_amount_original', 15, 2)
                ->nullable()
                ->after('bca_payment_usd_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_billing_items', function (Blueprint $table) {
            $table->dropColumn('bca_payment_usd_amount_original');
        });
    }
};
