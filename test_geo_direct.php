<?php

require_once 'vendor/autoload.php';

use GeoIp2\Database\Reader;

echo "=== Prueba de Geolocalización (Directa) ===\n\n";

// Ruta directa a la base de datos
$dbPath = __DIR__ . '/storage/geoip/GeoLite2-City.mmdb';

echo "=== Estado de la Base de Datos ===\n";
echo "Ruta: $dbPath\n";
echo "¿Existe?: " . (file_exists($dbPath) ? 'SÍ' : 'NO') . "\n";

if (!file_exists($dbPath)) {
    echo "\n⚠️  PROBLEMA: La base de datos GeoLite2 NO ESTÁ INSTALADA.\n";
    echo "Sin esta base de datos, todos los resultados serán 'Unknown' o valores por defecto.\n\n";

    echo "=== ¿Cómo funciona la geolocalización por IP? ===\n";
    echo "1. Cada IP pública pertenece a un rango asignado a un país/ciudad\n";
    echo "2. MaxMind recopila esta información y la distribuye en bases de datos\n";
    echo "3. La base de datos GeoLite2 contiene millones de rangos de IP\n";
    echo "4. Cuando consultamos una IP, buscamos en qué rango cae\n";
    echo "5. Ese rango nos dice el país, región, ciudad, etc.\n\n";

    echo "EJEMPLO:\n";
    echo "IP 187.184.10.89 -> Rango 187.184.0.0/16 -> México, CDMX\n\n";

    echo "SOLUCIÓN:\n";
    echo "1. Ir a: https://www.maxmind.com/en/geolite2/signup\n";
    echo "2. Crear cuenta GRATUITA\n";
    echo "3. Descargar 'GeoLite2-City' formato Binary (.mmdb)\n";
    echo "4. Descomprimir y copiar 'GeoLite2-City.mmdb' a:\n";
    echo "   $dbPath\n\n";

    echo "=== Alternativa: Servicio Web (para pruebas rápidas) ===\n";
    echo "Probando con servicio web gratuito...\n\n";

    // Probar con servicio web como alternativa
    $testIPs = ['187.184.10.89', '8.8.8.8'];

    foreach ($testIPs as $ip) {
        echo "IP: $ip\n";
        try {
            // Usar servicio web gratuito (solo para demostración)
            $url = "http://ip-api.com/json/$ip";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Mozilla/5.0 (compatible; GeoTest/1.0)'
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && $data['status'] === 'success') {
                    echo "  País: " . $data['country'] . " ({$data['countryCode']})\n";
                    echo "  Región: " . $data['regionName'] . "\n";
                    echo "  Ciudad: " . $data['city'] . "\n";
                    echo "  Zona horaria: " . $data['timezone'] . "\n";
                    echo "  ISP: " . ($data['isp'] ?? 'N/A') . "\n";
                    echo "  Latitud/Longitud: {$data['lat']}, {$data['lon']}\n";
                } else {
                    echo "  No se pudo obtener información\n";
                }
            } else {
                echo "  Error en la consulta web\n";
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

} else {
    echo "✅ Base de datos encontrada!\n";
    $size = filesize($dbPath);
    echo "Tamaño: " . round($size / 1024 / 1024, 2) . " MB\n\n";

    // Probar con la base de datos local
    try {
        $reader = new Reader($dbPath);

        $testIPs = ['187.184.10.89', '8.8.8.8', '200.57.7.118'];

        foreach ($testIPs as $ip) {
            echo "Probando IP: $ip\n";
            echo "-------------------\n";

            try {
                $record = $reader->city($ip);

                echo "País: " . $record->country->isoCode . " (" . $record->country->name . ")\n";
                echo "Región: " . $record->mostSpecificSubdivision->name . "\n";
                echo "Ciudad: " . $record->city->name . "\n";
                echo "Zona horaria: " . $record->location->timeZone . "\n";
                echo "Latitud: " . $record->location->latitude . "\n";
                echo "Longitud: " . $record->location->longitude . "\n";
                echo "Código postal: " . $record->postal->code . "\n";

            } catch (Exception $e) {
                echo "Error consultando IP: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }

    } catch (Exception $e) {
        echo "Error abriendo base de datos: " . $e->getMessage() . "\n";
    }
}

echo "=== Resumen ===\n";
echo "Para que funcione completamente:\n";
echo "1. Necesitas descargar GeoLite2-City.mmdb\n";
echo "2. Colocarlo en storage/geoip/\n";
echo "3. Reiniciar el servidor Laravel\n";
echo "4. Las consultas desde tu aplicación funcionarán automáticamente\n";
