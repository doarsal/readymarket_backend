<?php

return [
    'fallback_min_amount' => env('EXCHANGE_RATE_FALLBACK_MIN_AMOUNT', 95),
    'usd_amount'          => env('EXCHANGE_RATE_USD_AMOUNT', 5),
    'min_hour_of_day'     => env('EXCHANGE_RATE_MIN_HOUR_OF_DAY', 10),
    'timezone'            => env('EXCHANGE_RATE_TIMEZONE', 'America/Mexico_City'),
];
