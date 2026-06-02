<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Person;
use App\Models\Region;
use App\Models\VehicleData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleDataSeeder extends Seeder
{
    public function run(): void
    {
        $regions = Region::all();
        if ($regions->isEmpty()) {
            $regions = collect([
                Region::create([
                    'code' => 'REG-001',
                    'name' => 'DKI Jakarta',
                ]),
                Region::create([
                    'code' => 'REG-002',
                    'name' => 'Jawa Barat',
                ]),
            ]);
        }

        $dealers = Person::where('type', 'dealer')->get();
        if ($dealers->isEmpty()) {
            $companyId = Company::value('id') ?? 1;
            $dealers = collect([
                Person::create([
                    'uuid' => (string) Str::uuid(),
                    'pic_name' => 'Ahmad',
                    'company_id' => $companyId,
                    'code' => 'DLR-001',
                    'type' => 'dealer',
                    'name' => 'Dealer Fallback',
                ])
            ]);
        }

        for ($i = 1; $i <= 20; $i++) {
            $dealer = $dealers->random();
            $region = $regions->random();

            VehicleData::create([
                'uuid' => (string) Str::uuid(),
                'dealer_id' => $dealer->id,
                'region_id' => $region->id,
                'invoice_number' => 'INV/' . date('Ymd') . '/' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'invoice_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'invoice_receive_date' => now()->subDays(rand(1, 10))->format('Y-m-d'),
                'vehicle_type' => ['r2', 'r3', 'r4'][rand(0, 2)],
                
                // customer data
                'ktp_number' => '3171' . rand(100000000000, 999999999999),
                'phone_number' => '08' . rand(111111111, 999999999),
                'occupation' => ['Karyawan Swasta', 'PNS', 'Wiraswasta', 'Mahasiswa'][rand(0, 3)],
                'stnk_name' => 'Customer STNK ' . $i,
                'stnk_address' => 'Jl. Kebagusan No. ' . $i,
                'village' => 'Kebagusan',
                'district' => 'Pasar Minggu',
                'sub_village' => 'RT ' . str_pad(rand(1, 15), 3, '0', STR_PAD_LEFT),
                'sub_district' => 'RW ' . str_pad(rand(1, 15), 3, '0', STR_PAD_LEFT),
                'regency' => 'Jakarta Selatan',
                'postal_code' => '12' . rand(100, 999),

                // vehicle data
                'motorcycle_brand' => ['Honda', 'Yamaha', 'Suzuki'][rand(0, 2)],
                'motorcycle_type' => ['Scoopy', 'Nmax', 'Vario', 'Beat', 'Aerox'][rand(0, 4)],
                'motorcycle_category' => 'Scooter',
                'motorcycle_model' => 'Model-' . Str::upper(Str::random(4)),
                'manufacture_year' => rand(2024, 2026),
                'engine_capacity' => [110, 125, 150, 155, 250][rand(0, 4)],
                'color' => ['Hitam', 'Putih', 'Merah', 'Abu-abu', 'Biru'][rand(0, 4)],
                'price' => rand(20000000, 45000000),
                'chassis_number' => 'MH1KC' . Str::upper(Str::random(12)),
                'machine_number' => 'KC58E' . Str::upper(Str::random(7)),
                'form_ab' => 'FORM-AB-' . Str::upper(Str::random(6)),
                'pib' => 'PIB-' . Str::upper(Str::random(6)),
                'tpt_number' => 'TPT-' . Str::upper(Str::random(6)),
                'sut_number' => 'SUT-' . Str::upper(Str::random(6)),
                'srut_number' => 'SRUT-' . Str::upper(Str::random(6)),
                'fuel_type' => 'Bensin'
            ]);
        }
    }
}
