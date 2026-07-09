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
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->foreignId('goods_transaction_billing_id')->after('unit_transaction_billing_id')->nullable()->constrained('goods_transaction_billings')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            //
        });
    }
};
