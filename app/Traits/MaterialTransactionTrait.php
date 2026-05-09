<?php

namespace App\Traits;

use App\Models\MaterialTransaction;
use Illuminate\Support\Facades\DB;

trait MaterialTransactionTrait
{
    /**
     * Generate transaction code for material transactions
     * Format: TMU-0001HSA
     */
    public function generateMaterialCode(string $type): ?string
    {
        if (! in_array($type, ['purchase', 'sales'], true)) {
            return null;
        }

        return DB::transaction(function () {
            $lastTransaction = MaterialTransaction::where('code', 'like', 'TMU-%HSA')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $lastNumber = 0;

            if ($lastTransaction) {
                // Extract number from TMU-0001HSA (TMU- is index 0-3, number starts at index 4)
                $lastNumber = (int) substr($lastTransaction->code, 4, 4);
            }

            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            return "TMU-{$newNumber}HSA";
        });
    }
}
