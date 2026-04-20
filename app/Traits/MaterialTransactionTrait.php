<?php

namespace App\Traits;

use App\Models\MaterialTransaction;
use Illuminate\Support\Facades\DB;

trait MaterialTransactionTrait
{
    /**
     * Generate transaction code for material transactions
     * Format: PBL-WJY/20260411-0001 for purchase
     * Format: INV-WJY/20260411-0001 for sales
     */
    public function generateMaterialCode(string $type): ?string
    {
        if (! in_array($type, ['purchase', 'sales'], true)) {
            return null;
        }

        return DB::transaction(function () use ($type) {
            $prefix = $type === 'purchase' ? 'PBL' : 'INV';
            $date = now()->format('Ymd');

            $lastTransaction = MaterialTransaction::where('type', $type)
                ->whereDate('created_at', today())
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $lastNumber = 0;

            if ($lastTransaction && str_contains($lastTransaction->code, '-')) {
                // Extracts the last 4 digits from something like PBL-WJY/20260411-0001
                $lastNumber = (int) substr($lastTransaction->code, -4);
            }

            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            return "{$prefix}-WJY/{$date}-{$newNumber}";
        });
    }
}
