<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleHasFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'modules' => [
                    'master-data',
                    'master-data-transport-office',
                    'transaction',
                    'transaction-transport-office',
                    'warehouse',
                    'finance',
                    'report',
                    'report-transport-office',
                ],
                'features' => [
                    'master-data' => [
                        'accounts',
                        'suppliers',
                        'customers',
                        'unit-types',
                        'spare-parts',
                        'cash-accounts',
                        'users',
                    ],
                    'master-data-transport-office' => [
                        'dealers',
                        'customers',
                        'tariffs',
                        'drivers',
                        'vehicles',
                    ],
                    'transaction' => [
                        'transaction-journal',
                        'unit-purchases',
                        'unit-sales',
                    ],
                    'transaction-transport-office' => [
                        'transaction-journal', // arus transaksi
                        'expedition-delivery-orders', // input do ekspedisi
                        'invoices', // invoice
                        'expedition-reports', // laporan pertanggungjawaban
                        'vehicle-usage-reports', // data operasional kendaraan
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
                    'report-transport-office' => [
                        'stnk-reports', // stnk belum diterima
                        'bpkb-reports', // bpkb belum diterima
                        'vehicle-number-reports', // plat belum di terima
                        'ditlantas-process-reports', // proses ditlantas
                        'samsat-register-reports', // daftar samsat
                        'bpkb-register-reports', // daftar bpkb
                        'bbn-reports', // laporan BBN
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($features) {

            foreach ($features as $group) {

                foreach ($group['features'] as $moduleSlug => $featureSlugs) {

                    $moduleId = DB::table('modules')
                        ->where('slug', $moduleSlug)
                        ->value('id');

                    if (! $moduleId) {
                        continue;
                    }

                    foreach ($featureSlugs as $featureSlug) {

                        $featureId = DB::table('features')
                            ->where('slug', $featureSlug)
                            ->value('id');

                        if (! $featureId) {
                            continue;
                        }

                        $exists = DB::table('module_has_features')
                            ->where('module_id', $moduleId)
                            ->where('feature_id', $featureId)
                            ->exists();

                        if (! $exists) {
                            DB::table('module_has_features')->insert([
                                'module_id' => $moduleId,
                                'feature_id' => $featureId,
                            ]);
                        }
                    }
                }
            }
        });
    }
}
