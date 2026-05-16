<?php

namespace App\Traits;

use App\Models\DOExpedition;
use App\Models\DOInvoice;
use App\Models\DOOrderList;
use Exception;
use Illuminate\Support\Facades\Log;

trait DOTrait
{
    /**
     * Generate a unique DO Code for different modules.
     * Format: [Prefix]/YYYYMMDD-XXXX
     *
     * @param string $type The module type (order_list, invoice, expedition)
     * @return string|null
     */
    public function generateDOCode(string $type = 'expedition'): ?string
    {
        try {
            $prefix = match ($type) {
                'order_list' => 'ORDWJT',
                'invoice'    => 'INVWJT',
                'expedition' => 'DOEWJT',
                default      => 'DOEWJT'
            };

            $datePart = date('Ymd');
            $searchPrefix = "$prefix/$datePart-";

            // Determine model and column based on type
            $model = match ($type) {
                'order_list' => DOOrderList::class,
                'invoice'    => DOInvoice::class,
                default      => DOExpedition::class,
            };

            $column = 'code';
            
            $lastRecord = $model::where($column, 'like', "$searchPrefix%")
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastRecord) {
                return "$searchPrefix" . "0001";
            }

            $lastCode = $lastRecord->{$column};
            $lastNumber = (int) substr($lastCode, -4);
            $newNumber = $lastNumber + 1;
            $formattedNumber = str_pad($newNumber, 4, '0', STR_PAD_LEFT);

            return "$searchPrefix$formattedNumber";
        } catch (Exception $err) {
            Log::error("Error while creating $type code: " . $err->getMessage());
            return null;
        }
    }
}
