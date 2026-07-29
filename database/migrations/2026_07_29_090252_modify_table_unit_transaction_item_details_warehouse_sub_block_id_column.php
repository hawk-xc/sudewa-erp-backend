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
        Schema::table('unit_transaction_item_details', function (Blueprint $table) {
            $table->foreignId('warehouse_sub_block_id')->nullable(true)->after('unit_transaction_item_id')->constrained('warehouse_sub_blocks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_transaction_item_details', function (Blueprint $table) {
            $table->dropForeign(['warehouse_sub_block_id']);
            $table->dropColumn('warehouse_sub_block_id');
        });
    }
};
