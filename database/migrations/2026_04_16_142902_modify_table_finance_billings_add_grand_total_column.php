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
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->bigInteger('grand_total')->default(0)->nullable(false)->after('last_payment_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->dropColumn('grand_total');
        });
    }
};
