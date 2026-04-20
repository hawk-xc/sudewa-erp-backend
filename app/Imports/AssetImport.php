<?php

namespace App\Imports;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AssetImport implements ToCollection, WithHeadingRow
{
    protected int $companyId;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    private function generateCode(): string
    {
        $prefix = 'AST';
        $lastAsset = Asset::whereNotNull('code')->orderByDesc('id')->first();
        if (!$lastAsset) {
            return $prefix.'-001';
        }
        $lastNumber = (int) substr($lastAsset->code, -3);
        $newNumber = $lastNumber + 1;
        return $prefix.'-'.str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $rowData = [
                    'name' => isset($row['nama_asset']) ? trim($row['nama_asset']) : null,
                    'code' => isset($row['kode']) ? trim($row['kode']) : null,
                    'serial_number' => isset($row['nomor_serial']) ? trim($row['nomor_serial']) : null,
                    'purchase_date' => isset($row['tanggal_pembelian']) ? trim($row['tanggal_pembelian']) : null,
                    'type' => isset($row['tipe']) ? strtolower(trim($row['tipe'])) : 'inventory',
                    'price' => isset($row['harga']) ? (float) $row['harga'] : 0,
                ];

                $validator = Validator::make($rowData, [
                    'nama_asset' => 'required|string|max:255',
                    'kode' => 'required|string|unique:assets,code',
                    'nomor_serial' => 'required|string|unique:assets,serial_number',
                    'tanggal_beli' => 'nullable|date',
                    'tipe_asset' => 'required|in:inventory,vehicles,buildings,land',
                    'harga' => 'nullable|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                Asset::create([
                    'company_id' => $this->companyId,
                    'code' => $rowData['code'],
                    'name' => $rowData['name'],
                    'serial_number' => $rowData['serial_number'],
                    'purchase_date' => $rowData['purchase_date'],
                    'type' => $rowData['type'],
                    'price' => $rowData['price'],
                ]);
            }
        });
    }
}
