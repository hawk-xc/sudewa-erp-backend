<?php

namespace Tests\Feature;

use App\Models\BBNBill;
use App\Models\BBNBillBilling;
use App\Models\BBNBillBillingItem;
use App\Models\Cash;
use App\Models\Company;
use App\Models\DitlantasProcess;
use App\Models\FinanceBBNBilling;
use App\Models\Person;
use App\Models\TransactionFlow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BBNBillBillingItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $cash;
    protected $bankIdr;
    protected $bankUsd;
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

        $this->company = new Company();
        $this->company->id = 3;
        $this->company->name = 'Wajira Corp';
        $this->company->slug = 'wajira-corp';
        $this->company->save();

        $this->cash = Cash::create([
            'company_id' => $this->company->id,
            'code' => 'cash_idr',
            'type' => 'cash',
            'amount' => 100000000
        ]);

        $this->bankIdr = Cash::create([
            'company_id' => $this->company->id,
            'code' => 'bca_idr',
            'type' => 'bank',
            'amount' => 100000000
        ]);

        $this->bankUsd = Cash::create([
            'company_id' => $this->company->id,
            'code' => 'bca_usd',
            'type' => 'bank',
            'amount' => 100000
        ]);

        $this->vendor = Person::create([
            'company_id' => $this->company->id,
            'type' => 'vendor',
            'name' => 'Ditlantas Vendor',
            'code' => 'VEN01'
        ]);

        $this->ditlantasProcess = DitlantasProcess::create([
            'code' => 'DIT-0001',
            'vendor_id' => $this->vendor->id,
            'process_date' => now(),
        ]);

        $this->bbnBill = BBNBill::create([
            'code' => 'BBN-0001',
            'ditlantas_process_id' => $this->ditlantasProcess->id,
            'bill_date' => now(),
        ]);

        $this->bbnBillBilling = BBNBillBilling::create([
            'bbn_bill_id' => $this->bbnBill->id,
            'total_payment' => 10000000, // 10 Million
        ]);
    }

    public function test_partial_payment_fails_validation_and_creates_no_records()
    {
        $response = $this->actingAs($this->user)->postJson('/wapi/transaction/bbn-bill-billing-item', [
            'bbn_bill_billing_id' => $this->bbnBillBilling->id,
            'paid_date' => now()->toDateString(),
            'cash_id' => $this->cash->id,
            'amount' => 4000000, // Partial payment (4 Million out of 10 Million)
        ]);

        // Assert 422 Unprocessable Entity
        $response->assertStatus(422);

        // Verify BBNBillBillingItem was NOT created
        $this->assertEquals(0, BBNBillBillingItem::count());

        // Verify FinanceBBNBilling was NOT created
        $this->assertEquals(0, FinanceBBNBilling::count());

        // Verify TransactionFlow was NOT created
        $this->assertEquals(0, TransactionFlow::count());
    }

    public function test_full_payment_succeeds_and_creates_records()
    {
        $response = $this->actingAs($this->user)->postJson('/wapi/transaction/bbn-bill-billing-item', [
            'bbn_bill_billing_id' => $this->bbnBillBilling->id,
            'paid_date' => now()->toDateString(),
            'cash_id' => $this->bankUsd->id,
            'amount' => 10000000, // Full payment
        ]);

        $response->assertStatus(201);

        // Verify BBNBillBillingItem was created
        $this->assertEquals(1, BBNBillBillingItem::count());

        // Verify FinanceBBNBilling was created with the full amount and cash_id as null
        $this->assertEquals(1, FinanceBBNBilling::count());
        $this->assertDatabaseHas('finance_bbn_billings', [
            'bbn_bill_id' => $this->bbnBill->id,
            'cash_id' => null,
            'amount' => 10000000,
        ]);

        // Verify TransactionFlow was created with the full amount under bank_usd_debit
        $this->assertEquals(1, TransactionFlow::count());
        $this->assertDatabaseHas('transaction_flows', [
            'company_id' => $this->company->id,
            'description' => 'Pelunasan BBN Bill: BBN-0001',
            'bank_usd_debit' => 10000000,
            'bank_idr_debit' => 0,
            'cash_idr_debit' => 0,
        ]);
    }

    public function test_update_finance_bbn_billing_sets_cash_id_and_adjusts_amount()
    {
        // 1. Create a FinanceBBNBilling record with null cash_id
        $financeBBNBilling = FinanceBBNBilling::create([
            'bbn_bill_id' => $this->bbnBill->id,
            'cash_id' => null,
            'amount' => 1000000, // 1 Million
        ]);

        // Capture initial cash amount
        $initialCashAmount = (float) $this->cash->amount; // 100 Million

        // 2. Perform the update request to assign cash_id
        $response = $this->actingAs($this->user)->putJson("/wapi/finance/bbn-billing/{$financeBBNBilling->id}", [
            'cash_id' => $this->cash->id,
        ]);

        $response->assertStatus(200);

        // Verify the FinanceBBNBilling was updated
        $this->assertDatabaseHas('finance_bbn_billings', [
            'id' => $financeBBNBilling->id,
            'cash_id' => $this->cash->id,
        ]);

        // Verify that the cash amount was adjusted (incremented by 1 Million)
        $this->assertEquals($initialCashAmount + 1000000, (float) $this->cash->fresh()->amount);
    }

    public function test_update_finance_bbn_billing_changes_cash_id_and_reverses_and_adjusts_amount()
    {
        // 1. Create a FinanceBBNBilling record assigned to cash (100 Million initial)
        $financeBBNBilling = FinanceBBNBilling::create([
            'bbn_bill_id' => $this->bbnBill->id,
            'cash_id' => $this->cash->id,
            'amount' => 2000000, // 2 Million
        ]);

        // Set initial amounts for cash and bankIdr
        $this->cash->update(['amount' => 100000000]);
        $this->bankIdr->update(['amount' => 100000000]);

        // 2. Update to change cash_id to bankIdr
        $response = $this->actingAs($this->user)->putJson("/wapi/finance/bbn-billing/{$financeBBNBilling->id}", [
            'cash_id' => $this->bankIdr->id,
        ]);

        $response->assertStatus(200);

        // Verify the FinanceBBNBilling was updated
        $this->assertDatabaseHas('finance_bbn_billings', [
            'id' => $financeBBNBilling->id,
            'cash_id' => $this->bankIdr->id,
        ]);

        // Verify cash amount was reversed (decremented by 2 Million -> 98 Million)
        $this->assertEquals(98000000, (float) $this->cash->fresh()->amount);

        // Verify bankIdr amount was adjusted (incremented by 2 Million -> 102 Million)
        $this->assertEquals(102000000, (float) $this->bankIdr->fresh()->amount);
    }
}
