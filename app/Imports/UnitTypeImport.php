<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\UnitType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UnitTypeImport implements ToCollection, WithHeadingRow
{
    protected array $brandMap = [];

    public function __construct()
    {
        $this->brandMap = Brand::select('id', 'name')
            ->get()
            ->mapWithKeys(function ($brand) {
                return [strtolower(trim($brand->name)) => $brand->id];
            })
            ->toArray();
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $codesInFile = [];

            foreach ($rows as $index => $row) {
                $rowData = [
                    'kode' => isset($row['kode']) ? trim($row['kode']) : null,
                    'brand' => isset($row['brand']) ? strtolower(trim($row['brand'])) : null,
                    'nama' => isset($row['nama']) ? trim($row['nama']) : null,
                    'tipe_unit' => $row['tipe_unit'] ?? null,
                    'model_unit' => $row['model_unit'] ?? null,
                    'berat_netto' => $row['berat_netto'] ?? null,
                    'berat_brutto' => $row['berat_brutto'] ?? null,
                    'harga_beli' => $row['harga_beli'] ?? null,
                    'harga_jual' => $row['harga_jual'] ?? null,
                ];

                if (in_array($rowData['kode'], $codesInFile)) {
                    throw new \Exception("Duplicate code '{$rowData['kode']}' in file at row ".($index + 2));
                }
                $codesInFile[] = $rowData['kode'];

                $validator = Validator::make($rowData, [
                    'kode' => 'required|string|max:255',
                    'brand' => 'required|string',
                    'nama' => 'required|string|max:255',
                    'tipe_unit' => 'nullable|string',
                    'model_unit' => 'nullable|string',
                    'berat_netto' => 'nullable|numeric',
                    'berat_brutto' => 'nullable|numeric',
                    'harga_beli' => 'nullable|numeric',
                    'harga_jual' => 'nullable|numeric',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                $brandKey = $rowData['brand'];

                if (! isset($this->brandMap[$brandKey])) {

                    $brand = Brand::create([
                        'name' => ucfirst($brandKey),
                        'image' => null,
                    ]);

                    $this->brandMap[$brandKey] = $brand->id;
                }

                $brandId = $this->brandMap[$brandKey];

                UnitType::create([
                    'code' => $rowData['kode'],
                    'brand_id' => $brandId,
                    'name' => $rowData['nama'],
                    'unit_type' => $rowData['tipe_unit'],
                    'unit_model' => $rowData['model_unit'],
                    'netto_weight' => $rowData['berat_netto'],
                    'bruto_weight' => $rowData['berat_brutto'],
                    'buy_price' => $rowData['harga_beli'],
                    'sell_price' => $rowData['harga_jual'],
                ]);
            }
        });
    }
}
