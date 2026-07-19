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
        Schema::create('tax_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_id')->nullable(false)->constrained('taxes')->cascadeOnDelete();
            $table->string('name')->nullable(false);
            $table->integer('rate')->nullable(false)->default(1);
            $table->date('effective_from')->nullable(true);
            $table->date('effective_until')->nullable(true);
            $table->boolean('is_default')->nullable(false)->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_versions');
    }
};
