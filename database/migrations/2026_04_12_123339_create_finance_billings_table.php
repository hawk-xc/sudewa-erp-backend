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
        Schema::create('finance_billings', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('unit_transaction_billing_id')->nullable()->constrained('unit_transaction_billings')->cascadeOnDelete();
            $table->foreignId('goods_transaction_billing_id')->nullable()->constrained('goods_transaction_billings')->cascadeOnDelete();
            $table->foreignId('cash_flow_id')->nullable()->constrained('cash_flows')->cascadeOnDelete();
            $table->foreignId('cash_id')->nullable()->constrained('cashes')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('amount_original', 15, 2)->default(0);
            $table->string('payment_proof')->nullable();
            $table->date('payment_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_billings');
    }
};
