<?php

return [
    'price_multiplier' => env('PRICE_MULTIPLIER', 13),

    'first_buy' => [
        'active'   => env('FIRST_BUY_ACTIVE', true),
        'discount' => env('FIRST_BUY_DISCOUNT', 2),
    ],
];
