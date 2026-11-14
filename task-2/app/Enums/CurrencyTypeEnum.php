<?php

namespace App\Enums;

enum CurrencyTypeEnum: int
{
    case FIAT = 1;
    case CRYPTO = 2;

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getNames(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function fromName(string $name): self
    {
        return self::from(self::getValueByName($name));
    }

    public static function getValueByName(string $name): ?int
    {
        return self::toArray()[$name] ?? null;
    }
}
