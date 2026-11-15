<?php

namespace App\Services\Currencies\DTO;

class GetRatesDTO
{
    public function __construct(private readonly array $rates) {}

    public function getRates(): array
    {
        return $this->rates;
    }
}
