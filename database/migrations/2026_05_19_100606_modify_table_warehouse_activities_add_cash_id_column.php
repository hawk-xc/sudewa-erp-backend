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
            $table->foreignId('cash_id')->nullable(true)->after('person_id')->constrained('cashes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_activities', function (Blueprint $table) {
            $table->dropForeign(['cash_id']);
            $table->dropColumn('cash_id');
        });
    }
};
