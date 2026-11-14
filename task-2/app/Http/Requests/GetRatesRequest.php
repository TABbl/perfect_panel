<?php

namespace App\Http\Requests;

use App\Http\Resources\ErrorResource;
use App\Services\Currencies\CurrencyRateCache;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class GetRatesRequest extends FormRequest
{
    public function __construct(private readonly CurrencyRateCache $currencyRateCache)
    {
        parent::__construct();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);
        $currencyParam = $validated['currency'] ?? null;
        $symbols = is_string($currencyParam) && $currencyParam !== ''
            ? array_map('trim', explode(',', $currencyParam))
            : null;
        if ($symbols !== null && empty($symbols)) {
            throw new ValidationException(
                $this->validator,
                (new ErrorResource('At least one currency symbol must be provided', 400))->response()
            );
        }
        if ($symbols !== null) {
            $availableSymbols = array_fill_keys($this->currencyRateCache->symbols(), true);
            foreach ($symbols as $symbol) {
                if (isset($availableSymbols[$symbol])) {
                    continue;
                }
                throw new ValidationException(
                    $this->validator,
                    (new ErrorResource('Currency not found', 400))->response()
                );
            }
        }
        return ['symbols' => $symbols];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException(
            $validator,
            (new ErrorResource($validator->errors()->first(), 400))->response()
        );
    }
}
