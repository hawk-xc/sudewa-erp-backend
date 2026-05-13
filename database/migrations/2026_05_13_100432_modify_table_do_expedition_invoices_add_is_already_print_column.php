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
        Schema::table('do_expedition_invoices', function (Blueprint $table) {
            $table->boolean('is_already_print')->default(false)->nullable(false)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('do_expedition_invoices', function (Blueprint $table) {
            $table->dropColumn('is_already_print');
        });
    }
};
