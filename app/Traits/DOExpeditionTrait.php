<?php

namespace App\Traits;

use App\Models\DOExpedition;
use Exception;
use Illuminate\Support\Facades\Log;

trait DOExpeditionTrait
{
    /**
     * Generate a unique DO Code for expedition.
     * Format: DOE-WJT/YYYYMMDD-XXXX
     *
     * @return string|null
     */
    public function generateDOCode(): ?string
    {
        try {
            $prefix = 'DOE-WJT';
            $datePart = date('Ymd');
            
            $lastDO = DOExpedition::where('do_code', 'like', "$prefix/$datePart-%")
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastDO) {
                return "$prefix/$datePart-0001";
            }

            $lastCode = $lastDO->do_code;
            $lastNumber = (int) substr($lastCode, -4);
            $newNumber = $lastNumber + 1;
            $formattedNumber = str_pad($newNumber, 4, '0', STR_PAD_LEFT);

            return "$prefix/$datePart-$formattedNumber";
        } catch (Exception $err) {
            Log::error('Error while creating DO Expedition code: ' . $err->getMessage());
            return null;
        }
    }
}
