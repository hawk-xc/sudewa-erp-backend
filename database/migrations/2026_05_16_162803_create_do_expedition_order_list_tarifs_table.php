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
        Schema::create('do_expedition_order_list_tarifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('do_expedition_id')->constrained('do_expeditions')->cascadeOnDelete();
            $table->foreignId('do_order_list_tarif_id')->constrained('do_order_list_tarifs')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_expedition_order_list_tarifs');
    }
};
