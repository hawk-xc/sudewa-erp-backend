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
        if (!Schema::hasColumn('cash_flows', 'is_paid')) {
            Schema::table('cash_flows', function (Blueprint $table) {
                $table->boolean('is_paid')->default(false)->after('payment_proof');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('cash_flows', 'is_paid')) {
            Schema::table('cash_flows', function (Blueprint $table) {
                $table->dropColumn('is_paid');
            });
        }
    }
};
