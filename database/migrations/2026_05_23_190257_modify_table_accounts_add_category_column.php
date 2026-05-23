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
        if (!Schema::hasColumn('accounts', 'category')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->enum('category', [
                    'general_administration', // 1. Administrasi dan Umum
                    'current_assets',         // 2. Aktiva Lancar
                    'liabilities'             // 3. Pasiva Kewajiban
                ])
                ->default('general_administration')
                ->after('type')
                ->comment('Kategori akun: general_administration (Administrasi & Umum), current_assets (Aktiva Lancar), liabilities (Pasiva Kewajiban)');
            });
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'category')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
