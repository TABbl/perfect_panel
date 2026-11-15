<?php

namespace Tests\Feature;

use App\Enums\CurrencyTypeEnum;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Repositories\CurrencyRepository;
use App\Services\Currencies\CurrencyRateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class CurrencyApiTest extends TestCase
{
    private const INVALID_TOKEN_ERROR = [
        'status' => 'error',
        'code' => 403,
        'message' => 'Invalid token',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.api_auth_token' => 'test-token',
            'currencies.commission' => 2,
            'cache.stores.redis' => ['driver' => 'array'],
        ]);
        Cache::store('redis')->forget('currencies:usd-rates:v2');
    }

    public function test_rates_preserve_fractional_usd_values_for_fiat_currencies(): void
    {
        $currencies = new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
            $this->currency('fiat-rub', 'RUB', '0.0123456789', CurrencyTypeEnum::FIAT),
        ]);
        $this->bindRepository($currencies);

        $response = $this->withToken('test-token')
            ->getJson('/api/v1?method=rates&currency=USD,RUB');

        $response
            ->assertOk()
            ->assertJsonPath('data.RUB', '0.012592592478')
            ->assertJsonPath('data.USD', '1.02');

        $this->assertSame(['RUB', 'USD'], array_keys($response->json('data')));
    }

    public function test_convert_applies_commission_and_returns_the_required_fields(): void
    {
        $this->bindRepository(new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
            $this->currency('btc-bitcoin', 'BTC', '18000', CurrencyTypeEnum::CRYPTO),
        ]));

        $response = $this->withToken('test-token')->postJson(
            '/api/v1?method=convert&currency_from=USD&currency_to=BTC&value=0.01'
        );

        $response->assertOk()
            ->assertJsonPath('data.currency_from', 'USD')
            ->assertJsonPath('data.currency_to', 'BTC')
            ->assertJsonPath('data.value', 0.01)
            ->assertJsonPath('data.converted_value', 0.0000005447);

        $this->assertIsFloat($response->json('data.value'));
        $this->assertIsFloat($response->json('data.converted_value'));
        $this->assertIsFloat($response->json('data.rate'));
        $this->assertSame(
            ['currency_from', 'currency_to', 'value', 'converted_value', 'rate'],
            array_keys($response->json('data'))
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('minimumAmounts')]
    public function test_convert_validates_the_minimum_amount(string $literal, bool $query, int $status): void
    {
        $this->bindRepository(new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
            $this->currency('btc-bitcoin', 'BTC', '18000', CurrencyTypeEnum::CRYPTO),
        ]));

        $url = '/api/v1?method=convert&currency_from=USD&currency_to=BTC';
        $this->withToken('test-token');
        $response = $query
            ? $this->postJson($url.'&value='.rawurlencode($literal))
            : $this->call('POST', $url, [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer test-token',
            ], '{"value":'.$literal.'}');

        $response->assertStatus($status);
    }

    public static function minimumAmounts(): array
    {
        return [
            ['0.009999999999999', false, 403],
            ['0.01', false, 200],
        ];
    }

    public function test_convert_to_fiat_rounds_to_cents(): void
    {
        $this->bindRepository(new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
            $this->currency('btc-bitcoin', 'BTC', '18000', CurrencyTypeEnum::CRYPTO),
        ]));

        $this->withToken('test-token')->postJson('/api/v1?method=convert', [
            'currency_from' => 'BTC',
            'currency_to' => 'USD',
            'value' => '1.00',
        ])->assertOk()
            ->assertJsonPath('data.converted_value', 17647.06);
    }

    public function test_convert_uses_the_database_when_redis_is_unavailable(): void
    {
        $this->bindRepository(new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
            $this->currency('btc-bitcoin', 'BTC', '18000', CurrencyTypeEnum::CRYPTO),
        ]));
        Cache::shouldReceive('store')
            ->with('redis')
            ->andThrow(new \RuntimeException('Redis unavailable'));

        $this->withToken('test-token')->postJson('/api/v1?method=convert', [
            'currency_from' => 'USD',
            'currency_to' => 'BTC',
            'value' => '0.01',
        ])->assertOk()
            ->assertJsonPath('data.converted_value', 0.0000005447);
    }

    public function test_rates_uses_the_database_when_redis_is_unavailable(): void
    {
        $this->bindRepository(new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
            $this->currency('fiat-rub', 'RUB', '0.01', CurrencyTypeEnum::FIAT),
        ]));
        Cache::shouldReceive('store')
            ->with('redis')
            ->andThrow(new \RuntimeException('Redis unavailable'));

        $this->withToken('test-token')
            ->getJson('/api/v1?method=rates&currency=USD,RUB')
            ->assertOk()
            ->assertJsonPath('data.RUB', '0.0102')
            ->assertJsonPath('data.USD', '1.02');
    }

    public function test_convert_rejects_a_currency_with_a_zero_rate_during_validation(): void
    {
        $this->bindRepository(new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
            $this->currency('btc-bitcoin', 'BTC', '0', CurrencyTypeEnum::CRYPTO),
        ]));

        $this->withToken('test-token')->postJson('/api/v1?method=convert', [
            'currency_from' => 'USD',
            'currency_to' => 'BTC',
            'value' => '1.00',
        ])->assertStatus(403)
            ->assertExactJson(self::INVALID_TOKEN_ERROR);
    }

    public function test_convert_rejects_a_missing_currency_during_validation(): void
    {
        $this->bindRepository(new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
        ]));

        $this->withToken('test-token')->postJson('/api/v1?method=convert', [
            'currency_from' => 'USD',
            'currency_to' => 'BTC',
            'value' => '1.00',
        ])->assertStatus(403)
            ->assertExactJson(self::INVALID_TOKEN_ERROR);
    }

    public function test_each_method_rejects_other_http_verbs(): void
    {
        $this->withToken('test-token')
            ->getJson('/api/v1?method=convert')
            ->assertStatus(403)
            ->assertExactJson(self::INVALID_TOKEN_ERROR);

        $this->withToken('test-token')
            ->postJson('/api/v1?method=rates')
            ->assertStatus(403)
            ->assertExactJson(self::INVALID_TOKEN_ERROR);
    }

    public function test_missing_or_invalid_token_returns_the_exact_contract_error(): void
    {
        $expected = [
            'status' => 'error',
            'code' => 403,
            'message' => 'Invalid token',
        ];

        $this->getJson('/api/v1?method=rates')
            ->assertStatus(403)
            ->assertExactJson($expected);

        $this->withToken('wrong-token')
            ->postJson('/api/v1?method=convert')
            ->assertStatus(403)
            ->assertExactJson($expected);
    }

    public function test_rates_rejects_a_missing_currency(): void
    {
        $this->bindRepository(new Collection([
            $this->currency('fiat-usd', 'USD', '1', CurrencyTypeEnum::FIAT),
        ]));

        $this->withToken('test-token')
            ->getJson('/api/v1?method=rates&currency=USD,FAKE')
            ->assertStatus(403)
            ->assertExactJson(self::INVALID_TOKEN_ERROR);
    }

    public function test_validation_error_uses_the_same_three_field_format(): void
    {
        $this->withToken('test-token')
            ->postJson('/api/v1?method=convert')
            ->assertStatus(403)
            ->assertExactJson(self::INVALID_TOKEN_ERROR);
    }

    public function test_unexpected_api_error_uses_the_same_three_field_format(): void
    {
        $service = Mockery::mock(CurrencyRateService::class);
        $service->shouldReceive('getRates')->andThrow(new \RuntimeException('Database unavailable'));
        $this->app->instance(CurrencyRateService::class, $service);

        $this->withToken('test-token')
            ->getJson('/api/v1?method=rates')
            ->assertStatus(403)
            ->assertExactJson(self::INVALID_TOKEN_ERROR);
    }

    private function bindRepository(Collection $currencies): void
    {
        $repository = Mockery::mock(CurrencyRepository::class);
        $repository->shouldReceive('getAllCurrenciesWithRates')->once()->andReturn($currencies);
        $repository->shouldReceive('getCurrenciesWithRatesBySymbols')->never();
        $this->app->instance(CurrencyRepository::class, $repository);
    }

    private function currency(string $coinId, string $symbol, string $rate, CurrencyTypeEnum $type): Currency
    {
        $currency = new Currency([
            'coin_id' => $coinId,
            'symbol' => $symbol,
            'name' => $symbol,
            'type' => $type->value,
        ]);
        $currency->setRelation('rate', new CurrencyRate(['rate' => $rate]));

        return $currency;
    }
}
