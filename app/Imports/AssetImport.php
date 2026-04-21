<?php

namespace App\Imports;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

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
            return $prefix . '-001';
        }

        $lastNumber = (int) substr($lastAsset->code, -3);
        $newNumber = $lastNumber + 1;

        return $prefix . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {

            foreach ($rows as $index => $row) {

                if (
                    empty($row['nama_asset']) &&
                    empty($row['nomor_serial']) &&
                    empty($row['kode'])
                ) {
                    continue;
                }

                $name = isset($row['nama_asset']) ? trim($row['nama_asset']) : null;
                $code = isset($row['kode']) ? trim($row['kode']) : null;
                $serial = isset($row['nomor_serial']) ? trim($row['nomor_serial']) : null;

                $purchaseDate = null;
                if (!empty($row['tanggal_beli'])) {
                    try {
                        $purchaseDate = Date::excelToDateTimeObject($row['tanggal_beli'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        $purchaseDate = null;
                    }
                }

                $type = isset($row['tipe_asset']) && trim($row['tipe_asset']) !== ''
                    ? strtolower(trim($row['tipe_asset']))
                    : 'inventory';

                $price = isset($row['harga']) ? (float) $row['harga'] : 0;

                if (!$code) {
                    $code = $this->generateCode();
                }

                $rowData = [
                    'name' => $name,
                    'code' => $code,
                    'serial_number' => $serial,
                    'purchase_date' => $purchaseDate,
                    'type' => $type,
                    'price' => $price,
                ];

                $validator = Validator::make($rowData, [
                    'name' => 'required|string|max:255',
                    'code' => 'required|string|unique:assets,code',
                    'serial_number' => 'required|string|unique:assets,serial_number',
                    'purchase_date' => 'nullable|date',
                    'type' => 'required|in:inventory,vehicles,buildings,land',
                    'price' => 'nullable|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row ' . ($index + 2) . ': ' . json_encode($validator->errors()->all())
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