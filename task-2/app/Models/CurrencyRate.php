<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRate extends Model
{
    protected $table = 'currency_rates';
    protected $fillable = [
        'currency_id',
        'rate'
    ];
    protected $hidden = ['created_at','updated_at'];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }
}
