# Flujo de Decisión: Renovación Automática

## 🎯 ¿CUÁNDO se Habilita la Renovación Automática?

### Respuesta Corta:
**SE HABILITA AUTOMÁTICAMENTE** al aprovisionar productos que cumplan los criterios de elegibilidad.

---

## 📊 Diagrama de Flujo de Decisión

```
INICIO: Aprovisionamiento de Producto
    ↓
┌─────────────────────────────────────────┐
│ ¿MICROSOFT_AUTO_RENEW_SUBSCRIPTIONS=true? │
└─────────────────────────────────────────┘
    │
    ├─ NO → ❌ NO se configura renovación (PARA NINGÚN PRODUCTO)
    │
    └─ SÍ → Continuar evaluación por producto
              ↓
        ┌──────────────────────────┐
        │ BillingPlan del producto │
        └──────────────────────────┘
              ↓
        ┌─────────────────────────────────────┐
        │ ¿Es "OneTime" o "None"?             │
        └─────────────────────────────────────┘
              ├─ SÍ → ❌ NO renovable (compra única)
              │
              └─ NO → Continuar
                        ↓
                  ┌──────────────────┐
                  │ TermDuration     │
                  └──────────────────┘
                        ↓
                  ┌─────────────────────┐
                  │ ¿Está vacío?        │
                  └─────────────────────┘
                        ├─ SÍ → ❌ NO renovable
                        │
                        └─ NO → Continuar
                                  ↓
                            ┌─────────────────────────────┐
                            │ Análisis del ProductTitle    │
                            └─────────────────────────────┘
                                  ↓
                            ┌──────────────────────────────────────┐
                            │ ¿Contiene "Perpetual"?               │
                            └──────────────────────────────────────┘
                                  ├─ SÍ → ❌ NO renovable (licencia perpetua)
                                  │
                                  └─ NO → Continuar
                                            ↓
                                      ┌────────────────────────────────────┐
                                      │ ¿Contiene "Prepago" o "Prepaid"    │
                                      │ Y TermDuration = "P1M"?            │
                                      └────────────────────────────────────┘
                                            ├─ SÍ → ❌ NO renovable (crédito prepago)
                                            │
                                            └─ NO → Continuar
                                                      ↓
                                                ┌────────────────────────────────┐
                                                │ ¿Contiene "Reserved Instance"  │
                                                │ o "Reservation"?               │
                                                └────────────────────────────────┘
                                                      ├─ SÍ → ❌ NO renovable
                                                      │
                                                      └─ NO → Continuar
                                                                ↓
                                                          ┌─────────────────────────┐
                                                          │ BillingPlan es          │
                                                          │ Monthly/Annual/Triennial│
                                                          └─────────────────────────┘
                                                                ↓
                                                                ├─ SÍ → ✅ RENOVABLE
                                                                │
                                                                └─ NO → ❌ NO renovable
```

---

## 🤖 DECISIÓN AUTOMÁTICA - NO hay Intervención del Usuario

### ¿Dónde activa el usuario la renovación?
**NINGUNA PARTE** - Es completamente automático.

### ¿El usuario puede elegir?
**NO** - El sistema decide basándose en:
1. Configuración global (`.env`)
2. Tipo de producto (análisis automático)

### ¿Se fuerza la renovación?
**NO se fuerza** - Solo se configura para productos que **naturalmente** soportan renovación según Microsoft.

---

## 📋 Ejemplos Prácticos

### Ejemplo 1: Microsoft 365 Business Basic

```php
// Datos del producto (de la BD)
BillingPlan: "Monthly"
TermDuration: "P1M"
ProductTitle: "Microsoft 365 Business Basic"

// Evaluación automática
✅ NO es OneTime → Continúa
✅ Tiene TermDuration (P1M) → Continúa
✅ NO contiene "Perpetual" → Continúa
✅ NO es prepago → Continúa
✅ NO es Reserved Instance → Continúa
✅ BillingPlan es "Monthly" → ✅ RENOVABLE

// Resultado
→ Se agrega renewsTo al aprovisionar
→ Microsoft crea suscripción con auto-renew ENABLED
```

### Ejemplo 2: Office 2021 Professional (Perpetual)

```php
// Datos del producto
BillingPlan: "OneTime"
TermDuration: ""
ProductTitle: "Office Professional 2021 - Perpetual"

// Evaluación automática
❌ Es OneTime → NO RENOVABLE

// Resultado
→ NO se agrega renewsTo
→ Microsoft crea compra única (sin renovación)
```

### Ejemplo 3: Azure Reserved Instance

```php
// Datos del producto
BillingPlan: "OneTime"
TermDuration: "P1Y"
ProductTitle: "Azure Reserved VM Instance - 1 Year"

// Evaluación automática
❌ Es OneTime → NO RENOVABLE

// Resultado
→ NO se agrega renewsTo
→ Microsoft crea reserva de 1 año (sin renovación)
```

### Ejemplo 4: Azure Prepaid Credit

```php
// Datos del producto
BillingPlan: "Monthly"
TermDuration: "P1M"
ProductTitle: "Azure en Licencias Abiertas - Prepago por mes"

// Evaluación automática
✅ NO es OneTime → Continúa
✅ Tiene TermDuration → Continúa
✅ NO contiene "Perpetual" → Continúa
❌ Contiene "Prepago" Y TermDuration="P1M" → NO RENOVABLE

// Resultado
→ NO se agrega renewsTo
→ Es crédito prepago (compra mensual sin auto-renew)
```

---

## ⚙️ Control del Administrador

### Opción 1: Deshabilitar TODA la Renovación Automática

```bash
# En .env
MICROSOFT_AUTO_RENEW_SUBSCRIPTIONS=false
```

**Efecto:** NINGÚN producto tendrá renovación automática, incluso si es elegible.

### Opción 2: Cambiar el Término de Renovación

```bash
# En .env
MICROSOFT_DEFAULT_RENEWAL_TERM=P1M  # Mensual
MICROSOFT_DEFAULT_RENEWAL_TERM=P1Y  # Anual (recomendado)
MICROSOFT_DEFAULT_RENEWAL_TERM=P3Y  # Trienal
```

**Efecto:** Los productos elegibles se renovarán por el período especificado.

### NO hay opción por producto individual
**Limitación actual:** No se puede habilitar/deshabilitar por producto específico, solo globalmente.

---

## 🔐 Seguridad y Validaciones

### ¿Qué pasa si forzamos renovación en producto no elegible?

```
Producto: Office 2021 Perpetual
Configuración forzada: renewsTo = "P1Y"
    ↓
Envío a Microsoft Partner Center
    ↓
Microsoft IGNORA el renewsTo (silenciosamente)
    ↓
Suscripción creada SIN auto-renew
    ↓
✅ NO genera error en aprovisionamiento
```

**Conclusión:** Es seguro intentar configurar renovación en cualquier producto. Microsoft lo ignora si no aplica.

---

## 🎯 Resumen Ejecutivo

| Pregunta | Respuesta |
|----------|-----------|
| ¿Cuándo se habilita? | **Automáticamente al aprovisionar** |
| ¿Quién decide? | **El sistema (análisis automático)** |
| ¿El usuario elige? | **NO - Es automático** |
| ¿Se fuerza? | **NO - Solo productos elegibles** |
| ¿Control del admin? | **SÍ - Variable global en .env** |
| ¿Por producto? | **NO - Solo global** |
| ¿Microsoft rechaza? | **NO - Ignora si no aplica** |

---

## 💡 Recomendación de Implementación

### Estado Actual: ✅ Implementado
- Decisión automática
- Sin intervención del usuario
- Control global via .env

### Mejora Futura (Opcional):
Si quieres dar control al usuario final:

```php
// Tabla: cart_items
ALTER TABLE cart_items 
ADD COLUMN enable_auto_renew TINYINT(1) DEFAULT 1;

// Al aprovisionar
if ($cartItem->enable_auto_renew && $product->supportsAutoRenew()) {
    // Configurar renovación
}
```

**Interfaz de Usuario:**
```
[ ] Habilitar renovación automática para este producto
    (solo disponible para productos compatibles)
```

---

## 📊 Estadísticas de Productos (Ejemplo)

En un catálogo típico de Microsoft:

| Categoría | % del Catálogo | Soporta Auto-Renew |
|-----------|----------------|---------------------|
| Microsoft 365 | 40% | ✅ SÍ |
| Office 365 | 25% | ✅ SÍ |
| Azure Reservations | 15% | ❌ NO |
| Perpetual Software | 10% | ❌ NO |
| Azure Credits | 10% | ❌ NO |

**Aproximadamente 65% de productos soportan renovación automática**

---

## 🔍 Debugging

### Ver qué productos tienen auto-renew en logs:

```log
[2025-10-30 10:15:23] INFO: Auto-renewal configured for product
    product_id: 123
    product_title: "Microsoft 365 Business Basic"
    billing_plan: "Monthly"
    renewal_term: "P1Y"

[2025-10-30 10:15:24] INFO: Product does not support auto-renewal
    product_id: 456
    product_title: "Office 2021 Professional - Perpetual"
    billing_plan: "OneTime"
```

### Consulta SQL para analizar productos:

```sql
-- Productos que soportarían auto-renew
SELECT 
    idproduct,
    ProductTitle,
    BillingPlan,
    TermDuration,
    CASE 
        WHEN BillingPlan IN ('OneTime', 'one_time', 'None') THEN 'NO - OneTime'
        WHEN TermDuration IS NULL OR TermDuration = '' THEN 'NO - Sin término'
        WHEN ProductTitle LIKE '%Perpetual%' THEN 'NO - Perpetual'
        WHEN ProductTitle LIKE '%Prepago%' OR ProductTitle LIKE '%Prepaid%' THEN 'NO - Prepago'
        WHEN ProductTitle LIKE '%Reserved%' OR ProductTitle LIKE '%Reservation%' THEN 'NO - Reserva'
        WHEN BillingPlan IN ('Monthly', 'Annual', 'Triennial') THEN 'SÍ - Renovable'
        ELSE 'NO - Otro'
    END AS auto_renew_status
FROM products
WHERE is_active = 1
ORDER BY auto_renew_status;
```

---

## ✅ Conclusión

La renovación automática es una **decisión del sistema**, NO del usuario:

1. ✅ **Automático** - Sin intervención manual
2. ✅ **Inteligente** - Basado en tipo de producto
3. ✅ **Seguro** - Microsoft valida la elegibilidad
4. ✅ **Configurable** - Control global via .env
5. ❌ **Sin UI** - Usuario final no elige (actualmente)

**Es un comportamiento "set and forget"** - Configuras una vez en .env y funciona automáticamente para todos los pedidos.
