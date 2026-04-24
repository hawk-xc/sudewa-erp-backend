<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Company;
use App\Models\User;
use App\Models\AccountGroup;
use App\Models\SparepartCategory;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class MasterDataInputTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $token;

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

        $this->token = JWTAuth::fromUser($this->user);

        $this->company = Company::create([
            'name' => 'Wajira Project',
            'slug' => 'wajira-project',
        ]);
    }

    protected function getHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_input_brand()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/brand', [
            'name' => 'Yamaha',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_unit_type()
    {
        $brand = Brand::create(['name' => 'Honda']);

        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/unit-type', [
            'code' => 'NCX150',
            'brand_id' => $brand->id,
            'name' => 'Vario 150',
            'unit_type' => 'Scooter',
            'netto_weight' => 110,
            'buy_price' => 20000000,
            'sell_price' => 22000000,
        ]);
        $response->assertStatus(201);
    }

    public function test_input_customer()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/customer', [
            'company_id' => $this->company->id,
            'name' => 'John Doe',
            'address' => 'Jakarta',
            'phone' => '08123456789',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_supplier()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/supplier', [
            'company_id' => $this->company->id,
            'name' => 'Supplier A',
            'address' => 'Bandung',
            'phone' => '08987654321',
        ]);
        $response->assertStatus(200);
    }

    public function test_input_material()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/material', [
            'name' => 'Baut 10mm',
            'price' => 500,
            'type' => 'pcs',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_account_group()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/account-group', [
            'company_id' => $this->company->id,
            'group_code' => '1000',
            'description' => 'Asset Group',
        ]);
        $response->assertStatus(201);
    }
    
    public function test_input_sparepart_category()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/sparepart-category', [
            'name' => 'Mesin',
            'code' => 'CAT-001',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_sparepart()
    {
        $cat = SparepartCategory::create(['name' => 'Body', 'code' => 'CAT-002']);
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/sparepart', [
            'sparepart_category_id' => $cat->id,
            'code' => 'SP-001',
            'name' => 'Spion',
            'buy_price' => 50000,
            'sell_price' => 60000,
            'unit_type' => 'pcs',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_region()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/region', [
            'code' => 'REG-JABAR',
            'name' => 'Jawa Barat',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_dealer()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/dealer', [
            'company_id' => $this->company->id,
            'name' => 'Dealer Sentosa',
            'address' => 'Jakarta Timur',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_driver()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/driver', [
            'company_id' => $this->company->id,
            'name' => 'Asep Driver',
            'identity_number' => '3201010101010001',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_vendor()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/vendor', [
            'company_id' => $this->company->id,
            'name' => 'Vendor Karoseri',
            'phone' => '021-123456',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_tarif()
    {
        $cust = \App\Models\Person::create([
            'company_id' => $this->company->id,
            'type' => 'customer',
            'name' => 'John Tariff',
            'code' => 'CST-TRAF-001',
        ]);
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/tarif', [
            'customer_id' => $cust->id,
            'loading_in' => 'Jakarta',
            'loading_out' => 'Surabaya',
            'distance' => 800,
        ]);
        $response->assertStatus(201);
    }

    public function test_input_bbn()
    {
        $dealer = \App\Models\Person::create([
            'company_id' => $this->company->id,
            'type' => 'dealer',
            'name' => 'John Dealer',
            'code' => 'DLR-BBN-001',
        ]);
        $region = Region::create(['code' => 'REG-BBN', 'name' => 'BBN Region']);

        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/bbn', [
            'dealer_id' => $dealer->id,
            'region_id' => $region->id,
            'tnbk_code' => 'T-BBN',
            'vehicle_type' => 'r2',
            'un_notice_fee' => '1000000',
            'garwil_fee' => '100000',
            'countershop_fee' => '50000',
            'other_fee' => '0',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_vehicle_fleet()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/vehicle-fleet', [
            'registration_number' => 'B 1234 ABC',
            'type' => 'CDD',
            'machine_number' => 'MACH-FLEET-1',
            'chassis_number' => 'CHAS-FLEET-1',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_account()
    {
        $group = AccountGroup::create([
            'company_id' => $this->company->id,
            'group_code' => '2000',
        ]);
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/account', [
            'account_group_id' => $group->id,
            'code' => 'ACC-001',
            'name' => 'Cash in Hand',
            'type' => 'debet',
        ]);
        $response->assertStatus(201);
    }

    public function test_input_asset()
    {
        $response = $this->withHeaders($this->getHeaders())->postJson('/wapi/master-data/asset', [
            'company_id' => $this->company->id,
            'code' => 'AST-001',
            'serial_number' => 'SN-'.rand(1000, 9999),
            'name' => 'Laptop Office',
            'type' => 'inventory',
            'price' => 15000000,
        ]);
        $response->assertStatus(201);
    }
}
