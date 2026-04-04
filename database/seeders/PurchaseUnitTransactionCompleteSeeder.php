<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Person;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionBilling;
use App\Models\UnitTransactionBillingHistory;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use App\Models\UnitType;
use App\Models\UnitTypeDetailPpn;
use App\Models\WarehouseActivity;
use App\Models\WarehouseMovement;
use App\Traits\TransactionTrait;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseUnitTransactionCompleteSeeder extends Seeder
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
            $this->command->error('Company not found. Please create a company first.');

            return;
        }

        $warehouse = $company->warehouse;
        if (! $warehouse) {
            $this->command->error('Warehouse not found for company.');

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

        $unitTransactionLimit = 10;
        $unitTransactionItemLimit = 50;

        for ($i = 0; $i < $unitTransactionLimit; $i++) {

            DB::transaction(function () use (
                $warehouse,
                $persons,
                $unitTypes,
                $unitTransactionItemLimit
            ) {

                $detailLists = collect();

                $code = $this->generateCode('purchase');

                $unitTransaction = UnitTransaction::create([
                    'warehouse_id' => $warehouse->id,
                    'person_id' => $persons->random()->id,
                    'code' => $code,
                    'type' => 'purchase',
                    'max_capacity' => 10,
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
                        $detail = UnitTransactionItemDetail::create([
                            'unit_transaction_item_id' => $item->id,
                            'color' => ['Hitam', 'Putih', 'Merah'][array_rand([0, 1, 2])],
                            'machine_number' => 'ENG'.rand(10000, 99999),
                            'chassis_number' => 'CHS'.rand(10000, 99999),
                            'in_stock' => false,
                            'is_forecast' => false,
                        ]);

                        $detailLists->push($detail);
                    }
                }

                $grandTotal = $unitTransaction->getBrutoAmountActual();

                $billing = UnitTransactionBilling::create([
                    'unit_transaction_id' => $unitTransaction->id,
                    'grand_total' => $grandTotal,
                    'is_paid' => false,
                ]);

                UnitTransactionBillingHistory::create([
                    'unit_transaction_billing_id' => $billing->id,
                    'cash_payment_amount' => $grandTotal,
                    'bca_payment_amount' => 0,
                    'bca_payment_usd_amount' => 0,
                    'payment_at' => Carbon::now(),
                ]);

                $billing->update([
                    'is_paid' => true,
                    'last_payment_at' => now(),
                ]);

                foreach ($unitTransaction->unitTransactionItems as $item) {
                    foreach ($item->unitTransactionItemDetails as $detail) {

                        UnitTypeDetailPpn::create([
                            'unit_transaction_item_detail_id' => $detail->id,
                            'unit_transaction_id' => $unitTransaction->id,
                            'type' => 'ppn_purchase',
                        ]);
                    }
                }

                $activity = WarehouseActivity::create([
                    'person_id' => $unitTransaction->person_id,
                    'warehouse_id' => $warehouse->id,
                    'activity_type' => 'receipt',
                    'activity_date' => now(),
                ]);

                foreach ($detailLists as $detail) {

                    WarehouseMovement::create([
                        'unit_transaction_item_detail_id' => $detail->id,
                        'warehouse_activity_id' => $activity->id,
                        'unit_transaction_id' => $unitTransaction->id,
                        'status' => 'in',
                    ]);

                    $detail->update(['in_stock' => true]);
                }

                $unitTransaction->update([
                    'stock_state' => 'inbound_receipt',
                ]);

                $this->command->info("Created: {$code}");
            });
        }
    }
}
