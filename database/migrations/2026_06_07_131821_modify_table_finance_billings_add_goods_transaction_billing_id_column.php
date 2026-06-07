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
            $table->dropForeign(['unit_transaction_billing_id']);
            $table->unsignedBigInteger('unit_transaction_billing_id')->nullable()->change();
            $table->foreign('unit_transaction_billing_id')
                ->references('id')
                ->on('unit_transaction_billings')
                ->cascadeOnDelete();
            
            $table->foreignId('goods_transaction_billing_id')
                ->nullable()
                ->after('unit_transaction_billing_id')
                ->constrained('goods_transaction_billings')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->dropForeign(['goods_transaction_billing_id']);
            $table->dropColumn('goods_transaction_billing_id');
            
            $table->dropForeign(['unit_transaction_billing_id']);
            $table->unsignedBigInteger('unit_transaction_billing_id')->nullable(false)->change();
            $table->foreign('unit_transaction_billing_id')
                ->references('id')
                ->on('unit_transaction_billings')
                ->cascadeOnDelete();
        });
    }
};
