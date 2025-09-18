# Configuración de Geolocalización - GeoIP2

## Instalación de Base de Datos GeoLite2

Para que funcione la geolocalización por IP, necesitas descargar la base de datos GeoLite2-City de MaxMind.

### Paso 1: Registrarse en MaxMind (GRATIS)
1. Ve a https://www.maxmind.com/en/geolite2/signup
2. Crea una cuenta gratuita
3. Verifica tu email

### Paso 2: Generar License Key
1. Inicia sesión en tu cuenta MaxMind
2. Ve a "My Account" → "Manage License Keys"
3. Genera una nueva license key

### Paso 3: Descargar la Base de Datos
1. Ve a https://www.maxmind.com/en/accounts/current/geoip/downloads
2. Descarga **GeoLite2-City** en formato **Binary (.mmdb)**
3. Descomprime el archivo

### Paso 4: Instalar en Laravel
1. Copia el archivo `GeoLite2-City.mmdb` a la carpeta:
   ```
   backend/storage/geoip/GeoLite2-City.mmdb
   ```

### Paso 5: Verificar Instalación
Ejecuta este comando para probar:

```bash
cd backend
php artisan tinker --execute="
\$service = new App\Services\GeoLocationService();
\$result = \$service->getLocationByIP('8.8.8.8');
print_r(\$result);
"
```

## Actualización Automática (Opcional)

Para mantener la base de datos actualizada, puedes crear un comando programado:

```php
// En app/Console/Commands/UpdateGeoDatabase.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UpdateGeoDatabase extends Command
{
    protected $signature = 'geo:update-database';
    protected $description = 'Update GeoLite2 database';

    public function handle()
    {
        $this->info('Updating GeoLite2 database...');
        // Lógica para descargar y actualizar automáticamente
        $this->info('Database updated successfully!');
    }
}
```

## Campos de Geolocalización Almacenados

El sistema almacena la siguiente información geográfica:

- **country**: Código ISO del país (MX, US, etc.)
- **region**: Estado o región
- **city**: Ciudad
- **timezone**: Zona horaria
- **additional_data**: 
  - country_name: Nombre completo del país
  - region_code: Código de la región
  - postal_code: Código postal
  - latitude/longitude: Coordenadas (si están disponibles)
  - browser_coordinates: Coordenadas del navegador (si el usuario da permisos)

## Servicios Alternativos

Si prefieres un servicio en la nube (más fácil pero con límites):

1. **ipapi.com** (gratuito hasta 1000 requests/mes)
2. **ipstack.com** (gratuito hasta 10,000 requests/mes) 
3. **MaxMind Web Service** (de pago, más preciso)

## Troubleshooting

### Error: "GeoIP database not found"
- Verifica que el archivo está en `backend/storage/geoip/GeoLite2-City.mmdb`
- Verifica permisos de lectura del archivo

### Error: "GeoIP database could not be loaded"
- El archivo puede estar corrupto, descarga nuevamente
- Verifica que es el formato correcto (.mmdb)

### IPs locales devuelven ubicación por defecto
- Esto es normal para 127.0.0.1, localhost, e IPs privadas
- El sistema devuelve ubicación por defecto (México) para desarrollo local

## Performance

- La base de datos local es muy rápida (~1ms por consulta)
- Se recomienda usar caché para IPs repetidas si tienes mucho tráfico
- La base de datos ocupa ~70MB en disco

## Privacidad

- Solo se almacena información de país/ciudad, no coordenadas exactas por defecto
- Las coordenadas del navegador solo se obtienen con permiso del usuario
- Cumple con GDPR al no almacenar información personal identificable
