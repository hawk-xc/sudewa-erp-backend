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
        Schema::table('vehicle_registrations', function (Blueprint $table) {
            $table->decimal('stamp_fee', 15, 2)->default(0)->nullable(false)->after('skpd_fee');
            $table->decimal('pnbp_bpkb', 15, 2)->default(0)->nullable(false)->after('stamp_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_registrations', function (Blueprint $table) {
            $table->dropColumn('stamp_fee');
            $table->dropColumn('pnbp_bpkb');
        });
    }
};
