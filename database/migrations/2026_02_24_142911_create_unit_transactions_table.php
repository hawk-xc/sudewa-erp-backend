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
        Schema::create('unit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->nullable(true)->constrained('warehouses')->nullOnDelete();
            $table->foreignId('person_id')->nullable(true)->constrained('persons')->nullOnDelete();
            $table->string('code')->unique()->nullable(false);
            $table->enum('type', [
                'purchase',
                'sales',
            ])->default('purchase')->nullable(false);
            $table->integer('max_capacity')->default(0)->nullable(true);
            $table->enum('stock_state', [
                'draft',
                'cancel',
                'rejected',
                'prepare',
                'inbound_purcase_order',
                'inbound_incoming_goods',
                'inbound_receipt',
                'inbound_return',
                'outbound_reserved',
                'outbound_in_transit',
                'outbound_delivered',
                'outbound_return',
            ])->default('prepare')->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_transactions');
    }
};
