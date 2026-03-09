<?php

namespace App\Traits;

use App\Models\UnitTransaction;
use Illuminate\Support\Facades\DB;

trait TransactionTrait
{
    public function generateCode(string $type): ?string
    {
        if (! in_array($type, ['purchase', 'sales'], true)) {
            return null;
        }

        return DB::transaction(function () use ($type) {

            $prefix = $type === 'purchase' ? 'PBL' : 'INV';
            $date = now()->format('Ymd');

            $lastTransaction = UnitTransaction::where('type', $type)
                ->whereDate('created_at', today())
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $lastNumber = 0;

            if ($lastTransaction) {
                $lastNumber = (int) substr($lastTransaction->code, -4);
            }

            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            return "{$prefix}-WJM/{$date}-{$newNumber}";
        });
    }
}
