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
        Schema::create('spareparts', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('sparepart_category_id')->nullable(true)->constrained('sparepart_categories')->nullOnDelete();
            $table->string('code')->unique()->nullable(false);
            $table->string('name')->nullable(false);
            $table->integer('capacity')->default(0);
            $table->string('image')->nullable(true);
            $table->enum('unit_type', ['pcs', 'set', 'box'])->default('pcs');
            $table->decimal('price', 15, 2)->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spareparts');
    }
};
