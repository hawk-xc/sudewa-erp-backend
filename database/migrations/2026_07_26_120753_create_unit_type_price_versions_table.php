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
        Schema::create('unit_type_price_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('unit_type_id')->constrained('unit_types')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name')->nullable(true);
            $table->integer('buy_price')->default(0)->nullable(true);
            $table->integer('sell_price')->default(0)->nullable(true);
            $table->date('effective_from')->nullable(true);
            $table->date('effective_until')->nullable(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_lock')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_type_price_versions');
    }
};
