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
        Schema::table('persons', function (Blueprint $table) {
            $table->enum('type', ['supplier', 'customer', 'dealer', 'vendor', 'driver'])->change();
            $table->string('identity_number')->nullable(true)->unique()->after('pic_name');
            $table->string('drive_license_identity_number')->nullable(true)->unique()->after('identity_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->enum('type', ['supplier', 'customer', 'dealer', 'vendor'])->change();
            $table->dropColumn('identity_number');
            $table->dropColumn('drive_license_identity_number');
        });
    }
};
