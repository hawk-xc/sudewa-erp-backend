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
        Schema::create('unit_transaction_billings', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('unit_transaction_id')->nullable(true)->constrained('unit_transactions')->cascadeOnDelete();
            $table->decimal('bca_payment_amount', 15, 2)->nullable(false)->default(0);
            $table->decimal('bca_payment_usd_amount', 10, 2)->nullable(false)->default(0);
            $table->decimal('cash_payment_amount', 15, 2)->nullable(false)->default(0);
            $table->dateTime('payment_at')->default(now());
            $table->tinyInteger('is_paid')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_transaction_billings');
    }
};
