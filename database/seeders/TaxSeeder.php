<?php

namespace Database\Seeders;

use App\Models\Tax;
use App\Models\TaxVersion;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            ['code' => 'ppn', 'name' => 'Pajak Pertambahan Nilai', 'is_lock' => true],
            ['code' => 'dpp', 'name' => 'Dasar Pengenaan Pajak', 'is_lock' => true],
            ['code' => 'pph21', 'name' => 'Pajak Penghasilan Pasal 21', 'is_lock' => true],
            ['code' => 'pph23', 'name' => 'Pajak Penghasilan Pasal 23', 'is_lock' => true],
            ['code' => 'pphfinal', 'name' => 'Pajak Penghasilan Final', 'is_lock' => true],
            ['code' => 'beamaterei', 'name' => 'Bea Materai', 'is_lock' => true],
            ['code' => 'pajakdaerah', 'name' => 'Pajak Daerah', 'is_lock' => true],
            ['code' => 'pajakimpor', 'name' => 'Pajak Impor', 'is_lock' => true],
            ['code' => 'pajaklainnya', 'name' => 'Pajak Lainnya', 'is_lock' => true],
        ];

        $taxVersions = [
            'ppn' => [
                ['name' => 'PPN 11%', 'rate' => 11, 'is_default' => true, 'is_lock' => true],
            ],
            'dpp' => [
                ['name' => 'DPP 100%', 'rate' => 111, 'is_default' => true, 'is_lock' => true],
            ],
            'pph21' => [
                ['name' => 'PPh 21 - 5%', 'rate' => 5, 'is_default' => true, 'is_lock' => true],
            ],
            'pph23' => [
                ['name' => 'PPh 23 - 2%', 'rate' => 2, 'is_default' => true, 'is_lock' => true],
            ],
            'pphfinal' => [
                ['name' => 'PPh Final - 0.5%', 'rate' => 1, 'is_default' => true, 'is_lock' => true],
            ],
            'beamaterei' => [
                ['name' => 'Bea Materai Rp10.000', 'rate' => 10000, 'is_default' => true, 'is_lock' => true],
            ],
            'pajakdaerah' => [
                ['name' => 'Pajak Daerah 10%', 'rate' => 10, 'is_default' => true, 'is_lock' => true],
            ],
            'pajakimpor' => [
                ['name' => 'Pajak Impor 10%', 'rate' => 10, 'is_default' => true, 'is_lock' => true],
            ],
            'pajaklainnya' => [
                ['name' => 'Pajak Lainnya 0%', 'rate' => 0, 'is_default' => true, 'is_lock' => true],
            ],
        ];

        foreach ($taxes as $taxData) {
            $tax = Tax::create($taxData);

            if (isset($taxVersions[$tax->code])) {
                foreach ($taxVersions[$tax->code] as $versionData) {
                    $tax->TaxVersions()->create($versionData);
                }
            }
        }
    }
}
