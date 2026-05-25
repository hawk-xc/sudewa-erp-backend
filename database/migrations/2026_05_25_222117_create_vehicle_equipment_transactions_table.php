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
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('vehicle_fleet_id')->nullable()->constrained('vehicle_fleets')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->enum('type', ['receipt', 'dispatch']);
            $table->date('transaction_date');
            $table->decimal('purchase_amount', 15, 2)->default(0);
            $table->string('location')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
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
