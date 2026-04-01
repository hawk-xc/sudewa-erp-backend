<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Person;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use App\Models\UnitType;
use App\Traits\TransactionTrait;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseUnitTransactionDraftSeeder extends Seeder
{
    use TransactionTrait;

    public function run(): void
    {
        $this->createPurchaseTransaction();
    }

    private function createPurchaseTransaction(): void
    {
        $company = Company::first();

        if (! $company) {
            $this->command->error('Company not found.');

            return;
        }

        $warehouse = $company->warehouse;
        if (! $warehouse) {
            $this->command->error('Warehouse not found.');

            return;
        }

        $persons = Person::where('type', 'supplier')->limit(10)->get();
        if ($persons->isEmpty()) {
            $this->command->error('No suppliers found.');

            return;
        }

        $unitTypes = UnitType::limit(50)->get();
        if ($unitTypes->isEmpty()) {
            $this->command->error('No unit types found.');

            return;
        }

        $unitTransactionLimit = 20;
        $unitTransactionItemLimit = 35;

        for ($i = 0; $i < $unitTransactionLimit; $i++) {

            DB::transaction(function () use (
                $warehouse,
                $persons,
                $unitTypes,
                $unitTransactionItemLimit
            ) {

                $code = $this->generateCode('purchase');

                $unitTransaction = UnitTransaction::create([
                    'warehouse_id' => $warehouse->id,
                    'person_id' => $persons->random()->id,
                    'code' => $code,
                    'type' => 'purchase',
                    'max_capacity' => 100,
                    'stock_state' => 'draft',
                ]);

                for ($j = 0; $j < $unitTransactionItemLimit; $j++) {

                    $qty = rand(1, 5);
                    $price = rand(10000000, 20000000);
                    $bbn = rand(500000, 1000000);
                    $expedition = rand(300000, 700000);
                    $other = rand(300000, 700000);

                    $additional = $bbn + $expedition + $other;
                    $hpp = $price - $additional;
                    $dpp = ceil($hpp / 1.11);
                    $ppn = floor($dpp * 0.11);

                    $item = UnitTransactionItem::create([
                        'unit_transaction_id' => $unitTransaction->id,
                        'unit_type_id' => $unitTypes->random()->id,
                        'qty_total' => $qty,
                        'price' => $price,
                        'bbn_price' => $bbn,
                        'expedition_fee' => $expedition,
                        'other_fee' => $other,
                        'hpp_per_unit_price' => $hpp,
                        'dpp_per_unit_price' => $dpp,
                        'ppn_per_unit_price' => $ppn,
                        'hpp_total_price' => $hpp * $qty,
                        'dpp_total_price' => $dpp * $qty,
                        'ppn_total_price' => $ppn * $qty,
                        'ppn_percentage' => 11,
                    ]);

                    for ($k = 0; $k < $qty; $k++) {
                        UnitTransactionItemDetail::create([
                            'unit_transaction_item_id' => $item->id,
                            'color' => ['Hitam', 'Putih', 'Merah'][array_rand([0, 1, 2])],
                            'machine_number' => 'ENG'.rand(10000, 99999),
                            'chassis_number' => 'CHS'.rand(10000, 99999),
                            'in_stock' => false,
                            'is_forecast' => false,
                        ]);
                    }
                }

                $this->command->info("Created draft: {$code}");
            });
        }

        $this->command->info("Draft transactions created: {$unitTransactionLimit}");
    }
}
