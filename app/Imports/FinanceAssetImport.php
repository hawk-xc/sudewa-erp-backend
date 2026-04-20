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
                    'depreciation' => isset($row['depresiasi']) ? (float) $row['depresiasi'] : 0,
                    'residual_value' => isset($row['nilai_residu']) ? (float) $row['nilai_residu'] : 0,
                    'final_value' => isset($row['nilai_akhir']) ? (float) $row['nilai_akhir'] : 0,
                    'description' => isset($row['deskripsi']) ? trim($row['deskripsi']) : null,
                ];

                $validator = Validator::make($rowData, [
                    'economic_age' => 'nullable|integer|min:0',
                    'depreciation' => 'nullable|numeric|min:0',
                    'residual_value' => 'nullable|numeric|min:0',
                    'final_value' => 'nullable|numeric|min:0',
                    'description' => 'nullable|string',
                ]);

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
