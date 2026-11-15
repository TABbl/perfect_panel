<?php

namespace App\Services\Currencies;

use App\Enums\CurrencyTypeEnum;
use App\Models\Currency;
use App\Repositories\CurrencyRepository;
use App\Services\Currencies\DTO\ConvertDTO;
use App\Services\Currencies\DTO\ConvertEntity;
use App\Services\Currencies\DTO\GetRatesDTO;
use App\Services\Currencies\DTO\GetRatesEntity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CurrencyRateService
{
    // XXX: Internal calculation scale for bcmath operations.
    private const CALC_SCALE = 20;

    // XXX: Result scale when converting to a fiat currency.
    private const FIAT_RESULT_SCALE = 2;

    // XXX: Result scale when converting to a crypto currency.
    private const CRYPTO_RESULT_SCALE = 10;

    public function __construct(
        private CurrencyRepository $currencyRepository,
        private CurrencyRateCache $currencyRateCache,
        private CurrencyCommission $currencyCommission,
        private CurrenciesRatesGatewayService $currenciesRatesGateway
    ) {}

    /**
     * Convert an amount from one currency to another through USD.
     * All calculations are made with bcmath. The result is rounded
     * to 0.01 for fiat currencies and to 10 decimals for crypto ones.
     */
    public function convert(ConvertEntity $entity): ConvertDTO
    {
        $currencies = $this->currencyRateCache->conversionCurrencies([
            $entity->getCurrencyFrom(),
            $entity->getCurrencyTo(),
        ]);
        $fromCurrency = $currencies[$entity->getCurrencyFrom()];
        $toCurrency = $currencies[$entity->getCurrencyTo()];
        $rate = $this->currencyCommission->applyToPair($fromCurrency['rate_usd'], $toCurrency['rate_usd']);
        $result = bcmul($entity->getValue(), $rate, self::CALC_SCALE);

        return new ConvertDTO(
            $this->roundResult($result, CurrencyTypeEnum::from($toCurrency['type'])),
            trimDecimal($rate)
        );
    }

    /**
     * Get all currency rates (or only the given symbols) in USD
     * with the configured commission applied, sorted ascending by rate.
     */
    public function getRates(GetRatesEntity $entity): GetRatesDTO
    {
        return new GetRatesDTO($this->currencyRateCache->getRates($entity->getSymbols()));
    }

    /**
     * Round a conversion result: to 0.01 for fiat currencies
     * and to 10 decimals for crypto ones.
     */
    private function roundResult(string $value, CurrencyTypeEnum $type): string
    {
        if ($type === CurrencyTypeEnum::FIAT) {
            return bcRoundHalfUp($value, self::FIAT_RESULT_SCALE, self::CALC_SCALE);
        }

        return trimDecimal(bcRoundHalfUp($value, self::CRYPTO_RESULT_SCALE, self::CALC_SCALE));
    }

    /**
     * Fetch crypto tickers and fiat rates from CoinPaprika,
     * filter only needed fields and upsert currencies / currency_rates
     * (rate is calculated in USD).
     */
    public function fetchNewCurrencyRates(): void
    {
        $response = $this->currenciesRatesGateway->fetchTickers();

        [$cryptoCurrencies, $cryptoRates] = $this->buildCryptoRows($response);
        [$fiatCurrencies, $fiatRates] = $this->buildFiatRows();
        $currencies = [...$cryptoCurrencies, ...$fiatCurrencies];
        $rates = [...$cryptoRates, ...$fiatRates];
        DB::transaction(function () use ($currencies, $rates) {
            $this->currencyRepository->upsertCurrencies($currencies);
            $currencyIds = Currency::whereIn('coin_id', collect($rates)->pluck('coin_id'))
                ->pluck('id', 'coin_id');
            $rateRows = collect($rates)->map(fn (array $row) => [
                'currency_id' => $currencyIds[$row['coin_id']],
                'rate' => $row['rate'],
                'last_updated' => $row['last_updated'],
                'updated_at' => now(),
            ])->all();
            $this->currencyRepository->upsertRates($rateRows);
        });
        $this->currencyRateCache->refresh();
    }

    private function buildCryptoRows(array $response): array
    {
        $now = now();
        $currencies = [];
        $rates = [];
        $coinIds = [];
        foreach ($response as $item) {
            $coinId = $item['id'] ?? null;
            $price = $item['quotes']['USD']['price'] ?? null;
            if ($coinId === null || $price === null || in_array($coinId, $coinIds, true)) {
                continue;
            }
            $coinIds[] = $coinId;
            $currencies[] = [
                'coin_id' => $coinId,
                'name' => $item['name'],
                'symbol' => $item['symbol'],
                'type' => CurrencyTypeEnum::CRYPTO->value,
                'last_updated' => $item['last_updated'],
                'updated_at' => $now,
            ];
            $rates[] = [
                'coin_id' => $coinId,
                'rate' => $price,
                'last_updated' => $item['last_updated'],
            ];
        }

        return [$currencies, $rates];
    }

    /**
     * Build fiat currency rows with rates in USD,
     * recalculated through the BTC price in the given fiat currencies.
     */
    private function buildFiatRows(): array
    {
        $fiat = collect(config('currencies.fiat', []));
        if ($fiat->isEmpty()) {
            return [[], []];
        }
        $btcPrices = $this->currenciesRatesGateway->fetchBtcPrices($fiat->pluck('symbol')->all());
        $btcUsd = $btcPrices['USD'] ?? null;
        if ($btcUsd === null || $btcUsd <= 0) {
            Log::warning('CoinPaprika: BTC USD price is missing, fiat rates skipped', [
                'quotes' => array_keys($btcPrices),
            ]);

            return [[], []];
        }
        $lastUpdated = now();
        $currencies = [];
        $rates = [];
        foreach ($fiat as $fiatCurrency) {
            $symbol = $fiatCurrency['symbol'];
            $btcPrice = $btcPrices[$symbol] ?? null;
            if ($btcPrice === null || $btcPrice <= 0) {
                Log::warning('CoinPaprika: fiat quote is missing, skipped', ['symbol' => $symbol]);

                continue;
            }
            $currencies[] = [
                'coin_id' => 'fiat-'.strtolower($symbol),
                'name' => $fiatCurrency['name'],
                'symbol' => $symbol,
                'type' => CurrencyTypeEnum::FIAT->value,
                'last_updated' => $lastUpdated,
                'updated_at' => $lastUpdated,
            ];
            $rates[] = [
                'coin_id' => 'fiat-'.strtolower($symbol),
                'rate' => bcdiv((string) $btcUsd, (string) $btcPrice, 14),
                'last_updated' => $lastUpdated,
            ];
        }

        return [$currencies, $rates];
    }
}
