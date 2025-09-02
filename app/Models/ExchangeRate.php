<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_currency_id',
        'to_currency_id',
        'rate',
        'date',
        'source',
        'is_active'
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'date' => 'date',
        'is_active' => 'boolean',
    ];

    public function fromCurrency()
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    public static function getRate($fromCurrencyId, $toCurrencyId, $date = null)
    {
        $date = $date ?? now()->format('Y-m-d');

        $rate = self::where('from_currency_id', $fromCurrencyId)
                   ->where('to_currency_id', $toCurrencyId)
                   ->where('date', $date)
                   ->where('is_active', true)
                   ->first();

        return $rate ? $rate->rate : 1;
    }

    public static function convertAmount($amount, $fromCurrencyId, $toCurrencyId, $date = null)
    {
        if ($fromCurrencyId == $toCurrencyId) {
            return $amount;
        }

        $rate = self::getRate($fromCurrencyId, $toCurrencyId, $date);
        return $amount * $rate;
    }
}
