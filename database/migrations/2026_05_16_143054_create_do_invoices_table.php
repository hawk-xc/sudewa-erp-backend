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
        Schema::create('do_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('code')->unique(true)->nullable(false);
            $table->foreignId('customer_id')->nullable(true)->constrained('persons')->nullOnDelete();
            $table->date('date')->nullable(true);
            $table->string('subject')->nullable(true);
            $table->text('letter_content')->nullable(true);
            $table->text('description')->nullable(true);
            $table->boolean('is_already_print')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_invoices');
    }
};
