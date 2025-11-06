<?php

namespace App\Actions;

use Cache;
use Carbon\Carbon;
use Config;

class ExchangeRate
{
    public function execute(): float
    {
        $cacheKey        = 'exchange_rate';
        $cacheLastUpdate = $cacheKey . '_last_update';

        $timezone     = Config::get('exchange-rate.timezone');
        $minHourOfDay = Config::get('exchange-rate.min_hour_of_day');

        $now = Carbon::now($timezone)->format('Y-m-d');

        $cacheExists = Cache::has($cacheLastUpdate) && Cache::get($cacheLastUpdate) === $now;
        if (Carbon::now($timezone)->hour >= $minHourOfDay && !$cacheExists) {
            // TODO: Agregar consulta a la API para obtener el exchange rate
            $fallBackExchangeRate = Config::get('exchange-rate.fallback_rate');

            Cache::rememberForever($cacheKey, function() use ($fallBackExchangeRate) {
                return $fallBackExchangeRate;
            });
            Cache::rememberForever($cacheLastUpdate, function() use ($now) {
                return $now;
            });
        }

        return number_format(Cache::get($cacheKey), 2);
    }
}
