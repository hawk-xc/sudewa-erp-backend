<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MainCompanyWarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $main_company = [1, 2, 5];

        $companies = Company::whereIn('id', $main_company)->get();

        foreach ($companies as $company) {
            Warehouse::create([
                'company_id' => (int) $company->id,
                'name' => (string) $company->name.' Warehouse',
                'capacity' => (int) 10000,
                'description' => null,
            ]);
        }
    }
}
