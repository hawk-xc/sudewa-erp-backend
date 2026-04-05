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
            $table->enum('status', ['normal', 'minor_damage', 'major_damage', 'returned', 'refunded', 'lost', 'in_repair'])->default('normal')->after('is_forecast');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_transaction_item_details', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
