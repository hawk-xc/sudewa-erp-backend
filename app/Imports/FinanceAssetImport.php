<?php

namespace App\Imports;

use App\Models\Asset;
use App\Models\FinanceAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FinanceAssetImport implements ToCollection, WithHeadingRow
{
    public function __construct()
    {
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $code = $row['kode_asset'] ?? null;
                
                if (!$code) {
                    throw new \Exception('Error on row '.($index + 2).': Kode Asset is required');
                }

                $asset = Asset::where('code', $code)->first();

                if (!$asset) {
                    throw new \Exception('Error on row '.($index + 2).': Asset with code '.$code.' not found');
                }

                $rowData = [
                    'economic_age' => isset($row['umur_ekonomis']) ? (int) $row['umur_ekonomis'] : 0,
                    'description' => isset($row['deskripsi']) ? trim($row['deskripsi']) : null,
                ];

                if (isset($row['harga'])) {
                    $rowData['price'] = (float) $row['harga'];
                }
                if (isset($row['nomor_serial'])) {
                    $rowData['serial_number'] = trim($row['nomor_serial']);
                }
                if (!empty($row['tanggal_beli'])) {
                    try {
                        $rowData['purchase_date'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['tanggal_beli'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                $rules = [
                    'economic_age' => 'nullable|integer|min:0',
                    'description' => 'nullable|string',
                ];
                if (isset($rowData['price'])) {
                    $rules['price'] = 'nullable|numeric|min:0';
                }
                if (isset($rowData['serial_number'])) {
                    $rules['serial_number'] = 'nullable|string|unique:finance_assets,serial_number,' . ($asset->financeAsset?->id ?? 'NULL');
                }
                if (isset($rowData['purchase_date'])) {
                    $rules['purchase_date'] = 'nullable|date';
                }

                $validator = Validator::make($rowData, $rules);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                FinanceAsset::updateOrCreate(
                    ['asset_id' => $asset->id],
                    $rowData
                );
            }
        });
    }
}
