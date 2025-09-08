<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

$order = \App\Models\Order::with('billingInformation')->find(25);

if ($order && $order->billingInformation) {
    $b = $order->billingInformation;
    echo "RFC: " . $b->rfc . PHP_EOL;
    echo "Organization: " . $b->organization . PHP_EOL;
    echo "Postal Code: " . $b->postal_code . PHP_EOL;
    echo "Tax Regime ID: " . $b->tax_regime_id . PHP_EOL;
    echo "CFDI Usage ID: " . $b->cfdi_usage_id . PHP_EOL;
} else {
    echo "No billing info found for order 25" . PHP_EOL;
}
