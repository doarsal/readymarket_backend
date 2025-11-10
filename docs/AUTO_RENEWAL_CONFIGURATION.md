# Configuración de Renovación Automática de Suscripciones Microsoft

## 📋 Descripción General

Este documento describe cómo funciona la renovación automática de suscripciones de Microsoft en el sistema de aprovisionamiento.

## 🎯 ¿Qué es la Renovación Automática?

La renovación automática permite que las suscripciones de Microsoft se renueven automáticamente al final de su término, sin necesidad de intervención manual. Esto garantiza continuidad del servicio para el cliente.

## ⚙️ Configuración

### Variables de Entorno (.env)

```bash
# Habilitar/deshabilitar renovación automática global
MICROSOFT_AUTO_RENEW_SUBSCRIPTIONS=true

# Duración del término de renovación (P1M, P1Y, P3Y)
MICROSOFT_DEFAULT_RENEWAL_TERM=P1Y
```

### Valores Soportados para `MICROSOFT_DEFAULT_RENEWAL_TERM`

| Valor | Descripción | Uso Recomendado |
|-------|-------------|-----------------|
| `P1M` | 1 mes | Productos mensuales |
| `P1Y` | 1 año | **Recomendado** - Mayoría de productos |
| `P3Y` | 3 años | Compromisos de largo plazo |

## 🔍 Identificación de Productos que Soportan Renovación

El sistema utiliza los datos existentes de Microsoft (sin modificar la tabla `products`) para determinar si un producto soporta renovación automática.

### Reglas de Elegibilidad

#### ✅ **Productos que SÍ soportan renovación automática:**

1. **NCE License-Based Subscriptions**
   - Microsoft 365 Business Basic/Standard/Premium
   - Office 365 E1/E3/E5
   - Microsoft Teams
   - Exchange Online
   - SharePoint Online
   - **Criterio:** `BillingPlan` = `Monthly`, `Annual`, o `Triennial`

2. **Software Subscriptions**
   - Productos con suscripción recurrente
   - **Criterio:** Tiene `TermDuration` y billing recurrente

#### ❌ **Productos que NO soportan renovación automática:**

1. **One-Time Purchases**
   - Azure Reservations
   - **Criterio:** `BillingPlan` = `OneTime` o `one_time`

2. **Perpetual Licenses**
   - Office 2021 Professional
   - Windows Server Perpetual
   - **Criterio:** `ProductTitle` contiene "Perpetual"

3. **Azure Prepaid Credits**
   - Créditos de Azure prepagados
   - **Criterio:** `TermDuration` = `P1M` Y `ProductTitle` contiene "Prepago" o "Prepaid"

4. **Reserved Instances**
   - Azure Reserved Instances
   - **Criterio:** `ProductTitle` contiene "Reserved Instance" o "Reservation"

5. **Products without Term Duration**
   - Productos sin término definido
   - **Criterio:** `TermDuration` está vacío

### Campos de Microsoft Utilizados

El sistema analiza estos campos existentes de la tabla `products`:

- **`BillingPlan`**: Ciclo de facturación (Monthly, Annual, OneTime, etc.)
- **`TermDuration`**: Duración del término (P1M, P1Y, P3Y)
- **`ProductTitle`**: Título del producto (para identificar casos especiales)

## 💻 Uso Programático

### En el Modelo Product

```php
// Verificar si un producto soporta renovación
$product = Product::find($id);

if ($product->supportsAutoRenew()) {
    echo "Este producto soporta renovación automática";
} else {
    $reason = $product->getAutoRenewIneligibilityReason();
    echo "No soporta renovación: {$reason}";
}
```

### Ejemplos de Productos

```php
// Microsoft 365 Business Basic (Monthly)
BillingPlan: "Monthly"
TermDuration: "P1M"
→ ✅ Soporta auto-renew

// Office 2021 Professional (Perpetual)
BillingPlan: "OneTime"
ProductTitle: "Office Professional 2021 - Perpetual"
→ ❌ NO soporta (perpetual license)

// Azure Reserved Instance
BillingPlan: "OneTime"
ProductTitle: "Azure Reserved VM Instance"
→ ❌ NO soporta (one-time purchase)

// Azure Prepaid Credit
BillingPlan: "Monthly"
TermDuration: "P1M"
ProductTitle: "Azure en Licencias Abiertas - Prepago por mes"
→ ❌ NO soporta (prepaid credit)
```

## 🔧 Funcionamiento Técnico

### Flujo de Aprovisionamiento

1. **Creación del Line Item** (`prepareSingleLineItem`)
   ```php
   $lineItem = [
       'id' => 0,
       'catalogItemId' => $catalogItemId,
       'quantity' => $quantity,
       'billingCycle' => $billingCycle,
       'termDuration' => $termDuration
   ];
   ```

2. **Evaluación de Elegibilidad**
   ```php
   if ($this->productSupportsAutoRenew($product)) {
       // Producto es elegible para auto-renew
   }
   ```

3. **Agregado de Configuración de Renovación**
   ```php
   $lineItem['renewsTo'] = [
       'termDuration' => 'P1Y' // Desde configuración
   ];
   ```

4. **Envío a Microsoft Partner Center**
   - El API de Microsoft recibe el line item con `renewsTo`
   - La suscripción se crea con renovación automática habilitada

### Logs

El sistema registra información detallada en los logs:

```log
[INFO] Auto-renewal configured for product
  product_id: 123
  product_title: "Microsoft 365 Business Basic"
  billing_plan: "Monthly"
  term_duration: "P1M"
  renewal_term: "P1Y"

[INFO] Product does not support auto-renewal
  product_id: 456
  product_title: "Office 2021 Professional - Perpetual"
  billing_plan: "OneTime"
  term_duration: ""
```

## 🎛️ Control y Configuración

### Deshabilitar Renovación Global

Para deshabilitar la renovación automática para TODOS los productos:

```bash
MICROSOFT_AUTO_RENEW_SUBSCRIPTIONS=false
```

### Cambiar Término de Renovación

Para cambiar el término de renovación por defecto:

```bash
# Renovación mensual
MICROSOFT_DEFAULT_RENEWAL_TERM=P1M

# Renovación anual (recomendado)
MICROSOFT_DEFAULT_RENEWAL_TERM=P1Y

# Renovación trienal
MICROSOFT_DEFAULT_RENEWAL_TERM=P3Y
```

## 📊 Impacto en Base de Datos

**NINGUNO** - Esta implementación NO modifica la estructura de la tabla `products`.

Utiliza únicamente los campos existentes que ya vienen de Microsoft:
- `BillingPlan`
- `TermDuration`
- `ProductTitle`

## 🚨 Consideraciones Importantes

1. **No todos los productos soportan renovación automática**
   - El sistema evalúa automáticamente cada producto
   - Solo productos elegibles recibirán configuración de auto-renew

2. **Microsoft puede rechazar la configuración**
   - Si un producto no soporta `renewsTo`, Microsoft lo ignorará silenciosamente
   - No causará error en el aprovisionamiento

3. **La configuración es a nivel de line item**
   - Cada producto en un carrito se evalúa individualmente
   - Algunos productos pueden tener auto-renew, otros no

4. **Logs detallados**
   - Revisar logs para entender qué productos tienen auto-renew
   - Nivel DEBUG muestra todas las evaluaciones

## 🔗 Referencias

- [Microsoft Partner Center API - Cart Resources](https://learn.microsoft.com/en-us/partner-center/developer/cart-resources)
- [Microsoft Partner Center API - Subscription Resources](https://learn.microsoft.com/en-us/partner-center/developer/subscription-resources)
- [Microsoft Partner Center API - Product Resources](https://learn.microsoft.com/en-us/partner-center/developer/product-resources)

## 📝 Historial de Cambios

### 2025-10-30
- Implementación inicial de renovación automática
- Análisis basado en datos existentes de Microsoft
- Sin modificaciones a estructura de BD
