<?php
 
namespace App\Traits;
 
trait CalculateDecimalAmount
{
    public function calculateDecimalAmount(int|float|string $amount): int
    {
        $cleaned = trim((string) $amount);
        
        if (!is_numeric($cleaned)) {
            // Remove thousands separators and normalize decimal separator to '.'
            $lastComma = strrpos($cleaned, ',');
            $lastDot = strrpos($cleaned, '.');
            
            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    // comma is decimal, dots are thousands
                    $cleaned = str_replace('.', '', $cleaned);
                    $cleaned = str_replace(',', '.', $cleaned);
                } else {
                    // dot is decimal, commas are thousands
                    $cleaned = str_replace(',', '', $cleaned);
                }
            } elseif ($lastComma !== false) {
                // Only commas exist. If followed by exactly 3 digits, it's a thousands separator
                $digitsAfter = strlen($cleaned) - $lastComma - 1;
                if ($digitsAfter === 3) {
                    $cleaned = str_replace(',', '', $cleaned);
                } else {
                    $cleaned = str_replace(',', '.', $cleaned);
                }
            } elseif ($lastDot !== false) {
                // Only dots exist. If followed by exactly 3 digits, it's a thousands separator
                $digitsAfter = strlen($cleaned) - $lastDot - 1;
                if ($digitsAfter === 3) {
                    $cleaned = str_replace('.', '', $cleaned);
                }
            }
        }
        
        if (is_numeric($cleaned)) {
            return (int) round((float) $cleaned);
        }
        
        // Fallback: strip everything except digits, minus sign, and decimal point
        $isNegative = str_starts_with($cleaned, '-');
        $cleaned = preg_replace('/[^0-9.]/', '', $cleaned);
        if ($isNegative) {
            $cleaned = '-' . $cleaned;
        }
        
        return is_numeric($cleaned) ? (int) round((float) $cleaned) : 0;
    }
}
