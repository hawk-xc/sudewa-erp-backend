<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_fleets') && Schema::hasColumn('vehicle_fleets', 'type')) {
            Schema::table('vehicle_fleets', function (Blueprint $table) {
                $table->string('type')->nullable()->change();
            });

            DB::table('vehicle_fleets')
                ->whereNotIn('type', ['towing', 'cdd', 'fuso'])
                ->update(['type' => null]);

            Schema::table('vehicle_fleets', function (Blueprint $table) {
                $table->enum('type', ['towing', 'cdd', 'fuso'])->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('vehicle_fleets') && Schema::hasColumn('vehicle_fleets', 'type')) {
            Schema::table('vehicle_fleets', function (Blueprint $table) {
                $table->string('type')->nullable()->change();
            });
        }
    }
};
