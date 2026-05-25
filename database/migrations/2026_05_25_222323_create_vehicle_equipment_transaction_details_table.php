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
        Schema::create('vehicle_equipment_transaction_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->unsignedBigInteger('vehicle_equipment_id');
            $table->foreign('vehicle_equipment_id', 'fk_veh_eq_tx_det_eq')
                ->references('id')
                ->on('vehicle_equipment')
                ->onDelete('cascade');

            $table->unsignedBigInteger('vehicle_equipment_transaction_id');
            $table->foreign('vehicle_equipment_transaction_id', 'fk_veh_eq_tx_det_tx')
                ->references('id')
                ->on('vehicle_equipment_transactions')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_equipment_transaction_details');
    }
};
