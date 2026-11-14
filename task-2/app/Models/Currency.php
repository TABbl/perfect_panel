<?php

namespace App\Models;

use App\Enums\CurrencyTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Currency extends Model
{
    protected $table = 'currencies';
    protected $fillable = [
        'coin_id',
        'name',
        'symbol',
        'type',
        'last_updated',
    ];
    protected $hidden = ['created_at','updated_at'];
    protected $casts = [
        'type' => CurrencyTypeEnum::class,
    ];

    public function rate(): HasOne
    {
        return $this->hasOne(CurrencyRate::class, 'currency_id', 'id');
    }

    public function isFiat(): bool
    {
        return $this->type === CurrencyTypeEnum::FIAT;
    }

    public function isCrypto(): bool
    {
        return $this->type === CurrencyTypeEnum::CRYPTO;
    }
}
