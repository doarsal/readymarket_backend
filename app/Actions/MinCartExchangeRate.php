<?php

namespace App\Actions;

use Cache;
use Carbon\Carbon;
use Config;

class MinCartExchangeRate
{
    public function execute(): array
    {
        $cacheKey        = 'cart_min_amount';
        $cacheLastUpdate = $cacheKey . '_last_update';

        $timezone     = Config::get('exchange-rate.timezone');
        $minHourOfDay = Config::get('exchange-rate.min_hour_of_day');
        $usdAmount    = Config::get('exchange-rate.usd_amount');

        $now = Carbon::now($timezone)->format('Y-m-d');

        $cacheExists = Cache::has($cacheLastUpdate) && Cache::get($cacheLastUpdate) === $now;
        if (Carbon::now($timezone)->hour >= $minHourOfDay && !$cacheExists) {
            // TODO: Agregar consulta a la API para obtener el exchange rate
            $fallBackAmount = Config::get('exchange-rate.fallback_min_amount');

            Cache::rememberForever($cacheKey, function() use ($fallBackAmount) {
                return $fallBackAmount;
            });
            Cache::rememberForever($cacheLastUpdate, function() use ($now) {
                return $now;
            });
        }

        return [
            'exchange' => number_format(Cache::get($cacheKey), 2),
            'usd'      => number_format($usdAmount, 2),
        ];
    }
}
