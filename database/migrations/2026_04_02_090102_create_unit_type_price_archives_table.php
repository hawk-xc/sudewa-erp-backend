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
        Schema::create('unit_type_price_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('unit_type_id')->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->nullOnDelete();
            $table->decimal('buy_price', 15, 2)->nullable(false)->default(0);
            $table->decimal('sell_price', 15, 2)->nullable(false)->default(0);
            $table->string('note')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_type_price_archives');
    }
};
