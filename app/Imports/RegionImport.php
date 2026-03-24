<?php

namespace App\Imports;

use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RegionImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $codesInFile = [];

            foreach ($rows as $index => $row) {
                $rowData = [
                    'kode' => isset($row['kode']) ? trim($row['kode']) : null,
                    'nama' => isset($row['nama']) ? trim($row['nama']) : null,
                ];

                if (in_array($rowData['kode'], $codesInFile)) {
                    throw new \Exception("Duplicate code '{$rowData['kode']}' in file at row ".($index + 2));
                }
                $codesInFile[] = $rowData['kode'];

                $validator = Validator::make($rowData, [
                    'kode' => 'required|string',
                    'nama' => 'required|string|max:255',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                $exists = Region::where('code', $rowData['kode'])
                    ->exists();

                if ($exists) {
                    throw new \Exception(
                        "Region code '{$rowData['kode']}' already exists at row ".($index + 2)
                    );
                }

                Region::create([
                    'code' => $rowData['kode'],
                    'name' => $rowData['nama'],
                ]);
            }
        });
    }
}
