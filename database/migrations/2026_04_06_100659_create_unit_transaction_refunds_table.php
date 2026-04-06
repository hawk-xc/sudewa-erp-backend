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
        Schema::create('unit_transaction_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('unit_transaction_id')->nullable(false)->constrained('unit_transactions')->cascadeOnDelete();
            $table->foreignId('cash_id')->nullable(false)->constrained('cashes')->cascadeOnDelete();
            $table->decimal('refund_total', 15, 2)->default(0)->nullable(false);
            $table->string('description')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_transaction_refunds');
    }
};
