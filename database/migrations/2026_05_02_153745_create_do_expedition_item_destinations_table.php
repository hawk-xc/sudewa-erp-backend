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
        Schema::create('do_expedition_item_destinations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('do_expedition_item_id')->constrained('do_expedition_items')->onDelete('cascade');
            $table->integer('order_number')->default(0)->nullable(false);
            $table->string('destination');
            $table->text('driver_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_expedition_item_destinations');
    }
};
