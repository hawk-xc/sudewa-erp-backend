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
            $table->boolean('in_stock')->default(false)->nullable(false)->after('chassis_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_transaction_item_details', function (Blueprint $table) {
            $table->dropColumn('in_stock');
        });
    }
};
