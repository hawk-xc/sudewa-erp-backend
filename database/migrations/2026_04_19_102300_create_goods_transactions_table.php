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
        Schema::create('goods_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->foreignId('company_id')->constrained('companies')->nullable(true)->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('persons')->nullable(true)->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('persons')->nullable(true)->onDelete('cascade');
            $table->foreignId('vehicle_fleet_id')->constrained('vehicle_fleets')->nullable(true)->onDelete('cascade');
            $table->enum('category', ['maintenance', 'equipped'])->nullable(true);
            $table->enum('type', ['receipt', 'issue'])->nullable(false);
            $table->date('transaction_date')->nullable(true);
            $table->string('location')->nullable(true);
            $table->text('description')->nullable(true);
            $table->string('invoice_file')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_transactions');
    }
};
