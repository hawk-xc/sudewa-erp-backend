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
        Schema::create('tarifs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained('persons')->onDelete('cascade');
            $table->string('loading_in')->nullable();
            $table->string('loading_out')->nullable();
            $table->integer('distance')->default(0);
            $table->bigInteger('uj_towing')->default(0);
            $table->bigInteger('uj_cdd')->default(0);
            $table->bigInteger('uj_fuso')->default(0);
            $table->bigInteger('inv_cdd')->default(0);
            $table->bigInteger('inv_fuso')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarifs');
    }
};
