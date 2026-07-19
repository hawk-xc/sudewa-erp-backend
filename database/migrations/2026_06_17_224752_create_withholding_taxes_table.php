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
        Schema::create('withholding_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('cash_id')->nullable(false)->constrained('cashes')->onDelete('cascade');
            $table->enum('source', ['internal', 'external'])->default('internal')->nullable(false);
            $table->string('no_invoice')->nullable(false);
            $table->string('withholding_number')->unique(true)->nullable(false);
            $table->integer('withholding_age')->nullable(false)->default(0);
            $table->bigInteger('pph_amount')->nullable(false)->default(0);
            $table->string('pph_description')->nullable(true);
            $table->bigInteger('payment_amount')->nullable(false)->default(0);
            $table->date('payment_date')->default(now())->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withholding_taxes');
    }
};
