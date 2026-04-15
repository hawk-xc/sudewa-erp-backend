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
        Schema::create('vehicle_fleet_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_fleet_id')->constrained('vehicle_fleets')->onDelete('cascade');
            
            // category 1
            $table->integer('radio_tape')->default(0);
            $table->integer('jack')->default(0);
            $table->integer('spare_tire')->default(0);
            $table->integer('toolkit')->default(0);
            $table->integer('jack_handle')->default(0);
            $table->integer('pressure_pipe_1')->default(0);
            $table->integer('first_aid_kit')->default(0);
            $table->integer('cigarette_lighter')->default(0);
            $table->integer('pressure_pipe_2')->default(0);
            
            // category 2
            $table->integer('seat_saddle')->default(0);
            $table->integer('handlebar_hose')->default(0);
            $table->integer('fire_extinguisher')->default(0);
            $table->integer('large_tie_down_strap')->default(0);
            $table->integer('rearview_mirror')->default(0);
            $table->integer('ati_foam')->default(0);
            $table->integer('small_tie_down_strap')->default(0);
            $table->integer('toolbox_lock')->default(0);
            $table->integer('service_book')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_fleet_equipments');
    }
};
