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
        if (!Schema::hasColumn('do_order_lists', 'vehicle_type')) {
            Schema::table('do_order_lists', function (Blueprint $table) {
                $table->enum('vehicle_type', ['cdd', 'fuso', 'towing'])->nullable(false)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if(Schema::hasColumn('do_order_lists', 'vehicle_type')) {
            Schema::table('do_order_lists', function (Blueprint $table) {
                $table->dropColumn('vehicle_type');
            });
        }
    }
};
