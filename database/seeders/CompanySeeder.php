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
                'name' => 'PT DERALY GENERAL',
                'slug' => 'deraly-general',
                'description' => 'PT DERALY GENERAL',
                'type' => 'office',
            ],
        ];

        Company::insert($companies);

        $companies = Company::all();

        foreach ($companies as $company) {
            foreach ($cashes as $cash) {
                $code = (string) $cash[0];
                $parts = explode('_', $code);
                $name = $parts[0];
                $currency = isset($parts[1]) ? strtoupper($parts[1]) : '';
                $formattedName = strtolower($name) === 'cash' ? 'Cash' : strtoupper($name);
                $cashName = $currency ? "{$formattedName} {$currency}" : $formattedName;

                Cash::create([
                    'company_id' => (int) $company->id,
                    'code' => $code,
                    'cash_name' => $cashName,
                    'description' => (string) "Kas " . $code . " " . $company['name'],
                    'type' => (string) $cash[1],
                    'amount' => (int) 0,
                ]);
            }
        }
    }
}
