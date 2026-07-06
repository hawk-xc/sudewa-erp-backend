<?php

namespace App\Traits;

trait CalculateDecimalAmount
{
    public function calculateDecimalAmount(int|float|string $amount): int
    {
        [$wholeAmount, $decimalAmount, $isNegative] = $this->splitDecimalAmount($amount);

        if ($decimalAmount < 50) {
            return $isNegative ? -$wholeAmount : $wholeAmount;
        }

        $roundedAmount = $wholeAmount + 1;

        return $isNegative ? -$roundedAmount : $roundedAmount;
    }

    private function splitDecimalAmount(int|float|string $amount): array
    {
        $normalizedAmount = trim((string) $amount);
        $isNegative = str_starts_with($normalizedAmount, '-');
        $normalizedAmount = ltrim($normalizedAmount, '+-');

        $decimalSeparator = $this->detectDecimalSeparator($normalizedAmount);

        if ($decimalSeparator === null) {
            return [(int) preg_replace('/\D/', '', $normalizedAmount), 0, $isNegative];
        }

        $separatorPosition = strrpos($normalizedAmount, $decimalSeparator);
        $wholeAmount = substr($normalizedAmount, 0, $separatorPosition);
        $decimalAmount = substr($normalizedAmount, $separatorPosition + 1);

        $wholeAmount = (int) preg_replace('/\D/', '', $wholeAmount);
        $decimalAmount = (int) str_pad(substr(preg_replace('/\D/', '', $decimalAmount), 0, 2), 2, '0');

        return [$wholeAmount, $decimalAmount, $isNegative];
    }

    private function detectDecimalSeparator(string $amount): ?string
    {
        $lastCommaPosition = strrpos($amount, ',');
        $lastDotPosition = strrpos($amount, '.');

        if ($lastCommaPosition !== false && $lastDotPosition !== false) {
            return $lastCommaPosition > $lastDotPosition ? ',' : '.';
        }

        if ($lastCommaPosition !== false) {
            return strlen($amount) - $lastCommaPosition - 1 <= 2 ? ',' : null;
        }

        if ($lastDotPosition !== false) {
            return strlen($amount) - $lastDotPosition - 1 <= 2 ? '.' : null;
        }

        return null;
    }
}
