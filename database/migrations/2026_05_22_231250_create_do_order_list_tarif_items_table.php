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
        Schema::create('d_o_order_list_tarif_load_items', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('do_order_list_tarif_id')->constrained('do_order_list_tarifs')->cascadeOnDelete();
            $table->string('load_content')->nullable(false);
            $table->tinyInteger('qty')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('d_o_order_list_tarif_load_items');
    }
};
