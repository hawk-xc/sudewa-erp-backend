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
            $table->foreignId('cash_id')->nullable(true)->constrained('cashes')->nullOnDelete();
            $table->foreignId('account_id')->nullable(true)->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('amount_original', 15, 2)->default(0);
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
