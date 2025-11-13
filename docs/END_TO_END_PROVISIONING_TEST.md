# Test End-to-End de Aprovisionamiento

Comando para probar el flujo completo de aprovisionamiento desde la creación de cuenta Microsoft hasta la confirmación de la orden.

## 🎯 Propósito

El comando `orders:test-full-flow` automatiza todo el proceso de aprovisionamiento de productos Microsoft para validar que el sistema funciona correctamente de principio a fin, sin intervención manual.

---

## 🚀 Uso Básico

```bash
php artisan orders:test-full-flow --user-id=1
```

Este comando ejecuta automáticamente:

1. ✅ **Validación de usuario** - Verifica que el usuario existe
2. ✅ **Creación de cuenta Microsoft** - Crea nueva cuenta en Partner Center
3. ✅ **Aceptación de Customer Agreement** - Acepta automáticamente el contrato
4. ✅ **Creación de carrito** - Crea o reutiliza carrito activo
5. ✅ **Agregación de producto** - Añade Office 365 E1 por defecto
6. ✅ **Creación de orden** - Genera orden con snapshot completo
7. ✅ **Aprovisionamiento** - Provisiona en Microsoft Partner Center
8. ✅ **Verificación de suscripciones** - Confirma creación de subscriptions
9. ✅ **Confirmación final** - Marca orden como completada

---

## 📋 Opciones Disponibles

### `--user-id` (requerido)
ID del usuario que hará la compra.

```bash
php artisan orders:test-full-flow --user-id=5
```

### `--product-id` (opcional)
ID del producto a aprovisionar. Por defecto: **168** (Office 365 E1)

```bash
php artisan orders:test-full-flow --user-id=1 --product-id=200
```

### `--quantity` (opcional)
Cantidad de licencias a comprar. Por defecto: **1**

```bash
php artisan orders:test-full-flow --user-id=1 --quantity=10
```

### `--skip-account` (opcional)
Usar cuenta Microsoft existente en lugar de crear una nueva.

```bash
php artisan orders:test-full-flow --user-id=1 --skip-account --account-id=42
```

### `--account-id` (opcional)
ID de la cuenta Microsoft existente a usar (requiere `--skip-account`).

```bash
php artisan orders:test-full-flow --user-id=1 --skip-account --account-id=42
```

---

## 🎬 Ejemplos de Uso

### 1. Test básico con Office 365 E1
```bash
php artisan orders:test-full-flow --user-id=1
```

**Resultado esperado:**
- Nueva cuenta Microsoft creada
- 1 licencia de Office 365 E1 aprovisionada
- Suscripción activa en Microsoft Partner Center
- Orden marcada como completada

---

### 2. Test con producto específico y cantidad
```bash
php artisan orders:test-full-flow --user-id=1 --product-id=175 --quantity=5
```

**Resultado esperado:**
- 5 licencias del producto ID 175
- Orden con total calculado correctamente
- 5 licencias aprovisionadas

---

### 3. Test con cuenta existente
```bash
php artisan orders:test-full-flow --user-id=1 --skip-account --account-id=42
```

**Resultado esperado:**
- Usa cuenta Microsoft existente (ID: 42)
- No crea nueva cuenta
- Aprovisiona producto en cuenta existente

---

### 4. Test de múltiples licencias
```bash
php artisan orders:test-full-flow --user-id=1 --quantity=25
```

**Resultado esperado:**
- 25 licencias de Office 365 E1
- Precio total = $10.85 × 25 = $271.25 USD
- Todas las licencias aprovisionadas correctamente

---

## 📊 Salida del Comando

### Ejemplo de ejecución exitosa

```
╔════════════════════════════════════════════════════════════╗
║         TEST COMPLETO DE APROVISIONAMIENTO E2E             ║
╚════════════════════════════════════════════════════════════╝

1️⃣  Verificando usuario...
   ✓ Usuario: Salvador Rodriguez (salvador.rodriguez@readymind.ms)

2️⃣  Creando nueva cuenta Microsoft...
   ✓ Cuenta creada: rmcustomer1763057196
   ✓ Customer ID: 0512837d-58e3-4991-a22e-99f7f582a410
   ✓ Account ID: 50

3️⃣  Verificando producto...
   ✓ Producto: Office 365 E1
   ✓ SKU: 0001
   ✓ Precio: $10.85 USD

4️⃣  Creando carrito...
   ✓ Carrito creado (ID: 613)

5️⃣  Agregando producto al carrito...
   ✓ Producto agregado: Office 365 E1 x1

6️⃣  Creando orden...
   ✓ Orden creada: #ORD-2025-000063 (ID: 100)
   ✓ Subtotal: $10.85
   ✓ Total: $12.59 MXN
   ✓ Cart ID: 613
   ✓ Cart Items: 1
   ✓ Order Items: 1

7️⃣  Aprovisionando en Microsoft Partner Center...
   (Esto puede tardar unos segundos...)

   🔍 DEBUG - Datos del producto:
   ProductId: CFQ7TTC0LF8Q
   SkuId: 0001
   AvailabilityId (Id): CFQ7TTC0WLR4
   CatalogItemId: CFQ7TTC0LF8Q:0001:CFQ7TTC0WLR4
   is_available: true

   ⚠ Aprovisionamiento completado con advertencias
   Mensaje: ✅ ¡Orden completada! Los últimos 1 productos se procesaron exitosamente. Total: 1/1

8️⃣  Verificando suscripciones...
   ✓ Suscripciones creadas: 1
     • Subscription ID: f373e0ec-685a-45ec-ce36-6613661dee1b
       Producto: Office 365 E1
       Cantidad: 1
       Precio: $9.60

9️⃣  Confirmando orden como completada...
   ✓ Orden marcada como completada

╔════════════════════════════════════════════════════════════╗
║                  ✓ FLUJO COMPLETADO ✓                      ║
╚════════════════════════════════════════════════════════════╝

+------------------+----------------------+
| Concepto         | Detalle              |
+------------------+----------------------+
| Usuario          | Salvador Rodriguez   |
| Cuenta Microsoft | rmcustomer1763057196 |
| Orden            | #ORD-2025-000063     |
| Producto         | Office 365 E1        |
| Cantidad         | 1                    |
| Total            | $12.59 MXN           |
| Suscripciones    | 1                    |
| Estado           | ✓ Completado         |
+------------------+----------------------+

✓ Todo el flujo de aprovisionamiento funcionó correctamente de inicio a fin
```

---

## ⚠️ Prerequisitos

### 1. AvailabilityIds actualizados
Los productos deben tener AvailabilityIds válidos y actualizados:

```bash
# Sincronizar antes de probar
php artisan products:sync-availabilities --force
```

### 2. Producto disponible
El producto debe tener `is_available = 1`:

```sql
SELECT idproduct, ProductTitle, is_available 
FROM products 
WHERE idproduct = 168;
```

### 3. Usuario válido
El usuario debe existir y tener información completa:

```sql
SELECT id, name, email 
FROM users 
WHERE id = 1;
```

### 4. Credenciales de Partner Center
Las credenciales de Microsoft Partner Center deben estar configuradas en `.env`:

```env
PARTNER_CENTER_TENANT_ID=fa233b05-e848-45c4-957f-d3e11acfc49c
PARTNER_CENTER_CLIENT_ID=f5f50108-210a-4ae6-a3cc-86045bff57e7
PARTNER_CENTER_CLIENT_SECRET=your-secret-here
```

---

## 🔍 Verificación Post-Ejecución

### 1. Verificar orden en base de datos

```sql
SELECT 
    id,
    order_number,
    status,
    fulfillment_status,
    total_amount,
    processed_at
FROM orders
ORDER BY id DESC
LIMIT 1;
```

### 2. Verificar suscripciones creadas

```sql
SELECT 
    id,
    subscription_id,
    friendly_name,
    quantity,
    pricing,
    status
FROM subscriptions
WHERE order_id = <order_id>;
```

### 3. Verificar cuenta Microsoft

```sql
SELECT 
    id,
    microsoft_id,
    domain_concatenated,
    is_active
FROM microsoft_accounts
ORDER BY id DESC
LIMIT 1;
```

### 4. Verificar en Microsoft Partner Center

```bash
# Ver suscripciones del cliente
curl -X GET \
  "https://api.partnercenter.microsoft.com/v1/customers/{customer-id}/subscriptions" \
  -H "Authorization: Bearer {token}"
```

---

## 🐛 Troubleshooting

### Error: Usuario no encontrado
```
✗ Usuario con ID {id} no encontrado
```

**Solución:** Verifica que el usuario existe en la base de datos.

```sql
SELECT id, name FROM users WHERE id = 1;
```

---

### Error: Producto no disponible
```
✗ Producto ID {id} no encontrado o no disponible
```

**Solución:** Sincroniza availabilities y verifica el producto.

```bash
php artisan products:sync-availabilities --force
```

```sql
SELECT idproduct, ProductTitle, is_available 
FROM products 
WHERE idproduct = 168;
```

---

### Error: HTTP 400 - Error code 800002
```
Error: Failed to checkout cart in Microsoft Partner Center: HTTP 400
Código de Error: 800002
Descripción: Cart has line items with errors
```

**Causa:** AvailabilityId desactualizado o inválido.

**Solución:**
1. Sincronizar availabilities:
   ```bash
   php artisan products:sync-availabilities --force
   ```

2. Verificar el producto específico:
   ```bash
   php artisan tinker
   ```
   ```php
   $product = DB::table('products')->where('idproduct', 168)->first();
   echo $product->Id; // AvailabilityId
   ```

3. Consultar availability actual en Microsoft:
   ```php
   // En tinker
   $service = app('App\Services\MicrosoftAuthService');
   $token = $service->getAccessToken();
   $response = Http::withToken($token)
       ->get("https://api.partnercenter.microsoft.com/v1/products/CFQ7TTC0LF8Q/skus/0001/availabilities?country=MX");
   $response->json();
   ```

---

### Error: HTTP 400 - Error code 800074
```
Error code: 800074
Description: The customer is in review status
```

**Causa:** Cuenta Microsoft recién creada está en revisión.

**Solución:** 
1. Esperar 5-10 minutos para que Microsoft active la cuenta
2. O usar cuenta existente:
   ```bash
   php artisan orders:test-full-flow --user-id=1 --skip-account --account-id=42
   ```

---

### Error: Duplicate entry for unique_user_active_cart
```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-1'
```

**Causa:** El usuario ya tiene un carrito activo.

**Solución:** El comando maneja esto automáticamente reutilizando el carrito existente. Si persiste:

```sql
-- Ver carritos activos del usuario
SELECT id, user_id, status FROM carts WHERE user_id = 1 AND status = 'active';

-- Limpiar carritos antiguos (si es necesario)
UPDATE carts SET status = 'completed' WHERE user_id = 1 AND status = 'active';
```

---

## 📈 Casos de Uso

### 1. Testing después de deployment
Ejecutar después de cada deploy para validar integración con Microsoft:

```bash
php artisan orders:test-full-flow --user-id=1
```

---

### 2. Validación de productos nuevos
Probar aprovisionamiento de producto específico:

```bash
# Producto recién agregado
php artisan orders:test-full-flow --user-id=1 --product-id=250
```

---

### 3. Pruebas de escalabilidad
Probar con múltiples licencias:

```bash
php artisan orders:test-full-flow --user-id=1 --quantity=100
```

---

### 4. Debugging de aprovisionamiento
El comando incluye salida detallada para debugging:

```bash
php artisan orders:test-full-flow --user-id=1 -v
```

Muestra:
- CatalogItemId generado
- ProductId, SkuId, AvailabilityId
- Detalles de errores de Microsoft
- IDs de correlación y request

---

## 🔗 Comandos Relacionados

### Sincronizar productos antes de probar
```bash
php artisan products:sync-availabilities --force
```

### Ver órdenes creadas
```bash
php artisan tinker --execute="DB::table('orders')->orderBy('id', 'desc')->limit(5)->get(['id', 'order_number', 'status', 'total_amount'])"
```

### Limpiar cuentas de prueba
```sql
-- Ver cuentas de prueba (rmcustomer*)
SELECT id, domain_concatenated, created_at 
FROM microsoft_accounts 
WHERE domain LIKE 'rmcustomer%' 
ORDER BY id DESC;

-- Opcional: Eliminar cuentas viejas de prueba
-- DELETE FROM microsoft_accounts WHERE domain LIKE 'rmcustomer%' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

---

## 💡 Notas Importantes

1. **Cuentas de Prueba:** Cada ejecución crea una nueva cuenta Microsoft con formato `rmcustomerTIMESTAMP.onmicrosoft.com`

2. **Costos Reales:** Este comando crea órdenes y suscripciones REALES en Microsoft Partner Center. Usa con precaución en producción.

3. **Rate Limiting:** Microsoft limita las requests. No ejecutes este comando muchas veces seguidas.

4. **Scheduler Sync:** Los AvailabilityIds se sincronizan automáticamente cada lunes a las 4:00 AM, pero puedes forzar sincronización antes de probar.

5. **Debug Mode:** El comando incluye salida detallada del CatalogItemId y errores de Microsoft para facilitar debugging.

6. **Rollback:** El comando NO hace rollback automático. Las suscripciones creadas persisten en Microsoft.

---

## ✅ Checklist Pre-Ejecución

Antes de ejecutar el comando, verifica:

- [ ] AvailabilityIds actualizados (`php artisan products:sync-availabilities --force`)
- [ ] Producto disponible (`is_available = 1`)
- [ ] Usuario existe en base de datos
- [ ] Credenciales de Partner Center configuradas
- [ ] Conexión a base de datos activa
- [ ] Ambiente correcto (desarrollo/staging/producción)

---

## 📚 Ver También

- [Sincronización de Product Availabilities](./PRODUCT_AVAILABILITIES_SYNC.md)
- [Documentación de Microsoft Partner Center API](https://learn.microsoft.com/en-us/partner-center/develop/)
