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
        Schema::create('do_expedition_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('do_expedition_id')->constrained('do_expeditions')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('persons')->onDelete('cascade');
            $table->string('loading_in');
            $table->string('loading_out');
            $table->string('destination');
            $table->decimal('invoice_fee', 15, 2);
            $table->decimal('additional_cost_fee', 15, 2)->nullable();
            $table->decimal('other_fee', 15, 2)->nullable();
            $table->decimal('driver_fee', 15, 2)->nullable();
            $table->decimal('ppn_fee', 15, 2)->nullable();
            $table->decimal('service_fee', 15, 2)->nullable();
            $table->decimal('pph_fee', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_expedition_items');
    }
};
