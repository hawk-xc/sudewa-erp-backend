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
                // Map category/kategori from raw input to database enum value
                $categoryRaw = isset($row['kategori']) ? strtolower(trim($row['kategori'])) : null;
                if (!$categoryRaw && isset($row['category'])) {
                    $categoryRaw = strtolower(trim($row['category']));
                }

                $category = 'general_administration';
                if ($categoryRaw) {
                    if (str_contains($categoryRaw, 'aktiva') || str_contains($categoryRaw, 'lancar') || str_contains($categoryRaw, 'current')) {
                        $category = 'current_assets';
                    } elseif (str_contains($categoryRaw, 'pasiva') || str_contains($categoryRaw, 'kewajiban') || str_contains($categoryRaw, 'liabilit')) {
                        $category = 'liabilities';
                    }
                }

                $rowData = [
                    'grub_akun' => isset($row['grub_akun']) ? (int) trim($row['grub_akun']) : null,
                    'akun' => isset($row['akun']) ? trim($row['akun']) : null,
                    'nama' => isset($row['nama']) ? trim($row['nama']) : null,
                    'deskripsi' => $row['deskripsi'] ?? null,
                    'tipe_akun' => isset($row['tipe_akun']) ? strtolower(trim($row['tipe_akun'])) : null,
                    'kategori' => $category,
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
                    'kategori' => 'required|in:general_administration,current_assets,liabilities',
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
                    'category' => $rowData['kategori'],
                ]);
            }
        });
    }
}
