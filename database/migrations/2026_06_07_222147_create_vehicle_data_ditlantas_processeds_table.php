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
        Schema::create('vehicle_data_ditlantas_processed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ditlantas_process_id')->constrained('ditlantas_processed')->nullable(false)->cascadeOnDelete();
            $table->foreignId('vehicle_data_id')->constrained('vehicle_datas')->nullable(false)->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_data_ditlantas_processed');
    }
};
