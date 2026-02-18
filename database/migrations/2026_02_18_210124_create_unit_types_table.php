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
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(true)->nullable(false)->description('Unit Type Special Code');
            $table->foreignId('brand_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name')->nullable(false);
            $table->string('image')->nullable(true)->default(null);
            $table->string('type')->nullable(true)->default(null);
            $table->integer('netto_weight')->nullable(true);
            $table->integer('bruto_weight')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};
