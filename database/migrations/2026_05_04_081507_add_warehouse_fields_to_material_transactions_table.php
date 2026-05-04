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
        Schema::table('material_transactions', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('stock_state')->default('draft');
            $table->boolean('is_refunded')->default(false);
            $table->string('supplier_name')->nullable()->change();
        });

        Schema::table('warehouse_movements', function (Blueprint $table) {
            $table->foreignId('material_transaction_id')->nullable()->constrained('material_transactions')->cascadeOnDelete();
            $table->foreignId('material_transaction_detail_id')->nullable()->constrained('material_transaction_details')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_transactions', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['person_id', 'warehouse_id', 'stock_state', 'is_refunded']);
            $table->string('supplier_name')->nullable(false)->change();
        });

        Schema::table('warehouse_movements', function (Blueprint $table) {
            $table->dropForeign(['material_transaction_id']);
            $table->dropForeign(['material_transaction_detail_id']);
            $table->dropColumn(['material_transaction_id', 'material_transaction_detail_id']);
        });
    }
};
