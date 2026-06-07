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
        Schema::create('ditlantas_processed', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('vendor_id')->constrained('persons')->nullable(false)->cascadeOnDelete();
            $table->date('process_date')->nullable(false)->comment('Tanggal Proses');
            $table->string('note')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ditlantas_processed');
    }
};
