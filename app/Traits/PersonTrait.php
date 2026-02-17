<?php

namespace App\Traits;

use Exception;
use App\Models\Person;
use Illuminate\Support\Facades\Log;

trait PersonTrait
{
    public function generateCode(string $type): ?string
    {
        if (!in_array($type, ['customer', 'supplier'], true)) {
            return null;
        }

        try {
            $prefix = match ($type) {
                'customer' => 'CST',
                'supplier' => 'SPL',
            };

            $lastPerson = Person::where('type', $type)->whereNotNull('code')->orderByDesc('id')->first();

            if (!$lastPerson) {
                return $prefix . '-001';
            }

            $lastNumber = (int) substr($lastPerson->code, -3);

            $newNumber = $lastNumber + 1;

            $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            return $prefix . '-' . $formattedNumber;
        } catch (Exception $err) {
            Log::error('Error while creating person code: ' . $err->getMessage());
            return null;
        }
    }
}
