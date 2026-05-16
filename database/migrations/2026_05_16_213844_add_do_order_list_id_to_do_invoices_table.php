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
            $table->foreignId('do_order_list_id')->nullable()->after('customer_id')->constrained('do_order_lists')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('do_invoices', function (Blueprint $table) {
            $table->dropForeign(['do_order_list_id']);
            $table->dropColumn('do_order_list_id');
        });
    }
};
