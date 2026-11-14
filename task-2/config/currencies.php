<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fiat currencies
    |--------------------------------------------------------------------------
    |
    | List of fiat currencies that are stored alongside crypto currencies.
    | Rates are calculated in USD via BTC cross rates
    | (https://api.coinpaprika.com/v1/tickers/btc-bitcoin?quotes=...).
    | Note: the API accepts up to 3 quotes per request.
    |
    */

    'fiat' => [
        ['symbol' => 'USD', 'name' => 'US Dollar'],
        ['symbol' => 'EUR', 'name' => 'Euro'],
        ['symbol' => 'RUB', 'name' => 'Russian Ruble'],
        ['symbol' => 'GBP', 'name' => 'British Pound'],
        ['symbol' => 'JPY', 'name' => 'Japanese Yen'],
        ['symbol' => 'CNY', 'name' => 'Chinese Yuan'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange commission
    |--------------------------------------------------------------------------
    |
    | Percentage applied on top of the stored USD rate.
    |
    */

    'commission' => 2,
];
