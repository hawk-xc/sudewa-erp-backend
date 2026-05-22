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
        Schema::create('unit_transaction_refund_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('unit_transaction_refund_id');
            $table->string('code')->unique();
            $table->decimal('amount', 15, 2);
            $table->dateTime('payment_date');
            $table->timestamps();

            $table->foreign('unit_transaction_refund_id', 'ut_rf_pm_rf_id_foreign')
                ->references('id')
                ->on('unit_transaction_refunds')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_transaction_refund_payments');
    }
};
