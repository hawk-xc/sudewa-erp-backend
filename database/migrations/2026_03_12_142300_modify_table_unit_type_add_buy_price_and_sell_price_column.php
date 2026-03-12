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
        Schema::table('unit_types', function (Blueprint $table) {
            $table->decimal('buy_price', 15, 2)->default(0)->after('bruto_weight');
            $table->decimal('sell_price', 15, 2)->default(0)->after('buy_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->dropColumn(['buy_price', 'sell_price']);
        });
    }
};
