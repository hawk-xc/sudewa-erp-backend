<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\WarehouseBlock;
use App\Models\WarehouseSubBlock;
use Illuminate\Database\Seeder;

class WarehouseBlockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $blocks = ['A', 'B', 'C', 'D', 'E', 'F'];

        $warehouses = Warehouse::all();

        foreach ($warehouses as $warehouse) {
            foreach ($blocks as $block) {
                $warehouseBlock = WarehouseBlock::firstOrCreate([
                    'warehouse_id' => $warehouse->id,
                    'name' => 'Blok '.$block,
                    'description' => null,
                ]);

                for ($i = 1; $i <= 10; $i++) {
                    WarehouseSubBlock::firstOrCreate([
                        'warehouse_block_id' => $warehouseBlock->id,
                        'name' => 'Blok '.$block.'-'.$i,
                        'description' => null,
                        'is_active' => true,
                        'is_default' => false,
                    ]);
                }
            }
        }
    }
}
