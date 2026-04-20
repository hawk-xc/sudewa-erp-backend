<?php

namespace App\Traits;

use App\Models\VehicleRegistration;
use Illuminate\Support\Facades\DB;

trait VehicleTrait
{
    /**
     * Generate registration code with format TRM-YYMMDDXXX
     */
    public function generateRegistrationCode(): string
    {
        return DB::transaction(function () {
            $date = now()->format('ymd');
            $prefix = "TRM-{$date}";

            $last = VehicleRegistration::where('code', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $lastNumber = 0;
            if ($last) {
                // Get the last 3 digits
                $lastNumber = (int) substr($last->code, -3);
            }

            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

            return "{$prefix}{$newNumber}";
        });
    }
}
