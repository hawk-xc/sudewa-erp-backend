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
        Schema::create('vehicle_fleets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('registration_number');
            $table->string('type');
            $table->string('machine_number');
            $table->string('chassis_number');
            $table->date('stnk_age')->nullable();
            $table->date('kir_age')->nullable();
            $table->string('stnk_number')->nullable();
            $table->string('kir_book')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_fleets');
    }
};
