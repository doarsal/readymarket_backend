# 📊 Análisis: Página de Licencias de Usuario

## ✅ CONCLUSIÓN: **SÍ ES POSIBLE** crear la página de licencias

Tu sistema **YA TIENE** toda la infraestructura necesaria para mostrar las licencias de los usuarios.

---

## 🏗️ Infraestructura Existente

### 1. **Tabla `subscriptions`** ✅

```sql
CREATE TABLE subscriptions (
    id BIGINT PRIMARY KEY,
    order_id BIGINT,                    -- Relación con pedido
    subscription_identifier VARCHAR,    -- Número de orden
    offer_id VARCHAR,                   -- ID de oferta Microsoft
    subscription_id VARCHAR,            -- ID de suscripción en Microsoft
    term_duration VARCHAR,              -- Duración (P1M, P1Y, P3Y)
    transaction_type VARCHAR,           -- New, Renew, Upgrade
    friendly_name VARCHAR,              -- Nombre descriptivo
    quantity INT,                       -- Cantidad de licencias
    pricing DECIMAL(10,2),             -- Precio
    status INT,                        -- 1=Activa, 0=Inactiva
    microsoft_account_id BIGINT,       -- Cuenta Microsoft del usuario
    product_id INT,                    -- Producto asociado
    sku_id VARCHAR,                    -- SKU de Microsoft
    created_by VARCHAR,
    modified_by VARCHAR,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

**Índices para optimizar consultas:**
- ✅ `idx_subscriptions_order_status` (order_id, status)
- ✅ `idx_subscriptions_ms_account_status` (microsoft_account_id, status)
- ✅ `idx_subscriptions_identifier` (subscription_identifier)
- ✅ `idx_subscriptions_ms_id` (subscription_id)
- ✅ `idx_subscriptions_sku` (sku_id)

### 2. **Modelo `Subscription`** ✅

```php
// backend/app/Models/Subscription.php
class Subscription extends Model
{
    // Relaciones existentes
    public function order()              // → Pedido origen
    public function microsoftAccount()   // → Cuenta Microsoft del usuario
    public function product()            // → Detalles del producto
    
    // Scopes útiles
    public function scopeActive($query)                    // Solo activas
    public function scopeForMicrosoftAccount($query, $id)  // Por cuenta
}
```

### 3. **Relaciones en Modelo `Order`** ✅

```php
// backend/app/Models/Order.php
class Order extends Model
{
    public function subscriptions(): HasMany  // ✅ Ya existe
    public function user(): BelongsTo         // ✅ Usuario propietario
    public function microsoftAccount()        // ✅ Cuenta Microsoft
    public function orderItems()              // ✅ Items del pedido
}
```

---

## 📋 Tipos de Licencias que Puedes Mostrar

### **Basado en `transaction_type` y datos del producto:**

| Tipo | Campo Identificador | Ejemplo |
|------|-------------------|---------|
| **Suscripciones** | `transaction_type` = 'New' o 'Renew' | Microsoft 365, Office 365 |
| **Licencias Perpetuas** | `product.BillingPlan` = 'OneTime' | Office 2021 Professional |
| **Reservas de Azure** | `product.ProductTitle` LIKE '%Reserved%' | Azure Reserved Instances |
| **Créditos Azure** | `product.ProductTitle` LIKE '%Prepago%' | Azure Credits |

---

## 🎨 Propuesta de Página de Licencias

### **Ubicación Sugerida:**
```
Perfil de Usuario → Licencias
```

### **Pestañas Sugeridas:**

1. **Todas las Licencias** - Vista general
2. **Activas** - Solo licencias en uso
3. **Expiradas/Canceladas** - Historial
4. **Por Cuenta Microsoft** - Agrupadas por cuenta

---

## 💻 Implementación Backend

### **Endpoint Propuesto:**

```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function() {
    // Licencias del usuario autenticado
    Route::get('/user/licenses', [LicenseController::class, 'index']);
    Route::get('/user/licenses/{id}', [LicenseController::class, 'show']);
    
    // Por cuenta Microsoft
    Route::get('/user/microsoft-accounts/{accountId}/licenses', 
        [LicenseController::class, 'byAccount']);
});
```

### **Controlador:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Subscription;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    /**
     * Obtener todas las licencias del usuario autenticado
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Obtener todas las suscripciones a través de las órdenes del usuario
        $licenses = Subscription::query()
            ->whereHas('order', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'product',              // Información del producto
                'order',                // Datos del pedido
                'microsoftAccount'      // Cuenta Microsoft
            ])
            ->when($request->status, function($query, $status) {
                // Filtrar por estado: active, inactive, all
                if ($status === 'active') {
                    return $query->where('status', 1);
                } elseif ($status === 'inactive') {
                    return $query->where('status', 0);
                }
                return $query;
            })
            ->when($request->type, function($query, $type) {
                // Filtrar por tipo: subscription, perpetual, azure
                if ($type === 'subscription') {
                    return $query->whereIn('transaction_type', ['New', 'Renew']);
                } elseif ($type === 'perpetual') {
                    return $query->whereHas('product', function($q) {
                        $q->where('BillingPlan', 'OneTime')
                          ->where('ProductTitle', 'like', '%Perpetual%');
                    });
                }
                return $query;
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $licenses->map(function($license) {
                return [
                    'id' => $license->id,
                    'product_name' => $license->product->ProductTitle,
                    'product_icon' => $license->product->prod_icon,
                    'sku_title' => $license->product->SkuTitle,
                    'quantity' => $license->quantity,
                    'status' => $license->status ? 'Activa' : 'Inactiva',
                    'status_color' => $license->status ? 'success' : 'danger',
                    'type' => $this->determineLicenseType($license),
                    'microsoft_subscription_id' => $license->subscription_id,
                    'term_duration' => $license->term_duration,
                    'pricing' => $license->pricing,
                    'microsoft_account' => [
                        'id' => $license->microsoftAccount->id,
                        'domain' => $license->microsoftAccount->domain_concatenated,
                        'name' => $license->microsoftAccount->business_name,
                    ],
                    'order' => [
                        'id' => $license->order->id,
                        'order_number' => $license->order->order_number,
                        'created_at' => $license->order->created_at,
                    ],
                    'created_at' => $license->created_at,
                    'updated_at' => $license->updated_at,
                ];
            }),
            'meta' => [
                'current_page' => $licenses->currentPage(),
                'total' => $licenses->total(),
                'per_page' => $licenses->perPage(),
            ]
        ]);
    }
    
    /**
     * Determinar el tipo de licencia
     */
    private function determineLicenseType($license)
    {
        $product = $license->product;
        
        if ($product->BillingPlan === 'OneTime') {
            if (stripos($product->ProductTitle, 'Perpetual') !== false) {
                return 'perpetual';
            }
            return 'one_time';
        }
        
        if (stripos($product->ProductTitle, 'Azure') !== false) {
            if (stripos($product->ProductTitle, 'Reserved') !== false) {
                return 'azure_reservation';
            }
            if (stripos($product->ProductTitle, 'Prepago') !== false) {
                return 'azure_credit';
            }
            return 'azure_plan';
        }
        
        return 'subscription';
    }
    
    /**
     * Obtener licencias por cuenta Microsoft
     */
    public function byAccount(Request $request, $accountId)
    {
        $user = $request->user();
        
        // Verificar que la cuenta pertenece al usuario
        $microsoftAccount = $user->microsoftAccounts()
            ->where('id', $accountId)
            ->firstOrFail();
        
        $licenses = Subscription::where('microsoft_account_id', $accountId)
            ->with(['product', 'order'])
            ->active()
            ->get();
        
        return response()->json([
            'success' => true,
            'microsoft_account' => [
                'id' => $microsoftAccount->id,
                'domain' => $microsoftAccount->domain_concatenated,
                'business_name' => $microsoftAccount->business_name,
            ],
            'licenses' => $licenses,
            'total_licenses' => $licenses->sum('quantity'),
            'total_products' => $licenses->count(),
        ]);
    }
}
```

---

## 🎨 Implementación Frontend (Vue)

### **Ubicación:**
```
vue/src/views/profile/UserLicenses.vue
```

### **Estructura Sugerida:**

```vue
<template>
  <div class="card">
    <!-- Header con filtros -->
    <div class="card-header border-0 pt-6">
      <div class="card-title">
        <h3 class="fw-bold">Mis Licencias</h3>
      </div>
      
      <div class="card-toolbar">
        <!-- Filtro por estado -->
        <select v-model="filters.status" class="form-select me-3">
          <option value="all">Todas</option>
          <option value="active">Activas</option>
          <option value="inactive">Inactivas</option>
        </select>
        
        <!-- Filtro por tipo -->
        <select v-model="filters.type" class="form-select">
          <option value="all">Todos los tipos</option>
          <option value="subscription">Suscripciones</option>
          <option value="perpetual">Licencias Perpetuas</option>
          <option value="azure">Azure</option>
        </select>
      </div>
    </div>
    
    <!-- Body con tabla de licencias -->
    <div class="card-body">
      <!-- Loading state -->
      <div v-if="loading" class="text-center py-10">
        <span class="spinner-border text-primary"></span>
      </div>
      
      <!-- Lista de licencias -->
      <div v-else>
        <!-- Por cada cuenta Microsoft -->
        <div 
          v-for="account in groupedLicenses" 
          :key="account.id"
          class="mb-10"
        >
          <!-- Header de cuenta Microsoft -->
          <div class="d-flex align-items-center mb-5">
            <i class="ki-duotone ki-microsoft fs-2x text-primary me-3">
              <span class="path1"></span>
              <span class="path2"></span>
            </i>
            <div>
              <h4 class="mb-0">{{ account.domain }}</h4>
              <span class="text-muted">{{ account.total_licenses }} licencias</span>
            </div>
          </div>
          
          <!-- Tabla de licencias -->
          <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6">
              <thead>
                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                  <th>Producto</th>
                  <th>Tipo</th>
                  <th>Cantidad</th>
                  <th>Estado</th>
                  <th>Fecha de compra</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="license in account.licenses" :key="license.id">
                  <!-- Producto -->
                  <td>
                    <div class="d-flex align-items-center">
                      <img 
                        v-if="license.product_icon"
                        :src="license.product_icon" 
                        class="w-40px me-3"
                        :alt="license.product_name"
                      >
                      <div>
                        <div class="fw-bold">{{ license.product_name }}</div>
                        <div class="text-muted fs-7">{{ license.sku_title }}</div>
                      </div>
                    </div>
                  </td>
                  
                  <!-- Tipo -->
                  <td>
                    <span 
                      class="badge"
                      :class="getLicenseTypeBadge(license.type)"
                    >
                      {{ getLicenseTypeLabel(license.type) }}
                    </span>
                  </td>
                  
                  <!-- Cantidad -->
                  <td>
                    <span class="badge badge-light-primary fs-6">
                      {{ license.quantity }} {{ license.quantity > 1 ? 'licencias' : 'licencia' }}
                    </span>
                  </td>
                  
                  <!-- Estado -->
                  <td>
                    <span 
                      class="badge"
                      :class="`badge-light-${license.status_color}`"
                    >
                      {{ license.status }}
                    </span>
                  </td>
                  
                  <!-- Fecha -->
                  <td>{{ formatDate(license.created_at) }}</td>
                  
                  <!-- Acciones -->
                  <td class="text-end">
                    <button 
                      @click="viewLicenseDetails(license)"
                      class="btn btn-sm btn-light-primary"
                    >
                      Ver detalles
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Sin licencias -->
        <div v-if="groupedLicenses.length === 0" class="text-center py-10">
          <i class="ki-duotone ki-file-deleted fs-5x text-gray-400 mb-5">
            <span class="path1"></span>
            <span class="path2"></span>
          </i>
          <h3 class="text-gray-700">No tienes licencias aún</h3>
          <p class="text-muted">Comienza comprando productos en nuestra tienda</p>
          <router-link to="/products" class="btn btn-primary mt-5">
            Explorar productos
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import ApiService from '@/core/services/ApiService'

// State
const loading = ref(true)
const licenses = ref([])
const filters = ref({
  status: 'active',
  type: 'all'
})

// Computed
const groupedLicenses = computed(() => {
  // Agrupar licencias por cuenta Microsoft
  const groups = {}
  
  licenses.value.forEach(license => {
    const accountId = license.microsoft_account.id
    
    if (!groups[accountId]) {
      groups[accountId] = {
        id: accountId,
        domain: license.microsoft_account.domain,
        name: license.microsoft_account.name,
        total_licenses: 0,
        licenses: []
      }
    }
    
    groups[accountId].total_licenses += license.quantity
    groups[accountId].licenses.push(license)
  })
  
  return Object.values(groups)
})

// Methods
const fetchLicenses = async () => {
  loading.value = true
  try {
    const response = await ApiService.get('/user/licenses', {
      params: filters.value
    })
    licenses.value = response.data.data
  } catch (error) {
    console.error('Error fetching licenses:', error)
  } finally {
    loading.value = false
  }
}

const getLicenseTypeBadge = (type: string) => {
  const badges = {
    'subscription': 'badge-light-success',
    'perpetual': 'badge-light-info',
    'azure_plan': 'badge-light-primary',
    'azure_reservation': 'badge-light-warning',
    'azure_credit': 'badge-light-secondary',
  }
  return badges[type] || 'badge-light'
}

const getLicenseTypeLabel = (type: string) => {
  const labels = {
    'subscription': 'Suscripción',
    'perpetual': 'Perpetua',
    'azure_plan': 'Azure Plan',
    'azure_reservation': 'Reserva Azure',
    'azure_credit': 'Crédito Azure',
  }
  return labels[type] || type
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('es-MX', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const viewLicenseDetails = (license) => {
  // Abrir modal o navegar a página de detalles
  console.log('Ver detalles de:', license)
}

// Lifecycle
onMounted(() => {
  fetchLicenses()
})

// Watchers
watch(filters, () => {
  fetchLicenses()
}, { deep: true })
</script>
```

---

## 🔗 Agregar a la Navegación

### **En `UserProfileHeader.vue`:**

```vue
<li class="nav-item mt-2">
  <router-link 
    to="/profile/licenses"
    class="nav-link text-active-primary ms-0 me-10 py-5"
    :class="{ 'active': currentPage === 'licenses' }"
  >
    <i class="fas fa-key text-primary me-2"></i>
    Mis Licencias
    <span class="badge badge-circle badge-primary ms-2">{{ totalActiveLicenses }}</span>
  </router-link>
</li>
```

### **En `router/index.ts`:**

```typescript
{
  path: '/profile/licenses',
  name: 'profile-licenses',
  component: () => import('@/views/profile/UserLicenses.vue'),
  meta: {
    requiresAuth: true,
    pageTitle: 'Mis Licencias',
    breadcrumbs: ['Perfil', 'Licencias']
  }
}
```

---

## 📊 Estadísticas en el Dashboard

Podrías agregar un widget de resumen:

```vue
<!-- Widget en ProfileOverview.vue -->
<div class="card mb-5">
  <div class="card-header">
    <h3 class="card-title">Resumen de Licencias</h3>
  </div>
  <div class="card-body">
    <div class="row g-5">
      <!-- Total Licencias -->
      <div class="col-md-3">
        <div class="text-center">
          <i class="ki-duotone ki-key fs-3x text-primary mb-3"></i>
          <div class="fs-2x fw-bold">{{ stats.total_licenses }}</div>
          <div class="text-muted">Total Licencias</div>
        </div>
      </div>
      
      <!-- Activas -->
      <div class="col-md-3">
        <div class="text-center">
          <i class="ki-duotone ki-check-circle fs-3x text-success mb-3"></i>
          <div class="fs-2x fw-bold">{{ stats.active_licenses }}</div>
          <div class="text-muted">Activas</div>
        </div>
      </div>
      
      <!-- Productos -->
      <div class="col-md-3">
        <div class="text-center">
          <i class="ki-duotone ki-element-11 fs-3x text-info mb-3"></i>
          <div class="fs-2x fw-bold">{{ stats.total_products }}</div>
          <div class="text-muted">Productos</div>
        </div>
      </div>
      
      <!-- Cuentas -->
      <div class="col-md-3">
        <div class="text-center">
          <i class="ki-duotone ki-microsoft fs-3x text-warning mb-3"></i>
          <div class="fs-2x fw-bold">{{ stats.microsoft_accounts }}</div>
          <div class="text-muted">Cuentas MS</div>
        </div>
      </div>
    </div>
  </div>
</div>
```

---

## ✅ Resumen de Capacidades

| Capacidad | Estado | Nota |
|-----------|--------|------|
| Mostrar todas las licencias | ✅ Listo | Tabla `subscriptions` ya existe |
| Filtrar por estado (activa/inactiva) | ✅ Listo | Campo `status` disponible |
| Filtrar por tipo (suscripción/perpetua) | ✅ Listo | Basado en `product.BillingPlan` |
| Agrupar por cuenta Microsoft | ✅ Listo | Relación `microsoft_account_id` |
| Ver detalles del producto | ✅ Listo | Relación con `products` |
| Ver pedido origen | ✅ Listo | Relación con `orders` |
| Historial de compras | ✅ Listo | Timestamp `created_at` |
| Cantidad de licencias | ✅ Listo | Campo `quantity` |
| ID de Microsoft | ✅ Listo | Campo `subscription_id` |
| Precio de compra | ✅ Listo | Campo `pricing` |

---

## 🚀 Plan de Implementación

### **Fase 1: Backend (2-3 horas)**
1. ✅ Crear `LicenseController`
2. ✅ Agregar rutas API
3. ✅ Probar endpoints con Postman
4. ✅ Documentar API con Swagger

### **Fase 2: Frontend (4-5 horas)**
1. ✅ Crear vista `UserLicenses.vue`
2. ✅ Agregar a navegación
3. ✅ Implementar filtros y búsqueda
4. ✅ Crear modal de detalles
5. ✅ Integrar con API

### **Fase 3: Mejoras (2-3 horas)**
1. ✅ Widget de estadísticas en dashboard
2. ✅ Exportar a Excel/PDF
3. ✅ Notificaciones de expiración
4. ✅ Renovación automática desde UI

---

## 💡 Mejoras Futuras

1. **Sincronización con Microsoft:**
   - Consultar estado real en Microsoft Partner Center
   - Actualizar automáticamente cambios

2. **Gestión de Licencias:**
   - Reasignar licencias entre usuarios
   - Cancelar suscripciones
   - Upgrades/Downgrades

3. **Alertas:**
   - Notificar 30 días antes de expiración
   - Alertar cuando se agoten licencias

4. **Reportes:**
   - Uso de licencias por periodo
   - Costo total de licencias
   - ROI por producto

---

## 📝 Conclusión

**✅ TU SISTEMA YA ESTÁ PREPARADO** para mostrar licencias. Solo necesitas:

1. Crear el controlador backend (1 hora)
2. Crear la vista frontend (3 horas)
3. Agregar a la navegación (30 minutos)

**Total estimado: ~5 horas de desarrollo**

¿Quieres que implemente alguna de estas partes?
