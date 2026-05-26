<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use Illuminate\Database\Seeder;

class DefaultAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'type' => 'group',
                'code' => '1',
                'name' => 'AKTIVA',
                'children' => [
                    [
                        'type' => 'group',
                        'code' => '11',
                        'name' => 'AKTIVA LANCAR',
                        'children' => [
                            [
                                'type' => 'group',
                                'code' => '111',
                                'name' => 'KAS',
                                'children' => [
                                    [
                                        'type' => 'account',
                                        'code' => '11100001',
                                        'name' => 'CASH IN HAND',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'group',
                                'code' => '112',
                                'name' => 'KAS BANK',
                                'children' => [
                                    [
                                        'type' => 'account',
                                        'code' => '11200001',
                                        'name' => 'BANK BCA IDR',
                                    ],
                                    [
                                        'type' => 'account',
                                        'code' => '11200002',
                                        'name' => 'BANK BCA USD',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'group',
                                'code' => '113',
                                'name' => 'PIUTANG',
                                'children' => [
                                    [
                                        'type' => 'group',
                                        'code' => '1131',
                                        'name' => 'PIUTANG USAHA',
                                        'children' => [
                                            [
                                                'type' => 'account',
                                                'code' => '11310001',
                                                'name' => 'PIUTANG DAGANG',
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'group',
                                        'code' => '1132',
                                        'name' => 'PIUTANG PENDANAAN',
                                        'children' => [
                                            [
                                                'type' => 'account',
                                                'code' => '11320001',
                                                'name' => 'PIUTANG DIREKSI',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11320002',
                                                'name' => 'PIUTANG PEMEGANG SAHAM',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11320099',
                                                'name' => 'PIUTANG LAIN-LAIN',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'group',
                                'code' => '114',
                                'name' => 'PERSEDIAAN BARANG DAGANG',
                                'children' => [
                                    [
                                        'type' => 'group',
                                        'code' => '1141',
                                        'name' => 'PERSEDIAAN BARANG DAGANG HONDA',
                                        'children' => [
                                            [
                                                'type' => 'account',
                                                'code' => '11410001',
                                                'name' => 'PERSEDIAAN MOTOR HONDA SONIC',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410002',
                                                'name' => 'PERSEDIAAN MOTOR HONDA CBR',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410003',
                                                'name' => 'PERSEDIAAN MOTOR HONDA VARIO',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410004',
                                                'name' => 'PERSEDIAAN MOTOR HONDA PCX',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410005',
                                                'name' => 'PERSEDIAAN MOTOR HONDA ADV',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410006',
                                                'name' => 'PERSEDIAAN MOTOR HONDA SCOOPY',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410007',
                                                'name' => 'PERSEDIAAN MOTOR HONDA CRF',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410008',
                                                'name' => 'PERSEDIAAN MOTOR HONDA REVO',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410009',
                                                'name' => 'PERSEDIAAN MOTOR HONDA SUPRA',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410010',
                                                'name' => 'PERSEDIAAN MOTOR HONDA GENIO',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410011',
                                                'name' => 'PERSEDIAAN MOTOR HONDA BEAT',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410012',
                                                'name' => 'PERSEDIAAN MOTOR HONDA CB VERZA',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11410013',
                                                'name' => 'PERSEDIAAN MOTOR HONDA STYLO',
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'group',
                                        'code' => '1142',
                                        'name' => 'PERSEDIAAN BARANG DAGANG YAMAHA',
                                        'children' => [
                                            [
                                                'type' => 'account',
                                                'code' => '11420001',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA XMAX',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11420002',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA MT25',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11420003',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA MX KING',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11420004',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA AEROX',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11420005',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA MIO CW',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11420006',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA NMAX',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11420007',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA XSR 155',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11420008',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA GEAR',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11420009',
                                                'name' => 'PERSEDIAAN MOTOR YAMAHA VIXION',
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'group',
                                        'code' => '1143',
                                        'name' => 'PERSEDIAAN MOTOR SUZUKI',
                                        'children' => [
                                            [
                                                'type' => 'account',
                                                'code' => '11430001',
                                                'name' => 'PERSEDIAAN MOTOR SUZUKI SATRIA',
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'group',
                                        'code' => '1144',
                                        'name' => 'PERSEDIAAN SPAREPARTS',
                                        'children' => [
                                            [
                                                'type' => 'account',
                                                'code' => '11440001',
                                                'name' => 'PERSEDIAAN SPAREPARTS HONDA',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11440002',
                                                'name' => 'PERSEDIAAN SPAREPARTS YAMAHA',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11440003',
                                                'name' => 'PERSEDIAAN SPAREPARTS SUZUKI',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500001',
                                                'name' => 'SEWA GEDUNG DIBAYAR DI MUKA',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500002',
                                                'name' => 'UANG MUKA PPH FINAL',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500003',
                                                'name' => 'UANG MUKA PPH 23',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500004',
                                                'name' => 'UANG MUKA PPH 25',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500005',
                                                'name' => 'UANG MUKA PPN',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500006',
                                                'name' => 'UANG MUKA PEMBELIAN YAMAHA',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500007',
                                                'name' => 'UANG MUKA PEMBELIAN HONDA',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500008',
                                                'name' => 'UANG MUKA PEMBELIAN VESPA',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '11500009',
                                                'name' => 'PPN MASUKAN BELUM TERBIT',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '12100001',
                                                'name' => 'INVENTARIS KANTOR',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '12100002',
                                                'name' => 'KENDARAAN',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '12100003',
                                                'name' => 'BANGUNAN',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '12100004',
                                                'name' => 'TANAH',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '12200001',
                                                'name' => 'AKUMULASI PENYUSUTAN INVENTARIS KANTOR',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '12200002',
                                                'name' => 'AKUMULASI PENYUSUTAN KENDARAAN',
                                            ],
                                            [
                                                'type' => 'account',
                                                'code' => '12200003',
                                                'name' => 'AKUMULASI PENYUSUTAN BANGUNAN',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'group',
                                'code' => '115',
                                'name' => 'UANG MUKA',
                                'children' => [
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '12',
                        'name' => 'AKTIVA TETAP',
                        'children' => [
                            [
                                'type' => 'group',
                                'code' => '121',
                                'name' => 'AKTIVA TETAP',
                                'children' => [
                                ],
                            ],
                            [
                                'type' => 'group',
                                'code' => '122',
                                'name' => 'AKUMULASI PENYUSUTAN',
                                'children' => [
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'group',
                'code' => '2',
                'name' => 'HUTANG',
                'children' => [
                    [
                        'type' => 'group',
                        'code' => '21',
                        'name' => 'HUTANG ATAS USAHA',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '21000001',
                                'name' => 'HUTANG DAGANG',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '22',
                        'name' => 'HUTANG PENDANAAN',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '22000001',
                                'name' => 'HUTANG DIREKSI',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '23',
                        'name' => 'HUTANG PAJAK',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '23000001',
                                'name' => 'PPH 21',
                            ],
                            [
                                'type' => 'account',
                                'code' => '23000002',
                                'name' => 'PPH 23',
                            ],
                            [
                                'type' => 'account',
                                'code' => '23000003',
                                'name' => 'PPH 25',
                            ],
                            [
                                'type' => 'account',
                                'code' => '23000004',
                                'name' => 'PPH 29 (BADAN)',
                            ],
                            [
                                'type' => 'account',
                                'code' => '23000005',
                                'name' => 'PPN MASUKAN',
                            ],
                            [
                                'type' => 'account',
                                'code' => '23000006',
                                'name' => 'PPN KELUARAN',
                            ],
                            [
                                'type' => 'account',
                                'code' => '23000007',
                                'name' => 'PPH FINAL',
                            ],
                            [
                                'type' => 'account',
                                'code' => '23000008',
                                'name' => 'HUTANG PPN',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '24',
                        'name' => 'PENDAPATAN DITERIMA DIMUKA',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '24100001',
                                'name' => 'UANG MUKA PENJUALAN',
                            ],
                            [
                                'type' => 'account',
                                'code' => '24100099',
                                'name' => 'PENDAPATAN LAIN-LAIN',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'group',
                'code' => '3',
                'name' => 'EKUITAS',
                'children' => [
                    [
                        'type' => 'group',
                        'code' => '31',
                        'name' => 'MODAL DISETOR',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '31000001',
                                'name' => 'MODAL DISETOR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '31000002',
                                'name' => 'MODAL DIREKTUR',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '32',
                        'name' => 'LABA (RUGI)',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '32000001',
                                'name' => 'LABA (RUGI) DITAHAN PERIODE SEBELUMNYA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '32000002',
                                'name' => 'LABA (RUGI) TAHUN BERJALAN',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'group',
                'code' => '4',
                'name' => 'PENJUALAN',
                'children' => [
                    [
                        'type' => 'group',
                        'code' => '41',
                        'name' => 'PENJUALAN MOTOR HONDA',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '41000001',
                                'name' => 'PENJUALAN MOTOR HONDA SONIC',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000002',
                                'name' => 'PENJUALAN MOTOR HONDA CBR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000003',
                                'name' => 'PENJUALAN MOTOR HONDA VARIO',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000004',
                                'name' => 'PENJUALAN MOTOR HONDA PCX',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000005',
                                'name' => 'PENJUALAN MOTOR HONDA ADV',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000006',
                                'name' => 'PENJUALAN MOTOR HONDA SCOOPY',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000007',
                                'name' => 'PENJUALAN MOTOR HONDA CRF',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000008',
                                'name' => 'PENJUALAN MOTOR HONDA REVO',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000009',
                                'name' => 'PENJUALAN MOTOR HONDA SUPRA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000010',
                                'name' => 'PENJUALAN MOTOR HONDA GENIO',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000011',
                                'name' => 'PENJUALAN MOTOR HONDA BEAT',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000012',
                                'name' => 'PENJUALAN MOTOR HONDA CB VERZA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '41000013',
                                'name' => 'PENJUALAN MOTOR HONDA STYLO',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '42',
                        'name' => 'PENJUALAN MOTOR YAMAHA',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '42000001',
                                'name' => 'PENJUALAN MOTOR YAMAHA XMAX',
                            ],
                            [
                                'type' => 'account',
                                'code' => '42000002',
                                'name' => 'PENJUALAN MOTOR YAMAHA MT25',
                            ],
                            [
                                'type' => 'account',
                                'code' => '42000003',
                                'name' => 'PENJUALAN MOTOR YAMAHA MX KING',
                            ],
                            [
                                'type' => 'account',
                                'code' => '42000004',
                                'name' => 'PENJUALAN MOTOR YAMAHA AEROX',
                            ],
                            [
                                'type' => 'account',
                                'code' => '42000005',
                                'name' => 'PENJUALAN MOTOR YAMAHA MIO CW',
                            ],
                            [
                                'type' => 'account',
                                'code' => '42000006',
                                'name' => 'PENJUALAN MOTOR YAMAHA NMAX',
                            ],
                            [
                                'type' => 'account',
                                'code' => '42000007',
                                'name' => 'PENJUALAN MOTOR YAMAHA XSR 155',
                            ],
                            [
                                'type' => 'account',
                                'code' => '42000008',
                                'name' => 'PENJUALAN MOTOR YAMAHA GEAR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '42000009',
                                'name' => 'PENJUALAN MOTOR YAMAHA VIXION',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '43',
                        'name' => 'PENJUALAN BARANG DAGANG SUZUKI',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '43000001',
                                'name' => 'PENJUALAN MOTOR SUZUKI SATRIA',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '44',
                        'name' => 'PENJUALAN SPAREPARTS',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '44000001',
                                'name' => 'PENJUALAN SPAREPARTS HONDA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '44000002',
                                'name' => 'PENJUALAN SPAREPARTS YAMAHA',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '45',
                        'name' => 'RETUR DAN DISKON',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '45000001',
                                'name' => 'RETUR PENJUALAN',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'group',
                'code' => '5',
                'name' => 'BIAYA PENJUALAN',
                'children' => [
                    [
                        'type' => 'group',
                        'code' => '51',
                        'name' => 'HARGA POKOK PENJUALAN HONDA',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '51000001',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA SONIC',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000002',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA CBR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000003',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA VARIO',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000004',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA PCX',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000005',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA ADV',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000006',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA SCOOPY',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000007',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA CRF',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000008',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA REVO',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000009',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA SUPRA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000010',
                                'name' => 'HARGA POKOK PENJUALAN  MOTOR HONDA GENIO',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000011',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA BEAT',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000012',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA CB VERZA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '51000013',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR HONDA STYLO',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '52',
                        'name' => 'HARGA POKOK PENJUALAN YAMAHA',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '52000001',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA XMAX',
                            ],
                            [
                                'type' => 'account',
                                'code' => '52000002',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA MT25',
                            ],
                            [
                                'type' => 'account',
                                'code' => '52000003',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA MX KING',
                            ],
                            [
                                'type' => 'account',
                                'code' => '52000004',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA AEROX',
                            ],
                            [
                                'type' => 'account',
                                'code' => '52000005',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA MIO CW',
                            ],
                            [
                                'type' => 'account',
                                'code' => '52000006',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA NMAX',
                            ],
                            [
                                'type' => 'account',
                                'code' => '52000007',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA XSR 155',
                            ],
                            [
                                'type' => 'account',
                                'code' => '52000008',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA GEAR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '52000009',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR YAMAHA VIXION',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '53',
                        'name' => 'HARGA POKOK PENJUALAN BARANG DAGANG SUZUKI',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '53000001',
                                'name' => 'HARGA POKOK PENJUALAN MOTOR SUZUKI SATRIA',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '54',
                        'name' => 'HARGA POKOK PENJUALAN SPAREPARTS',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '54000001',
                                'name' => 'HARGA POKOK PENJUALAN SPAREPARTS HONDA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '54000002',
                                'name' => 'HARGA POKOK PENJUALAN SPAREPARTS YAMAHA',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'group',
                'code' => '6',
                'name' => 'BIAYA OPERASIONAL',
                'children' => [
                    [
                        'type' => 'group',
                        'code' => '61',
                        'name' => 'BIAYA ADMINISTRASI & UMUM',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '61000001',
                                'name' => 'BIAYA OPERASIONAL KANTOR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000002',
                                'name' => 'BIAYA ADMINISTRASI KANTOR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000003',
                                'name' => 'BIAYA SEWA KANTOR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000004',
                                'name' => 'BIAYA GAJI PEGAWAI',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000005',
                                'name' => 'BIAYA MARKETING',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000006',
                                'name' => 'BIAYA RUMAH TANGGA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000007',
                                'name' => 'BIAYA KERUSAKAN KENDARAAN',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000008',
                                'name' => 'BIAYA LISTRIK',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000009',
                                'name' => 'BIAYA SANKSI DENDA/BUNGA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000010',
                                'name' => 'BIAYA JASA EKSPEDISI',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000011',
                                'name' => 'BIAYA PERALATAN',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000012',
                                'name' => 'BIAYA SERAGAM PEGAWAI',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000013',
                                'name' => 'BIAYA JASA EKSPEDISI',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000014',
                                'name' => 'BIAYA SERVIS',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000015',
                                'name' => 'BIAYA TRANSPORT DAN BBM',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000016',
                                'name' => 'BIAYA CLAIM SPAREPART',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000017',
                                'name' => 'BIAYA PERJALANAN DINAS DAN AKOMODASI',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000018',
                                'name' => 'BIAYA REPRESENTASI',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000019',
                                'name' => 'BIAYA JASA KONSULTAN',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000020',
                                'name' => 'BIAYA JASA NOTARIS',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000021',
                                'name' => 'BIAYA BBN UNIT',
                            ],
                            [
                                'type' => 'account',
                                'code' => '61000099',
                                'name' => 'BIAYA OPERASIONAL LAIN-LAIN',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '62',
                        'name' => 'BIAYA PENYUSUTAN',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '62000001',
                                'name' => 'BIAYA PENYUSUTAN INVENTARIS KANTOR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '62000002',
                                'name' => 'BIAYA PENYUSUTAN KENDARAAN',
                            ],
                            [
                                'type' => 'account',
                                'code' => '62000003',
                                'name' => 'BIAYA PENYUSUTAN BANGUNAN',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'group',
                'code' => '7',
                'name' => 'PENDAPATAN DAN BEBAN DILUAR USAHA',
                'children' => [
                    [
                        'type' => 'group',
                        'code' => '71',
                        'name' => 'PENDAPATAN DILUAR USAHA',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '71000001',
                                'name' => 'JASA GIRO',
                            ],
                            [
                                'type' => 'account',
                                'code' => '71000002',
                                'name' => 'PENDAPATAN BUNGA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '71000003',
                                'name' => 'SELISIH PPN-NORMA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '71000004',
                                'name' => 'PENGHAPUSAN HUTANG',
                            ],
                            [
                                'type' => 'account',
                                'code' => '71000099',
                                'name' => 'PENDAPATAN LAIN-LAIN',
                            ],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'code' => '72',
                        'name' => 'BIAYA DILUAR USAHA',
                        'children' => [
                            [
                                'type' => 'account',
                                'code' => '72000001',
                                'name' => 'BIAYA ADMINISTRASI BANK IDR',
                            ],
                            [
                                'type' => 'account',
                                'code' => '72000002',
                                'name' => 'BIAYA ADMINISTRASI BANK USD',
                            ],
                            [
                                'type' => 'account',
                                'code' => '72000003',
                                'name' => 'BIAYA TRANSFER ANTAR BANK',
                            ],
                            [
                                'type' => 'account',
                                'code' => '72000004',
                                'name' => 'BIAYA BUNGA',
                            ],
                            [
                                'type' => 'account',
                                'code' => '72000005',
                                'name' => 'BIAYA DENDA PAJAK',
                            ],
                            [
                                'type' => 'account',
                                'code' => '72000006',
                                'name' => 'RUGI SELISIH KURS',
                            ],
                            [
                                'type' => 'account',
                                'code' => '72000099',
                                'name' => 'BIAYA LAIN-LAIN',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->insertAccounts($accounts);
    }

    private function insertAccounts(
        array $items,
        ?string $mainGroup = null
    ): void {

        foreach ($items as $item) {

            // Group utama (1 AKTIVA, 2 HUTANG, dst)
            if (
                $item['type'] === 'group'
                && strlen($item['code']) === 1
            ) {

                $mainGroup = $item['name'];

                AccountGroup::firstOrCreate(
                    [
                        'group_code' => $item['code']
                    ],
                    [
                        'company_id' => 1,
                        'description' => $item['name']
                    ]
                );

            }

            if ($mainGroup) {

                $group = AccountGroup::where(
                    'description',
                    $mainGroup
                )->first();

                Account::updateOrCreate(
                    [
                        'code' => $item['code']
                    ],
                    [
                        'account_group_id' => $group?->id,
                        'name' => $item['name'],
                        'description' => null,
                        'is_lock' => true,
                    ]
                );

            }

            if (!empty($item['children'])) {

                $this->insertAccounts(
                    $item['children'],
                    $mainGroup
                );

            }

        }

    }
}