<?php

namespace App\Console;

use App\Repositories\CurrencyRepository;
use App\Services\Currencies\CurrencyRateService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use Throwable;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('currency:update-rates')->everyTenMinutes();
    }

    /**
     * Bootstrap the application for artisan commands.
     */
    public function bootstrap(): void
    {
        parent::bootstrap();
        $this->fetchRatesIfFirstRun();
    }

    /**
     * Fetch rates at the first application run.
     */
    protected function fetchRatesIfFirstRun(): void
    {
        try {
            $repository = app(CurrencyRepository::class);
            if ($repository->isEmptyRates()) {
                app(CurrencyRateService::class)->fetchNewCurrencyRates();
                Log::info('Currency rates fetched at first application run.');
            }
        } catch (Throwable $e) {
            Log::warning('First run currency rates fetch failed: '.$e->getMessage());
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
