<?php

namespace App\Traits;

use App\Models\VehicleEquipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait VehicleEquipmentTrait
{
    /**
     * Generate unique code for vehicle equipment.
     * Format: PLK/{YYYYMMDD}-000{i}
     */
    public function generateEquipmentCode(): string
    {
        return DB::transaction(function () {
            $today = Carbon::now()->format('Ymd');
            $prefix = "PLK/{$today}-";

            // Find last equipment created today with code matching prefix
            $lastEquipment = VehicleEquipment::where('code', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $lastNumber = 0;

            if ($lastEquipment) {
                // Extract sequence number from e.g. PLK/20260525-0001
                $parts = explode('-', $lastEquipment->code);
                $lastNumber = (int) end($parts);
            }

            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            return $prefix . $newNumber;
        });
    }
}
