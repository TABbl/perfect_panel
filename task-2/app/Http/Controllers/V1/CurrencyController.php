<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\ConvertRequest;
use App\Http\Requests\GetRatesRequest;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\SuccessResource;
use App\Services\Currencies\CurrencyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyRateService $currencyRateService)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        return match ($request->query('method')) {
            'rates' => $request->isMethod('GET')
                ? $this->rates(app(GetRatesRequest::class))
                : (new ErrorResource('Method not allowed', 405))->response(),
            'convert' => $request->isMethod('POST')
                ? $this->convert(app(ConvertRequest::class))
                : (new ErrorResource('Method not allowed', 405))->response(),
            default => (new ErrorResource('Method not found', 404))->response(),
        };
    }

    public function rates(GetRatesRequest $request): JsonResponse
    {
        ['symbols' => $symbols] = $request->validated();
        $rates = $this->currencyRateService->rates($symbols);
        return (new SuccessResource($rates))->response();
    }

    public function convert(ConvertRequest $request): JsonResponse
    {
        ['currency_from' => $from, 'currency_to' => $to, 'value' => $value] = $request->validated();
        $result = $this->currencyRateService->convert($from, $to, (string) $value);
        return (new SuccessResource([
            'currency_from' => $from,
            'currency_to' => $to,
            'value' => (float) $value,
            'converted_value' => (float) $result['converted_value'],
            'rate' => (float) $result['rate'],
        ]))->response();
    }
}
