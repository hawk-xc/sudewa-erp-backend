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
        Schema::table('do_order_list_tarifs', function (Blueprint $table) {
            if (Schema::hasColumn('do_order_list_tarifs', 'qty')) {
                $table->dropColumn('qty');
            }
            if (Schema::hasColumn('do_order_list_tarifs', 'load_content')) {
                $table->dropColumn('load_content');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('do_order_list_tarifs', function (Blueprint $table) {
            if (!Schema::hasColumn('do_order_list_tarifs', 'qty')) {
                $table->integer('qty')->default(0);
            }
            if (!Schema::hasColumn('do_order_list_tarifs', 'load_content')) {
                $table->string('load_content')->nullable();
            }
        });
    }
};
