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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('code')->unique(true)->nullable(false);
            $table->string('group_code')->unique(false)->nullable(true);
            $table->string('name')->nullable(false);
            $table->string('description')->nullable(true);
            $table->enum('type', ['debet', 'credit'])->default('debet');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
