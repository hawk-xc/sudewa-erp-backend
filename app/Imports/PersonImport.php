<?php

namespace App\Imports;

use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PersonImport implements ToCollection, WithHeadingRow
{
    protected string $type;

    protected int $companyId;

    public function __construct(string $type, int $companyId)
    {
        $this->type = $type == 'supplier' ? 'supplier' : 'customer';
        $this->companyId = $companyId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $codesInFile = [];

            foreach ($rows as $index => $row) {
                $rowData = [
                    'name' => isset($row['nama']) ? trim($row['nama']) : null,
                    'address' => $row['alamat'] ?? null,
                    'phone' => $row['telp'] ?? null,
                    'npwp' => $row['npwp'] ?? null,
                    'pic_name' => $row['nama_pic'] ?? null,
                ];

                $validator = Validator::make($rowData, [
                    'nama' => 'required|string|max:255',
                    'alamat' => 'nullable|string|max:255',
                    'telp' => 'nullable|string|max:255',
                    'npwp' => 'nullable|string|max:255',
                    'nama_pic' => 'nullable|string|max:255',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                Person::create([
                    'company_id' => $this->companyId,
                    'type' => $this->type,
                    'name' => $rowData['name'],
                    'address' => $rowData['address'],
                    'phone' => $rowData['phone'],
                    'npwp' => $rowData['npwp'],
                    'pic_name' => $rowData['pic_name'],
                ]);
            }
        });
    }
}
