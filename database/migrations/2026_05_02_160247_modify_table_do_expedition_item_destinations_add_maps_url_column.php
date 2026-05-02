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
        Schema::table('do_expedition_item_destinations', function (Blueprint $table) {
            $table->string('maps_url')->nullable()->after('driver_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('do_expedition_item_destinations', function (Blueprint $table) {
            $table->dropColumn('maps_url');
        });
    }
};
