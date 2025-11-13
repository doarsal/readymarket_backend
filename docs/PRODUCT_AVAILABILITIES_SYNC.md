# Sincronización de Product Availabilities

Sistema para mantener actualizados los AvailabilityIds de los productos desde Microsoft Partner Center.

## 📋 Componentes

### 1. Command (Artisan)
**Uso manual desde terminal**

### 2. Job (Queue)
**Ejecución en background**

### 3. Scheduled Task
**Ejecución automática semanal**

---

## 🚀 Uso del Comando

### Ejecución básica
```bash
php artisan products:sync-availabilities
```

### Con opciones
```bash
# Especificar mercado
php artisan products:sync-availabilities --market=US

# Cambiar tamaño de batch y delay
php artisan products:sync-availabilities --batch-size=100 --delay=3

# Forzar sincronización de TODOS los productos (incluso los ya sincronizados)
php artisan products:sync-availabilities --force

# Combinación de opciones
php artisan products:sync-availabilities --market=MX --batch-size=50 --delay=5 --force
```

### Opciones disponibles

| Opción | Descripción | Default | Ejemplo |
|--------|-------------|---------|---------|
| `--market` | Código de mercado (MX, US, CA, etc) | MX | `--market=US` |
| `--batch-size` | Productos a procesar antes de pausar | 50 | `--batch-size=100` |
| `--delay` | Segundos de pausa entre batches | 5 | `--delay=10` |
| `--force` | Sincronizar todos, incluso ya sincronizados | false | `--force` |

---

## 📦 Uso del Job

### Despachar el Job manualmente

```php
use App\Jobs\SyncProductAvailabilitiesJob;

// Básico
SyncProductAvailabilitiesJob::dispatch();

// Con opciones personalizadas
SyncProductAvailabilitiesJob::dispatch(
    market: 'MX',
    batchSize: 50,
    delay: 5,
    force: false
);

// Despachar con delay
SyncProductAvailabilitiesJob::dispatch()
    ->delay(now()->addMinutes(5));

// Despachar en queue específica
SyncProductAvailabilitiesJob::dispatch()
    ->onQueue('microsoft-sync');
```

### Desde Tinker
```bash
php artisan tinker
```

```php
// En tinker
App\Jobs\SyncProductAvailabilitiesJob::dispatch();
```

### Desde un Controlador o Service
```php
use App\Jobs\SyncProductAvailabilitiesJob;

class ProductController extends Controller
{
    public function syncAvailabilities()
    {
        // Despachar job
        SyncProductAvailabilitiesJob::dispatch();
        
        return response()->json([
            'message' => 'Sincronización iniciada en background'
        ]);
    }
}
```

---

## ⏰ Ejecución Programada (Scheduler)

El comando se ejecuta **automáticamente** cada semana según configuración en `routes/console.php`:

```php
Schedule::command('products:sync-availabilities')
    ->weekly()
    ->mondays()
    ->at('04:00')
    ->withoutOverlapping()
    ->runInBackground();
```

### Configuración actual
- **Frecuencia**: Semanal
- **Día**: Lunes
- **Hora**: 4:00 AM
- **Ejecución**: En background
- **Overlap**: Previene ejecuciones simultáneas

### Cambiar la programación

Edita `routes/console.php`:

```php
// Diario a las 3:00 AM
Schedule::command('products:sync-availabilities')
    ->daily()
    ->at('03:00');

// Cada 6 horas
Schedule::command('products:sync-availabilities')
    ->everyFourHours();

// Dos veces por semana
Schedule::command('products:sync-availabilities')
    ->weekly()
    ->mondays()
    ->at('04:00');

Schedule::command('products:sync-availabilities')
    ->weekly()
    ->thursdays()
    ->at('04:00');
```

### Activar el Scheduler

Para que las tareas programadas funcionen, agrega esto a tu cron (Linux/Mac):

```bash
* * * * * cd /path/to/marketplace/backend && php artisan schedule:run >> /dev/null 2>&1
```

En Windows (Task Scheduler), ejecuta cada minuto:
```bash
cd C:\xampp\htdocs\marketplace\backend && php artisan schedule:run
```

---

## 📊 Monitoreo y Logs

### Ver logs
```bash
# Logs generales
tail -f storage/logs/laravel.log

# Filtrar solo sync de availabilities
tail -f storage/logs/laravel.log | grep "availabilities sync"
```

### Verificar última sincronización

```sql
SELECT 
    COUNT(*) as total_products,
    COUNT(CASE WHEN availability_checked_at IS NOT NULL THEN 1 END) as synced,
    COUNT(CASE WHEN is_available = 1 THEN 1 END) as available,
    MAX(availability_checked_at) as last_sync
FROM products;
```

### Ver productos con errores
```sql
SELECT ProductId, SkuId, ProductTitle, availability_error, availability_checked_at
FROM products
WHERE availability_error IS NOT NULL
ORDER BY availability_checked_at DESC
LIMIT 20;
```

---

## 🎯 Casos de Uso

### 1. Sincronización inicial después de importar productos
```bash
php artisan products:sync-availabilities --force
```

### 2. Revisar solo productos sin sincronizar
```bash
php artisan products:sync-availabilities
```

### 3. Sincronización rápida (batches grandes, sin delay)
```bash
php artisan products:sync-availabilities --batch-size=100 --delay=0
```

### 4. Sincronización segura (Microsoft rate limits)
```bash
php artisan products:sync-availabilities --batch-size=25 --delay=10
```

### 5. Ejecutar en background via Job
```php
// Desde código PHP
SyncProductAvailabilitiesJob::dispatch();
```

---

## ⚠️ Notas Importantes

1. **Rate Limiting**: Microsoft limita requests. El delay entre batches previene throttling.

2. **Timeouts**: Cada request tiene timeout de 30 segundos. Total del job: 1 hora.

3. **Segments**: El sistema prefiere availabilities del segmento "Commercial" cuando están disponibles.

4. **Atomic Updates**: Cada producto se actualiza independientemente. Si uno falla, continúa con los demás.

5. **is_available Flag**: 
   - `true`: Producto tiene availabilities y puede comprarse
   - `false`: Producto no disponible o no encontrado

6. **Idempotencia**: Ejecutar múltiples veces es seguro, solo actualiza lo necesario.

---

## 🔧 Troubleshooting

### El comando no aparece
```bash
php artisan list | grep products
php artisan clear-compiled
php artisan config:clear
```

### Job no se ejecuta
```bash
# Verificar queue worker está corriendo
php artisan queue:work

# Ver jobs fallidos
php artisan queue:failed
```

### Scheduler no funciona
```bash
# Probar manualmente
php artisan schedule:run

# Ver tareas programadas
php artisan schedule:list
```

---

## 📈 Rendimiento

Con la configuración por defecto (batch-size=50, delay=5):

- **605 productos**: ~1 minuto
- **1000 productos**: ~2 minutos  
- **5000 productos**: ~8 minutos

Ajusta según necesidad y límites de Microsoft API.
