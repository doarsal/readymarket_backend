<?php

return [
    'fallback_rate'   => env('EXCHANGE_RATE_FALLBACK_MIN_AMOUNT', 19.2),
    'multiplier'      => env('EXCHANGE_RATE_MULTIPLIER', 2),
    'min_hour_of_day' => env('EXCHANGE_RATE_MIN_HOUR_OF_DAY', 10),
    'timezone'        => env('EXCHANGE_RATE_TIMEZONE', 'America/Mexico_City'),
    'min_cart_amount' => env('EXCHANGE_RATE_MIN_CART_AMOUNT', 5),
];
