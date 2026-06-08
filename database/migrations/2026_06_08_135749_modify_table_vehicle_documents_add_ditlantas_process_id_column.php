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
        if (!Schema::hasColumn('vehicle_documents', 'ditlantas_process_id')) {
            Schema::table('vehicle_documents', function (Blueprint $table) {
                $table->foreignId('ditlantas_process_id')->after('uuid')->constrained('ditlantas_processed')->onDelete('cascade');
                
            });
        }
    }
    
    /**
     * Reverse the migrations.
    */
    public function down(): void
    {
        if (Schema::hasColumn('vehicle_documents', 'ditlantas_process_id')) {
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->dropForeign('ditlantas_process_id');
            $table->dropColumn('ditlantas_process_id');
        });
    }
    }
};
