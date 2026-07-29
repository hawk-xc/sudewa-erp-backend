<?php

namespace App\Imports;

use App\Models\WarehouseBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WarehouseBlockImport implements ToCollection, WithHeadingRow
{
    protected int $warehouseId;

    public function __construct(int $warehouseId)
    {
        $this->warehouseId = $warehouseId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                if (empty($row['name']) && empty($row['nama'])) {
                    continue;
                }

                $name = isset($row['name']) ? trim($row['name']) : (isset($row['nama']) ? trim($row['nama']) : null);
                $description = isset($row['description']) ? trim($row['description']) : (isset($row['deskripsi']) ? trim($row['deskripsi']) : null);

                $rowData = [
                    'warehouse_id' => $this->warehouseId,
                    'name' => $name,
                    'description' => $description,
                ];

                $validator = Validator::make($rowData, [
                    'warehouse_id' => 'required|exists:warehouses,id',
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row ' . ($index + 2) . ': ' . json_encode($validator->errors()->all())
                    );
                }

                WarehouseBlock::create($rowData);
            }
        });
    }
}
