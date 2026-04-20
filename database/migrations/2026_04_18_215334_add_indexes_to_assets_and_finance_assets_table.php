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
        Schema::table('assets', function (Blueprint $table) {
            $table->index('name');
            $table->index('type');
            $table->index('purchase_date');
        });

        Schema::table('finance_assets', function (Blueprint $table) {
            $table->index('asset_id');
            $table->index('economic_age');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['type']);
            $table->dropIndex(['purchase_date']);
        });

        Schema::table('finance_assets', function (Blueprint $table) {
            $table->dropIndex(['asset_id']);
            $table->dropIndex(['economic_age']);
        });
    }
};
