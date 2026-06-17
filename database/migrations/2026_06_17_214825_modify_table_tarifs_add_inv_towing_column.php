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
        if (!Schema::hasColumn('tarifs', 'inv_towing')) {
            Schema::table('tarifs', function (Blueprint $table) {
                $table->bigInteger('inv_towing')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('tarifs', 'inv_towing')) {
            Schema::table('tarifs', function (Blueprint $table) {
                $table->dropColumn('inv_towing');
            });
        }
    }
};
