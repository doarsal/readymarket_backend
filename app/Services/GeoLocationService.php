<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Support\Facades\Log;

class GeoLocationService
{
    private $reader;
    private $databasePath;

    public function __construct()
    {
        // Path a la base de datos GeoLite2-City.mmdb
        $this->databasePath = storage_path('geoip/GeoLite2-City.mmdb');

        if (file_exists($this->databasePath)) {
            try {
                $this->reader = new Reader($this->databasePath);
            } catch (\Exception $e) {
                Log::warning('GeoIP database could not be loaded: ' . $e->getMessage());
                $this->reader = null;
            }
        } else {
            Log::warning('GeoIP database not found at: ' . $this->databasePath);
            $this->reader = null;
        }
    }

    /**
     * Obtener información de geolocalización por IP
     */
    public function getLocationByIP($ipAddress)
    {
        // IPs locales o privadas
        if ($this->isLocalIP($ipAddress)) {
            return $this->getDefaultLocation();
        }

        // Intentar con base de datos local primero (más rápido)
        if ($this->reader) {
            return $this->getLocationFromDatabase($ipAddress);
        }

        // Fallback: usar servicio web (para desarrollo)
        return $this->getLocationFromWebService($ipAddress);
    }

    /**
     * Obtener ubicación desde base de datos local
     */
    private function getLocationFromDatabase($ipAddress)
    {
        try {
            $record = $this->reader->city($ipAddress);

            return [
                'country' => $record->country->isoCode ?? null,
                'country_name' => $record->country->name ?? null,
                'region' => $record->mostSpecificSubdivision->name ?? null,
                'region_code' => $record->mostSpecificSubdivision->isoCode ?? null,
                'city' => $record->city->name ?? null,
                'timezone' => $record->location->timeZone ?? null,
                'latitude' => $record->location->latitude ?? null,
                'longitude' => $record->location->longitude ?? null,
                'postal_code' => $record->postal->code ?? null,
                'source' => 'database'
            ];

        } catch (AddressNotFoundException $e) {
            Log::info('IP address not found in GeoIP database: ' . $ipAddress);
            return $this->getDefaultLocation();
        } catch (\Exception $e) {
            Log::error('GeoIP database lookup failed: ' . $e->getMessage());
            return $this->getLocationFromWebService($ipAddress);
        }
    }

    /**
     * Obtener ubicación desde servicio web (fallback)
     */
    private function getLocationFromWebService($ipAddress)
    {
        try {
            // Usar servicio gratuito como fallback
            $url = "http://ip-api.com/json/{$ipAddress}?fields=status,country,countryCode,region,regionName,city,timezone,lat,lon,isp,zip";

            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'Mozilla/5.0 (compatible; LaravelApp/1.0)',
                    'ignore_errors' => true
                ]
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response) {
                $data = json_decode($response, true);

                if ($data && isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'country' => $data['countryCode'] ?? null,
                        'country_name' => $data['country'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'region_code' => $data['region'] ?? null,
                        'city' => $data['city'] ?? null,
                        'timezone' => $data['timezone'] ?? null,
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                        'postal_code' => $data['zip'] ?? null,
                        'source' => 'webservice'
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::warning('Web service geolocation failed: ' . $e->getMessage());
        }

        // Si todo falla, devolver ubicación por defecto
        return $this->getDefaultLocation();
    }

    /**
     * Verificar si es IP local o privada
     */
    private function isLocalIP($ip)
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return true;
        }

        // IPs privadas
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return false;
    }

    /**
     * Ubicación por defecto para IPs locales o cuando falla la consulta
     */
    private function getDefaultLocation()
    {
        return [
            'country' => 'MX',
            'country_name' => 'Mexico',
            'region' => 'Unknown',
            'region_code' => null,
            'city' => 'Unknown',
            'timezone' => 'America/Mexico_City',
            'latitude' => null,
            'longitude' => null,
            'postal_code' => null,
            'source' => 'default'
        ];
    }

    /**
     * Obtener información de usuario agent
     */
    public function parseUserAgent($userAgent)
    {
        $agent = new \Jenssegers\Agent\Agent();
        $agent->setUserAgent($userAgent);

        return [
            'browser' => $agent->browser(),
            'browser_version' => $agent->version($agent->browser()),
            'os' => $agent->platform(),
            'os_version' => $agent->version($agent->platform()),
            'device_type' => $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop'),
            'is_mobile' => $agent->isMobile(),
            'is_bot' => $agent->isRobot(),
        ];
    }

    /**
     * Obtener IP real del cliente (considerando proxies)
     */
    public static function getRealIP()
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
