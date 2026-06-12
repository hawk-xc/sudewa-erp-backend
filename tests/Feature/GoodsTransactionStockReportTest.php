<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\GoodsTransaction;
use App\Models\GoodsTransactionDetail;
use App\Models\Material;
use App\Models\VehicleEquipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsTransactionStockReportTest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $warehouse;
    protected $material;
    protected $vehicleEquipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->company = Company::create(['name' => 'Wajira Corp', 'slug' => 'wajira-corp']);
        $this->warehouse = Warehouse::create([
            'company_id' => $this->company->id, 
            'name' => 'Main Warehouse', 
            'code' => 'WH01',
            'capacity' => 1000
        ]);

        $this->material = Material::create([
            'code' => 'MAT01',
            'name' => 'Semen Padang 50kg',
            'type' => 'pcs'
        ]);

        $this->vehicleEquipment = VehicleEquipment::create([
            'code' => 'EQP01',
            'name' => 'Helm Wajira'
        ]);
    }

    public function test_receipt_material_report_hides_nested_details_and_applies_projections()
    {
        // 1. Create a receipt transaction
        $transaction = GoodsTransaction::create([
            'code' => 'TRM-MT-001',
            'company_id' => $this->company->id,
            'type' => 'receipt',
            'transaction_date' => now()->toDateString(),
        ]);

        // 2. Create details
        GoodsTransactionDetail::create([
            'goods_transaction_id' => $transaction->id,
            'material_id' => $this->material->id,
            'qty' => 10,
            'type' => 'pcs',
            'price' => 75000,
            'description' => 'Penerimaan Semen',
        ]);

        // 3. Call the API endpoint
        $response = $this->getJson("/wapi/report/receipt-material?company_id={$this->company->id}");

        $response->assertStatus(200);

        // Verify structure of the response
        $data = $response->json('data.data.0');

        $this->assertNotNull($data);
        $this->assertEquals(10, $data['qty']);
        
        // Assert projected fields in goods_transaction
        $this->assertArrayHasKey('goods_transaction', $data);
        $this->assertEquals($transaction->id, $data['goods_transaction']['id']);
        $this->assertEquals('TRM-MT-001', $data['goods_transaction']['code']);
        $this->assertArrayHasKey('transaction_date', $data['goods_transaction']);
        $this->assertEquals('receipt', $data['goods_transaction']['type']);

        // Assert nested goods_transaction_details is HIDDEN
        $this->assertArrayNotHasKey('goods_transaction_details', $data['goods_transaction']);

        // Assert projected fields in material
        $this->assertArrayHasKey('material', $data);
        $this->assertEquals($this->material->id, $data['material']['id']);
        $this->assertEquals('MAT01', $data['material']['code']);
        $this->assertEquals('Semen Padang 50kg', $data['material']['name']);

        // Assert hidden top-level fields on GoodsTransactionDetail
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('goods_transaction_id', $data);
        $this->assertArrayNotHasKey('material_id', $data);
        $this->assertArrayNotHasKey('vehicle_equipment_id', $data);
    }

    public function test_issue_material_report_hides_nested_details_and_applies_projections()
    {
        // 1. Create an issue transaction
        $transaction = GoodsTransaction::create([
            'code' => 'TRM-MT-002',
            'company_id' => $this->company->id,
            'type' => 'issue',
            'transaction_date' => now()->toDateString(),
        ]);

        // 2. Create details
        GoodsTransactionDetail::create([
            'goods_transaction_id' => $transaction->id,
            'material_id' => $this->material->id,
            'qty' => 5,
            'type' => 'pcs',
            'price' => 75000,
            'description' => 'Pengeluaran Semen',
        ]);

        // 3. Call the API endpoint
        $response = $this->getJson("/wapi/report/issue-material?company_id={$this->company->id}");

        $response->assertStatus(200);

        // Verify structure of the response
        $data = $response->json('data.data.0');

        $this->assertNotNull($data);
        $this->assertEquals(5, $data['qty']);
        
        // Assert projected fields in goods_transaction
        $this->assertArrayHasKey('goods_transaction', $data);
        $this->assertEquals($transaction->id, $data['goods_transaction']['id']);
        $this->assertEquals('TRM-MT-002', $data['goods_transaction']['code']);
        $this->assertArrayNotHasKey('goods_transaction_details', $data['goods_transaction']);
    }

    public function test_receipt_vehicle_equipment_report_hides_nested_details_and_applies_projections()
    {
        // 1. Create a receipt transaction
        $transaction = GoodsTransaction::create([
            'code' => 'TRM-EQ-001',
            'company_id' => $this->company->id,
            'type' => 'receipt',
            'transaction_date' => now()->toDateString(),
        ]);

        // 2. Create details
        GoodsTransactionDetail::create([
            'goods_transaction_id' => $transaction->id,
            'vehicle_equipment_id' => $this->vehicleEquipment->id,
            'qty' => 2,
            'type' => 'pcs',
            'price' => 150000,
            'description' => 'Penerimaan Helm',
        ]);

        // 3. Call the API endpoint
        $response = $this->getJson("/wapi/report/receipt-vehicle-equipment?company_id={$this->company->id}");

        $response->assertStatus(200);

        // Verify structure of the response
        $data = $response->json('data.data.0');

        $this->assertNotNull($data);
        $this->assertEquals(2, $data['qty']);
        
        // Assert projected fields in goods_transaction
        $this->assertArrayHasKey('goods_transaction', $data);
        $this->assertEquals($transaction->id, $data['goods_transaction']['id']);
        $this->assertEquals('TRM-EQ-001', $data['goods_transaction']['code']);
        $this->assertArrayNotHasKey('goods_transaction_details', $data['goods_transaction']);

        // Assert projected fields in vehicle_equipment
        $this->assertArrayHasKey('vehicle_equipment', $data);
        $this->assertEquals($this->vehicleEquipment->id, $data['vehicle_equipment']['id']);
        $this->assertEquals('EQP01', $data['vehicle_equipment']['code']);
        $this->assertEquals('Helm Wajira', $data['vehicle_equipment']['name']);

        // Assert hidden top-level fields on GoodsTransactionDetail
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('goods_transaction_id', $data);
        $this->assertArrayNotHasKey('material_id', $data);
        $this->assertArrayNotHasKey('vehicle_equipment_id', $data);
    }

    public function test_issue_vehicle_equipment_report_hides_nested_details_and_applies_projections()
    {
        // 1. Create an issue transaction
        $transaction = GoodsTransaction::create([
            'code' => 'TRM-EQ-002',
            'company_id' => $this->company->id,
            'type' => 'issue',
            'transaction_date' => now()->toDateString(),
        ]);

        // 2. Create details
        GoodsTransactionDetail::create([
            'goods_transaction_id' => $transaction->id,
            'vehicle_equipment_id' => $this->vehicleEquipment->id,
            'qty' => 1,
            'type' => 'pcs',
            'price' => 150000,
            'description' => 'Pengeluaran Helm',
        ]);

        // 3. Call the API endpoint
        $response = $this->getJson("/wapi/report/issue-vehicle-equipment?company_id={$this->company->id}");

        $response->assertStatus(200);

        // Verify structure of the response
        $data = $response->json('data.data.0');

        $this->assertNotNull($data);
        $this->assertEquals(1, $data['qty']);
        
        // Assert projected fields in goods_transaction
        $this->assertArrayHasKey('goods_transaction', $data);
        $this->assertEquals($transaction->id, $data['goods_transaction']['id']);
        $this->assertEquals('TRM-EQ-002', $data['goods_transaction']['code']);
        $this->assertArrayNotHasKey('goods_transaction_details', $data['goods_transaction']);
    }
}
