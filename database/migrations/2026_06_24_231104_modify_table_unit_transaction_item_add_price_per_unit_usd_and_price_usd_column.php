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
        if (!Schema::hasColumn('unit_transaction_items', 'price_per_unit_usd')) {
            if (!Schema::hasColumn('unit_transaction_items', 'price_usd')) {
                Schema::table('unit_transaction_items', function (Blueprint $table) {
                    $table->decimal('price_per_unit_usd', 8, 2)->default(0)->nullable(false)->after('price');
                    $table->decimal('price_usd', 8, 2)->default(0)->nullable(false)->after('price_per_unit_usd');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_transaction_items', function (Blueprint $table) {
            $table->dropColumn('price_per_unit_usd');
            $table->dropColumn('price_usd');
        });
    }
};
