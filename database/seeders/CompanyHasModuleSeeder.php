<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyHasModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'company' => 'wajira-morindo',
                'modules' => [
                    'master-data',
                    'transaction',
                    'warehouse',
                    'finance',
                    'report',
                ],
            ],
            [
                'company' => 'wajira-international',
                'modules' => [
                    'master-data',
                    'transaction',
                    'warehouse',
                    'finance',
                    'report',
                ],
            ],
            [
                'company' => 'wajira-transindo',
                'modules' => [
                    'master-data',
                    'transaction',
                    'report',
                ],
            ],
            [
                'company' => 'adhiyas-agradasta',
                'modules' => [
                    'master-data',
                    'transaction',
                    'warehouse',
                    'finance',
                    'report',
                ],
            ],
            [
                'company' => 'wajira-yanotama',
                'modules' => [
                    'master-data',
                    'transaction',
                    'report',
                ],
            ],
        ];

        DB::transaction(function () use ($companies) {
            foreach ($companies as $company) {
                $companyId = DB::table('companies')
                    ->where('slug', $company['company'])
                    ->value('id');

                if (! $companyId) {
                    continue;
                }

                $moduleIds = DB::table('modules')
                    ->whereIn('slug', $company['modules'])
                    ->pluck('id');

                foreach ($moduleIds as $moduleId) {
                    DB::table('company_has_modules')->insert([
                        'company_id' => $companyId,
                        'module_id' => $moduleId,
                    ]);
                }
            }
        });
    }
}
