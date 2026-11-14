<?php

namespace App\Services\Currencies;

use App\Models\Currency;
use App\Repositories\CurrencyRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CurrencyRateCache
{
    private const KEY = 'currencies:usd-rates';
    private const CALC_SCALE = 20;
    private const TTL_SECONDS = 700;

    private ?array $currentSnapshot = null;

    public function __construct(private readonly CurrencyRepository $currencyRepository)
    {
    }

    /**
     * Return symbols of active currencies that have a rate.
     * Populate Redis from the database if it has not been warmed yet.
     *
     * @return array<int, string>
     */
    public function symbols(): array
    {
        return $this->snapshot()['symbols'];
    }

    /**
     * Return USD rates with commission, sorted by rate.
     *
     * @return array<string, string>
     */
    public function rates(?array $symbols = null): array
    {
        $rates = $this->snapshot()['rates'];
        return $symbols === null || $symbols === []
            ? $rates
            : array_intersect_key($rates, array_fill_keys($symbols, true));
    }

    /**
     * Return original USD rates for conversion. Fiat takes precedence
     * when different currencies share the same symbol.
     */
    public function conversionCurrencies(array $symbols): array
    {
        $wanted = array_fill_keys($symbols, true);
        $selected = [];
        foreach ($this->snapshot()['currencies'] as $currency) {
            $symbol = $currency['symbol'];
            if (!isset($wanted[$symbol])) {
                continue;
            }
            if (!isset($selected[$symbol]) || $currency['type'] < $selected[$symbol]['type']) {
                $selected[$symbol] = $currency;
            }
        }
        return $selected;
    }

    /**
     * Replace the cached snapshot after the database transaction commits.
     */
    public function refresh(): void
    {
        $snapshot = $this->buildSnapshot();
        $this->currentSnapshot = $snapshot;
        try {
            Cache::store('redis')->put(self::KEY, $snapshot, self::TTL_SECONDS);
        } catch (Throwable $e) {
            Log::warning('Currency cache refresh failed', ['error' => $e->getMessage()]);
        }
    }

    private function snapshot(): array
    {
        if ($this->currentSnapshot !== null) {
            return $this->currentSnapshot;
        }
        try {
            $snapshot = Cache::store('redis')->get(self::KEY);
        } catch (Throwable $e) {
            Log::warning('Currency cache read failed', ['error' => $e->getMessage()]);
            return $this->currentSnapshot = $this->buildSnapshot();
        }
        if (is_array($snapshot) && isset($snapshot['currencies'], $snapshot['symbols'], $snapshot['rates'])) {
            return $this->currentSnapshot = $snapshot;
        }
        $snapshot = $this->buildSnapshot();
        $this->currentSnapshot = $snapshot;
        try {
            Cache::store('redis')->put(self::KEY, $snapshot, self::TTL_SECONDS);
        } catch (Throwable $e) {
            Log::warning('Currency cache fill failed', ['error' => $e->getMessage()]);
        }
        return $snapshot;
    }

    private function buildSnapshot(): array
    {
        $currencies = $this->currencyRepository->getAllCurrenciesWithRates()
            ->filter(fn (Currency $currency): bool => $currency->rate !== null)
            ->map(fn (Currency $currency): array => [
                'coin_id' => $currency->coin_id,
                'symbol' => $currency->symbol,
                'name' => $currency->name,
                'type' => $currency->type->value,
                'rate_usd' => (string) $currency->rate->rate,
                'last_updated' => $currency->rate->last_updated,
            ])
            ->values()
            ->all();
        $commission = (string) config('currencies.commission', 0);
        $multiplier = bcadd('1', bcdiv($commission, '100', 14), 14);
        $rates = [];
        foreach ($currencies as $currency) {
            $rate = bcmul($currency['rate_usd'], $multiplier, self::CALC_SCALE);
            $rate = rtrim(rtrim($rate, '0'), '.');
            if (!isset($rates[$currency['symbol']]) || bccomp($rate, $rates[$currency['symbol']], self::CALC_SCALE) > 0) {
                $rates[$currency['symbol']] = $rate;
            }
        }
        uasort($rates, fn (string $a, string $b): int => bccomp($a, $b, self::CALC_SCALE));
        return [
            'currencies' => $currencies,
            'symbols' => array_values(array_unique(array_column($currencies, 'symbol'))),
            'rates' => $rates,
        ];
    }
}
