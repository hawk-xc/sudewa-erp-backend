<?php

namespace App\Traits;

use App\Models\BBNBill;
use Exception;
use Illuminate\Support\Facades\Log;

trait BBNBillTrait
{
    /**
     * Generate a unique BBN Bill Code.
     * Format: INV-XXXXX
     *
     * @return string|null
     */
    public function generateBBNBillCode(): ?string
    {
        try {
            $prefix = 'INV-';
            
            $lastBill = BBNBill::orderBy('id', 'desc')
                ->first();

            if (!$lastBill || empty($lastBill->code)) {
                return "{$prefix}00001";
            }

            $lastCode = $lastBill->code;
            // Extract the number part after the prefix
            $lastNumber = (int) str_replace($prefix, '', $lastCode);
            $newNumber = $lastNumber + 1;
            $formattedNumber = str_pad($newNumber, 5, '0', STR_PAD_LEFT);

            return "{$prefix}{$formattedNumber}";
        } catch (Exception $err) {
            Log::error('Error while creating BBN Bill code: ' . $err->getMessage());
            return null;
        }
    }
}
