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
        Schema::create('warehouse_sub_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('warehouse_block_id')->constrained('warehouse_blocks')->cascadeOnDelete();
            $table->string('name')->nullable(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_sub_blocks');
    }
};
