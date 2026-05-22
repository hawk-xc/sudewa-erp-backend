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
        Schema::table('warehouse_movements', function (Blueprint $table) {
            $table->enum('status', ['in', 'out', 'refund'])->defualt('in')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_movements', function (Blueprint $table) {
            $table->enum('status', ['in', 'out'])->defualt('in')->change();
        });
    }
};
