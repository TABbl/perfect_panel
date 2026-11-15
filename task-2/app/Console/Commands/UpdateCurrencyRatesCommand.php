<?php

namespace App\Console\Commands;

use App\Exceptions\CurrenciesRatesGatewayException;
use App\Services\Currencies\CurrencyRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateCurrencyRatesCommand extends Command
{
    protected $signature = 'currency:update-rates';
    protected $description = 'Fetch new currency rates from CoinPaprika';

    public function handle(CurrencyRateService $service): int
    {
        Log::channel('single')->info('Currency rates update started');

        try {
            $service->fetchNewCurrencyRates();
            Log::channel('single')->info('Currency rates update completed');
            $this->info('Currency rates updated successfully.');
            return self::SUCCESS;
        } catch (CurrenciesRatesGatewayException $e) {
            Log::channel('single')->error('Currency rates service is unavailable', ['exception' => $e]);
            $this->error('Currency rates service is unavailable: ' . $e->getMessage());
            return self::FAILURE;
        } catch (Throwable $e) {
            Log::channel('single')->error('Currency rates update failed', ['exception' => $e]);
            $this->error('Failed to update currency rates: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
