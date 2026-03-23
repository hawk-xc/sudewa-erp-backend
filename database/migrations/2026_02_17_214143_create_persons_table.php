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
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('company_id')
                ->nullable(false)
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->string('code')->unique()->nullable(false);
            $table->enum('type', ['supplier', 'customer', 'dealer']);
            $table->string('name')->nullable(false);
            $table->string('address')->nullable(true);
            $table->string('phone')->nullable(true);
            $table->string('npwp')->nullable(true);
            $table->string('pic_name')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
