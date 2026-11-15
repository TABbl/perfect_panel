<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\ConvertRequest;
use App\Http\Requests\GetRatesRequest;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\SuccessResource;
use App\Services\Currencies\CurrencyRateService;
use App\Services\Currencies\DTO\ConvertEntity;
use App\Services\Currencies\DTO\GetRatesEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyRateService $currencyRateService) {}

    public function handle(Request $request): JsonResponse
    {
        return match ($request->query('method')) {
            'rates' => $request->isMethod('GET')
                ? $this->rates(app(GetRatesRequest::class))
                : (new ErrorResource('Invalid token', 403))->response(),
            'convert' => $request->isMethod('POST')
                ? $this->convert(app(ConvertRequest::class))
                : (new ErrorResource('Invalid token', 403))->response(),
            default => (new ErrorResource('Invalid token', 403))->response(),
        };
    }

    public function rates(GetRatesRequest $request): JsonResponse
    {
        $entity = GetRatesEntity::fromRequest($request->validated());
        $rates = $this->currencyRateService->getRates($entity);

        return (new SuccessResource($rates->getRates()))->response();
    }

    public function convert(ConvertRequest $request): JsonResponse
    {
        $entity = ConvertEntity::fromRequest($request->validated());
        $result = $this->currencyRateService->convert($entity);

        return (new SuccessResource([
            'currency_from' => $entity->getCurrencyFrom(),
            'currency_to' => $entity->getCurrencyTo(),
            'value' => (float) $entity->getValue(),
            'converted_value' => (float) $result->getConvertedValue(),
            'rate' => (float) $result->getRate(),
        ]))->response();
    }
}
