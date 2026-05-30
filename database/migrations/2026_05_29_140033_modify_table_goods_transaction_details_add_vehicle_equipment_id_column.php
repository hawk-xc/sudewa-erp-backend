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
        if (!Schema::hasColumn('goods_transaction_details', 'vehicle_equipment_id')) {
            Schema::table('goods_transaction_details', function (Blueprint $table) {
                $table->foreignId('vehicle_equipment_id')->nullable(true)->constrained('vehicle_equipments')->onDelete('cascade'); 
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('goods_transaction_details', 'vehicle_equipment_id')) {    
            Schema::table('goods_transaction_details', function (Blueprint $table) {
                $table->dropForeign('vehicle_equipment_id');
                $table->dropColumn('vehicle_equipment_id');
            });
        }
    }
};
