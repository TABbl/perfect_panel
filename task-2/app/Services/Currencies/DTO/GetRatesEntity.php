<?php

namespace App\Services\Currencies\DTO;

class GetRatesEntity
{
    public function __construct(private readonly ?array $symbols) {}

    public static function fromRequest(array $validated): self
    {
        return new self($validated['symbols'] ?? null);
    }

    public function getSymbols(): ?array
    {
        return $this->symbols;
    }
}
