<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Person;
use App\Traits\GlobalCodeNumberTrait;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PersonSeeder extends Seeder
{
    use GlobalCodeNumberTrait;

    public function run(): void
    {
        $typeCompanyMap = [
            'supplier' => [1, 2, 3],
            'customer' => [1, 2, 3],
            'dealer' => [3, 4],
            'vendor' => [3],
            'driver' => [4],
        ];

        $companySlugs = Company::pluck('slug', 'id')->toArray();

        foreach ($typeCompanyMap as $type => $companyIds) {
            for ($i = 1; $i <= 50; $i++) {
                // Distribute company IDs evenly using round-robin
                $companyId = $companyIds[($i - 1) % count($companyIds)];
                $companySlug = $companySlugs[$companyId] ?? 'default';

                Person::create([
                    'uuid' => (string) Str::uuid(),
                    'pic_name' => 'Ahmad',
                    'company_id' => $companyId,
                    'code' => $this->code($companySlug, $type),
                    'type' => $type,
                    'name' => ucfirst($type).' '.$i,
                    'address' => 'Jl. Contoh Alamat No. '.$i,
                    'npwp' => (string) rand(100000000000000, 999999999999999),
                    'phone' => '08'.rand(1111111111, 9999999999),
                ]);
            }
        }
    }
}


