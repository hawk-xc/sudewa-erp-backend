<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Person;
use App\Models\User;
use App\Models\UnitType;
use App\Models\Warehouse;
use App\Models\Cash;
use App\Models\UnitTransactionItemDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionWarehouseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $warehouse;
    protected $supplier;
    protected $customer;
    protected $brand;
    protected $unitType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->user = User::create([
            'name' => 'Admin Test',
            'username' => 'admintest',
            'firstname' => 'Admin',
            'lastname' => 'Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'secure_password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->company = Company::create(['name' => 'Wajira Corp', 'slug' => 'wajira-corp']);
        $this->warehouse = Warehouse::create([
            'company_id' => $this->company->id, 
            'name' => 'Main Warehouse', 
            'code' => 'WH01',
            'capacity' => 1000
        ]);
        
        // Create Cash records to avoid errors during billing history creation
        Cash::create(['company_id' => $this->company->id, 'code' => 'cash_idr', 'cash_name' =>  'CASH IDR', 'type' => 'cash', 'amount' => 0]);
        Cash::create(['company_id' => $this->company->id, 'code' => 'bca_idr', 'cash_name' =>  'BCA IDR', 'type' => 'bank', 'amount' => 0]);
        Cash::create(['company_id' => $this->company->id, 'code' => 'bca_usd', 'cash_name' =>  'BCA USD', 'type' => 'bank', 'amount' => 0]);

        $this->supplier = Person::create([
            'company_id' => $this->company->id,
            'type' => 'supplier',
            'name' => 'Supplier A',
            'code' => 'SUP01'
        ]);

        $this->customer = Person::create([
            'company_id' => $this->company->id,
            'type' => 'customer',
            'name' => 'Customer B',
            'code' => 'CUS01'
        ]);

        $this->brand = Brand::create(['name' => 'Honda']);
        $this->unitType = UnitType::create([
            'brand_id' => $this->brand->id,
            'code' => 'NCX150',
            'name' => 'Vario 150',
            'buy_price' => 20000000,
            'sell_price' => 22000000,
            'netto_weight' => 110,
            'bruto_weight' => 113
        ]);
    }

    public function test_purchase_and_warehouse_entry_flow()
    {
        // 1. Tambah unit transaksi baru (Purchase)
        $response = $this->actingAs($this->user)->postJson('/wapi/transaction/unit-transaction/unit-transaction', [
            'company_id' => $this->company->id,
            'person_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase',
            'transaction_date' => now()->toDateString(),
            'description' => 'Test Purchase',
            'stock_state' => 'inbound_receipt',
        ]);
        $response->assertStatus(201);
        $transactionId = $response->json('data.id');

        // 2. Tambah unit transaksi item baru
        $response = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-item', [
            'unit_transaction_id' => $transactionId,
            'unit_type_id' => $this->unitType->id,
            'qty_total' => 1,
            'price' => 20000000,
        ]);
        $response->assertStatus(201);
        $itemId = $response->json('data.id');

        // 3. Unit transaksi item detail baru (Machine/Chassis Number)
        $response = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-item-detail', [
            'unit_transaction_item_id' => $itemId,
            'machine_number' => 'MACH001',
            'chassis_number' => 'CHAS001',
            'color' => 'Red',
            'production_year' => 2024,
        ]);
        $response->assertStatus(201);
        $detailId = $response->json('data.id');

        // 4. Unit transaksi billing
        $response = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-billing', [
            'unit_transaction_id' => $transactionId,
            'company_id' => $this->company->id,
        ]);
        $response->assertStatus(201);
        $billingId = $response->json('data.id');
        $grandTotal = $response->json('data.grand_total');

        // 5. Pay the billing
        $response = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-billing-history', [
            'unit_transaction_billing_id' => $billingId,
            'cash_payment_amount' => $grandTotal,
            'payment_at' => now()->toDateString(),
        ]);
        $response->assertStatus(201);

        // 6. Warehouse Activity - Receipt
        $response = $this->postJson('/wapi/warehouse/warehouse-activity', [
            'person_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'activity_type' => 'receipt',
            'activity_date' => now()->toDateString(),
        ]);
        $response->assertStatus(201);
        $activityId = $response->json('data.id');

        // 7. Receipt Stock
        $response = $this->putJson("/wapi/warehouse/warehouse-activity/{$activityId}/receipt-stock", [
            'unit_transaction_details' => [$detailId]
        ]);
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('unit_transaction_item_details', [
            'id' => $detailId,
            'in_stock' => true
        ]);
    }

    public function test_sales_and_warehouse_exit_flow()
    {
        // --- PURCHASE SETUP TO GET STOCK ---
        $purchaseResponse = $this->actingAs($this->user)->postJson('/wapi/transaction/unit-transaction/unit-transaction', [
            'company_id' => $this->company->id,
            'person_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase',
            'stock_state' => 'inbound_receipt',
            'transaction_date' => now()->toDateString(),
        ]);
        $transactionIdIn = $purchaseResponse->json('data.id');
        
        $itemResponse = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-item', [
            'unit_transaction_id' => $transactionIdIn,
            'unit_type_id' => $this->unitType->id,
            'qty_total' => 1,
            'price' => 20000000,
        ]);
        $itemIdIn = $itemResponse->json('data.id');
        
        $detailResponse = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-item-detail', [
            'unit_transaction_item_id' => $itemIdIn,
            'machine_number' => 'MACH-SALE',
            'chassis_number' => 'CHAS-SALE',
            'color' => 'Blue',
            'production_year' => 2024,
        ]);
        $detailId = $detailResponse->json('data.id');

        $billingResponse = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-billing', [
            'unit_transaction_id' => $transactionIdIn,
            'company_id' => $this->company->id,
        ]);
        $billingIdIn = $billingResponse->json('data.id');
        $grandTotalIn = $billingResponse->json('data.grand_total');

        $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-billing-history', [
            'unit_transaction_billing_id' => $billingIdIn,
            'cash_payment_amount' => $grandTotalIn,
        ]);

        $receiptActivity = $this->postJson('/wapi/warehouse/warehouse-activity', [
            'person_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'activity_type' => 'receipt',
            'activity_date' => now()->toDateString(),
        ]);
        $activityIdIn = $receiptActivity->json('data.id');
        $this->putJson("/wapi/warehouse/warehouse-activity/{$activityIdIn}/receipt-stock", [
            'unit_transaction_details' => [$detailId]
        ]);

        // --- SALES FLOW ---
        // 1. Tambah unit transaksi baru (Sales)
        $response = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction', [
            'company_id' => $this->company->id,
            'person_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'sales',
            'stock_state' => 'outbound_reserved',
            'transaction_date' => now()->toDateString(),
        ]);
        $response->assertStatus(201);
        $salesId = $response->json('data.id');

        // 2. Tambah unit transaksi item baru
        $response = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-item', [
            'unit_transaction_id' => $salesId,
            'unit_type_id' => $this->unitType->id,
            'qty_total' => 1,
            'price' => 22000000,
        ]);
        $response->assertStatus(201);
        $itemIdOut = $response->json('data.id');

        // 3. unit transaksi item sales baru (Memilih unit dari stok)
        $response = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-item-sales', [
            'unit_transaction_item_id' => $itemIdOut,
            'unit_transaction_details' => [$detailId],
        ]);
        $response->assertStatus(201);

        // 4. unit transaksi billing
        $response = $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-billing', [
            'unit_transaction_id' => $salesId,
            'company_id' => $this->company->id,
        ]);
        $response->assertStatus(201);
        $billingIdOut = $response->json('data.id');
        $grandTotalOut = $response->json('data.grand_total');

        // 5. Pay the billing
        $this->postJson('/wapi/transaction/unit-transaction/unit-transaction-billing-history', [
            'unit_transaction_billing_id' => $billingIdOut,
            'cash_payment_amount' => $grandTotalOut,
        ])->assertStatus(201);

        // 6. Warehouse Activity - Issue
        $response = $this->postJson('/wapi/warehouse/warehouse-activity', [
            'person_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'activity_type' => 'issue',
            'activity_date' => now()->toDateString(),
        ]);
        $response->assertStatus(201);
        $activityIdOut = $response->json('data.id');

        // 7. Dispatch Stock
        $response = $this->putJson("/wapi/warehouse/warehouse-activity/{$activityIdOut}/dispatch-stock", [
            'unit_transaction_details' => [$detailId]
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('unit_transaction_item_details', [
            'id' => $detailId,
            'in_stock' => false
        ]);
    }
}
