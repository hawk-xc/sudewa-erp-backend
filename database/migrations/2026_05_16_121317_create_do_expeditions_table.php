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
        Schema::create('do_expeditions', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('code')->unique()->nullable(false);

            $table->foreignId('do_order_list_id')->nullable(false)->constrained('do_order_lists')->cascadeOnDelete();

            $table->foreignId('vehicle_id')->nullable(true)->constrained('vehicle_fleets')->nullOnDelete();

            $table->foreignId('driver_id')->nullable(true)->constrained('persons')->nullOnDelete();

            $table->date('date')->nullable(true);
            $table->text('driver_note')->nullable(true);
            $table->boolean('is_printed')->default(false)->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_expeditions');
    }
};
