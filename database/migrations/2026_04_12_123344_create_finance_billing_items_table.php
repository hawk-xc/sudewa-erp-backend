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
        Schema::create('finance_billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_billing_id')->constrained('finance_billings')->cascadeOnDelete();
            $table->bigInteger('bca_payment_amount')->default(0)->nullable(true);
            $table->bigInteger('bca_payment_usd_amount')->default(0)->nullable(true);
            $table->bigInteger('cash_payment_amount')->default(0)->nullable(true);
            $table->string('payment_proof')->nullable(true);
            $table->date('payment_at')->nullable(true);
            $table->text('note')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_billing_items');
    }
};
