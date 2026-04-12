<?php

namespace Database\Seeders;

use App\Models\Cash;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cashes = [
            [
                'cash_idr',
                'cash'
            ],
            [
                'bca_idr',
                'bank'
            ],
            [
                'bca_usd',
                'bank'
            ]
        ];

        $companies = [
            [
                'code' => 1,
                'uuid' => Str::uuid(),
                'name' => 'PT Wajira Morindo',
                'slug' => 'wajira-morindo',
                'description' => 'PT Wajira Morindo',
                'type' => 'office',
            ],
            [
                'code' => 2,
                'uuid' => Str::uuid(),
                'name' => 'PT Wajira International',
                'slug' => 'wajira-international',
                'description' => 'PT Wajira International',
                'type' => 'office',
            ],
            [
                'code' => 3,
                'uuid' => Str::uuid(),
                'name' => 'PT Wajira Yanotama',
                'slug' => 'wajira-yanotama',
                'description' => 'PT Wajira Yanotama',
                'type' => 'transport_office',
            ],
            [
                'code' => 4,
                'uuid' => Str::uuid(),
                'name' => 'PT Wajira Transindo',
                'slug' => 'wajira-transindo',
                'description' => 'PT Wajira Transindo',
                'type' => 'transport_office',
            ],
            [
                'code' => 5,
                'uuid' => Str::uuid(),
                'name' => 'PT Adhiyas Agradasta',
                'slug' => 'adhiyas-agradasta',
                'description' => 'PT Adhiyas Agradasta',
                'type' => 'office',
            ],
        ];

        Company::insert($companies);

        $companies = Company::all();

        foreach ($companies as $company) {
            foreach ($cashes as $cash) {
                Cash::create([
                    'company_id' => (int) $company->id,
                    'code' => (string) $cash[0],
                    'description' => (string) "Kas " . $cash[0] . " " . $company['name'],
                    'type' => (string) $cash[1],
                    'amount' => (int) 0,
                ]);
            }
        }
    }
}
