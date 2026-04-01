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
        Schema::create('unit_type_detail_ppns', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('unit_transaction_item_detail_id')->constrained('unit_transaction_item_details')->cascadeOnDelete();
            $table->foreignId('unit_transaction_id')->constrained('unit_transactions')->cascadeOnDelete();
            $table->enum('type', ['ppn_purchase', 'ppn_sales'])->default('ppn_purchase');
            $table->date('fpm_date')->nullable(true);
            $table->date('nsfpm_age')->nullable(true);
            $table->decimal('nsfp_amount', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_type_detail_ppns');
    }
};
