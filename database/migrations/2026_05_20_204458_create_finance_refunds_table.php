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
        Schema::create('finance_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('unit_transaction_refund_id')->constrained('unit_transaction_refunds')->cascadeOnDelete();
            $table->foreignId('cash_id')->nullable(true)->constrained('cashes')->nullOnDelete();
            $table->enum('status', ['waiting', 'reject', 'approve'])->default('waiting')->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_refunds');
    }
};
