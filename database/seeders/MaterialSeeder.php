<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $materials = [
            [
                'code' => 'MAT-WJT-001',
                'name' => 'Semen Padang 50kg',
                'price' => 75000,
                'type' => 'pcs',
            ],
            [
                'code' => 'MAT-WJT-002',
                'name' => 'Besi Beton 12mm',
                'price' => 95000,
                'type' => 'pcs',
            ],
            [
                'code' => 'MAT-WJT-003',
                'name' => 'Pasir Cor (m3)',
                'price' => 250000,
                'type' => 'pcs',
            ],
            [
                'code' => 'MAT-WJT-004',
                'name' => 'Batu Belah',
                'price' => 180000,
                'type' => 'pcs',
            ],
            [
                'code' => 'MAT-WJT-005',
                'name' => 'Cat Tembok 25kg (set)',
                'price' => 650000,
                'type' => 'set',
            ],
        ];

        foreach ($materials as $material) {
            Material::firstOrCreate(
                ['code' => $material['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $material['name'],
                    'price' => $material['price'],
                    'type' => $material['type'],
                ]
            );
        }
    }
}
