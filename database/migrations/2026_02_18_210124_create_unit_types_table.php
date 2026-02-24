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
            $table->uuid();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('code')->unique(true)->nullable(false)->description('Unit Type Special Code');
            $table->string('name')->nullable(false);
            $table->integer('capacity')->default(0)->nullable(false);
            $table->string('image')->nullable(true)->default(null);
            $table->string('unit_type')->nullable(true)->default(null);
            $table->string('unit_model')->nullable(true)->default(null);
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
