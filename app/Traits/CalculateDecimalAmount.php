<?php

namespace App\Traits;

trait CalculateDecimalAmount
{
    /**
     * Membulatkan angka berdasarkan 2 digit desimal.
     *
     * Contoh:
     * 10.000,56 => 10.001
     * 10.000,50 => 10.000
     * 10.000,44 => 10.000
     */
    private function roundAmount(float $amount): int
    {
        $absolute = abs($amount);

        $integer = floor($absolute);
        $decimal = (int) round(($absolute - $integer) * 100);

        $result = $decimal > 50
            ? ceil($absolute)
            : floor($absolute);

        return $amount < 0
            ? -(int) $result
            : (int) $result;
    }

    public function calculateDecimalAmount(int|float|string $amount): int
    {
        $cleaned = trim((string) $amount);

        if (!is_numeric($cleaned)) {
            $lastComma = strrpos($cleaned, ',');
            $lastDot = strrpos($cleaned, '.');

            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    // Format Indonesia: 10.000,56
                    $cleaned = str_replace('.', '', $cleaned);
                    $cleaned = str_replace(',', '.', $cleaned);
                } else {
                    // Format Internasional: 10,000.56
                    $cleaned = str_replace(',', '', $cleaned);
                }
            } elseif ($lastComma !== false) {
                $digitsAfter = strlen($cleaned) - $lastComma - 1;

                if ($digitsAfter === 3) {
                    // 10,000
                    $cleaned = str_replace(',', '', $cleaned);
                } else {
                    // 10000,56
                    $cleaned = str_replace(',', '.', $cleaned);
                }
            } elseif ($lastDot !== false) {
                $digitsAfter = strlen($cleaned) - $lastDot - 1;

                if ($digitsAfter === 3) {
                    // 10.000
                    $cleaned = str_replace('.', '', $cleaned);
                }
            }
        }

        if (is_numeric($cleaned)) {
            return $this->roundAmount((float) $cleaned);
        }

        $isNegative = str_starts_with($cleaned, '-');

        $cleaned = preg_replace('/[^0-9.]/', '', $cleaned);

        if ($isNegative) {
            $cleaned = '-' . $cleaned;
        }

        return is_numeric($cleaned)
            ? $this->roundAmount((float) $cleaned)
            : 0;
    }
}