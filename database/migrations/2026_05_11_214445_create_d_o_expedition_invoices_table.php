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
        Schema::create('do_expedition_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('do_expedition_id')->constrained('do_expeditions')->onDelete('cascade');
            $table->integer('qty')->default(0)->nullable(true);
            $table->string('do_letter_code')->nullable(true);
            $table->string('do_assignment_code')->nullable(true);
            $table->string('description')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_expedition_invoices');
    }
};
