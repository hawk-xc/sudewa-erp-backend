<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('vehicle_registrations', 'ditlantas_process_id')) {
            Schema::table('vehicle_registrations', function (Blueprint $table) {
                $table->foreignId('ditlantas_process_id')->after('uuid')->constrained('ditlantas_processed')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('vehicle_registrations', 'ditlantas_process_id')) {
            Schema::table('vehicle_registrations', function (Blueprint $table) {
                $table->dropForeign('ditlantas_process_id')->constrained('ditlantas_processed');
                $table->dropColumn('ditlantas_process_id');
            });
        }
    }
};
