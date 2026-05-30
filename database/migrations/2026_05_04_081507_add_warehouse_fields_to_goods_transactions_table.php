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
        Schema::table('goods_transactions', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('stock_state')->default('draft');
            $table->boolean('is_refunded')->default(false);
        });

        Schema::table('warehouse_movements', function (Blueprint $table) {
            $table->foreignId('goods_transaction_id')->after('unit_transaction_item_detail_id')->nullable()->constrained('goods_transactions')->cascadeOnDelete();
            $table->foreignId('goods_transaction_detail_id')->after('goods_transaction_id')->nullable()->constrained('goods_transaction_details')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_transactions', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['person_id', 'warehouse_id', 'stock_state', 'is_refunded']);
        });

        Schema::table('warehouse_movements', function (Blueprint $table) {
            $table->dropForeign(['goods_transaction_id']);
            $table->dropForeign(['goods_transaction_detail_id']);
            $table->dropColumn(['goods_transaction_id', 'goods_transaction_detail_id']);
        });
    }
};
