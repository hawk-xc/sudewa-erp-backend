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
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('group_code');
            $table->foreignId('account_group_id')->nullable()->constrained('account_groups')->nullOnDelete()->after('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('group_code');
            $table->dropForeign(['account_group_id']);
            $table->dropColumn('account_group_id');
        });
    }
};
