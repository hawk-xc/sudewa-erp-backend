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
        if (!Schema::hasTable('migrations')) {
             throw new \Exception("Migrations table is missing before creating uj_driver_billing_payments");
        }
        Schema::create('uj_driver_billing_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('do_expedition_id')->constrained('do_expeditions')->cascadeOnDelete();
            $table->foreignId('cash_id')->constrained('cashes')->cascadeOnDelete();
            $table->decimal('amount', 15,2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uj_driver_billing_payments');
    }
};
