<?php

namespace App\Services\Currencies;

final class CurrencyCommission
{
    private const CALC_SCALE = 20;

    /**
     * Calculate the cross-rate of two currencies with the commission applied.
     */
    public function applyToPair(string $fromRate, string $toRate): string
    {
        $rate = bcdiv($fromRate, $toRate, self::CALC_SCALE);
        return bcdiv($rate, $this->multiplier(), self::CALC_SCALE);
    }

    /**
     * Apply the commission to a list of USD rates.
     */
    public function applyToRates(array $rates): array
    {
        $multiplier = $this->multiplier();
        return array_map(
            fn (string $rate): string => bcmul($rate, $multiplier, self::CALC_SCALE),
            $rates
        );
    }

    private function multiplier(): string
    {
        $commission = (string) config('currencies.commission', 0);
        return bcadd('1', bcdiv($commission, '100', 14), 14);
    }
}
