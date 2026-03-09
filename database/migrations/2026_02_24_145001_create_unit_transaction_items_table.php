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
        Schema::create('unit_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('unit_transaction_id')->nullable(false)->constrained('unit_transactions')->cascadeOnDelete();
            // allow null
            $table->foreignId('unit_type_id')->nullable(true)->constrained('unit_types')->nullOnDelete();
            $table->foreignId('sparepart_id')->nullable(true)->constrained('spareparts')->nullOnDelete();
            $table->integer('qty_total')->default(0)->nullable(false);
            $table->decimal('price', 15, 2)->default(0)->nullable(false);
            $table->decimal('bbn_price', 15, 2)->default(0)->nullable(false);
            $table->decimal('hpp_per_unit_price', 15, 2)->default(0)->nullable(false);
            $table->decimal('dpp_per_unit_price', 15, 2)->default(0)->nullable(false);
            $table->decimal('ppn_per_unit_price', 15, 2)->default(0)->nullable(false);
            $table->decimal('hpp_total_price', 15, 2)->default(0)->nullable(false);
            $table->decimal('dpp_total_price', 15, 2)->default(0)->nullable(false);
            $table->decimal('ppn_total_price', 15, 2)->default(0)->nullable(false);
            $table->decimal('expedition_fee', 15, 2)->default(0)->nullable(false);
            $table->decimal('other_fee', 15, 2)->default(0)->nullable(false);
            $table->integer('ppn_percentage')->default(11)->comment('indonesian national PPN 11%');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_transaction_items');
    }
};
