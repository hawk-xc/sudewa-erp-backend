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
        Schema::create('finance_invoice_billing_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('do_invoice_id')->references('id')->on('do_invoices')->onDelete('cascade');
            $table->foreignId('cash_id')->references('id')->on('cashes')->onDelete('cascade');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_invoice_billing_payments');
    }
};
