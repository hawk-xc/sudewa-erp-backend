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
        Schema::create('goods_transaction_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique(true)->nullable(false);
            $table->foreignId('goods_transaction_id')->constrained('goods_transactions')->onDelete('cascade');
            $table->foreignId('material_id')->nullable(true)->constrained('materials')->onDelete('cascade');
            $table->foreignId('vehicle_equipment_id')->nullable(true)->constrained('vehicle_equipments')->onDelete('cascade');
            $table->boolean('in_stock')->default(false);
            $table->boolean('is_forecast')->default(true);
            $table->integer('qty')->default(1);
            $table->enum('type', ['pcs', 'set', 'box'])->nullable(true);
            $table->decimal('price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_transaction_details');
    }
};
