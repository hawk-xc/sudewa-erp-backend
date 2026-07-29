<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_transaction_item_details', function (Blueprint $table) {
            $table->index('unit_transaction_item_id', 'utid_item_id_idx');
            $table->index('in_stock', 'utid_in_stock_idx');
            $table->index(['unit_transaction_item_id', 'in_stock'], 'utid_item_stock_idx');
        });

        Schema::table('unit_transaction_items', function (Blueprint $table) {
            $table->index('unit_transaction_id', 'uti_trx_id_idx');
            $table->index('unit_type_id', 'uti_type_id_idx');
        });

        Schema::table('unit_transactions', function (Blueprint $table) {
            $table->index('warehouse_id', 'ut_wh_idx');
        });
    }

    public function down(): void
    {
        Schema::table('unit_transaction_item_details', function (Blueprint $table) {
            $table->dropIndex('utid_item_id_idx');
            $table->dropIndex('utid_in_stock_idx');
            $table->dropIndex('utid_item_stock_idx');
        });

        Schema::table('unit_transaction_items', function (Blueprint $table) {
            $table->dropIndex('uti_trx_id_idx');
            $table->dropIndex('uti_type_id_idx');
        });

        Schema::table('unit_transactions', function (Blueprint $table) {
            $table->dropIndex('ut_wh_idx');
        });
    }
};
