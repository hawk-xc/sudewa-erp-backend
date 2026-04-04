<?php

namespace App\Imports;

use App\Models\UnitType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Validation\Rule;

class UnitTypePriceChangeImport implements ToCollection, WithHeadingRow
{
    protected array $unitTypeMap = [];
    protected array $userMap = [];

    public function __construct()
    {
        $this->unitTypeMap = UnitType::select('id', 'name')
            ->get()
            ->mapWithKeys(function ($unitType) {
                return [strtolower(trim($unitType->name)) => $unitType->id];
            })
            ->toArray();

        $this->userMap = User::select('id', 'name')
            ->get()
            ->mapWithKeys(function ($user) {
                return [strtolower(trim($user->name)) => $user->id];
            })
            ->toArray();
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $rowData = [
                    'unit_type_name' => isset($row['nama_tipe_unit']) ? strtolower(trim($row['nama_tipe_unit'])) : null,
                    'user_name' => isset($row['nama_user']) ? strtolower(trim($row['nama_user'])) : null,
                    'buy_price' => $row['harga_beli'] ?? null,
                    'sell_price' => $row['harga_jual'] ?? null,
                    'note' => $row['catatan'] ?? null,
                ];

                $validator = Validator::make($rowData, [
                    'unit_type_name' => [
                        'required',
                        'string',
                        Rule::in(array_keys($this->unitTypeMap)),
                    ],
                    'user_name' => [
                        'required',
                        'string',
                        Rule::in(array_keys($this->userMap)),
                    ],
                    'buy_price' => 'required|numeric',
                    'sell_price' => 'required|numeric',
                    'note' => 'nullable|string',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row ' . ($index + 2) . ': ' . json_encode($validator->errors()->all())
                    );
                }

                $unitTypeId = $this->unitTypeMap[$rowData['unit_type_name']];
                $userId = $this->userMap[$rowData['user_name']];

                \App\Models\UnitTypePriceArchive::create([
                    'unit_type_id' => $unitTypeId,
                    'user_id' => $userId,
                    'buy_price' => $rowData['buy_price'],
                    'sell_price' => $rowData['sell_price'],
                    'note' => $rowData['note'],
                ]);
            }
        });
    }
}
