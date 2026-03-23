<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\TransactionFlow;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionFlowSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::pluck('id')->toArray();
        // $unitTransactions = UnitTransaction::pluck('id')->toArray();

        // if (empty($companies) || empty($unitTransactions)) {
        //     $this->command->warn('Company / UnitTransaction kosong!');

        //     return;
        // }

        for ($i = 1; $i <= 50; $i++) {

            $type = rand(1, 3);

            $bankUsdDebit = 0;
            $bankUsdCredit = 0;
            $bankIdrDebit = 0;
            $bankIdrCredit = 0;
            $cashIdrDebit = 0;
            $cashIdrCredit = 0;

            $amount = rand(100000, 5000000);

            switch ($type) {
                case 1: // INCOME
                    $bankIdrDebit = $amount;
                    $name = 'Pemasukan dari penjualan';
                    $description = 'Transaksi pemasukan dari customer';
                    break;

                case 2: // EXPENSE
                    $cashIdrCredit = $amount;
                    $name = 'Pengeluaran operasional';
                    $description = 'Biaya operasional harian';
                    break;

                case 3: // TRANSFER
                    $bankIdrCredit = $amount;
                    $cashIdrDebit = $amount;
                    $name = 'Transfer bank ke kas';
                    $description = 'Pemindahan dana dari bank ke kas';
                    break;
            }

            TransactionFlow::create([
                'company_id' => $companies[array_rand($companies)],
                // 'unit_transaction_id' => $unitTransactions[array_rand($unitTransactions)],
                'transaction_date' => Carbon::now()->subDays(rand(0, 30)),
                'name' => $name,
                'description' => $description,
                'bank_usd_debit' => $bankUsdDebit,
                'bank_usd_credit' => $bankUsdCredit,
                'bank_idr_debit' => $bankIdrDebit,
                'bank_idr_credit' => $bankIdrCredit,
                'cash_idr_debit' => $cashIdrDebit,
                'cash_idr_credit' => $cashIdrCredit,
                'transaction_proof' => null,
            ]);
        }
    }
}
