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
        Schema::create('do_expeditions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('do_code')->unique();
            $table->date('date');
            $table->foreignId('vehicle_id')->constrained('vehicle_fleets')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('persons')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_expeditions');
    }
};
