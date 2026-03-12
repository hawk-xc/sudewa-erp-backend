<?php

namespace Database\Seeders;

use App\Models\Sparepart;
use App\Models\SparepartCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SparepartSeeder extends Seeder
{
    public function run(): void
    {
        $categories = SparepartCategory::pluck('id', 'name');

        $spareparts = [
            ['category' => 'Mesin', 'name' => 'Piston'],
            ['category' => 'Mesin', 'name' => 'Ring Piston'],
            ['category' => 'Mesin', 'name' => 'Klep In'],
            ['category' => 'Mesin', 'name' => 'Klep Ex'],
            ['category' => 'Mesin', 'name' => 'Noken As'],

            ['category' => 'Kelistrikan', 'name' => 'Aki'],
            ['category' => 'Kelistrikan', 'name' => 'CDI'],
            ['category' => 'Kelistrikan', 'name' => 'Kiprok'],
            ['category' => 'Kelistrikan', 'name' => 'Koil'],
            ['category' => 'Kelistrikan', 'name' => 'Busi'],

            ['category' => 'Rem', 'name' => 'Kampas Rem Depan'],
            ['category' => 'Rem', 'name' => 'Kampas Rem Belakang'],
            ['category' => 'Rem', 'name' => 'Master Rem'],
            ['category' => 'Rem', 'name' => 'Selang Rem'],

            ['category' => 'Suspensi', 'name' => 'Shock Depan'],
            ['category' => 'Suspensi', 'name' => 'Shock Belakang'],

            ['category' => 'Rantai & Gear', 'name' => 'Rantai'],
            ['category' => 'Rantai & Gear', 'name' => 'Gear Depan'],
            ['category' => 'Rantai & Gear', 'name' => 'Gear Belakang'],

            ['category' => 'Ban & Velg', 'name' => 'Ban Depan'],
            ['category' => 'Ban & Velg', 'name' => 'Ban Belakang'],
            ['category' => 'Ban & Velg', 'name' => 'Velg Depan'],
            ['category' => 'Ban & Velg', 'name' => 'Velg Belakang'],

            ['category' => 'Filter', 'name' => 'Filter Udara'],
            ['category' => 'Filter', 'name' => 'Filter Oli'],

            ['category' => 'Oli & Pelumas', 'name' => 'Oli Mesin'],
            ['category' => 'Oli & Pelumas', 'name' => 'Oli Gardan'],

            ['category' => 'Lampu', 'name' => 'Lampu Depan'],
            ['category' => 'Lampu', 'name' => 'Lampu Belakang'],
            ['category' => 'Lampu', 'name' => 'Lampu Sein'],
        ];

        $data = [];

        foreach ($spareparts as $index => $sp) {

            if (! isset($categories[$sp['category']])) {
                continue;
            }

            $data[] = [
                'uuid' => Str::uuid(),
                'sparepart_category_id' => $categories[$sp['category']],
                'code' => 'SPR'.str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'name' => $sp['name'],
                'buy_price' => rand(10000, 500000),
                'sell_price' => rand(10000, 500000),
                'capacity' => rand(1, 50),
                'image' => null,
                'unit_type' => 'box',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Tambahkan sampai 100 sparepart
        $count = count($data);
        $categoryIds = SparepartCategory::pluck('id')->toArray();

        for ($i = $count; $i < 100; $i++) {
            $data[] = [
                'uuid' => Str::uuid(),
                'sparepart_category_id' => $categoryIds[array_rand($categoryIds)],
                'code' => 'SPR'.str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'name' => 'Sparepart '.($i + 1),
                'buy_price' => rand(10000, 500000),
                'sell_price' => rand(10000, 500000),
                'capacity' => rand(1, 50),
                'image' => null,
                'unit_type' => 'pcs',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Sparepart::insert($data);
    }
}
