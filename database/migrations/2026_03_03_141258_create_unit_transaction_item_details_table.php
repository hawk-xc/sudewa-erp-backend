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
        Schema::create('unit_transaction_item_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_transaction_item_id')->constrained('unit_transaction_items')->cascadeOnDelete();
            $table->string('uuid')->unique()->nullable(false);
            $table->string('color')->nullable(false);
            $table->string('machine_number')->nullable(true);
            $table->string('chassis_number')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_transaction_item_details');
    }
};
