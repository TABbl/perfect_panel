<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyCommandApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.api_auth_token' => str_repeat('A', 64),
            'services.coinpaprika.url' => 'https://api.coinpaprika.com/v1/tickers',
            'currencies.commission' => 2,
            'currencies.fiat' => [
                ['symbol' => 'USD', 'name' => 'US Dollar'],
                ['symbol' => 'RUB', 'name' => 'Russian Ruble'],
            ],
            'cache.stores.redis' => ['driver' => 'array'],
        ]);
        Http::fake([
            'https://api.coinpaprika.com/v1/tickers/btc-bitcoin*' => Http::response([
                'quotes' => [
                    'USD' => ['price' => 18000],
                    'RUB' => ['price' => 1800000],
                ],
            ]),
            'https://api.coinpaprika.com/v1/tickers/' => Http::response([
                [
                    'id' => 'btc-bitcoin',
                    'name' => 'Bitcoin',
                    'symbol' => 'BTC',
                    'last_updated' => '2026-11-15T00:00:00Z',
                    'quotes' => ['USD' => ['price' => 18000]],
                ],
            ]),
        ]);
        Http::preventStrayRequests();
        $this->artisan('currency:update-rates')->assertExitCode(0);
    }

    public function test_rates_returns_commissioned_sorted_rates_and_exact_auth_error(): void
    {
        $token = str_repeat('A', 64);
        $response = $this->withToken($token)
            ->getJson('/api/v1?method=rates&currency=BTC,USD,RUB');
        $response->assertOk()->assertExactJson([
            'status' => 'success',
            'code' => 200,
            'data' => [
                'RUB' => '0.0102',
                'USD' => '1.02',
                'BTC' => '18360',
            ],
        ]);
        $this->assertSame(['RUB', 'USD', 'BTC'], array_keys($response->json('data')));

        $this->withoutToken()->getJson('/api/v1?method=rates')
            ->assertStatus(403)
            ->assertExactJson([
                'status' => 'error',
                'code' => 403,
                'message' => 'Invalid token',
            ]);
    }

    public function test_convert_recalculates_both_directions_and_returns_exact_auth_error(): void
    {
        $token = str_repeat('A', 64);
        $forward = $this->withToken($token)
            ->postJson('/api/v1?method=convert', [
                'currency_from' => 'USD',
                'currency_to' => 'BTC',
                'value' => '0.01',
            ])
            ->assertOk()
            ->assertJsonPath('data.currency_from', 'USD')
            ->assertJsonPath('data.currency_to', 'BTC')
            ->assertJsonPath('data.value', 0.01)
            ->assertJsonPath('data.converted_value', 0.0000005447);
        $this->assertEqualsWithDelta(1 / 18360, $forward->json('data.rate'), 1e-15);
        $this->assertSame(
            ['currency_from', 'currency_to', 'value', 'converted_value', 'rate'],
            array_keys($forward->json('data'))
        );
        $reverse = $this->withToken($token)
            ->postJson('/api/v1?method=convert', [
                'currency_from' => 'BTC',
                'currency_to' => 'USD',
                'value' => '1.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.converted_value', 17647.06);
        $this->assertEqualsWithDelta(18000 / 1.02, $reverse->json('data.rate'), 1e-8);
        $this->withToken('invalid-token')
            ->postJson('/api/v1?method=convert')
            ->assertStatus(403)
            ->assertExactJson([
                'status' => 'error',
                'code' => 403,
                'message' => 'Invalid token',
            ]);
    }
}
