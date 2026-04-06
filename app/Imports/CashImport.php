<?php

namespace App\Imports;

use App\Models\Cash;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CashImport implements ToCollection, WithHeadingRow
{
    protected int $companyId;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $codesInFile = [];

            foreach ($rows as $index => $row) {
                $rowData = [
                    'nominal' => isset($row['nominal']) ? trim($row['nominal']) : null,
                    'deskripsi' => $row['deskripsi'] ?? null,
                    'tipe' => isset($row['tipe']) ? strtolower(trim($row['tipe'])) : null,
                ];

                $validator = Validator::make($rowData, [
                    'nominal' => 'required|string|max:50',
                    'deskripsi' => 'nullable|string',
                    'tipe' => 'required|in:cash,bank',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row ' . ($index + 2) . ': ' . json_encode($validator->errors()->all())
                    );
                }

                Cash::create([
                    'company_id' => $this->companyId,
                    'code' => $rowData['nominal'],
                    'description' => $rowData['deskripsi'],
                    'type' => $rowData['tipe'],
                ]);
            }
        });
    }
}
