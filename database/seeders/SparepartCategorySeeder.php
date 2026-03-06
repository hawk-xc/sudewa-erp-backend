<?php

namespace Database\Seeders;

use App\Models\SparepartCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SparepartCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['uuid' => Str::uuid(), 'code' => 'SPC001', 'name' => 'Mesin'],
            ['uuid' => Str::uuid(), 'code' => 'SPC002', 'name' => 'Sistem Bahan Bakar'],
            ['uuid' => Str::uuid(), 'code' => 'SPC003', 'name' => 'Sistem Pendingin'],
            ['uuid' => Str::uuid(), 'code' => 'SPC004', 'name' => 'Kelistrikan'],
            ['uuid' => Str::uuid(), 'code' => 'SPC005', 'name' => 'Suspensi'],
            ['uuid' => Str::uuid(), 'code' => 'SPC006', 'name' => 'Rem'],
            ['uuid' => Str::uuid(), 'code' => 'SPC007', 'name' => 'Rantai & Gear'],
            ['uuid' => Str::uuid(), 'code' => 'SPC008', 'name' => 'Ban & Velg'],
            ['uuid' => Str::uuid(), 'code' => 'SPC009', 'name' => 'Body & Cover'],
            ['uuid' => Str::uuid(), 'code' => 'SPC010', 'name' => 'Knalpot'],
            ['uuid' => Str::uuid(), 'code' => 'SPC011', 'name' => 'Filter'],
            ['uuid' => Str::uuid(), 'code' => 'SPC012', 'name' => 'Oli & Pelumas'],
            ['uuid' => Str::uuid(), 'code' => 'SPC013', 'name' => 'Lampu'],
            ['uuid' => Str::uuid(), 'code' => 'SPC014', 'name' => 'Aksesoris'],
            ['uuid' => Str::uuid(), 'code' => 'SPC015', 'name' => 'Lainnya'],
        ];

        SparepartCategory::insert($categories);
    }
}
