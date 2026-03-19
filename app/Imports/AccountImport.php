<?php

namespace App\Imports;

use App\Models\Account;
use App\Models\AccountGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountImport implements ToCollection, WithHeadingRow
{
    protected $companyId;

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
                    'grub_akun' => isset($row['grub_akun']) ? (int) trim($row['grub_akun']) : null,
                    'akun' => isset($row['akun']) ? trim($row['akun']) : null,
                    'nama' => isset($row['nama']) ? trim($row['nama']) : null,
                    'deskripsi' => $row['deskripsi'] ?? null,
                    'tipe_akun' => isset($row['tipe_akun']) ? strtolower(trim($row['tipe_akun'])) : null,
                ];

                if (in_array($rowData['akun'], $codesInFile)) {
                    throw new \Exception("Duplicate code '{$rowData['akun']}' in file at row ".($index + 2));
                }
                $codesInFile[] = $rowData['akun'];

                $validator = Validator::make($rowData, [
                    'grub_akun' => 'required|integer',
                    'akun' => ['required', 'string', 'max:50'],
                    'nama' => 'required|string|max:255',
                    'deskripsi' => 'nullable|string',
                    'tipe_akun' => 'required|in:debet,kredit',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                $exists = Account::where('code', $rowData['akun'])
                    ->whereHas('accountGroup', function ($q) {
                        $q->where('company_id', $this->companyId);
                    })
                    ->exists();

                if ($exists) {
                    throw new \Exception(
                        "Account code '{$rowData['akun']}' already exists for this company at row ".($index + 2)
                    );
                }

                $accountGroup = AccountGroup::firstOrCreate(
                    [
                        'group_code' => $rowData['grub_akun'],
                        'company_id' => (int) $this->companyId,
                    ],
                    [
                        'description' => null,
                    ]
                );

                Account::create([
                    'account_group_id' => $accountGroup->id,
                    'code' => $rowData['akun'],
                    'name' => $rowData['nama'],
                    'description' => $rowData['deskripsi'],
                    'type' => $rowData['tipe_akun'] === 'debet' ? 'debet' : 'credit',
                ]);
            }
        });
    }
}
