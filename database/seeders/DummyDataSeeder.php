<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BrandSeeder::class,
            PersonSeeder::class,
            SparepartCategorySeeder::class,
            SparepartSeeder::class,
            UnitTypeSeeder::class,
        ]);
    }
}
