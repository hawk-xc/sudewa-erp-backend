<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Master Data',
                'slug' => 'master-data',
                'description' => 'Data Utama',
            ],
            [
                'name' => 'Transaction',
                'slug' => 'transaction',
                'description' => 'Transaksi',
            ],
            [
                'name' => 'Warehouse',
                'slug' => 'warehouse',
                'description' => 'Gudang',
            ],
            [
                'name' => 'Finance',
                'slug' => 'finance',
                'description' => 'Keuangan',
            ],
            [
                'name' => 'Report',
                'slug' => 'report',
                'description' => 'Laporan',
            ],
        ];

        Module::insert($modules);
    }
}
