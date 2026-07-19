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
        Schema::table('unit_transaction_items', function (Blueprint $table) {
            $table->foreignId('dpp_tax_id')->nullable()->after('sparepart_id')->constrained('tax_versions')->nullOnDelete();
            $table->decimal('dpp_tax_rate', 5, 2)->default(0)->nullable(false)->after('dpp_tax_id');
            $table->foreignId('ppn_tax_id')->nullable()->after('dpp_tax_rate')->constrained('tax_versions')->nullOnDelete();
            $table->decimal('ppn_tax_rate', 5, 2)->default(0)->nullable(false)->after('ppn_tax_id');
            $table->dropColumn('ppn_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_transaction_items', function (Blueprint $table) {
            $table->integer('ppn_percentage')->default(11)->comment('indonesian national PPN 11%')->after('other_fee');
            $table->dropForeign(['dpp_tax_id']);
            $table->dropColumn(['dpp_tax_id', 'dpp_tax_rate', 'ppn_tax_id', 'ppn_tax_rate']);
        });
    }
};
