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
            ['code' => 'PPN', 'name' => 'Pajak Pertambahan Nilai', 'is_lock' => true],
            ['code' => 'PPh21', 'name' => 'Pajak Penghasilan Pasal 21', 'is_lock' => false],
            ['code' => 'PPh23', 'name' => 'Pajak Penghasilan Pasal 23', 'is_lock' => false],
            ['code' => 'PPhFinal', 'name' => 'Pajak Penghasilan Final', 'is_lock' => false],
            ['code' => 'BeaMaterai', 'name' => 'Bea Materai', 'is_lock' => false],
            ['code' => 'PajakDaerah', 'name' => 'Pajak Daerah', 'is_lock' => false],
            ['code' => 'PajakImpor', 'name' => 'Pajak Impor', 'is_lock' => false],
            ['code' => 'PajakLainnya', 'name' => 'Pajak Lainnya', 'is_lock' => false],
        ];

        $taxVersions = [
            'PPN' => [
                ['name' => 'PPN 11%', 'rate' => 11, 'is_default' => true],
            ],
            'PPh21' => [
                ['name' => 'PPh 21 - 5%', 'rate' => 5, 'is_default' => true],
            ],
            'PPh23' => [
                ['name' => 'PPh 23 - 2%', 'rate' => 2, 'is_default' => true],
            ],
            'PPhFinal' => [
                ['name' => 'PPh Final - 0.5%', 'rate' => 1, 'is_default' => true],
            ],
            'BeaMaterai' => [
                ['name' => 'Bea Materai Rp10.000', 'rate' => 10000, 'is_default' => true],
            ],
            'PajakDaerah' => [
                ['name' => 'Pajak Daerah 10%', 'rate' => 10, 'is_default' => true],
            ],
            'PajakImpor' => [
                ['name' => 'Pajak Impor 10%', 'rate' => 10, 'is_default' => true],
            ],
            'PajakLainnya' => [
                ['name' => 'Pajak Lainnya 0%', 'rate' => 0, 'is_default' => true],
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
