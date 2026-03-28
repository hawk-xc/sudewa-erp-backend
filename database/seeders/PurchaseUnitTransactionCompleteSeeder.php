<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Person;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionBilling;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use App\Models\UnitType;
use App\Models\WarehouseActivity;
use App\Models\WarehouseMovement;
use App\Traits\TransactionTrait;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

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
        
        if (!$company) {
            $this->command->error("Company not found. Please create a company first.");
            return;
        }
        
        $warehouse = $company->warehouse;
        if (!$warehouse) {
            $this->command->error("Warehouse not found for company. Please create a warehouse first.");
            return;
        }
        
        $persons = Person::where('type', 'supplier')->limit(10)->get();
        if ($persons->isEmpty()) {
            $this->command->error("No suppliers found. Please create suppliers first.");
            return;
        }
        
        $unitTypes = UnitType::limit(50)->get();
        if ($unitTypes->isEmpty()) {
            $this->command->error("No unit types found. Please create unit types first.");
            return;
        }

        $unitTransactionLimit = 10;
        $unitTransactionItemLimit = 10;
        
        $createdTransactions = [];

        for ($i = 0; $i < $unitTransactionLimit; $i++) {
            $unitTransactionItemDetailLists = collect();
            
            // Generate transaction code
            $code = $this->generateCode('purchase');
            
            $unitTransaction = UnitTransaction::create([
                'warehouse_id' => $warehouse->id,
                'person_id' => $persons->random()->id,
                'code' => $code,
                'type' => 'purchase',
                'max_capacity' => 100,
                'stock_state' => 'draft',
            ]);
            
            $totalAmount = 0;
            
            for ($j = 0; $j < $unitTransactionItemLimit; $j++) {
                $qty = rand(1, 10);
                $price = rand(100000000, 200000000); // This is the total price including tax and fees
                $bbnPrice = rand(1000000, 5000000);
                $expeditionFee = rand(500000, 2000000);
                $otherFee = rand(500000, 2000000);
                $ppnPercentage = 11;
                
                // Calculate additional fees (total fees)
                $additionalFee = $bbnPrice + $expeditionFee + $otherFee;
                
                // Calculate HPP (Harga Pokok Penjualan)
                // HPP = Price - Additional Fees
                $hpp = $price - $additionalFee;
                
                // Calculate DPP (Dasar Pengenaan Pajak)
                // DPP = ceil(HPP / 1.11)
                $dpp = ceil($hpp / 1.11);
                
                // Calculate PPN (Pajak Pertambahan Nilai)
                // PPN = floor(DPP * 0.11)
                $ppn = floor($dpp * 0.11);
                
                // Calculate total prices
                $hppTotalPrice = $hpp * $qty;
                $dppTotalPrice = $dpp * $qty;
                $ppnTotalPrice = $ppn * $qty;
                
                $unitTransactionItem = UnitTransactionItem::create([
                    'unit_transaction_id' => $unitTransaction->id,
                    'unit_type_id' => $unitTypes->random()->id,
                    'sparepart_id' => null,
                    'qty_total' => $qty,
                    'price' => $price,
                    'bbn_price' => $bbnPrice,
                    'expedition_fee' => $expeditionFee,
                    'other_fee' => $otherFee,
                    'hpp_per_unit_price' => $hpp,
                    'dpp_per_unit_price' => $dpp,
                    'ppn_per_unit_price' => $ppn,
                    'hpp_total_price' => $hppTotalPrice,
                    'dpp_total_price' => $dppTotalPrice,
                    'ppn_total_price' => $ppnTotalPrice,
                    'ppn_percentage' => $ppnPercentage,
                ]);
                
                // Calculate total amount for billing
                // Total amount = Price * Qty
                $itemTotalAmount = $price * $qty;
                $totalAmount += $itemTotalAmount;
                
                for ($k = 0; $k < $qty; $k++) {
                    $colors = ['Hitam', 'Putih', 'Silver', 'Merah', 'Biru'];
                    $machineNumber = 'ENG' . date('Y') . str_pad($unitTransactionItem->id, 3, '0', STR_PAD_LEFT) . str_pad($k + 1, 4, '0', STR_PAD_LEFT);
                    $chassisNumber = 'CHS' . date('Y') . str_pad($unitTransactionItem->id, 3, '0', STR_PAD_LEFT) . str_pad($k + 1, 4, '0', STR_PAD_LEFT);
                    
                    $unitTransactionItemDetail = UnitTransactionItemDetail::create([
                        'unit_transaction_item_id' => $unitTransactionItem->id,
                        'color' => $colors[array_rand($colors)],
                        'machine_number' => $machineNumber,
                        'chassis_number' => $chassisNumber,
                        'in_stock' => false,
                        'is_forecast' => false,
                    ]);
                    
                    $unitTransactionItemDetailLists->push($unitTransactionItemDetail);
                }
            }
            
            // Add unit transaction billing
            UnitTransactionBilling::create([
                'unit_transaction_id' => $unitTransaction->id,
                'bca_payment_amount' => $totalAmount,
                'bca_payment_usd_amount' => 0,
                'cash_payment_amount' => 0,
                'bca_payment_liability' => $totalAmount,
                'bca_payment_usd_liability' => 0,
                'cash_payment_liability' => 0,
                'payment_at' => Carbon::now()->subDays(rand(1, 30)),
                'is_paid' => true,
            ]);
            
            // Create warehouse activity for receipt
            $warehouseActivity = WarehouseActivity::create([
                'person_id' => $unitTransaction->person_id,
                'warehouse_id' => $warehouse->id,
                'activity_type' => 'receipt',
                'activity_date' => Carbon::now(),
            ]);
            
            // Process warehouse movements
            foreach ($unitTransactionItemDetailLists as $unitTransactionItemDetail) {
                // Create warehouse movement
                $warehouseMovement = WarehouseMovement::firstOrCreate(
                    [
                        'unit_transaction_item_detail_id' => $unitTransactionItemDetail->id,
                        'status' => 'in',
                    ],
                    [
                        'warehouse_activity_id' => $warehouseActivity->id,
                        'unit_transaction_id' => $unitTransaction->id,
                        'status' => 'in',
                    ]
                );
                
                // Update unit transaction item detail to in stock
                $unitTransactionItemDetail->update([
                    'in_stock' => true,
                    'is_forecast' => false
                ]);
                
                // Call receiptStock method if exists
                if (method_exists($unitTransactionItemDetail, 'receiptStock')) {
                    $unitTransactionItemDetail->receiptStock($warehouseActivity->id);
                }
            }
            
            // Update stock state after all movements are created
            $unitTransaction->update(['stock_state' => 'inbound_incoming_goods']);
            
            $createdTransactions[] = [
                'code' => $code,
                'total_amount' => $totalAmount,
                'items_count' => $unitTransactionItemLimit
            ];
            
            $this->command->info("Created purchase transaction: {$code} with total: " . number_format($totalAmount, 2));
        }
        
        $this->command->info("Total transactions created: " . count($createdTransactions));
    }
}