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
        Schema::table('do_order_lists', function (Blueprint $table) {
            $table->integer('ppn')->default(0)->after('bill_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('do_order_lists', function (Blueprint $table) {
            $table->dropColumn('ppn');
        });
    }
};
