<?php

namespace App\Imports;

use App\Models\VehicleEquipment;
use App\Traits\VehicleEquipmentTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VehicleEquipmentImport implements ToCollection, WithHeadingRow
{
    use VehicleEquipmentTrait;

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $codesInFile = [];

            foreach ($rows as $index => $row) {
                // Support 'nama_perlengkapan' and 'nama'
                $name = isset($row['nama_perlengkapan']) ? trim($row['nama_perlengkapan']) : (isset($row['nama']) ? trim($row['nama']) : null);
                
                // If code is in file, use it; otherwise generate it
                $code = isset($row['kode']) ? trim($row['kode']) : null;

                $rowData = [
                    'code' => $code,
                    'name' => $name,
                ];

                $validator = Validator::make($rowData, [
                    'name' => 'required|string|max:255',
                    'code' => 'nullable|string|max:255',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                if ($rowData['code']) {
                    if (in_array($rowData['code'], $codesInFile)) {
                        throw new \Exception("Duplicate code '{$rowData['code']}' in file at row ".($index + 2));
                    }
                    $codesInFile[] = $rowData['code'];

                    $exists = VehicleEquipment::where('code', $rowData['code'])->exists();
                    if ($exists) {
                        throw new \Exception(
                            "Vehicle Equipment code '{$rowData['code']}' already exists at row ".($index + 2)
                        );
                    }
                } else {
                    $rowData['code'] = $this->generateEquipmentCode();
                }

                VehicleEquipment::create([
                    'code' => $rowData['code'],
                    'name' => $rowData['name'],
                ]);
            }
        });
    }
}
