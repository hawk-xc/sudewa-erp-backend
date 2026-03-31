<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_transaction_billing_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid();

            $table->foreignId('unit_transaction_billing_id')
                ->constrained('unit_transaction_billings')
                ->cascadeOnDelete()
                ->index('idx_utbh_unit_transaction_billing_id');

            $table->decimal('bca_payment_amount', 15, 2)->default(0);
            $table->decimal('bca_payment_usd_amount', 10, 2)->default(0);
            $table->decimal('cash_payment_amount', 15, 2)->default(0);

            $table->string('payment_proof')->nullable(true);
            $table->dateTime('payment_at')->nullable(true);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('unit_transaction_billing_id', 'fk_utbh_utb_id')
                ->references('id')
                ->on('unit_transaction_billings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_transaction_billing_histories');
    }
};
