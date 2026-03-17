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
        if (!Schema::hasTable('warehouse_activities')) {
            Schema::create('warehouse_activities', function (Blueprint $table) {
                $table->id();
                $table->uuid();
                $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->string('activity_number')->unique()->nullable(false);
                $table->enum('activity_type', ['receipt', 'issue'])->default('receipt');
                $table->dateTime('activity_date')->default(now());
                $table->text('description')->nullable(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_activities');
    }
};
