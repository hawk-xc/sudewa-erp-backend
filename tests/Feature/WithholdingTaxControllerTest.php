<?php

namespace Tests\Feature;

use App\Models\Cash;
use App\Models\Company;
use App\Models\User;
use App\Models\WithholdingTax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithholdingTaxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $cash;

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

        $this->company = Company::create([
            'name' => 'Wajira Corp',
            'slug' => 'wajira-corp',
        ]);

        $this->cash = Cash::create([
            'company_id' => $this->company->id,
            'code' => 'cash_idr',
            'cash_name' => 'CASH IDR',
            'type' => 'cash',
            'amount' => 10000000, // 10 Million
        ]);
    }

    public function test_store_internal_withholding_tax_deducts_cash()
    {
        $initialBalance = (float) $this->cash->amount;

        $response = $this->actingAs($this->user)->postJson('/wapi/finance/withholding-tax', [
            'source' => 'internal',
            'cash_id' => $this->cash->id,
            'withholding_number' => 'WHT-001',
            'withholding_age' => 30,
            'pph_amount' => 500000, // 500k
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 0,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertEquals(1, WithholdingTax::count());

        // Assert cash balance was decremented (10M - 500K = 9.5M)
        $this->assertEquals($initialBalance - 500000, (float) $this->cash->fresh()->amount);
    }

    public function test_store_external_withholding_tax_does_not_deduct_cash()
    {
        $initialBalance = (float) $this->cash->amount;

        $response = $this->actingAs($this->user)->postJson('/wapi/finance/withholding-tax', [
            'source' => 'external',
            'cash_id' => $this->cash->id,
            'withholding_number' => 'WHT-002',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 0,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertEquals(1, WithholdingTax::count());

        // Assert cash balance was NOT decremented
        $this->assertEquals($initialBalance, (float) $this->cash->fresh()->amount);
    }

    public function test_update_withholding_tax_recalculates_cash_deduction()
    {
        // 1. Create internal withholding tax (starts with 500k deduction)
        $wht = WithholdingTax::create([
            'source' => 'internal',
            'cash_id' => $this->cash->id,
            'withholding_number' => 'WHT-003',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 0,
            'payment_date' => now()->toDateString(),
        ]);

        // Adjust starting amount to reflect the initial creation
        $this->cash->update(['amount' => 9500000]);

        // 2. Update to 200k pph_amount (reverses 500k debet, applies 200k credit -> net +300k to cash)
        $response = $this->actingAs($this->user)->putJson("/wapi/finance/withholding-tax/{$wht->id}", [
            'pph_amount' => 200000,
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
            'cash_id' => $this->cash->id,
            'withholding_number' => 'WHT-004',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 0,
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
            'cash_id' => $this->cash->id,
            'withholding_number' => 'WHT-005',
            'withholding_age' => 30,
            'pph_amount' => 500000,
            'pph_description' => 'Pph Pasal 23',
            'payment_amount' => 0,
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
