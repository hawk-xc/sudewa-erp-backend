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
        Schema::create('warehouse_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('serial_number')->unique()->nullable(false);
            $table->foreignId('warehouse_activity_id')->nullable(true)->constrained('warehouse_activities')->cascadeOnDelete();
            $table->foreignId('unit_transaction_id')->nullable(true)->constrained('unit_transactions')->nullOnDelete();
            $table->foreignId('unit_transaction_item_detail_id')->nullable(true)->constrained('unit_transaction_item_details')->cascadeOnDelete();
            $table->enum('status', ['in', 'out'])->nullable(false);
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_movements');
    }
};
