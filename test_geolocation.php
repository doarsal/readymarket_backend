<?php

require __DIR__ . '/vendor/autoload.php';

// Cargar Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

use App\Services\GeoLocationService;

echo "Probando GeoLocationService...\n";

$service = new GeoLocationService();

// Probar con IP pública conocida (Google DNS)
$result = $service->getLocationByIP('8.8.8.8');
echo "IP 8.8.8.8 (Google DNS):\n";
print_r($result);

// Probar con IP local
$result2 = $service->getLocationByIP('127.0.0.1');
echo "\nIP 127.0.0.1 (localhost):\n";
print_r($result2);

// Probar UserAgent parsing
$userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
$agentData = $service->parseUserAgent($userAgent);
echo "\nUser Agent parsing:\n";
print_r($agentData);

echo "\nTest completado!\n";
