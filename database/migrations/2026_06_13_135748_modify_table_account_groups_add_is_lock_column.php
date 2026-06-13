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
        if (!Schema::hasColumn('account_groups', 'is_lock')) {
            Schema::table('account_groups', function (Blueprint $table) {
                $table->boolean('is_lock')->default(false)->nullable(false)->after('group_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('account_groups', 'is_lock')) {
            Schema::table('account_groups', function (Blueprint $table) {
                $table->dropColumn('is_lock');
            });
        }
    }
};
