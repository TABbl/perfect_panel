<?php

if (!function_exists('trimDecimal')) {
    function trimDecimal(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}

if (!function_exists('bcRoundHalfUp')) {
    function bcRoundHalfUp(string $value, int $scale, int $calculationScale = 20): string
    {
        $factor = bcpow('10', (string) $scale, 0);
        $shifted = bcmul($value, $factor, $calculationScale);
        $rounded = bcadd($shifted, '0.5', 0);

        return bcdiv($rounded, $factor, $scale);
    }
}
