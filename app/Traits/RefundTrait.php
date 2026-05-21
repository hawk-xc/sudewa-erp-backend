<?php

namespace App\Traits;

use App\Models\UnitTransactionRefund;
use Illuminate\Support\Facades\DB;

trait RefundTrait
{
    public function generateRefundCode(): string
    {
        return DB::transaction(function () {
            $prefix = 'RFDWJM';
            $date = now()->format('Ymd');

            $lastRefund = UnitTransactionRefund::whereDate('created_at', today())
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $lastNumber = 0;

            if ($lastRefund) {
                // Extracts last 4 digits
                $lastNumber = (int) substr($lastRefund->code, -4);
            }

            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            return "{$prefix}{$date}{$newNumber}";
        });
    }
}
