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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('slug')->unique(true)->nullable(false);
            $table->string('name')->nullable(false);
            $table->string('code')->nullable(true);
            $table->text('description')->nullable(true);
            $table->enum('type', ['office', 'transport_office'])->default('office');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
