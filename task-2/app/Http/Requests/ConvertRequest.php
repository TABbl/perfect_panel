<?php

namespace App\Http\Requests;

use App\Http\Resources\ErrorResource;
use App\Rules\CurrencyWithRateExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ConvertRequest extends FormRequest
{
    public function __construct(private readonly CurrencyWithRateExists $currencyWithRateExists)
    {
        parent::__construct();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'currency_from' => ['required', 'string', $this->currencyWithRateExists],
            'currency_to' => ['required', 'string', $this->currencyWithRateExists],
            'value' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException(
            $validator,
            (new ErrorResource('Invalid token', 403))->response()
        );
    }
}
