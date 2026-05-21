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
        Schema::create('unit_transaction_refund_item_detail', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('unit_transaction_refund_id');
            $table->unsignedBigInteger('unit_transaction_item_detail_id');

            $table->foreign('unit_transaction_refund_id', 'ut_refund_id_foreign')
                ->references('id')
                ->on('unit_transaction_refunds')
                ->cascadeOnDelete();

            $table->foreign('unit_transaction_item_detail_id', 'ut_item_detail_id_foreign')
                ->references('id')
                ->on('unit_transaction_item_details')
                ->cascadeOnDelete();

            $table->unique(['unit_transaction_refund_id', 'unit_transaction_item_detail_id'], 'ut_refund_item_detail_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_transaction_refund_item_detail');
    }
};
