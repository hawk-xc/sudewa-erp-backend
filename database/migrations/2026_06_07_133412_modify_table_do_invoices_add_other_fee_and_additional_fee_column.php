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
        Schema::table('do_invoices', function (Blueprint $table) {
            $table->decimal('other_fee', 15, 2)->after('description')->default(0);
            $table->decimal('additional_fee', 15, 2)->after('other_fee')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('do_invoices', function (Blueprint $table) {
            $table->dropColumn('other_fee');
            $table->dropColumn('additional_fee');
        });
    }
};
