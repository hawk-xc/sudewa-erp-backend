<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('
            CREATE TRIGGER after_asset_insert
            AFTER INSERT ON assets
            FOR EACH ROW
            BEGIN
                INSERT INTO finance_assets (asset_id, uuid, created_at, updated_at)
                VALUES (NEW.id, UUID(), NOW(), NOW());
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS after_asset_insert");
    }
};
