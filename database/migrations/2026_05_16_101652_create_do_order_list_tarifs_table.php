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
        Schema::create('do_order_list_tarifs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('do_orderlist_id')->nullable()->constrained('do_order_lists')->cascadeOnDelete();
            $table->foreignId('tarif_id')->nullable()->constrained('tarifs')->cascadeOnDelete();
            $table->integer('qty')->default(0);
            $table->string('load_content')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_order_list_tarifs');
    }
};
