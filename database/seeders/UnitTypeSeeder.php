<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\UnitType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnitTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        UnitType::truncate();
        DB::table('unit_type_price_versions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $brands = Brand::pluck('id', 'name');

        $motors = [
            ['brand' => 'Honda', 'name' => 'Beat'],
            ['brand' => 'Honda', 'name' => 'Vario 125'],
            ['brand' => 'Honda', 'name' => 'Vario 160'],
            ['brand' => 'Honda', 'name' => 'Scoopy'],
            ['brand' => 'Honda', 'name' => 'PCX 160'],
            ['brand' => 'Honda', 'name' => 'ADV 160'],
            ['brand' => 'Honda', 'name' => 'CB150R'],
            ['brand' => 'Honda', 'name' => 'CRF150L'],

            ['brand' => 'Yamaha', 'name' => 'NMAX'],
            ['brand' => 'Yamaha', 'name' => 'Aerox'],
            ['brand' => 'Yamaha', 'name' => 'Mio M3'],
            ['brand' => 'Yamaha', 'name' => 'Fino'],
            ['brand' => 'Yamaha', 'name' => 'Gear 125'],
            ['brand' => 'Yamaha', 'name' => 'Lexi'],
            ['brand' => 'Yamaha', 'name' => 'R15'],
            ['brand' => 'Yamaha', 'name' => 'Vixion'],

            ['brand' => 'Suzuki', 'name' => 'Nex II'],
            ['brand' => 'Suzuki', 'name' => 'Address'],
            ['brand' => 'Suzuki', 'name' => 'Satria FU'],
            ['brand' => 'Suzuki', 'name' => 'GSX R150'],
            ['brand' => 'Suzuki', 'name' => 'GSX S150'],

            ['brand' => 'Kawasaki', 'name' => 'Ninja 250'],
            ['brand' => 'Kawasaki', 'name' => 'KLX 150'],
            ['brand' => 'Kawasaki', 'name' => 'W175'],

            ['brand' => 'TVS', 'name' => 'Ntorq 125'],
            ['brand' => 'TVS', 'name' => 'Callisto 110'],
        ];

        $data = [];
        $counter = 1;

        foreach ($motors as $index => $motor) {
            if (! isset($brands[$motor['brand']])) {
                continue;
            }

            $data[] = [
                'uuid' => Str::uuid(),
                'code' => 'MT' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
                'brand_id' => $brands[$motor['brand']],
                'name' => $motor['name'],
                'image' => null,
                'unit_type' => 'motorcycle',
                'unit_model' => $motor['name'],
                'netto_weight' => rand(90, 140),
                'bruto_weight' => rand(100, 160),
                'buy_price' => rand(10000000, 50000000),
                'sell_price' => rand(10000000, 50000000),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Tambahkan data random sampai 50
        $count = count($data);
        $brandIds = Brand::pluck('id')->toArray();

        for ($i = $count; $i < 50; $i++) {
            $data[] = [
                'uuid' => Str::uuid(),
                'code' => 'MT' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
                'brand_id' => $brandIds[array_rand($brandIds)],
                'name' => 'Motor Type ' . ($i + 1),
                'image' => null,
                'unit_type' => 'motorcycle',
                'unit_model' => 'Model ' . ($i + 1),
                'netto_weight' => rand(90, 140),
                'bruto_weight' => rand(100, 160),
                'buy_price' => rand(10000000, 50000000),
                'sell_price' => rand(10000000, 50000000),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($data as $item) {
            $unitType = UnitType::create($item);

            $unitType->unitTypePriceVersions()->create([
                'name' => 'Initial Price',
                'buy_price' => $item['buy_price'],
                'sell_price' => $item['sell_price'],
                'effective_from' => now(),
                'effective_until' => null,
                'is_default' => true,
                'is_lock' => false,
            ]);
        }
    }
}
