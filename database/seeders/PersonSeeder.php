<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Traits\PersonTrait;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PersonSeeder extends Seeder
{
    use PersonTrait;

    public function run(): void
    {
        $types = ['supplier', 'customer'];

        for ($i = 1; $i <= 100; $i++) {

            $type = $i <= 50 ? 'supplier' : 'customer';

            Person::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => null,
                'company_id' => 1,
                'code' => $type === 'supplier' ? $this->generateCode('supplier') : $this->generateCode('customer'),
                'type' => $type,
                'name' => ucfirst($type).' '.$i,
                'address' => 'Jl. Contoh Alamat No. '.$i,
                'npwp' => rand(100000000000000, 999999999999999),
                'phone' => '08'.rand(1111111111, 9999999999),
            ]);
        }
    }
}
