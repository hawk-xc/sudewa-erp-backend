<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // auth
        $this->call(PermissionSeeder::class);
        $this->call(UserSeeder::class);

        // app flow seed
        $this->call(CompanySeeder::class);
        $this->call(ModuleSeeder::class);
        $this->call(FeatureSeeder::class);

        // app flow pivot
        $this->call(CompanyHasModuleSeeder::class);
        $this->call(ModuleHasFeatureSeeder::class);

        // Dummy Data
        $this->call(BrandSeeder::class);
        $this->call(MainCompanyWarehouseSeeder::class);

        // Default Group Account and Account Data
        $this->call(DefaultAccountSeeder::class);
        $this->call(VehicleFleetSeeder::class);

        // Tax and Tax Version
        $this->call(TaxSeeder::class);
    }
}
