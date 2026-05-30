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
        Schema::create('vehicle_equipment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->foreignId('person_id')->nullable(true)->constrained('persons')->nullOnDelete();
            $table->foreignId('vehicle_fleet_id')->nullable(true)->constrained('vehicle_fleets')->nullOnDelete();
            $table->string('supplier_name')->nullable(true);
            $table->enum('type', ['receipt', 'dispatch'])->default('receipt');
            $table->date('transaction_date')->nullable(true);
            $table->string('location')->nullable(true);
            $table->enum('category', ['general', 'equipment', 'maintenance'])->default('general')->nullable(false);
            $table->text('description')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_equipment_transactions');
    }
};
