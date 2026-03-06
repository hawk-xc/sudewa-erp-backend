<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Toyota',
            'Honda',
            'Suzuki',
            'Mitsubishi',
            'Daihatsu',
            'Isuzu',
            'Nissan',
            'Mazda',
            'Hyundai',
            'Kia',
        ];

        foreach ($brands as $brand) {
            Brand::create([
                'name' => $brand,
            ]);
        }
    }
}
