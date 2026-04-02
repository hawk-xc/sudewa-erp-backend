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
            $table->string('image')->nullable(true)->after('drive_license_identity_number');
            $table->string('map_link')->nullable(true)->after('image');
            $table->string('social_media_1_link')->nullable(true)->after('map_link');   
            $table->string('social_media_2_link')->nullable(true)->after('social_media_1_link');
            $table->string('social_media_3_link')->nullable(true)->after('social_media_2_link');
            $table->string('social_media_4_link')->nullable(true)->after('social_media_3_link');
            $table->string('website_link')->nullable(true)->after('social_media_4_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn('image');
            $table->dropColumn('map_link');
            $table->dropColumn('social_media_1_link');
            $table->dropColumn('social_media_2_link');
            $table->dropColumn('social_media_3_link');
            $table->dropColumn('social_media_4_link');
            $table->dropColumn('website_link');
        });
    }
};
