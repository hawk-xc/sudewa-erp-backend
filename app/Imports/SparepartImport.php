<?php

namespace App\Imports;

use App\Models\Sparepart;
use App\Models\SparepartCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SparepartImport implements ToCollection, WithHeadingRow
{
    protected array $categoryMap = [];

    public function __construct()
    {
        $this->categoryMap = SparepartCategory::select('id', 'name')
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
                    'kategori' => isset($row['kategori']) ? strtolower(trim($row['kategori'])) : null,
                    'nama' => isset($row['nama']) ? trim($row['nama']) : null,
                    'tipe_unit' => $row['tipe_unit'] ?? null,
                    'harga_beli' => $row['harga_beli'] ?? null,
                    'harga_jual' => $row['harga_jual'] ?? null,
                ];

                if (in_array($rowData['kode'], $codesInFile)) {
                    throw new \Exception("Duplicate code '{$rowData['kode']}' in file at row ".($index + 2));
                }
                $codesInFile[] = $rowData['kode'];

                $validator = Validator::make($rowData, [
                    'kode' => 'required|string|max:255',
                    'kategori' => 'required|string',
                    'nama' => 'required|string|max:255',
                    'tipe_unit' => 'nullable|string|in:pcs,set,box',
                    'harga_beli' => 'nullable|numeric',
                    'harga_jual' => 'nullable|numeric',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                $sparepartCategoryKey = $rowData['kategori'];

                if (! isset($this->categoryMap[$sparepartCategoryKey])) {
                    $category = SparepartCategory::create([
                        'name' => ucfirst($sparepartCategoryKey),
                        'code' => Str::random(10),
                        'image' => null,
                    ]);

                    $this->categoryMap[$sparepartCategoryKey] = $category->id;
                }

                $categoryId = $this->categoryMap[$sparepartCategoryKey];

                Sparepart::create([
                    'code' => $rowData['kode'],
                    'sparepart_category_id' => $categoryId,
                    'name' => $rowData['nama'],
                    'unit_type' => $rowData['tipe_unit'],
                    'buy_price' => $rowData['harga_beli'],
                    'sell_price' => $rowData['harga_jual'],
                ]);
            }
        });
    }
}
