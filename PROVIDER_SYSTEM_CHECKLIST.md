# ✅ Checklist de Validación - Sistema de Proveedores

Este checklist te ayudará a verificar que el sistema de gestión de proveedores fue instalado correctamente y funciona como se esperaba.

---

## 📋 Pre-Instalación

- [ ] **Backup creado**
  ```bash
  mysqldump -u root -p medtravel medtravel_services_catalog > backup_antes_proveedores.sql
  ```

- [ ] **Archivos SQL presentes**
  - [ ] `sql/INSTALL_COP_SYSTEM.sql` existe
  - [ ] `sql/service_providers_table.sql` existe

- [ ] **Archivos PHP presentes**
  - [ ] `admin/ajax/service_providers.php` existe
  - [ ] `admin/ajax/exchange_rate.php` existe
  - [ ] `admin/ajax/medtravel_services.php` actualizado
  - [ ] `admin/js/medtravel_services.js` actualizado
  - [ ] `admin/medtravel_services.php` actualizado

---

## 🗄️ Verificación de Base de Datos

### 1. Tabla `exchange_rates`

```sql
-- Verificar que existe
DESCRIBE exchange_rates;
```

**Resultado esperado**: Tabla con columnas `id`, `from_currency`, `to_currency`, `rate`, `effective_date`, `source`, `is_active`, `created_by`, `notes`, `created_at`

- [ ] ✅ Tabla existe
- [ ] ✅ Tiene al menos 1 registro activo
  ```sql
  SELECT * FROM exchange_rates WHERE is_active = 1;
  ```

---

### 2. Tabla `service_providers`

```sql
-- Verificar estructura
DESCRIBE service_providers;
```

**Resultado esperado**: 
- Columnas: `id`, `provider_name`, `provider_type`, `tax_id`, `country`, `city`, `address`, `contact_name`, `contact_email`, `contact_phone`, `website`, `payment_terms`, `rating`, `is_active`, `is_preferred`, `notes`, `created_at`, `updated_at`
- Índices: `idx_provider_type`, `idx_is_active`

- [ ] ✅ Tabla creada correctamente
- [ ] ✅ Tiene proveedores de ejemplo (5 registros)
  ```sql
  SELECT id, provider_name, provider_type FROM service_providers;
  ```
- [ ] ✅ Enum `provider_type` contiene: `airline`, `hotel`, `transport`, `restaurant`, `tour_operator`, `other`

---

### 3. Foreign Key en `medtravel_services_catalog`

```sql
-- Verificar columna provider_id
DESCRIBE medtravel_services_catalog;

-- Verificar foreign key
SHOW CREATE TABLE medtravel_services_catalog;
```

**Verificar**:
- [ ] ✅ Columna `provider_id INT NULL` existe
- [ ] ✅ Columna `exchange_rate DECIMAL(10,2)` existe
- [ ] ✅ Columna `cost_price_cop DECIMAL(12,2)` existe
- [ ] ✅ Foreign key `fk_service_provider` configurada
- [ ] ✅ Constraint `ON DELETE RESTRICT ON UPDATE CASCADE`

---

### 4. Triggers de Pricing

```sql
-- Verificar triggers
SHOW TRIGGERS FROM medtravel LIKE 'medtravel_services_catalog';
```

**Resultado esperado**:
- [ ] ✅ `calculate_pricing_before_insert` existe
- [ ] ✅ `calculate_pricing_before_update` existe

**Probar funcionamiento**:
```sql
-- Insertar servicio con COP, verificar cálculo automático de USD
INSERT INTO medtravel_services_catalog 
(service_type, service_name, cost_price_cop, exchange_rate, sale_price, currency) 
VALUES 
('transport', 'Test Trigger', 100000, 4150, 30, 'USD');

-- Verificar que cost_price se calculó automáticamente
SELECT service_name, cost_price_cop, exchange_rate, cost_price 
FROM medtravel_services_catalog 
WHERE service_name = 'Test Trigger';
-- cost_price debería ser ≈ 24.10 (100000/4150)

-- Limpiar test
DELETE FROM medtravel_services_catalog WHERE service_name = 'Test Trigger';
```

- [ ] ✅ Trigger calcula `cost_price` automáticamente desde `cost_price_cop / exchange_rate`
- [ ] ✅ Trigger calcula `commission_amount` y `commission_percentage`

---

### 5. Vista `v_services_with_provider`

```sql
-- Verificar vista
DESCRIBE v_services_with_provider;

-- Probar consulta
SELECT service_name, provider_name, provider_type 
FROM v_services_with_provider 
LIMIT 5;
```

- [ ] ✅ Vista creada correctamente
- [ ] ✅ Hace JOIN entre servicios y proveedores

---

## 🔌 Verificación de APIs

### 1. API de Proveedores

**Test 1: Listar proveedores**
```bash
curl "http://localhost/medtravel/admin/ajax/service_providers.php?action=list"
```

**O desde consola del navegador** (mientras estés logueado):
```javascript
$.get('ajax/service_providers.php?action=list', function(r) { console.log(r); });
```

**Resultado esperado**:
```json
{
  "ok": true,
  "data": [
    {
      "id": 1,
      "provider_name": "Avianca",
      "provider_type": "airline",
      ...
    }
  ]
}
```

- [ ] ✅ Responde correctamente
- [ ] ✅ Devuelve array de proveedores
- [ ] ✅ Incluye todos los campos esperados

---

**Test 2: Filtrar por tipo**
```javascript
$.get('ajax/service_providers.php?action=list&type=hotel', function(r) { 
    console.log('Hoteles:', r.data.length); 
});
```

- [ ] ✅ Filtra correctamente por `provider_type`

---

**Test 3: Solo activos**
```javascript
$.get('ajax/service_providers.php?action=list&active_only=1', function(r) { 
    console.log('Activos:', r.data); 
});
```

- [ ] ✅ Solo devuelve proveedores con `is_active = 1`

---

### 2. API de Exchange Rate

```javascript
$.get('ajax/exchange_rate.php?action=get_current', function(r) { 
    console.log('Tasa actual:', r); 
});
```

**Resultado esperado**:
```json
{
  "ok": true,
  "rate": 4150.00,
  "effective_date": "2024-01-15",
  "source": "Manual",
  "from_currency": "USD",
  "to_currency": "COP"
}
```

- [ ] ✅ Devuelve tasa de cambio activa
- [ ] ✅ Incluye fecha y fuente

---

## 🖥️ Verificación Frontend

### 1. Cargar Página de Servicios

1. Ir a: `http://localhost/medtravel/admin/medtravel_services.php`
2. Iniciar sesión si es necesario
3. Abrir consola del navegador (F12)

**Verificar**:
- [ ] ✅ Página carga sin errores JavaScript
- [ ] ✅ DataTable muestra servicios existentes
- [ ] ✅ No hay errores 404 en Network tab

---

### 2. Abrir Modal de Nuevo Servicio

1. Click en botón "Nuevo Servicio"
2. Observar consola y Network tab

**Verificar**:
- [ ] ✅ Modal se abre correctamente
- [ ] ✅ Se ejecuta AJAX a `exchange_rate.php` (carga tasa)
- [ ] ✅ Se ejecuta AJAX a `service_providers.php?action=list` (carga proveedores)
- [ ] ✅ Campo "Tasa de Cambio" tiene valor cargado desde BD

---

### 3. Tab "Provider"

1. En el modal, ir a tab "Provider"
2. Observar dropdown de proveedores

**Verificar**:
- [ ] ✅ Dropdown contiene opción "Seleccionar proveedor..."
- [ ] ✅ Lista proveedores activos con iconos emoji (✈️, 🏨, 🚗, etc.)
- [ ] ✅ Botón "Nuevo" presente
- [ ] ✅ Campos de contacto están en **readonly**

---

### 4. Seleccionar Proveedor

1. Seleccionar "✈️ Avianca" del dropdown
2. Observar campos de contacto

**Verificar**:
- [ ] ✅ Campo "Nombre Comercial" se llena con "Avianca"
- [ ] ✅ Campo "Persona de Contacto" se llena automáticamente
- [ ] ✅ Campo "Email" se llena automáticamente
- [ ] ✅ Campo "Teléfono" se llena automáticamente
- [ ] ✅ Campos son **readonly** (no editables)

---

### 5. Validación de Formulario

1. Con proveedor seleccionado
2. Completar SOLO:
   - Service Type: "Flight"
   - Service Name: "Vuelo Test"

**Verificar**:
- [ ] ✅ Botón "Save" sigue **deshabilitado** (falta pricing)

3. Completar:
   - Costo en COP: 1000000
   - Precio de Venta: 300

**Verificar**:
- [ ] ✅ Campo "Costo (USD)" se calcula automáticamente (≈ 240.96)
- [ ] ✅ Preview de comisión se actualiza
- [ ] ✅ Botón "Save" se **habilita** (validación pasada)

---

### 6. Guardar Servicio

1. Click en "Save"
2. Observar Network tab

**Verificar**:
- [ ] ✅ POST a `ajax/medtravel_services.php` con `action=create`
- [ ] ✅ Payload incluye `provider_id` (no `provider_name`)
- [ ] ✅ Respuesta: `{"ok": true, "message": "Service created successfully"}`
- [ ] ✅ Toastr muestra notificación de éxito
- [ ] ✅ Modal se cierra
- [ ] ✅ DataTable se recarga automáticamente
- [ ] ✅ Nuevo servicio aparece en tabla con nombre de proveedor

---

### 7. Editar Servicio Recién Creado

1. Click en botón "Edit" del servicio creado
2. Ir a tab "Provider"

**Verificar**:
- [ ] ✅ Dropdown tiene seleccionado el proveedor correcto
- [ ] ✅ Campos de contacto se llenaron automáticamente
- [ ] ✅ Tab "Pricing" muestra valores correctos (COP, USD, tasa)

---

### 8. Verificar en Base de Datos

```sql
SELECT 
    s.id,
    s.service_name,
    s.provider_id,
    p.provider_name,
    s.cost_price_cop,
    s.exchange_rate,
    s.cost_price,
    s.commission_amount
FROM medtravel_services_catalog s
LEFT JOIN service_providers p ON s.provider_id = p.id
WHERE s.service_name = 'Vuelo Test';
```

**Verificar**:
- [ ] ✅ `provider_id` tiene valor (no NULL)
- [ ] ✅ `provider_name` del JOIN coincide con el seleccionado
- [ ] ✅ `cost_price_cop` = 1000000
- [ ] ✅ `exchange_rate` = 4150 (o tasa actual)
- [ ] ✅ `cost_price` ≈ 240.96 (calculado por trigger)
- [ ] ✅ `commission_amount` y `commission_percentage` calculados

---

## 🛡️ Verificación de Integridad

### 1. Intentar Eliminar Proveedor con Servicios

```sql
-- Verificar que Avianca tiene servicios asociados
SELECT COUNT(*) FROM medtravel_services_catalog WHERE provider_id = 1;
```

Si hay servicios (>0):

```javascript
// Desde consola del navegador
$.post('ajax/service_providers.php', {
    action: 'delete',
    id: 1 // ID de Avianca
}, function(r) { 
    console.log(r); 
});
```

**Resultado esperado**:
```json
{
  "ok": false,
  "message": "Cannot delete provider with associated services. Found X service(s)."
}
```

- [ ] ✅ No permite eliminar proveedor con servicios
- [ ] ✅ Mensaje de error claro

---

### 2. Crear Servicio Sin Proveedor

1. Crear servicio completando solo campos obligatorios
2. **NO** seleccionar proveedor
3. Guardar

**Verificar**:
- [ ] ✅ Se guarda correctamente (provider_id = NULL)
- [ ] ✅ En DataTable, columna "Provider" muestra "N/A"

---

## 🔄 Migración de Datos Legacy (Opcional)

Si tienes servicios antiguos con datos en `provider_name`, `provider_contact`, etc.:

```sql
-- 1. Ver servicios legacy (sin provider_id)
SELECT id, service_name, provider_name, provider_contact
FROM medtravel_services_catalog
WHERE provider_id IS NULL 
  AND provider_name IS NOT NULL;
```

- [ ] ✅ Hay servicios legacy que requieren migración

**Ejecutar migración**:
```sql
-- 2. Crear proveedores desde datos únicos
INSERT IGNORE INTO service_providers 
(provider_name, contact_name, contact_email, contact_phone, provider_type, notes)
SELECT DISTINCT
    provider_name,
    provider_contact,
    provider_email,
    provider_phone,
    'other',
    'Migrado automáticamente'
FROM medtravel_services_catalog
WHERE provider_name IS NOT NULL
  AND provider_name != ''
  AND provider_id IS NULL;

-- 3. Asignar provider_id a servicios
UPDATE medtravel_services_catalog s
INNER JOIN service_providers p ON s.provider_name = p.provider_name
SET s.provider_id = p.id
WHERE s.provider_id IS NULL
  AND s.provider_name IS NOT NULL;

-- 4. Verificar migración
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN provider_id IS NOT NULL THEN 1 ELSE 0 END) as con_provider,
    SUM(CASE WHEN provider_id IS NULL THEN 1 ELSE 0 END) as sin_provider
FROM medtravel_services_catalog;
```

- [ ] ✅ Servicios legacy migrados correctamente
- [ ] ✅ `provider_id` asignado donde corresponde

---

## 📊 Resumen Final

### Base de Datos
- [ ] Tabla `exchange_rates` ✅
- [ ] Tabla `service_providers` ✅
- [ ] FK `fk_service_provider` ✅
- [ ] Triggers de pricing ✅
- [ ] Vista `v_services_with_provider` ✅

### Backend APIs
- [ ] `ajax/service_providers.php` funcional ✅
- [ ] `ajax/exchange_rate.php` funcional ✅
- [ ] `ajax/medtravel_services.php` actualizado ✅

### Frontend
- [ ] Dropdown de proveedores carga ✅
- [ ] Auto-fill de contactos funciona ✅
- [ ] Validación de formulario correcta ✅
- [ ] Guardado con `provider_id` ✅
- [ ] Edición carga proveedor seleccionado ✅

### Integridad
- [ ] No permite eliminar proveedor con servicios ✅
- [ ] Triggers calculan precios automáticamente ✅
- [ ] Relación FK funciona correctamente ✅

---

## 🚨 Problemas Comunes

### Dropdown vacío
**Síntoma**: No aparecen proveedores en el dropdown

**Solución**:
1. Verificar en consola: ¿Hay errores JavaScript?
2. Network tab: ¿La request a `service_providers.php?action=list` responde OK?
3. Verificar sesión: `console.log($.cookie())` - debe tener sesión activa
4. Verificar BD: `SELECT COUNT(*) FROM service_providers WHERE is_active = 1;`

---

### Botón Save no se habilita
**Síntoma**: Después de llenar el formulario, botón sigue deshabilitado

**Solución**:
1. Abrir consola: Buscar errores en `validateFormRealTime()`
2. Verificar campos obligatorios:
   - Service Type ✅
   - Service Name ✅
   - Exchange Rate > 0 ✅
   - Cost Price COP >= 0 ✅
3. Revisar `admin/js/medtravel_services.js` línea ~350 (función `validateServiceForm`)

---

### Error al guardar
**Síntoma**: Al hacer submit, error 500 o mensaje de error

**Posibles causas**:
1. Columna `provider_id` no existe → Ejecutar `sql/service_providers_table.sql`
2. FK no configurada → Verificar `SHOW CREATE TABLE medtravel_services_catalog`
3. Error PHP → Revisar `admin/logs/medtravel_services.log`

---

## ✅ Certificación

Si todos los items están marcados ✅, el sistema está correctamente instalado y funcional.

**Firma de validación**:
- Fecha: _______________
- Validado por: _______________
- Versión: 1.0.0

---

**Documentación completa**: Ver `PROVIDER_MANAGEMENT_README.md`
