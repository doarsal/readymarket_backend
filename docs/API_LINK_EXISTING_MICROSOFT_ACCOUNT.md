# Microsoft Accounts API - Link Existing Account Feature

## Descripción

Esta documentación describe la nueva funcionalidad para **vincular cuentas Microsoft existentes** que ya tienen un Global Admin configurado.

## Diferencias entre los dos tipos de cuentas

### 1. **Crear Nueva Cuenta** (`POST /api/v1/microsoft-accounts`)
- Crea una nueva cuenta Microsoft desde cero
- Requiere datos completos (organización, dirección, código postal, etc.)
- Se crea automáticamente en Microsoft Partner Center
- Se genera contraseña automática
- Estado: `is_pending: false`, `is_active: true`, `account_type: created`

### 2. **Vincular Cuenta Existente** (`POST /api/v1/microsoft-accounts/link-existing`)
- Vincula una cuenta Microsoft que ya existe
- Solo requiere dominio y email del Global Admin
- **NO** se crea en Partner Center (la cuenta ya existe)
- Se envían instrucciones por email al Global Admin
- Estado: `is_pending: true`, `is_active: false`, `account_type: linked`

---

## Endpoints

### 1. POST /api/v1/microsoft-accounts/link-existing

Vincula una cuenta Microsoft existente.

#### Request

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "domain": "miempresa.com",
  "global_admin_email": "admin@miempresa.onmicrosoft.com"
}
```

**Campos:**
- `domain` (required, string): Dominio de la organización (se convierte automáticamente a .onmicrosoft.com)
- `global_admin_email` (required, email): Email del Global Administrator

#### Response Success (201 Created)

```json
{
  "success": true,
  "data": {
    "id": 123,
    "microsoft_id": "pending-link-671a1234567890ab-1730000000",
    "domain": "miempresa",
    "domain_concatenated": "miempresa.onmicrosoft.com",
    "admin_email": "admin@miempresa.onmicrosoft.com",
    "global_admin_email": "admin@miempresa.onmicrosoft.com",
    "first_name": "Pending",
    "last_name": "Verification",
    "organization": "miempresa",
    "account_type": "linked",
    "is_pending": true,
    "is_active": false,
    "is_default": false,
    "status_text": "Pendiente",
    "created_at": "2025-10-25T16:00:00.000000Z"
  },
  "invitation_data": {
    "domain": "miempresa.onmicrosoft.com",
    "global_admin_email": "admin@miempresa.onmicrosoft.com",
    "urls": {
      "billing_profile": "https://admin.microsoft.com/Adminportal/Home?#/BillingAccounts/billing-accounts",
      "partner_invitation": "https://admin.microsoft.com/Adminportal/Home?invType=ResellerRelationship&partnerId=fa233b05-e848-45c4-957f-d3e11acfc49c&msppId=0&DAP=true#/BillingAccounts/partner-invitation",
      "admin_center": "https://admin.microsoft.com/#/homepage"
    },
    "partner": {
      "partner_name": "ReadyMarket of Readymind Mexico SA de CV",
      "partner_email": "backofficemex@readymind.ms",
      "partner_phone": "5585261168",
      "partner_id": "fa233b05-e848-45c4-957f-d3e11acfc49c"
    },
    "instructions": {
      "step_1": {
        "title": "Paso 1: Verificar perfil de facturación",
        "description": "Inicia sesión con tu cuenta de Global Admin...",
        "url": "https://admin.microsoft.com/Adminportal/Home?#/BillingAccounts/billing-accounts"
      },
      "step_2": {
        "title": "Paso 2: Aceptar invitación del Partner",
        "description": "Después de completar el perfil...",
        "url": "https://admin.microsoft.com/Adminportal/Home?invType=ResellerRelationship..."
      }
    }
  },
  "message": "Cuenta vinculada correctamente. Se han enviado las instrucciones por email.",
  "next_steps": [
    "Se ha registrado la cuenta como pendiente",
    "Revisa tu correo para seguir las instrucciones",
    "Debes aceptar la invitación desde el portal de Microsoft",
    "Una vez aceptada, la cuenta se activará automáticamente"
  ]
}
```

#### Response Error (422 Validation Error)

```json
{
  "message": "The domain field is required. (and 1 more error)",
  "errors": {
    "domain": [
      "El dominio ya está registrado para este usuario."
    ],
    "global_admin_email": [
      "El correo del Global Admin debe ser válido."
    ]
  }
}
```

---

### 2. PATCH /api/v1/microsoft-accounts/{id}/verify-link

Verifica y activa una cuenta vinculada después de que el Global Admin haya aceptado la invitación.

#### Request

**Headers:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (integer): ID de la cuenta Microsoft

#### Response Success (200 OK)

```json
{
  "success": true,
  "message": "Cuenta verificada y activada correctamente",
  "data": {
    "id": 123,
    "microsoft_id": "pending-link-671a1234567890ab-1730000000",
    "domain": "miempresa",
    "domain_concatenated": "miempresa.onmicrosoft.com",
    "account_type": "linked",
    "is_pending": false,
    "is_active": true,
    "status_text": "Activa",
    "created_at": "2025-10-25T16:00:00.000000Z",
    "updated_at": "2025-10-25T16:30:00.000000Z"
  }
}
```

#### Response Error (404 Not Found)

```json
{
  "success": false,
  "message": "Cuenta no encontrada"
}
```

#### Response Error (422 Unprocessable Entity)

```json
{
  "success": false,
  "message": "Esta cuenta no es una cuenta vinculada"
}
```

---

## Flujo Completo de Vinculación

### 1. Usuario inicia vinculación

```javascript
// Frontend realiza POST request
const response = await fetch('/api/v1/microsoft-accounts/link-existing', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    domain: 'miempresa.com',
    global_admin_email: 'admin@miempresa.onmicrosoft.com'
  })
});

const data = await response.json();
console.log(data.invitation_data.urls.partner_invitation);
```

### 2. Backend crea cuenta pendiente

- Se crea registro en BD con `is_pending: true`, `is_active: false`
- Se genera ID temporal
- Se envía email con instrucciones al Global Admin

### 3. Global Admin recibe email

El email contiene:
- Instrucciones paso a paso
- Links directos a:
  - Perfil de facturación
  - Aceptar invitación de partner
- Información de contacto del partner

### 4. Global Admin acepta invitación

El Global Admin debe:
1. Iniciar sesión en Microsoft Admin Center
2. Completar perfil de facturación
3. Hacer clic en el link de invitación
4. Aceptar la relación de CSP Partner

### 5. Usuario verifica cuenta

```javascript
// Frontend realiza PATCH request para verificar
const response = await fetch(`/api/v1/microsoft-accounts/${accountId}/verify-link`, {
  method: 'PATCH',
  headers: {
    'Authorization': 'Bearer ' + token
  }
});

const data = await response.json();
// Cuenta ahora está activa: is_active: true, is_pending: false
```

---

## Validaciones

### Campo: domain
- **Requerido**: Sí
- **Tipo**: String
- **Longitud máxima**: 150 caracteres
- **Limpieza automática**: Se remueven prefijos como `http://`, `https://`, `www.`
- **Validación**: No debe estar registrado para el usuario actual
- **Transformación**: Se agrega `.onmicrosoft.com` automáticamente

### Campo: global_admin_email
- **Requerido**: Sí
- **Tipo**: Email válido
- **Longitud máxima**: 255 caracteres

---

## Estados de Cuenta

| Campo | Valor para Linked Account | Descripción |
|-------|---------------------------|-------------|
| `account_type` | `"linked"` | Tipo de cuenta |
| `is_pending` | `true` → `false` | Pendiente hasta verificación |
| `is_active` | `false` → `true` | Inactiva hasta verificación |
| `is_default` | `false` | No se marca como default automáticamente |
| `microsoft_id` | `"pending-link-..."` | ID temporal hasta verificación |

---

## Email de Instrucciones

El email enviado al Global Admin contiene:

### Paso 1: Verificar Perfil
- Link directo al perfil de facturación
- Nota sobre tiempo de actualización (5 minutos)

### Paso 2: Aceptar Invitación
- Link directo para aceptar invitación CSP
- Nota sobre permisos requeridos (Global Admin)

### Información Adicional
- Requisitos necesarios
- Información de contacto del partner
- Links útiles

---

## Configuración Requerida

Agregar en `.env`:

```env
# Microsoft Partner Information
MICROSOFT_PARTNER_ID=fa233b05-e848-45c4-957f-d3e11acfc49c
MICROSOFT_MSPP_ID=0
MICROSOFT_PARTNER_EMAIL=backofficemex@readymind.ms
MICROSOFT_PARTNER_PHONE=5585261168
MICROSOFT_PARTNER_NAME="ReadyMarket of Readymind Mexico SA de CV"
```

---

## Ejemplos de Uso

### Vue.js / Frontend

```typescript
// Vincular cuenta existente
async function linkExistingAccount(domain: string, adminEmail: string) {
  try {
    const response = await apiClient.post('/microsoft-accounts/link-existing', {
      domain: domain,
      global_admin_email: adminEmail
    });
    
    // Mostrar mensaje de éxito y URLs
    showSuccessMessage(response.data.message);
    showInstructions(response.data.invitation_data);
    
    return response.data;
  } catch (error) {
    handleError(error);
  }
}

// Verificar cuenta vinculada
async function verifyLinkedAccount(accountId: number) {
  try {
    const response = await apiClient.patch(
      `/microsoft-accounts/${accountId}/verify-link`
    );
    
    showSuccessMessage('Cuenta verificada y activada');
    reloadAccounts();
    
    return response.data;
  } catch (error) {
    handleError(error);
  }
}
```

---

## Notas Importantes

1. **No se duplica funcionalidad**: La ruta `POST /microsoft-accounts` sigue existiendo para crear nuevas cuentas
2. **Email automático**: Se envía automáticamente al Global Admin con todas las instrucciones
3. **Estado pendiente**: Las cuentas vinculadas permanecen pendientes hasta verificación manual
4. **Sin interacción con Partner Center**: Este endpoint NO crea nada en Microsoft, solo registra localmente
5. **Verificación manual**: Por ahora, la verificación es manual (el usuario hace clic en "Verificar")
6. **Futura mejora**: Se puede implementar webhook o polling para verificar automáticamente cuando se acepta la invitación

---

## Migración Ejecutada

```sql
ALTER TABLE microsoft_accounts 
ADD COLUMN global_admin_email VARCHAR(255) NULL AFTER email,
ADD COLUMN account_type ENUM('created', 'linked') DEFAULT 'created' AFTER is_pending;
```

---

## Archivos Creados/Modificados

### Nuevos Archivos:
1. `app/Http/Requests/LinkExistingMicrosoftAccountRequest.php`
2. `app/Services/MicrosoftPartnerInvitationService.php`
3. `resources/views/emails/microsoft-link-existing-instructions.blade.php`
4. `database/migrations/2025_10_25_160006_add_global_admin_email_to_microsoft_accounts_table.php`

### Archivos Modificados:
1. `app/Http/Controllers/Api/MicrosoftAccountController.php` - Agregados métodos `linkExisting()` y `verifyLink()`
2. `app/Models/MicrosoftAccount.php` - Agregados campos `global_admin_email` y `account_type`
3. `app/Http/Resources/MicrosoftAccountResource.php` - Agregados campos en respuesta
4. `config/services.php` - Agregada configuración del partner
5. `routes/api.php` - Agregadas nuevas rutas

---

## Testing

### Test Manual con cURL:

```bash
# Vincular cuenta existente
curl -X POST http://localhost:8000/api/v1/microsoft-accounts/link-existing \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "domain": "testcompany.com",
    "global_admin_email": "admin@testcompany.onmicrosoft.com"
  }'

# Verificar cuenta vinculada
curl -X PATCH http://localhost:8000/api/v1/microsoft-accounts/123/verify-link \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

✅ **Implementación completada y lista para usar en el frontend**
