# Guía de Monitoreo de Aprovisionamiento de Productos

## ✅ Problema Resuelto

El sistema ahora maneja correctamente el aprovisionamiento individual de productos y **NO marcará una orden como completada si hay productos que fallan**.

### Cambios Implementados:

1. **Estado de Orden Corregido:**
   - ✅ Todos exitosos → `status: 'completed'`, `fulfillment_status: 'fulfilled'`
   - ⚠️ Algunos fallidos → `status: 'processing'`, `fulfillment_status: 'partially_fulfilled'`
   - ❌ Todos fallidos → `status: 'failed'`, `fulfillment_status: 'failed'`

2. **Respuesta Detallada:**
   - Lista cada producto con su estado individual
   - Incluye mensajes de error específicos de Microsoft
   - Muestra IDs de suscripción para productos exitosos

3. **Validación Corregida:**
   - Removida validación incorrecta de `termDuration`
   - Usa configuración correcta del producto

## 📋 Cómo Ver los Resultados

### 1. Respuesta del API/Servicio
Cuando llames al servicio de aprovisionamiento, ahora recibirás:

```json
{
  "success": false,
  "message": "⚠️ Aprovisionamiento parcial: 1/3 productos exitosos, 2 fallaron",
  "order_id": 123,
  "order_status": "processing",
  "fulfillment_status": "partially_fulfilled",
  "total_products": 3,
  "successful_products": 1,
  "failed_products": 2,
  "product_details": [
    {
      "product_id": 456,
      "product_title": "Microsoft 365 Business Premium",
      "quantity": 1,
      "status": "success",
      "processed_at": "2025-01-15 10:30:00",
      "subscription_id": "sub_12345",
      "microsoft_cart_id": "cart_67890"
    },
    {
      "product_id": 789,
      "product_title": "Advanced eDiscovery Storage",
      "quantity": 1,
      "status": "failed",
      "processed_at": "2025-01-15 10:31:00",
      "error_message": "Invalid TermDuration 'P1M' for product: Advanced eDiscovery Storage",
      "microsoft_error_details": {
        "http_status": 400,
        "error_code": "InvalidTermDuration",
        "correlation_id": "abc-123-def"
      }
    }
  ]
}
```

### 2. Base de Datos - Tabla `order_items`
```sql
-- Ver todos los productos de una orden específica
SELECT 
    oi.id,
    oi.product_id,
    p.ProductTitle,
    oi.quantity,
    oi.fulfillment_status,
    oi.fulfillment_error,
    oi.processing_started_at,
    oi.microsoft_subscription_id
FROM order_items oi
LEFT JOIN products p ON p.id = oi.product_id  
WHERE oi.order_id = 123;

-- Ver productos fallidos con errores
SELECT 
    oi.product_id,
    p.ProductTitle,
    oi.fulfillment_error,
    oi.processing_started_at
FROM order_items oi
LEFT JOIN products p ON p.id = oi.product_id
WHERE oi.fulfillment_status = 'failed'
ORDER BY oi.processing_started_at DESC;
```

### 3. Comandos de Artisan

#### Ver Detalles de una Orden
```bash
php artisan order:details 123
```
**Salida ejemplo:**
```
📋 DETALLES DE ORDEN: 123
===============================

📊 INFORMACIÓN GENERAL:
Order Number: ORD-2025-001
Status: processing
Fulfillment Status: partially_fulfilled
Created: 2025-01-15 10:00:00

📦 PRODUCTOS Y ESTADOS DE FULFILLMENT:
=====================================

Producto 1:
  - Product ID: 456
  - Título: Microsoft 365 Business Premium
  - Cantidad: 1
  - Estado: ✅ CUMPLIDO
  - Microsoft Subscription ID: sub_12345

Producto 2:
  - Product ID: 789
  - Título: Advanced eDiscovery Storage
  - Cantidad: 1
  - Estado: ❌ FALLIDO
  - Error: Invalid TermDuration 'P1M' for product: Advanced eDiscovery Storage
```

#### Reporte de Productos
```bash
php artisan products:report
```

#### Reintentar Productos Fallidos
```bash
php artisan retry:failed-products 123
```

#### Probar Escenario Completo
```bash
php artisan test:provisioning-scenario 123 -v
```

### 4. Usando Tinker (Consola Laravel)
```bash
php artisan tinker
```

```php
// Ver orden con detalles
$order = \App\Models\Order::with(['cart.items.product'])->find(123);
echo "Status: " . $order->status . "\n";
echo "Fulfillment: " . $order->fulfillment_status . "\n";

// Ver productos individuales
$orderItems = \App\Models\OrderItem::where('order_id', 123)->get();
foreach($orderItems as $item) {
    echo "Producto {$item->product_id}: {$item->fulfillment_status}\n";
    if($item->fulfillment_error) {
        echo "  Error: {$item->fulfillment_error}\n";
    }
}

// Ver productos fallidos recientes
$failed = \App\Models\OrderItem::where('fulfillment_status', 'failed')
    ->with('product')
    ->orderBy('processing_started_at', 'desc')
    ->take(10)
    ->get();
foreach($failed as $item) {
    echo "❌ {$item->product->ProductTitle}: {$item->fulfillment_error}\n";
}
```

## 🔧 Casos de Uso Comunes

### Monitoreo Diario
1. **Ver órdenes parcialmente cumplidas:**
   ```sql
   SELECT id, order_number, status, fulfillment_status 
   FROM orders 
   WHERE fulfillment_status = 'partially_fulfilled';
   ```

2. **Productos que más fallan:**
   ```bash
   php artisan products:report --errors-only
   ```

### Resolución de Problemas
1. **Ver detalles específicos de error:**
   ```bash
   php artisan order:details [ORDER_ID]
   ```

2. **Reintentar automáticamente:**
   ```bash
   php artisan retry:failed-products [ORDER_ID]
   ```

### Reportes para Management
```bash
# Reporte completo de la última semana
php artisan products:report --days=7

# Estadísticas de éxito/fallo
php artisan products:report --summary
```

## 💡 Consejos

1. **Para órdenes con fallos parciales**: El sistema las mantiene en estado `processing` para revisión manual
2. **Errores de Microsoft**: Se capturan con correlation IDs para facilitar soporte
3. **Reintentos**: Usa el comando retry para productos específicos que fallaron por errores temporales
4. **Monitoreo**: Revisa regularmente las órdenes en estado `processing` con `fulfillment_status: 'partially_fulfilled'`
