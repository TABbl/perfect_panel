<?php

namespace App\Services\Currencies\DTO;

class ConvertEntity
{
    public function __construct(
        private readonly string $currencyFrom,
        private readonly string $currencyTo,
        private readonly string $value
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            $validated['currency_from'],
            $validated['currency_to'],
            (string) $validated['value']
        );
    }

    public function getCurrencyFrom(): string
    {
        return $this->currencyFrom;
    }

    public function getCurrencyTo(): string
    {
        return $this->currencyTo;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
