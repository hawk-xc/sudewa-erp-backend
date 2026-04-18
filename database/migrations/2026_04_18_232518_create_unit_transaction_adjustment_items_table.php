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
        Schema::dropIfExists('unit_transaction_adjustment_items');
        Schema::create('unit_transaction_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->unsignedBigInteger('unit_transaction_adjustment_id');
            $table->foreign('unit_transaction_adjustment_id', 'utai_adj_id_fk')
                ->references('id')->on('unit_transaction_adjustments')
                ->onDelete('cascade');

            $table->unsignedBigInteger('unit_transaction_item_id')->nullable();
            $table->foreign('unit_transaction_item_id', 'utai_item_id_fk')
                ->references('id')->on('unit_transaction_items')
                ->onDelete('cascade');

            $table->unsignedBigInteger('unit_transaction_item_detail_id')->nullable();
            $table->foreign('unit_transaction_item_detail_id', 'utai_detail_id_fk')
                ->references('id')->on('unit_transaction_item_details')
                ->onDelete('cascade');

            $table->integer('qty')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_transaction_adjustment_items');
    }
};
