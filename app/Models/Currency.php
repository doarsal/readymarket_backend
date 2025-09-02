<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'symbol',
        'symbol_position',
        'decimal_places',
        'thousands_separator',
        'decimal_separator',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
        'sort_order' => 'integer',
    ];

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_currencies')
                    ->withPivot('is_default', 'is_active', 'sort_order', 'auto_update_rate')
                    ->withTimestamps();
    }

    public function exchangeRatesFrom()
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    public function exchangeRatesTo()
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    public function formatPrice($amount)
    {
        $formattedAmount = number_format(
            $amount,
            $this->decimal_places,
            $this->decimal_separator,
            $this->thousands_separator
        );

        return $this->symbol_position === 'before'
            ? $this->symbol . $formattedAmount
            : $formattedAmount . $this->symbol;
    }
}
