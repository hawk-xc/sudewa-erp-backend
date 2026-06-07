<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\Person;
use App\Models\GoodsTransaction;
use App\Models\GoodsTransactionDetail;
use App\Models\GoodsTransactionBilling;
use App\Http\Controllers\Transaction\GoodsTransactionController;
use App\Http\Controllers\Transaction\GoodsTransactionDetailController;
use App\Http\Controllers\Transaction\GoodsTransactionBillingController;

class WarehouseGoodsTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyId = 3;

        // 1. Cek terlebih dahulu apakah pada company_id == 3 sudah terdapat data material.
        // Karena table materials tidak memiliki company_id, kita memeriksa apakah ada data material secara global.
        // Jika belum ada, jalankan seeder input data material.
        $hasMaterial = Material::exists();
        if (!$hasMaterial) {
            $this->call(MaterialSeeder::class);
        }
        $material = Material::first();
        if (!$material) {
            $this->command->error("No materials found in the database. Seeding aborted.");
            return;
        }

        // Pastikan supplier dan customer dengan company_id = 3 ada untuk kelancaran testing.
        // Apabila belum ada di database, kita create secara otomatis.
        $supplier = Person::where('type', 'supplier')->where('company_id', $companyId)->first();
        if (!$supplier) {
            $supplier = Person::where('type', 'supplier')->first();
        }
        if (!$supplier) {
            $supplier = Person::create([
                'pic_name' => 'John Doe',
                'company_id' => $companyId,
                'code' => 'SUP-WJY-TEST',
                'type' => 'supplier',
                'name' => 'Supplier Testing Wajira Yanotama',
                'address' => 'Jl. Testing Supplier No. 1',
                'npwp' => '123456789012345',
                'phone' => '081234567890',
            ]);
        }

        $customer = Person::where('type', 'customer')->where('company_id', $companyId)->first();
        if (!$customer) {
            $customer = Person::where('type', 'customer')->first();
        }
        if (!$customer) {
            $customer = Person::create([
                'pic_name' => 'Jane Doe',
                'company_id' => $companyId,
                'code' => 'CUS-WJY-TEST',
                'type' => 'customer',
                'name' => 'Customer Testing Wajira Yanotama',
                'address' => 'Jl. Testing Customer No. 1',
                'npwp' => '123456789012345',
                'phone' => '081234567890',
            ]);
        }

        // Resolving Controllers dari Container
        $goodsTransactionController = app(GoodsTransactionController::class);
        $goodsTransactionDetailController = app(GoodsTransactionDetailController::class);
        $goodsTransactionBillingController = app(GoodsTransactionBillingController::class);

        // ==========================================
        // FLOW A: PENERIMAAN MATERIAL (RECEIPT)
        // ==========================================

        // Step 2: Membuat data goods transaction (receipt) menggunakan controller
        $receiptReqData = [
            'type' => 'receipt',
            'company_id' => $companyId,
            'supplier_id' => $supplier->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Penerimaan Material Seeder Testing',
        ];
        $receiptRequest = Request::create('/api/goods-transactions', 'POST', $receiptReqData);
        $receiptRequest->headers->set('Accept', 'application/json');

        try {
            $receiptResponse = $goodsTransactionController->store($receiptRequest);
            if ($receiptResponse->getStatusCode() !== 201) {
                $this->command->error("Failed to create receipt transaction. Response: " . $receiptResponse->getContent());
                return;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->command->error("Validation error during receipt transaction creation: " . json_encode($e->errors()));
            throw $e;
        }

        $receiptResult = json_decode($receiptResponse->getContent(), true)['data'];
        $receiptTxId = $receiptResult['id'];

        // Step 3: Menambah data goods transaction item menggunakan controller
        $receiptDetailReqData = [
            'goods_transaction_id' => $receiptTxId,
            'material_id' => $material->id,
            'qty' => 10,
            'type' => $material->type, // Harus sesuai tipe unit material (pcs/set/box)
            'price' => $material->price ?? 50000,
            'description' => 'Penerimaan Item Semen',
        ];
        $receiptDetailRequest = Request::create('/api/goods-transaction-details', 'POST', $receiptDetailReqData);
        $receiptDetailRequest->headers->set('Accept', 'application/json');

        try {
            $receiptDetailResponse = $goodsTransactionDetailController->store($receiptDetailRequest);
            if ($receiptDetailResponse->getStatusCode() !== 201) {
                $this->command->error("Failed to add receipt detail. Response: " . $receiptDetailResponse->getContent());
                return;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->command->error("Validation error during receipt detail creation: " . json_encode($e->errors()));
            throw $e;
        }

        $receiptDetailResult = json_decode($receiptDetailResponse->getContent(), true)['data'];

        // Step 4: Menambah data pembayaran pemasukan (billing) menggunakan controller
        $receiptBillingReqData = [
            'goods_transaction_id' => $receiptTxId,
        ];
        $receiptBillingRequest = Request::create('/api/goods-transaction-billings', 'POST', $receiptBillingReqData);
        $receiptBillingRequest->headers->set('Accept', 'application/json');

        try {
            $receiptBillingResponse = $goodsTransactionBillingController->store($receiptBillingRequest);
            if ($receiptBillingResponse->getStatusCode() !== 201) {
                $this->command->error("Failed to create receipt billing. Response: " . $receiptBillingResponse->getContent());
                return;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->command->error("Validation error during receipt billing creation: " . json_encode($e->errors()));
            throw $e;
        }

        $receiptBillingResult = json_decode($receiptBillingResponse->getContent(), true)['data'];


        // ==========================================
        // FLOW B: PENGELUARAN MATERIAL (ISSUE)
        // ==========================================

        // Step 2: Membuat data goods transaction (issue) menggunakan controller
        $issueReqData = [
            'type' => 'issue',
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Pengeluaran Material Seeder Testing',
        ];
        $issueRequest = Request::create('/api/goods-transactions', 'POST', $issueReqData);
        $issueRequest->headers->set('Accept', 'application/json');

        try {
            $issueResponse = $goodsTransactionController->store($issueRequest);
            if ($issueResponse->getStatusCode() !== 201) {
                $this->command->error("Failed to create issue transaction. Response: " . $issueResponse->getContent());
                return;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->command->error("Validation error during issue transaction creation: " . json_encode($e->errors()));
            throw $e;
        }

        $issueResult = json_decode($issueResponse->getContent(), true)['data'];
        $issueTxId = $issueResult['id'];

        // Step 3: Menambah data goods transaction item menggunakan controller
        $issueDetailReqData = [
            'goods_transaction_id' => $issueTxId,
            'material_id' => $material->id,
            'qty' => 5, // Mengeluarkan 5 (masih aman di bawah qty receipt = 10)
            'type' => $material->type,
            'price' => ($material->price ?? 50000) + 10000,
            'description' => 'Pengeluaran Item Semen',
        ];
        $issueDetailRequest = Request::create('/api/goods-transaction-details', 'POST', $issueDetailReqData);
        $issueDetailRequest->headers->set('Accept', 'application/json');

        try {
            $issueDetailResponse = $goodsTransactionDetailController->store($issueDetailRequest);
            if ($issueDetailResponse->getStatusCode() !== 201) {
                $this->command->error("Failed to add issue detail. Response: " . $issueDetailResponse->getContent());
                return;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->command->error("Validation error during issue detail creation: " . json_encode($e->errors()));
            throw $e;
        }

        $issueDetailResult = json_decode($issueDetailResponse->getContent(), true)['data'];

        // Step 4: Menambah data pembayaran pengeluaran (billing) menggunakan controller
        $issueBillingReqData = [
            'goods_transaction_id' => $issueTxId,
        ];
        $issueBillingRequest = Request::create('/api/goods-transaction-billings', 'POST', $issueBillingReqData);
        $issueBillingRequest->headers->set('Accept', 'application/json');

        try {
            $issueBillingResponse = $goodsTransactionBillingController->store($issueBillingRequest);
            if ($issueBillingResponse->getStatusCode() !== 201) {
                $this->command->error("Failed to create issue billing. Response: " . $issueBillingResponse->getContent());
                return;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->command->error("Validation error during issue billing creation: " . json_encode($e->errors()));
            throw $e;
        }

        $issueBillingResult = json_decode($issueBillingResponse->getContent(), true)['data'];
    }
}
