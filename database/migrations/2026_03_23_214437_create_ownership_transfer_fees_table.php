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
        Schema::create('ownership_transfer_fees', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('dealer_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('tnbk_code')->nullable(false);
            $table->enum('vehicle_type', ['r2', 'r3', 'r4'])->default('r2');
            $table->decimal('un_notice_fee', 15, 2)->default(0);
            $table->decimal('garwil_fee', 15, 2)->default(0);
            $table->decimal('countershop_fee', 15, 2)->default(0);
            $table->decimal('other_fee', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ownership_transfer_fees');
    }
};
