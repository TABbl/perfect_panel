<?php

namespace App\Http\Requests;

use App\Http\Resources\ErrorResource;
use App\Rules\CurrencyWithRateExists;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ConvertRequest extends FormRequest
{
    public function __construct(private readonly CurrencyWithRateExists $currencyWithRateExists)
    {
        parent::__construct();
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('value');
        if ($this->isJson() && is_float($value)) {
            $value = $this->exactJsonAmount();
            if ($value === null) {
                $this->merge(['value' => null]);
                return;
            }
        }

        if (is_numeric($value)) {
            $value = trim((string) $value);
            if (preg_match('/[eE]([+-]?\d+)$/', $value, $match) && abs((int) $match[1]) > 1000) {
                $this->merge(['value' => null]);
                return;
            }

            $this->merge(['value' => (string) BigDecimal::of($value)]);
        }
    }

    private function exactJsonAmount(): ?string
    {
        $json = $this->getContent();
        $body = '';
        $inString = false;
        for ($i = 0, $length = strlen($json); $i < $length; $i++) {
            $char = $json[$i];
            if ($inString && $char === '\\') {
                $body .= $char . $json[++$i];
                continue;
            }
            if ($char === '"') {
                $inString = !$inString;
            }
            if (!$inString && ($char === '-' || ctype_digit($char))) {
                $size = strspn($json, '0123456789.eE+-', $i);
                $body .= '"' . substr($json, $i, $size) . '"';
                $i += $size - 1;
                continue;
            }
            $body .= $char;
        }

        return json_decode($body, true)['value'] ?? null;
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
            (new ErrorResource($validator->errors()->first(), 400))->response()
        );
    }
}
