<?php

namespace App\Imports;

use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
                    'code' => isset($row['code']) ? trim($row['code']) : null,
                    'name' => isset($row['name']) ? trim($row['name']) : null,
                    'address' => $row['address'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'npwp' => $row['npwp'] ?? null,
                    'pic_name' => $row['pic_name'] ?? null,
                ];

                if (in_array($rowData['code'], $codesInFile)) {
                    throw new \Exception("Duplicate code '{$rowData['code']}' in file at row ".($index + 2));
                }
                $codesInFile[] = $rowData['code'];

                $validator = Validator::make($rowData, [
                    'code' => 'required|string|max:255',
                    'name' => 'required|string|max:255',
                    'address' => 'nullable|string|max:255',
                    'phone' => 'nullable|string|max:255',
                    'npwp' => 'nullable|string|max:255',
                    'pic_name' => 'nullable|string|max:255',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                $exists = Person::where('code', $rowData['code'])
                    ->where('company_id', $this->companyId)
                    ->where('type', $this->type)
                    ->exists();

                if ($exists) {
                    throw new \Exception(
                        "Code '{$rowData['code']}' already exists for this {$this->type} at row ".($index + 2)
                    );
                }

                Person::create([
                    'uuid' => Str::uuid(),
                    'company_id' => $this->companyId,
                    'code' => $rowData['code'],
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
