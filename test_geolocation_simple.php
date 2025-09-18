<?php

require_once 'vendor/autoload.php';

use App\Services\GeoLocationService;

echo "=== Prueba de Geolocalización ===\n\n";

// Crear instancia del servicio
$geoService = new GeoLocationService();

// IPs para probar
$testIPs = [
    '187.184.10.89', // IP que mencionaste
    '8.8.8.8',       // Google DNS
    '1.1.1.1',       // Cloudflare DNS
    '200.57.7.118',  // IP mexicana conocida
    '127.0.0.1'      // Localhost
];

foreach ($testIPs as $ip) {
    echo "Probando IP: $ip\n";
    echo "-------------------\n";

    try {
        $result = $geoService->getLocationByIP($ip);

        if ($result) {
            echo "País: " . ($result['country'] ?? 'null') . "\n";
            echo "País (nombre): " . ($result['country_name'] ?? 'null') . "\n";
            echo "Región: " . ($result['region'] ?? 'null') . "\n";
            echo "Ciudad: " . ($result['city'] ?? 'null') . "\n";
            echo "Zona horaria: " . ($result['timezone'] ?? 'null') . "\n";
            echo "Latitud: " . ($result['latitude'] ?? 'null') . "\n";
            echo "Longitud: " . ($result['longitude'] ?? 'null') . "\n";
            echo "Código postal: " . ($result['postal_code'] ?? 'null') . "\n";
        } else {
            echo "No se pudieron obtener datos\n";
        }

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// Verificar si existe la base de datos
$dbPath = __DIR__ . '/storage/geoip/GeoLite2-City.mmdb';
echo "=== Estado de la Base de Datos ===\n";
echo "Ruta esperada: $dbPath\n";
echo "¿Existe?: " . (file_exists($dbPath) ? 'SÍ' : 'NO') . "\n";

if (!file_exists($dbPath)) {
    echo "\n⚠️  PROBLEMA: La base de datos GeoLite2 no está instalada.\n";
    echo "Sin esta base de datos, todos los resultados serán 'Unknown' o valores por defecto.\n\n";
    echo "SOLUCIÓN:\n";
    echo "1. Registrarse gratis en MaxMind: https://www.maxmind.com/en/geolite2/signup\n";
    echo "2. Descargar GeoLite2-City.mmdb\n";
    echo "3. Copiarlo a: storage/geoip/GeoLite2-City.mmdb\n\n";
} else {
    echo "✅ Base de datos encontrada.\n";
    $size = filesize($dbPath);
    echo "Tamaño: " . round($size / 1024 / 1024, 2) . " MB\n";
}
