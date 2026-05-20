<?php

namespace App\Imports;

use App\Models\Tarif;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TarifImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $rowData = [
                    'loading_in' => $row['muat'] ?? $row['loading_in'] ?? null,
                    'loading_out' => $row['bongkar'] ?? $row['loading_out'] ?? null,
                    'distance' => $row['jarak'] ?? $row['distance'] ?? 0,
                    'uj_towing' => $row['uj_towing'] ?? $row['uang_jalan_towing'] ?? 0,
                    'uj_cdd' => $row['uj_cdd'] ?? $row['uang_jalan_cdd'] ?? 0,
                    'uj_fuso' => $row['uj_fuso'] ?? $row['uang_jalan_fuso'] ?? 0,
                    'inv_cdd' => $row['inv_cdd'] ?? $row['invoice_cdd'] ?? 0,
                    'inv_fuso' => $row['inv_fuso'] ?? $row['invoice_fuso'] ?? 0,
                    'is_active' => isset($row['status']) && $row['status'] == 'aktif' ? 1 : 0,
                ];

                $validator = Validator::make($rowData, [
                    'loading_in' => 'required|string|max:249',
                    'loading_out' => 'required|string|max:249',
                    'distance' => 'required|integer',
                    'uj_towing' => 'nullable|integer',
                    'uj_cdd' => 'nullable|integer',
                    'uj_fuso' => 'nullable|integer',
                    'inv_cdd' => 'nullable|integer',
                    'inv_fuso' => 'nullable|integer',
                    'is_active' => 'nullable',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                Tarif::create([
                    'loading_in' => $rowData['loading_in'],
                    'loading_out' => $rowData['loading_out'],
                    'distance' => $rowData['distance'],
                    'uj_towing' => $rowData['uj_towing'],
                    'uj_cdd' => $rowData['uj_cdd'],
                    'uj_fuso' => $rowData['uj_fuso'],
                    'inv_cdd' => $rowData['inv_cdd'],
                    'inv_fuso' => $rowData['inv_fuso'],
                    'is_active' => $rowData['is_active'],
                ]);
            }
        });
    }
}
