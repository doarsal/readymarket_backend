# 📋 Endpoints de Facturación Electrónica

## 🚀 Endpoint Principal - Generar Factura Completa

### **POST** `/api/v1/invoices/generate-from-order/{orderId}`

Genera una factura completa (XML + PDF) con una sola llamada, usando solo el ID de la orden.

**URL Ejemplo:**
```bash
POST http://localhost/marketplace/backend/public/api/v1/invoices/generate-from-order/25
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Invoice generated successfully from order #25",
  "data": {
    "invoice": {
      "id": 28,
      "uuid": "DA36AF7A-E733-4B32-8D59-82F287B09E45",
      "invoice_number": "FAC-000001",
      "status": "stamped",
      "total": "546.00"
    },
    "download_urls": {
      "pdf": "http://localhost/marketplace/backend/public/api/v1/invoices/28/download/pdf",
      "xml": "http://localhost/marketplace/backend/public/api/v1/invoices/28/download/xml"
    }
  }
}
```

---

## 📄 Endpoints de Descarga

### **GET** `/api/v1/invoices/{invoiceId}/download/pdf`

Descarga el archivo PDF de la factura.

**URL Ejemplo:**
```bash
GET http://localhost/marketplace/backend/public/api/v1/invoices/28/download/pdf
```

**Headers de Respuesta:**
- `Content-Type: application/pdf`
- `Content-Disposition: attachment; filename="factura_FAC-000001.pdf"`

---

### **GET** `/api/v1/invoices/{invoiceId}/download/xml`

Descarga el archivo XML de la factura (CFDI 4.0).

**URL Ejemplo:**
```bash
GET http://localhost/marketplace/backend/public/api/v1/invoices/28/download/xml
```

**Headers de Respuesta:**
- `Content-Type: application/xml`
- `Content-Disposition: attachment; filename="factura_FAC-000001.xml"`

---

## 🔧 Características Técnicas

### ✅ **Implementación Actual:**
- ✅ Usa endpoint `timbrarJSON2` de FacturaloPlus
- ✅ Obtiene XML y PDF en una sola operación
- ✅ Parámetro `plantilla: '1'` para PDF correcto
- ✅ Almacena ambos archivos en Base64
- ✅ UUIDs válidos para SAT
- ✅ Endpoints de descarga funcionales

### 📊 **Flujo Completo:**
1. **Generación**: `POST /generate-from-order/{orderId}` → Crea factura completa
2. **Descarga PDF**: `GET /{invoiceId}/download/pdf` → Descarga PDF
3. **Descarga XML**: `GET /{invoiceId}/download/xml` → Descarga XML

### 🔍 **Validaciones:**
- ✅ Orden debe estar pagada (`payment_status = 'paid'`)
- ✅ Previene duplicados (una factura por orden)
- ✅ Valida que la factura esté timbrada antes de descargar
- ✅ Manejo de errores completo

---

## 📝 Ejemplos de Uso

### PowerShell (Windows):
```powershell
# Generar factura
$response = Invoke-RestMethod -Uri "http://localhost/marketplace/backend/public/api/v1/invoices/generate-from-order/25" -Method POST

# Descargar PDF
Invoke-RestMethod -Uri "http://localhost/marketplace/backend/public/api/v1/invoices/28/download/pdf" -OutFile "factura.pdf"

# Descargar XML
Invoke-RestMethod -Uri "http://localhost/marketplace/backend/public/api/v1/invoices/28/download/xml" -OutFile "factura.xml"
```

### cURL:
```bash
# Generar factura
curl -X POST "http://localhost/marketplace/backend/public/api/v1/invoices/generate-from-order/25"

# Descargar PDF
curl "http://localhost/marketplace/backend/public/api/v1/invoices/28/download/pdf" -o factura.pdf

# Descargar XML
curl "http://localhost/marketplace/backend/public/api/v1/invoices/28/download/xml" -o factura.xml
```

---

## 🎯 Casos de Uso

1. **Generar factura después del pago**: Llamar `/generate-from-order/{orderId}` cuando el pago se confirme
2. **Enviar factura por email**: Usar las URLs de descarga para adjuntar archivos
3. **Portal de usuario**: Mostrar links de descarga en el historial de facturas
4. **Integración con contabilidad**: Descargar XMLs para subir a sistemas contables

---

## ⚠️ Notas Importantes

- Los archivos se almacenan en Base64 en la base de datos
- El PDF se genera automáticamente con el XML durante el timbrado
- Los UUIDs son únicos y válidos para el SAT
- Las facturas no se pueden regenerar una vez timbradas
- Usar datos de prueba de FacturaloPlus para testing
