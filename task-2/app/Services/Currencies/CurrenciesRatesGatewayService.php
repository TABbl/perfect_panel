<?php

namespace App\Services\Currencies;

use App\Exceptions\CurrenciesRatesGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CurrenciesRatesGatewayService
{
    // XXX: The CoinPaprika API accepts up to 3 quotes per request.
    private const FIAT_QUOTES_CHUNK_SIZE = 3;

    public function fetchTickers(): array
    {
        return $this->request('/');
    }

    public function fetchBtcPrices(array $symbols): array
    {
        $prices = [];
        foreach (array_chunk($symbols, self::FIAT_QUOTES_CHUNK_SIZE) as $chunk) {
            $response = $this->request('/btc-bitcoin', ['quotes' => implode(',', $chunk)]);
            foreach ($response['quotes'] ?? [] as $quote => $data) {
                if (isset($data['price'])) {
                    $prices[$quote] = $data['price'];
                }
            }
        }

        return $prices;
    }

    private function request(string $path, array $query = []): array
    {
        try {
            return Http::baseUrl(config('services.coinpaprika.url'))
                ->timeout((int) config('services.coinpaprika.timeout'))
                ->get($path, $query)
                ->throw()
                ->json();
        } catch (Throwable $e) {
            Log::error('CoinPaprika request failed', [
                'path' => $path,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw new CurrenciesRatesGatewayException('Currency rates service is unavailable', previous: $e);
        }
    }
}
