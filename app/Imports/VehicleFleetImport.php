<?php

namespace App\Imports;

use App\Models\VehicleFleet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class VehicleFleetImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                if (
                    empty($row['nomor_polisi']) &&
                    empty($row['nomor_mesin']) &&
                    empty($row['nomor_rangka'])
                ) {
                    continue;
                }

                $stnkAge = null;
                if (!empty($row['masa_berlaku_stnk'])) {
                    try {
                        $stnkAge = Date::excelToDateTimeObject($row['masa_berlaku_stnk'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        $stnkAge = null;
                    }
                }

                $kirAge = null;
                if (!empty($row['masa_berlaku_kir'])) {
                    try {
                        $kirAge = Date::excelToDateTimeObject($row['masa_berlaku_kir'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        $kirAge = null;
                    }
                }

                $rowData = [
                    'registration_number' => isset($row['nomor_polisi']) ? trim($row['nomor_polisi']) : null,
                    'type' => isset($row['tipe_kendaraan']) ? strtolower(trim($row['tipe_kendaraan'])) : null,
                    'machine_number' => isset($row['nomor_mesin']) ? trim($row['nomor_mesin']) : null,
                    'chassis_number' => isset($row['nomor_rangka']) ? trim($row['nomor_rangka']) : null,
                    'stnk_age' => $stnkAge,
                    'kir_age' => $kirAge,
                    'stnk_number' => isset($row['nomor_stnk']) ? trim($row['nomor_stnk']) : null,
                    'kir_book' => isset($row['nomor_buku_kir']) ? trim($row['nomor_buku_kir']) : null,
                ];

                $validator = Validator::make($rowData, [
                    'registration_number' => 'required|string|max:249',
                    'type' => 'required|string|in:fuso,towing,cdd',
                    'machine_number' => 'required|string|max:249|unique:vehicle_fleets,machine_number',
                    'chassis_number' => 'required|string|max:249|unique:vehicle_fleets,chassis_number',
                    'stnk_age' => 'nullable|date',
                    'kir_age' => 'nullable|date',
                    'stnk_number' => 'nullable|string|max:249',
                    'kir_book' => 'nullable|string|max:249',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row ' . ($index + 2) . ': ' . json_encode($validator->errors()->all())
                    );
                }

                VehicleFleet::create($rowData);
            }
        });
    }
}
