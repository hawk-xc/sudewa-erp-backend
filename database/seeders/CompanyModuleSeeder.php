<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = ['master-data', 'transaction', 'warehouse', 'finance', 'report'];

        $groups = [
            [
                'companies' => ['wajira-morindo', 'wajira-international', 'wajira-yanotama'],
                'modules' => $modules,
            ],
            [
                'companies' => ['wajira-transindo', 'adhiyas-agradasta'],
                'modules' => [$modules[0], $modules[1], $modules[4]],
            ],
        ];

        foreach ($groups as $group) {
            $companyIds = DB::table('companies')->whereIn('slug', $group['companies'])->pluck('id');
            $moduleIds = DB::table('modules')->whereIn('slug', $group['modules'])->pluck('id');

            foreach ($companyIds as $companyId) {
                foreach ($moduleIds as $moduleId) {
                    DB::table('company_modules')->insert([
                        'company_id' => $companyId,
                        'module_id' => $moduleId,
                    ]);
                }
            }
        }
    }
}
