<?php

namespace App\Rules;

use App\Services\Currencies\CurrencyRateCache;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CurrencyWithRateExists implements ValidationRule
{
    private const RATE_SCALE = 20;

    public function __construct(private readonly CurrencyRateCache $currencyRateCache)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('Currency not found');
            return;
        }

        $currencies = $this->currencyRateCache->conversionCurrencies([$value]);
        if (!isset($currencies[$value])) {
            $fail('Currency not found');
            return;
        }

        if (bccomp($currencies[$value]['rate_usd'], '0', self::RATE_SCALE) <= 0) {
            $fail('Currency rate must be positive');
        }
    }
}
