<?php

namespace App\Imports;

use App\Models\WarehouseSubBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WarehouseSubBlockImport implements ToCollection, WithHeadingRow
{
    protected int $warehouseBlockId;

    public function __construct(int $warehouseBlockId)
    {
        $this->warehouseBlockId = $warehouseBlockId;
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

                $isActive = true;
                if (isset($row['is_active'])) {
                    $isActive = filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN);
                }

                $isDefault = false;
                if (isset($row['is_default'])) {
                    $isDefault = filter_var($row['is_default'], FILTER_VALIDATE_BOOLEAN);
                }

                $rowData = [
                    'warehouse_block_id' => $this->warehouseBlockId,
                    'name' => $name,
                    'description' => $description,
                    'is_active' => $isActive,
                    'is_default' => $isDefault,
                ];

                $validator = Validator::make($rowData, [
                    'warehouse_block_id' => 'required|exists:warehouse_blocks,id',
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'is_active' => 'boolean',
                    'is_default' => 'boolean',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row ' . ($index + 2) . ': ' . json_encode($validator->errors()->all())
                    );
                }

                if ($rowData['is_default']) {
                    WarehouseSubBlock::where('warehouse_block_id', $this->warehouseBlockId)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                WarehouseSubBlock::create($rowData);
            }
        });
    }
}
