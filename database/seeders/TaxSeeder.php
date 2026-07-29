<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            ['code' => 'ppn', 'name' => 'Pajak Pertambahan Nilai', 'is_lock' => true],
            ['code' => 'dpp', 'name' => 'Dasar Pengenaan Pajak', 'is_lock' => false],
            ['code' => 'pph21', 'name' => 'Pajak Penghasilan Pasal 21', 'is_lock' => false],
            ['code' => 'pph23', 'name' => 'Pajak Penghasilan Pasal 23', 'is_lock' => false],
            ['code' => 'pphfinal', 'name' => 'Pajak Penghasilan Final', 'is_lock' => false],
            ['code' => 'beamaterei', 'name' => 'Bea Materai', 'is_lock' => false],
            ['code' => 'pajakdaerah', 'name' => 'Pajak Daerah', 'is_lock' => false],
            ['code' => 'pajakimpor', 'name' => 'Pajak Impor', 'is_lock' => false],
            ['code' => 'pajaklainnya', 'name' => 'Pajak Lainnya', 'is_lock' => false],
        ];

        $taxVersions = [
            'ppn' => [
                ['name' => 'PPN 11%', 'rate' => 11, 'is_default' => true, 'is_lock' => true],
            ],
            'dpp' => [
                ['name' => 'DPP 100%', 'rate' => 111, 'is_default' => true, 'is_lock' => false],
            ],
            'pph21' => [
                ['name' => 'PPh 21 - 5%', 'rate' => 5, 'is_default' => true, 'is_lock' => false],
            ],
            'pph23' => [
                ['name' => 'PPh 23 - 2%', 'rate' => 2, 'is_default' => true, 'is_lock' => false],
            ],
            'pphfinal' => [
                ['name' => 'PPh Final - 0.5%', 'rate' => 1, 'is_default' => true, 'is_lock' => false],
            ],
            'beamaterei' => [
                ['name' => 'Bea Materai Rp10.000', 'rate' => 10000, 'is_default' => true, 'is_lock' => false],
            ],
            'pajakdaerah' => [
                ['name' => 'Pajak Daerah 10%', 'rate' => 10, 'is_default' => true, 'is_lock' => false],
            ],
            'pajakimpor' => [
                ['name' => 'Pajak Impor 10%', 'rate' => 10, 'is_default' => true, 'is_lock' => false],
            ],
            'pajaklainnya' => [
                ['name' => 'Pajak Lainnya 0%', 'rate' => 0, 'is_default' => true, 'is_lock' => false],
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
