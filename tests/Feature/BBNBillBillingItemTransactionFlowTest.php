<?php

namespace Tests\Feature;

use App\Models\BBNBill;
use App\Models\BBNBillBilling;
use App\Models\BBNBillBillingItem;
use App\Models\Cash;
use App\Models\Company;
use App\Models\DitlantasProcess;
use App\Models\Person;
use App\Models\User;
use App\Models\TransactionFlow;
use App\Models\Finance\FinanceBBNBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BBNBillBillingItemTransactionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $cash;
    protected $vendor;
    protected $ditlantasProcess;
    protected $bbnBill;
    protected $bbnBillBilling;

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

        // Create the company with ID 4 since TransactionFlow is constrained to company 4 as requested.
        $this->company = Company::create([
            'id' => 4,
            'name' => 'Wajira Corp 4',
            'slug' => 'wajira-corp-4'
        ]);

        // Create Cash record for payment
        $this->cash = Cash::create([
            'company_id' => 4,
            'code' => 'bca_idr',
            'type' => 'bank',
            'amount' => 10000000
        ]);

        // Create Vendor / Person
        $this->vendor = Person::create([
            'company_id' => 4,
            'type' => 'vendor',
            'name' => 'Ditlantas Vendor Name',
            'code' => 'VND01'
        ]);

        // Create Ditlantas Process
        $this->ditlantasProcess = DitlantasProcess::create([
            'vendor_id' => $this->vendor->id,
            'process_date' => now()->toDateString(),
            'note' => 'Test Process Note'
        ]);

        // Create BBN Bill
        $this->bbnBill = BBNBill::create([
            'code' => 'BBN-TEST-0001',
            'ditlantas_process_id' => $this->ditlantasProcess->id,
            'bill_date' => now()->toDateString(),
        ]);

        // Create BBN Bill Billing
        $this->bbnBillBilling = BBNBillBilling::create([
            'bbn_bill_id' => $this->bbnBill->id,
            'total_payment' => 5000000,
        ]);
    }

    public function test_transaction_flow_and_finance_bbn_billing_created_on_bbn_bill_billing_item_settlement_bank()
    {
        // 1. Post a billing item payment that does NOT settle the billing (amount < total_payment)
        $response = $this->actingAs($this->user)->postJson('/wapi/transaction/bbn-bill-billing-item', [
            'bbn_bill_billing_id' => $this->bbnBillBilling->id,
            'paid_date' => now()->toDateString(),
            'cash_id' => $this->cash->id,
            'amount' => 2000000,
        ]);

        $response->assertStatus(201);
        
        // Assert no TransactionFlow created yet
        $this->assertDatabaseMissing('transaction_flows', [
            'company_id' => 4,
            'name' => 'Ditlantas Vendor Name',
        ]);

        // Assert FinanceBBNBilling is created for the first payment
        $this->assertDatabaseHas('finance_bbn_billings', [
            'bbn_bill_id' => $this->bbnBill->id,
            'cash_id' => $this->cash->id,
            'amount' => 2000000,
        ]);

        // 2. Post a billing item payment that fully settles the billing (remaining 3000000)
        $response = $this->postJson('/wapi/transaction/bbn-bill-billing-item', [
            'bbn_bill_billing_id' => $this->bbnBillBilling->id,
            'paid_date' => now()->toDateString(),
            'cash_id' => $this->cash->id,
            'amount' => 3000000,
        ]);

        $response->assertStatus(201);

        // Assert FinanceBBNBilling is created for the second payment
        $this->assertDatabaseHas('finance_bbn_billings', [
            'bbn_bill_id' => $this->bbnBill->id,
            'cash_id' => $this->cash->id,
            'amount' => 3000000,
        ]);

        // Assert TransactionFlow is created because billing is now fully paid (remaining_payment = 0)
        $this->assertDatabaseHas('transaction_flows', [
            'company_id' => 4,
            'name' => 'Ditlantas Vendor Name',
            'description' => 'Pelunasan BBN Bill: BBN-TEST-0001',
            'bank_idr_credit' => 5000000, // billing total_payment
            'cash_idr_credit' => 0,
        ]);
    }

    public function test_transaction_flow_and_finance_bbn_billing_created_on_bbn_bill_billing_item_settlement_cash()
    {
        // Update cash to cash type
        $cashCash = Cash::create([
            'company_id' => 4,
            'code' => 'cash_idr',
            'type' => 'cash',
            'amount' => 10000000
        ]);

        // Post a billing item payment that fully settles the billing immediately
        $response = $this->actingAs($this->user)->postJson('/wapi/transaction/bbn-bill-billing-item', [
            'bbn_bill_billing_id' => $this->bbnBillBilling->id,
            'paid_date' => now()->toDateString(),
            'cash_id' => $cashCash->id,
            'amount' => 5000000,
        ]);

        $response->assertStatus(201);

        // Assert FinanceBBNBilling is created
        $this->assertDatabaseHas('finance_bbn_billings', [
            'bbn_bill_id' => $this->bbnBill->id,
            'cash_id' => $cashCash->id,
            'amount' => 5000000,
        ]);

        // Assert TransactionFlow is created because billing is now fully paid
        $this->assertDatabaseHas('transaction_flows', [
            'company_id' => 4,
            'name' => 'Ditlantas Vendor Name',
            'description' => 'Pelunasan BBN Bill: BBN-TEST-0001',
            'bank_idr_credit' => 0,
            'cash_idr_credit' => 5000000, // billing total_payment
        ]);
    }

    public function test_finance_bbn_billing_endpoints()
    {
        // Create a record
        $financeBBNBilling = FinanceBBNBilling::create([
            'bbn_bill_id' => $this->bbnBill->id,
            'cash_id' => $this->cash->id,
            'amount' => 1000000,
        ]);

        // Test GET index
        $response = $this->actingAs($this->user)->getJson('/wapi/finance/finance-bbn-billing');
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'amount' => 1000000
        ]);

        // Test GET show
        $response = $this->getJson("/wapi/finance/finance-bbn-billing/{$financeBBNBilling->id}");
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'amount' => 1000000
        ]);

        // Test PUT update
        $response = $this->putJson("/wapi/finance/finance-bbn-billing/{$financeBBNBilling->id}", [
            'amount' => 1500000,
        ]);
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'amount' => 1500000
        ]);

        $this->assertDatabaseHas('finance_bbn_billings', [
            'id' => $financeBBNBilling->id,
            'amount' => 1500000,
        ]);
    }
}
