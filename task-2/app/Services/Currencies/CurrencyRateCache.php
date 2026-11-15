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

    public function __construct(
        private readonly CurrencyRepository $currencyRepository,
        private readonly CurrencyCommission $currencyCommission
    ) {
    }

    /**
     * Return symbols of active currencies that have a rate.
     * Populate Redis from the database if it has not been warmed yet.
     *
     * @return array<int, string>
     */
    public function symbols(): array
    {
        return $this->getSnapshot()['symbols'];
    }

    /**
     * Return USD rates with commission, sorted by rate.
     *
     * @return array<string, string>
     */
    public function getRates(?array $symbols = null): array
    {
        $wanted = $symbols === null || $symbols === []
            ? null
            : array_fill_keys($symbols, true);
        $rates = [];
        foreach ($this->getSnapshot()['currencies_with_commission'] as $currency) {
            if ($wanted !== null && !isset($wanted[$currency['symbol']])) {
                continue;
            }
            $rates[$currency['symbol']] = $currency['rate'];
        }
        uasort($rates, fn (string $a, string $b): int => bccomp($a, $b, self::CALC_SCALE));
        return $rates;
    }

    /**
     * Return original USD rates for conversion. Fiat takes precedence
     * when different currencies share the same symbol.
     */
    public function conversionCurrencies(array $symbols): array
    {
        $wanted = array_fill_keys($symbols, true);
        $selected = [];
        foreach ($this->getSnapshot()['currencies'] as $currency) {
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
        $this->currentSnapshot = $this->buildSnapshot();
        $this->storeSnapshot($this->currentSnapshot);
    }

    private function getSnapshot(): array
    {
        return $this->currentSnapshot ??= $this->loadSnapshot();
    }

    private function loadSnapshot(): array
    {
        $snapshot = $this->readFromCache();
        if ($snapshot !== null) {
            return $snapshot;
        }
        $snapshot = $this->buildSnapshot();
        $this->storeSnapshot($snapshot);
        return $snapshot;
    }

    private function readFromCache(): ?array
    {
        try {
            $snapshot = Cache::store('redis')->get(self::KEY);
        } catch (Throwable $e) {
            Log::warning('Currency cache read failed', ['error' => $e->getMessage()]);
            return null;
        }
        return $this->isSnapshot($snapshot) ? $snapshot : null;
    }

    private function storeSnapshot(array $snapshot): void
    {
        try {
            Cache::store('redis')->put(self::KEY, $snapshot, self::TTL_SECONDS);
        } catch (Throwable $e) {
            Log::warning('Currency cache write failed', ['error' => $e->getMessage()]);
        }
    }

    private function isSnapshot(mixed $snapshot): bool
    {
        return is_array($snapshot)
            && isset($snapshot['currencies'], $snapshot['symbols'], $snapshot['currencies_with_commission']);
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
        return [
            'currencies' => $currencies,
            'symbols' => array_values(array_unique(array_column($currencies, 'symbol'))),
            'currencies_with_commission' => $this->buildCurrenciesWithCommission($currencies),
        ];
    }

    private function buildCurrenciesWithCommission(array $currencies): array
    {
        $commissionedRates = $this->currencyCommission->applyToRates(
            array_column($currencies, 'rate_usd')
        );
        $result = [];
        foreach ($currencies as $index => $currency) {
            $result[$currency['coin_id']] = [
                ...$currency,
                'rate' => trimDecimal($commissionedRates[$index]),
            ];
        }
        return $result;
    }
}
