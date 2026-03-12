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
        Schema::create('transaction_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('unit_transaction_id')->nullable()->constrained('unit_transactions')->nullOnDelete();
            $table->date('transaction_date')->nullable(false)->default(date('Y-m-d'));
            $table->text('name')->nullable(true);
            $table->text('description')->nullable(true);
            $table->decimal('bank_usd_debit', 10, 2)->default(0)->nullable(false);
            $table->decimal('bank_usd_credit', 10, 2)->default(0)->nullable(false);
            $table->decimal('bank_idr_debit', 15, 2)->default(0)->nullable(false);
            $table->decimal('bank_idr_credit', 15, 2)->default(0)->nullable(false);
            $table->decimal('cash_idr_debit', 15, 2)->default(0)->nullable(false);
            $table->decimal('cash_idr_credit', 15, 2)->default(0)->nullable(false);
            $table->string('transaction_proof')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_flows');
    }
};
