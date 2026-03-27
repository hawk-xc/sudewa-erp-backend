<?php

namespace App\Imports;

use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UnitTransactionItemDetailImport implements ToCollection, WithHeadingRow
{
    protected $unitTransactionItemId;

    public function __construct(int $unitTransactionItemId)
    {
        $this->unitTransactionItemId = $unitTransactionItemId;
    }

    public function collection(Collection $rows)
    {
        $unitItemTransaction = UnitTransactionItem::findOrFail($this->unitTransactionItemId);
        $quantityChecker = $unitItemTransaction->qty_total;
        $currentCount = $rows->count();

        DB::transaction(function () use ($rows, $quantityChecker, $currentCount) {

            $machineNumbers = [];

            foreach ($rows as $index => $row) {
                if ($currentCount >= $quantityChecker) {
                    throw new \Exception(
                        'Unit Transaction Item Capacity Reach Maximum value'
                    );
                }

                $rowData = [
                    'warna' => $row['warna'] ?? null,
                    'nomor_mesin' => isset($row['nomor_mesin']) ? trim($row['nomor_mesin']) : null,
                    'nomor_rangka' => isset($row['nomor_rangka']) ? trim($row['nomor_rangka']) : null,
                ];

                if (in_array($rowData['nomor_mesin'], $machineNumbers)) {
                    throw new \Exception(
                        "Duplicate Nomor Mesin '{$rowData['nomor_mesin']}' at row ".($index + 2)
                    );
                }
                $machineNumbers[] = $rowData['nomor_mesin'];

                $validator = Validator::make($rowData, [
                    'warna' => 'nullable|string|max:100',
                    'nomor_mesin' => 'required|string|max:100|unique:unit_transaction_item_details,machine_number',
                    'nomor_rangka' => 'required|string|max:100|unique:unit_transaction_item_details,chassis_number',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                $exists = UnitTransactionItemDetail::where('machine_number', $rowData['nomor_mesin'])
                    ->exists();

                if ($exists) {
                    throw new \Exception(
                        "Nomor Mesin '{$rowData['nomor_mesin']}' already exists at row ".($index + 2)
                    );
                }

                UnitTransactionItemDetail::create([
                    'unit_transaction_item_id' => $this->unitTransactionItemId,
                    'color' => $rowData['warna'],
                    'machine_number' => $rowData['nomor_mesin'],
                    'chassis_number' => $rowData['nomor_rangka'],
                ]);
            }
        });
    }
}
