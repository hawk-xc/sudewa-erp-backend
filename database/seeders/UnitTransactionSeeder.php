<?php

namespace Database\Seeders;

use App\Models\Cash;
use App\Models\Company;
use App\Models\Person;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionBilling;
use App\Models\UnitTransactionBillingHistory;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use App\Models\UnitTransactionItemSales;
use App\Models\UnitType;
use App\Models\UnitTypeDetailPpn;
use App\Models\WarehouseActivity;
use App\Models\WarehouseMovement;
use App\Traits\TransactionTrait;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitTransactionSeeder extends Seeder
{
    use TransactionTrait;

    public function run(): void
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

        $suppliers = Person::where('type', 'supplier')->get();
        if ($suppliers->isEmpty()) {
            $this->command->error('No suppliers found.');
            return;
        }

        $customers = Person::where('type', 'customer')->get();
        if ($customers->isEmpty()) {
            $this->command->error('No customers found.');
            return;
        }

        $unitTypes = UnitType::all();
        if ($unitTypes->isEmpty()) {
            $this->command->error('No unit types found.');
            return;
        }

        // 1. Seed Purchase Transactions
        $this->command->info('Seeding Purchase Transactions...');
        $this->seedPurchaseTransactions($company, $warehouse, $suppliers, $unitTypes);

        // 2. Seed Sales Transactions
        $this->command->info('Seeding Sales Transactions...');
        $this->seedSalesTransactions($company, $warehouse, $customers);
    }

    private function seedPurchaseTransactions($company, $warehouse, $suppliers, $unitTypes): void
    {
        // A. Complete Purchase Transactions (10 transactions)
        for ($i = 0; $i < 10; $i++) {
            DB::transaction(function () use ($company, $warehouse, $suppliers, $unitTypes) {
                $detailLists = collect();
                $code = $this->generateCode('purchase');

                $unitTransaction = UnitTransaction::create([
                    'warehouse_id' => $warehouse->id,
                    'person_id' => $suppliers->random()->id,
                    'code' => $code,
                    'type' => 'purchase',
                    'max_capacity' => 50,
                    'stock_state' => 'draft',
                ]);

                // Create items for complete purchase
                $itemLimit = rand(2, 5);
                for ($j = 0; $j < $itemLimit; $j++) {
                    $qty = rand(2, 5);
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
                            'machine_number' => 'ENG'.rand(1000000, 9999999),
                            'chassis_number' => 'CHS'.rand(1000000, 9999999),
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

                $history = UnitTransactionBillingHistory::create([
                    'unit_transaction_billing_id' => $billing->id,
                    'payment_at' => Carbon::now(),
                ]);

                // Link the cash flow
                $cash = Cash::where('company_id', $company->id)->where('code', 'cash_idr')->first();
                if ($cash) {
                    $history->cashes()->attach($cash->id, ['amount' => $grandTotal]);
                    $cash->increment('amount', $grandTotal);
                }

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

                $this->command->info("Created Complete Purchase: {$code}");
            });
        }

        // B. Draft Purchase Transactions (10 transactions)
        for ($i = 0; $i < 10; $i++) {
            DB::transaction(function () use ($warehouse, $suppliers, $unitTypes) {
                $code = $this->generateCode('purchase');

                $unitTransaction = UnitTransaction::create([
                    'warehouse_id' => $warehouse->id,
                    'person_id' => $suppliers->random()->id,
                    'code' => $code,
                    'type' => 'purchase',
                    'max_capacity' => 50,
                    'stock_state' => 'draft',
                ]);

                // Create items for draft purchase
                $itemLimit = rand(2, 5);
                for ($j = 0; $j < $itemLimit; $j++) {
                    $qty = rand(2, 5);
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
                            'machine_number' => 'ENG'.rand(1000000, 9999999),
                            'chassis_number' => 'CHS'.rand(1000000, 9999999),
                            'in_stock' => false,
                            'is_forecast' => false,
                        ]);
                    }
                }

                $this->command->info("Created Draft Purchase: {$code}");
            });
        }
    }

    private function seedSalesTransactions($company, $warehouse, $customers): void
    {
        // Helper to load current available stock grouped by unit_type_id
        $getAvailableDetails = function () {
            return UnitTransactionItemDetail::where('in_stock', true)
                ->whereHas('unitTransactionItem', function ($query) {
                    $query->whereNotNull('unit_type_id');
                })
                ->with('unitTransactionItem')
                ->get()
                ->groupBy(function ($detail) {
                    return $detail->unitTransactionItem->unit_type_id;
                });
        };

        // A. Complete Sales Transactions (5 transactions)
        for ($i = 0; $i < 5; $i++) {
            DB::transaction(function () use ($company, $warehouse, $customers, $getAvailableDetails) {
                $availableDetails = $getAvailableDetails();
                // Filter groups that actually have details
                $availableDetails = $availableDetails->filter(fn ($group) => $group->isNotEmpty());

                if ($availableDetails->isEmpty()) {
                    $this->command->warn('No details available in stock to seed Complete Sales.');
                    return;
                }

                $code = $this->generateCode('sales');
                $unitTransaction = UnitTransaction::create([
                    'warehouse_id' => $warehouse->id,
                    'person_id' => $customers->random()->id,
                    'code' => $code,
                    'type' => 'sales',
                    'max_capacity' => 10,
                    'stock_state' => 'draft',
                ]);

                $itemsCount = rand(1, 3);
                $salesDetails = collect();

                for ($j = 0; $j < $itemsCount; $j++) {
                    if ($availableDetails->isEmpty()) {
                        break;
                    }

                    $unitTypeId = $availableDetails->keys()->random();
                    $detailsForType = $availableDetails->get($unitTypeId);

                    $qty = rand(1, min(3, $detailsForType->count()));
                    if ($qty <= 0) {
                        continue;
                    }

                    // Price configuration
                    $price = rand(15000000, 25000000);
                    $bbn = rand(500000, 1000000);
                    $other = rand(300000, 700000);

                    $additional = $bbn + $other;
                    $hpp = $price - $additional;
                    $dpp = ceil($hpp / 1.11);
                    $ppn = floor($dpp * 0.11);

                    $salesItem = UnitTransactionItem::create([
                        'unit_transaction_id' => $unitTransaction->id,
                        'unit_type_id' => $unitTypeId,
                        'qty_total' => $qty,
                        'price' => $price,
                        'bbn_price' => $bbn,
                        'other_fee' => $other,
                        'hpp_per_unit_price' => $hpp,
                        'dpp_per_unit_price' => $dpp,
                        'ppn_per_unit_price' => $ppn,
                        'hpp_total_price' => $hpp * $qty,
                        'dpp_total_price' => $dpp * $qty,
                        'ppn_total_price' => $ppn * $qty,
                        'ppn_percentage' => 11,
                    ]);

                    // Link details to sales
                    $sliced = $detailsForType->slice(0, $qty);
                    foreach ($sliced as $detail) {
                        UnitTransactionItemSales::create([
                            'unit_transaction_item_id' => $salesItem->id,
                            'unit_transaction_item_detail_id' => $detail->id,
                        ]);
                        $salesDetails->push($detail);
                    }

                    // Update local copy of available details to prevent double selling
                    $availableDetails->put($unitTypeId, $detailsForType->slice($qty));
                }

                if ($salesDetails->isEmpty()) {
                    // Rollback/delete if no items were created
                    $unitTransaction->delete();
                    return;
                }

                $grandTotal = $unitTransaction->getBrutoAmountActual();
                $billing = UnitTransactionBilling::create([
                    'unit_transaction_id' => $unitTransaction->id,
                    'grand_total' => $grandTotal,
                    'is_paid' => false,
                ]);

                $history = UnitTransactionBillingHistory::create([
                    'unit_transaction_billing_id' => $billing->id,
                    'payment_at' => Carbon::now(),
                ]);

                // Link the cash flow
                $cash = Cash::where('company_id', $company->id)->where('code', 'cash_idr')->first();
                if ($cash) {
                    $history->cashes()->attach($cash->id, ['amount' => $grandTotal]);
                    $cash->increment('amount', $grandTotal);
                }

                $billing->update([
                    'is_paid' => true,
                    'last_payment_at' => now(),
                ]);

                foreach ($salesDetails as $detail) {
                    UnitTypeDetailPpn::create([
                        'unit_transaction_item_detail_id' => $detail->id,
                        'unit_transaction_id' => $unitTransaction->id,
                        'type' => 'ppn_sales',
                    ]);
                }

                // Create dispatch warehouse activity
                WarehouseActivity::create([
                    'person_id' => $unitTransaction->person_id,
                    'warehouse_id' => $warehouse->id,
                    'activity_type' => 'issue',
                    'activity_date' => now(),
                ]);

                // Dispatch stock
                foreach ($salesDetails as $detail) {
                    $detail->dispatchStock();
                }

                $unitTransaction->update([
                    'stock_state' => 'outbound_delivered',
                ]);

                $this->command->info("Created Complete Sales: {$code}");
            });
        }

        // B. Draft Sales Transactions (5 transactions)
        for ($i = 0; $i < 5; $i++) {
            DB::transaction(function () use ($company, $warehouse, $customers, $getAvailableDetails) {
                $availableDetails = $getAvailableDetails();
                $availableDetails = $availableDetails->filter(fn ($group) => $group->isNotEmpty());

                if ($availableDetails->isEmpty()) {
                    $this->command->warn('No details available in stock to seed Draft Sales.');
                    return;
                }

                $code = $this->generateCode('sales');
                $unitTransaction = UnitTransaction::create([
                    'warehouse_id' => $warehouse->id,
                    'person_id' => $customers->random()->id,
                    'code' => $code,
                    'type' => 'sales',
                    'max_capacity' => 10,
                    'stock_state' => 'draft',
                ]);

                $itemsCount = rand(1, 3);
                $salesDetails = collect();

                for ($j = 0; $j < $itemsCount; $j++) {
                    if ($availableDetails->isEmpty()) {
                        break;
                    }

                    $unitTypeId = $availableDetails->keys()->random();
                    $detailsForType = $availableDetails->get($unitTypeId);

                    $qty = rand(1, min(3, $detailsForType->count()));
                    if ($qty <= 0) {
                        continue;
                    }

                    $price = rand(15000000, 25000000);
                    $bbn = rand(500000, 1000000);
                    $other = rand(300000, 700000);

                    $additional = $bbn + $other;
                    $hpp = $price - $additional;
                    $dpp = ceil($hpp / 1.11);
                    $ppn = floor($dpp * 0.11);

                    $salesItem = UnitTransactionItem::create([
                        'unit_transaction_id' => $unitTransaction->id,
                        'unit_type_id' => $unitTypeId,
                        'qty_total' => $qty,
                        'price' => $price,
                        'bbn_price' => $bbn,
                        'other_fee' => $other,
                        'hpp_per_unit_price' => $hpp,
                        'dpp_per_unit_price' => $dpp,
                        'ppn_per_unit_price' => $ppn,
                        'hpp_total_price' => $hpp * $qty,
                        'dpp_total_price' => $dpp * $qty,
                        'ppn_total_price' => $ppn * $qty,
                        'ppn_percentage' => 11,
                    ]);

                    $sliced = $detailsForType->slice(0, $qty);
                    foreach ($sliced as $detail) {
                        UnitTransactionItemSales::create([
                            'unit_transaction_item_id' => $salesItem->id,
                            'unit_transaction_item_detail_id' => $detail->id,
                        ]);
                        $salesDetails->push($detail);
                    }

                    $availableDetails->put($unitTypeId, $detailsForType->slice($qty));
                }

                if ($salesDetails->isEmpty()) {
                    $unitTransaction->delete();
                    return;
                }

                $this->command->info("Created Draft Sales: {$code}");
            });
        }
    }
}
