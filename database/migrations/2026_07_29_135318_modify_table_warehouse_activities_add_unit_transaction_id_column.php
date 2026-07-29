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
        Schema::table('warehouse_activities', function (Blueprint $table) {
            $table->foreignId('unit_transaction_id')->nullable(true)->after('warehouse_id')->constrained('unit_transactions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_activities', function (Blueprint $table) {
            $table->dropColumn('unit_transaction_id');
        });
    }
};
