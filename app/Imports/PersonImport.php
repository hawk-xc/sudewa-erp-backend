<?php

namespace App\Imports;

use App\Models\Person;
use App\Traits\PersonTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PersonImport implements ToCollection, WithHeadingRow
{
    use PersonTrait;

    protected string $type;

    protected int $companyId;

    public function __construct(string $type, int $companyId)
    {
        $this->type = (string) $type;

        $this->companyId = $companyId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $codesInFile = [];

            foreach ($rows as $index => $row) {
                $rowData = [
                    'nama' => isset($row['nama']) ? trim($row['nama']) : null,
                    'alamat' => $row['alamat'] ?? null,
                    'telp' => $row['telp'] ?? null,
                    'npwp' => $row['npwp'] ?? null,
                    'nama_pic' => $row['nama_pic'] ?? null,
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
                    'code' => $this->generateCode($this->type),
                    'type' => $this->type,
                    'name' => $rowData['nama'],
                    'address' => $rowData['alamat'],
                    'phone' => $rowData['telp'],
                    'npwp' => $rowData['npwp'],
                    'pic_name' => $rowData['nama_pic'],
                ]);
            }
        });
    }
}
