<?php

namespace Tests\Feature;

use App\Models\Cash;
use App\Models\Company;
use App\Models\User;
use App\Models\WithholdingTax;
use App\Models\UnitTransaction;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithholdingTaxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $cash;
    protected $unitTransaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->user = User::where('username', 'admintest')->first() ?? User::create([
            'name' => 'Admin Test',
            'username' => 'admintest',
            'firstname' => 'Admin',
            'lastname' => 'Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'secure_password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->company = Company::find(1) ?? Company::create([
            'id' => 1,
            'name' => 'Wajira Corp',
            'slug' => 'wajira-corp',
        ]);

        $this->cash = Cash::where('code', 'cash_idr')->first();
        if (!$this->cash) {
            $this->cash = Cash::create([
                'company_id' => $this->company->id,
                'code' => 'cash_idr',
                'cash_name' => 'CASH IDR',
                'type' => 'cash',
                'amount' => 10000000, // 10 Million
            ]);
        } else {
            $this->cash->update(['amount' => 10000000]);
        }

        $warehouse = Warehouse::where('name', 'Test Warehouse')->first() ?? Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Test Warehouse',
        ]);

        $this->unitTransaction = UnitTransaction::where('code', 'TX-001')->first() ?? UnitTransaction::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'TX-001',
            'type' => 'purchase',
            'stock_state' => 'prepare',
        ]);
    }


    public function test_store_withholding_tax_fails_if_no_relation_provided()
    {
        $response = $this->actingAs($this->user)->postJson('/wapi/finance/withholding-tax', [
            'source' => 'internal',
            'company_id' => $this->company->id,
            'cash_id' => $this->cash->id,
            'withholding_number' => 'WHT-ERR-1',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 500000,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['unit_transaction_id', 'bbn_bill_id', 'do_invoice_id']);
    }

    public function test_store_withholding_tax_fails_if_multiple_relations_provided()
    {
        $response = $this->actingAs($this->user)->postJson('/wapi/finance/withholding-tax', [
            'source' => 'internal',
            'company_id' => $this->company->id,
            'cash_id' => $this->cash->id,
            'unit_transaction_id' => $this->unitTransaction->id,
            'bbn_bill_id' => 999, // Multiple relations
            'withholding_number' => 'WHT-ERR-2',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 500000,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['unit_transaction_id', 'bbn_bill_id', 'do_invoice_id']);
    }

    public function test_update_withholding_tax_recalculates_cash_deduction()
    {
        // 1. Create internal withholding tax (starts with 500k deduction)
        $wht = WithholdingTax::create([
            'source' => 'internal',
            'company_id' => $this->company->id,
            'cash_id' => $this->cash->id,
            'unit_transaction_id' => $this->unitTransaction->id,
            'no_invoice' => 'INV-003',
            'withholding_number' => 'WHT-003',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 500000,
            'payment_date' => now()->toDateString(),
        ]);

        // Adjust starting amount to reflect the initial creation
        $this->cash->update(['amount' => 9500000]);

        // 2. Update to 200k payment_amount (reverses 500k debet, applies 200k credit -> net +300k to cash)
        $response = $this->actingAs($this->user)->putJson("/wapi/finance/withholding-tax/{$wht->id}", [
            'payment_amount' => 200000,
        ]);

        $response->assertStatus(200);

        // Assert cash balance is now 9.8 Million
        $this->assertEquals(9800000, (float) $this->cash->fresh()->amount);
    }

    public function test_update_withholding_tax_toggles_source_resets_cash()
    {
        // 1. Create internal withholding tax (starts with 500k deduction)
        $wht = WithholdingTax::create([
            'source' => 'internal',
            'company_id' => $this->company->id,
            'cash_id' => $this->cash->id,
            'unit_transaction_id' => $this->unitTransaction->id,
            'no_invoice' => 'INV-004',
            'withholding_number' => 'WHT-004',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 500000,
            'payment_date' => now()->toDateString(),
        ]);

        $this->cash->update(['amount' => 9500000]);

        // 2. Change source to external (reverses 500k debet, does not deduct -> net +500k to cash)
        $response = $this->actingAs($this->user)->putJson("/wapi/finance/withholding-tax/{$wht->id}", [
            'source' => 'external',
        ]);

        $response->assertStatus(200);

        // Assert cash balance is restored to 10 Million
        $this->assertEquals(10000000, (float) $this->cash->fresh()->amount);
    }

    public function test_destroy_internal_withholding_tax_reverts_cash()
    {
        // 1. Create internal withholding tax (starts with 500k deduction)
        $wht = WithholdingTax::create([
            'source' => 'internal',
            'company_id' => $this->company->id,
            'cash_id' => $this->cash->id,
            'unit_transaction_id' => $this->unitTransaction->id,
            'no_invoice' => 'INV-005',
            'withholding_number' => 'WHT-005',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 500000,
            'payment_date' => now()->toDateString(),
        ]);

        $this->cash->update(['amount' => 9500000]);

        // 2. Delete
        $response = $this->actingAs($this->user)->deleteJson("/wapi/finance/withholding-tax/{$wht->id}");

        $response->assertStatus(200);
        $this->assertEquals(0, WithholdingTax::count());

        // Assert cash balance is restored to 10 Million
        $this->assertEquals(10000000, (float) $this->cash->fresh()->amount);
    }
}
