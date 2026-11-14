<?php

namespace App\Repositories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CurrencyRepository
{
    public function getAllCurrency(): Collection
    {
        return Currency::all('symbol');
    }

    public function isEmptyRates(): bool
    {
        return !DB::table('currency_rates')->exists();
    }

    public function upsertCurrencies(array $rows): void
    {
        Currency::upsert($rows, ['coin_id'], ['name', 'symbol', 'type', 'last_updated']);
    }

    public function upsertRates(array $rows): void
    {
        DB::table('currency_rates')->upsert($rows, ['currency_id'], ['rate', 'last_updated']);
    }

    public function getCurrenciesWithRatesBySymbols(array $symbols): Collection
    {
        return Currency::whereIn('symbol', $symbols)
            ->where('is_active', true)
            ->orderBy('type')
            ->with('rate')
            ->get();
    }

    public function getAllCurrenciesWithRates(): Collection
    {
        return Currency::where('is_active', true)
            ->orderBy('id')
            ->with('rate')
            ->get();
    }
}
