<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'modules' => ['master-data', 'transaction', 'warehouse', 'finance', 'report'],
                'features' => [
                    'master-data' => [
                        'chart-of-accounts',
                        'suppliers',
                        'customers',
                        'unit-types',
                        'spare-parts',
                        'cash-accounts',
                        'users',
                    ],
                    'transaction' => [
                        'transaction-journal',
                        'unit-purchases',
                        'unit-sales',
                    ],
                    'warehouse' => [
                        'unit-inventory',
                        'unit-receipts',
                        'unit-dispatches',
                    ],
                    'finance' => [
                        'daily-cash-transactions',
                        'purchase-vat-records',
                        'sales-vat-records',
                        'purchase-refunds',
                        'sales-refunds',
                        'accounts-payable',
                        'payable-payments',
                        'accounts-receivable',
                        'receivable-collections',
                    ],
                    'report' => [
                        'cash-transaction-reports',
                        'accounting-reports',
                        'purchase-reports',
                        'sales-reports',
                        'receipt-reports',
                        'dispatch-reports',
                        'inventory-reports',
                    ],
                ],
            ],
        ];

        foreach ($features as $featureGroup) {

            foreach ($featureGroup['features'] as $moduleSlug => $featureSlugs) {

                $moduleId = DB::table('modules')
                    ->where('slug', $moduleSlug)
                    ->value('id');

                if (! $moduleId) {
                    continue;
                }

                $featureIds = DB::table('features')
                    ->whereIn('slug', $featureSlugs)
                    ->pluck('id');

                foreach ($featureIds as $featureId) {
                    DB::table('module_features')->insert([
                        'module_id' => $moduleId,
                        'feature_id' => $featureId,
                    ]);
                }
            }
        }
    }
}
