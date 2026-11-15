<?php

namespace App\Services\Currencies\DTO;

class ConvertDTO
{
    public function __construct(
        private readonly string $convertedValue,
        private readonly string $rate
    ) {}

    public function getConvertedValue(): string
    {
        return $this->convertedValue;
    }

    public function getRate(): string
    {
        return $this->rate;
    }
}
