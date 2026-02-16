<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'PT Wajira Morindo',
                'slug' => 'wajira-morindo',
                'description' => 'PT Wajira Morindo',
            ],
            [
                'name' => 'PT Wajira International',
                'slug' => 'wajira-international',
                'description' => 'PT Wajira International',
            ],
            [
                'name' => 'PT Wajira Yanotama',
                'slug' => 'wajira-yanotama',
                'description' => 'PT Wajira Yanotama',
            ],
            [
                'name' => 'PT Wajira Transindo',
                'slug' => 'wajira-transindo',
                'description' => 'PT Wajira Transindo',
            ],
            [
                'name' => 'PT Adhiyas Agradasta',
                'slug' => 'adhiyas-agradasta',
                'description' => 'PT Adhiyas Agradasta',
            ],
        ];

        Company::insert($companies);
    }
}
