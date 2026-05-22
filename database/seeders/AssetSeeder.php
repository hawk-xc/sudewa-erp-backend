<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyId = \App\Models\Company::first()?->id ?? 1;
        
        $assetTypes = ['inventory', 'vehicles', 'buildings', 'land'];
        
        for ($i = 1; $i <= 15; $i++) {
            $asset = \App\Models\Asset::create([
                'company_id' => $companyId,
                'code' => 'AST-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'name' => 'Asset Dummy ' . $i,
                'purchase_date' => now()->subDays(rand(1, 365))->format('Y-m-d'),
                'type' => $assetTypes[array_rand($assetTypes)],
                'price' => rand(1000000, 50000000),
            ]);

            // Database trigger already created the FinanceAsset record.
            // We can now update it with some dummy finance data.
            $asset->load('financeAsset'); // Assuming there is a hasOne relationship
            if ($asset->financeAsset) {
                $asset->financeAsset->update([
                    'economic_age' => rand(1, 10),
                    'description' => 'Dummy finance details for ' . $asset->name,
                ]);
            }
        }
    }
}
