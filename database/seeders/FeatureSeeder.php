<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [

            // ===============================
            // Master Data Module
            // ===============================
            [
                'name' => 'Master Akun',
                'slug' => 'chart-of-accounts',
                'description' => 'Account master data',
            ],
            [
                'name' => 'Master Supplier',
                'slug' => 'suppliers',
                'description' => 'Supplier master data',
            ],
            [
                'name' => 'Master Customer',
                'slug' => 'customers',
                'description' => 'Customer master data',
            ],
            [
                'name' => 'Master Tipe Unit',
                'slug' => 'unit-types',
                'description' => 'Unit type master data',
            ],
            [
                'name' => 'Master Sparepart',
                'slug' => 'spare-parts',
                'description' => 'Spare part master data',
            ],
            [
                'name' => 'Master Kas',
                'slug' => 'cash-accounts',
                'description' => 'Cash account master data',
            ],
            [
                'name' => 'Master User',
                'slug' => 'users',
                'description' => 'User master data',
            ],

            // === TAMBAHAN MASTER DATA BARU ===
            [
                'name' => 'Master Dealer',
                'slug' => 'dealers',
                'description' => 'Dealer master data',
            ],
            [
                'name' => 'Master Tarif',
                'slug' => 'tariffs',
                'description' => 'Tariff master data',
            ],
            [
                'name' => 'Master Driver',
                'slug' => 'drivers',
                'description' => 'Driver master data',
            ],
            [
                'name' => 'Master Kendaraan',
                'slug' => 'vehicles',
                'description' => 'Vehicle master data',
            ],

            // ===============================
            // Transaction Module
            // ===============================
            [
                'name' => 'Arus Transaksi',
                'slug' => 'transaction-flow',
                'description' => 'Transaction flow records',
            ],
            [
                'name' => 'Jurnal Transaksi',
                'slug' => 'transaction-journal',
                'description' => 'General transaction journal records',
            ],
            [
                'name' => 'Pembelian Unit',
                'slug' => 'unit-purchases',
                'description' => 'Unit purchase transactions',
            ],
            [
                'name' => 'Penjualan Unit',
                'slug' => 'unit-sales',
                'description' => 'Unit sales transactions',
            ],
            [
                'name' => 'Faktur',
                'slug' => 'invoices',
                'description' => 'Invoice transaction records',
            ],
            [
                'name' => 'Surat Jalan Ekspedisi',
                'slug' => 'expedition-delivery-orders',
                'description' => 'Expedition delivery order records',
            ],

            // ===============================
            // Warehouse Module
            // ===============================
            [
                'name' => 'Stok Unit',
                'slug' => 'unit-inventory',
                'description' => 'Unit inventory data',
            ],
            [
                'name' => 'Penerimaan Unit',
                'slug' => 'unit-receipts',
                'description' => 'Unit receiving records',
            ],
            [
                'name' => 'Pengeluaran Unit',
                'slug' => 'unit-dispatches',
                'description' => 'Unit dispatch records',
            ],

            // ===============================
            // Finance Module
            // ===============================
            [
                'name' => 'Transaksi Kas Harian',
                'slug' => 'daily-cash-transactions',
                'description' => 'Daily cash transactions',
            ],
            [
                'name' => 'Data PPN Pembelian',
                'slug' => 'purchase-vat-records',
                'description' => 'Purchase VAT records',
            ],
            [
                'name' => 'Data PPN Penjualan',
                'slug' => 'sales-vat-records',
                'description' => 'Sales VAT records',
            ],
            [
                'name' => 'Data Refund Beli',
                'slug' => 'purchase-refunds',
                'description' => 'Purchase refund records',
            ],
            [
                'name' => 'Data Refund Jual',
                'slug' => 'sales-refunds',
                'description' => 'Sales refund records',
            ],
            [
                'name' => 'Data Hutang',
                'slug' => 'accounts-payable',
                'description' => 'Accounts payable records',
            ],
            [
                'name' => 'Data Pembayaran Hutang',
                'slug' => 'payable-payments',
                'description' => 'Accounts payable payments',
            ],
            [
                'name' => 'Data Piutang',
                'slug' => 'accounts-receivable',
                'description' => 'Accounts receivable records',
            ],
            [
                'name' => 'Data Terima Piutang',
                'slug' => 'receivable-collections',
                'description' => 'Accounts receivable collections',
            ],

            // ===============================
            // Reporting Module
            // ===============================
            [
                'name' => 'Laporan Transaksi Kas',
                'slug' => 'cash-transaction-reports',
                'description' => 'Cash transaction reports',
            ],
            [
                'name' => 'Laporan Akuntansi',
                'slug' => 'accounting-reports',
                'description' => 'Accounting reports',
            ],
            [
                'name' => 'Laporan Pembelian',
                'slug' => 'purchase-reports',
                'description' => 'Purchase reports',
            ],
            [
                'name' => 'Laporan Penjualan',
                'slug' => 'sales-reports',
                'description' => 'Sales reports',
            ],
            [
                'name' => 'Laporan Penerimaan',
                'slug' => 'receipt-reports',
                'description' => 'Receiving reports',
            ],
            [
                'name' => 'Laporan Pengiriman',
                'slug' => 'dispatch-reports',
                'description' => 'Dispatch reports',
            ],
            [
                'name' => 'Laporan Stok',
                'slug' => 'inventory-reports',
                'description' => 'Inventory reports',
            ],
            [
                'name' => 'Laporan Ekspedisi',
                'slug' => 'expedition-reports',
                'description' => 'Expedition activity reports',
            ],
            [
                'name' => 'Laporan Pemakaian Kendaraan',
                'slug' => 'vehicle-usage-reports',
                'description' => 'Vehicle usage reports',
            ],
            [
                'name' => 'Laporan STNK',
                'slug' => 'stnk-reports',
                'description' => 'STNK document reports',
            ],
            [
                'name' => 'Laporan BPKB',
                'slug' => 'bpkb-reports',
                'description' => 'BPKB document reports',
            ],
            [
                'name' => 'Laporan Nomor Polisi',
                'slug' => 'vehicle-number-reports',
                'description' => 'Vehicle registration number reports',
            ],
            [
                'name' => 'Laporan Proses Ditlantas',
                'slug' => 'ditlantas-process-reports',
                'description' => 'Ditlantas processing reports',
            ],
            [
                'name' => 'Laporan Register Samsat',
                'slug' => 'samsat-register-reports',
                'description' => 'Samsat registration reports',
            ],
            [
                'name' => 'Laporan Register BPKB',
                'slug' => 'bpkb-register-reports',
                'description' => 'BPKB registration reports',
            ],
            [
                'name' => 'Laporan BBN',
                'slug' => 'bbn-reports',
                'description' => 'BBN (Vehicle Title Transfer) reports',
            ],

        ];

        Feature::insert($features);
    }
}
