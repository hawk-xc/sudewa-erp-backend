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
        Schema::create('goods_transaction_billing_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('goods_transaction_billing_id')->nullable()->constrained('goods_transaction_billings', 'id', 'gtbp_billing_foreign')->nullOnDelete();
            $table->foreignId('cash_id')->nullable()->constrained('cashes')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('transaction_date')->nullable(true);
            $table->text('description')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_transaction_billing_payments');
    }
};
