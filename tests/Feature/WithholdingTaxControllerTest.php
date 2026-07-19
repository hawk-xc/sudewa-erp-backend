<?php

namespace Tests\Feature;

use App\Models\Cash;
use App\Models\Company;
use App\Models\User;
use App\Models\WithholdingTax;
use App\Models\Warehouse;
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
    }
}
