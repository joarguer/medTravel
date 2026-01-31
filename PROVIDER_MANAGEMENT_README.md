# Sistema de Gestión de Proveedores - MedTravel Services

## 📋 Descripción General

Este módulo implementa un **sistema independiente de gestión de proveedores** para los servicios complementarios de MedTravel (vuelos, hoteles, transporte, restaurantes, etc.).

### ✅ Objetivos Cumplidos

1. ✅ **Tabla independiente de proveedores** - Los proveedores se gestionan en `service_providers`
2. ✅ **Selector dropdown** - Selección de proveedores desde catálogo existente
3. ✅ **Auto-fill de datos** - Carga automática de información de contacto
4. ✅ **Reutilización** - Un proveedor puede asociarse a múltiples servicios
5. ✅ **Integridad referencial** - FK con validación de eliminación

---

## 🗄️ Estructura de Base de Datos

### Tabla: `service_providers`

```sql
CREATE TABLE service_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(200) NOT NULL,
    provider_type ENUM('airline', 'hotel', 'transport', 'restaurant', 'tour_operator', 'other') NOT NULL,
    tax_id VARCHAR(50),
    country VARCHAR(100),
    city VARCHAR(100),
    address TEXT,
    contact_name VARCHAR(150),
    contact_email VARCHAR(150),
    contact_phone VARCHAR(50),
    website VARCHAR(255),
    payment_terms TEXT,
    rating DECIMAL(3,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    is_preferred TINYINT(1) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_provider_type (provider_type),
    INDEX idx_is_active (is_active)
);
```

### Relación con `medtravel_services_catalog`

```sql
ALTER TABLE medtravel_services_catalog 
ADD COLUMN provider_id INT NULL AFTER description,
ADD CONSTRAINT fk_service_provider 
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) 
    ON DELETE RESTRICT ON UPDATE CASCADE;
```

**IMPORTANTE**: 
- `ON DELETE RESTRICT` - No permite eliminar proveedor si tiene servicios asociados
- Los campos antiguos (`provider_name`, `provider_contact`, etc.) quedan como legacy para retrocompatibilidad

---

## 📁 Archivos Creados/Modificados

### 1. SQL de Instalación

**Archivo**: `sql/service_providers_table.sql`

Contiene:
- ✅ Creación de tabla `service_providers`
- ✅ ALTER TABLE con validación (no falla si columna ya existe)
- ✅ Vista `v_services_with_provider` (JOIN automático)
- ✅ 5 proveedores de ejemplo (Avianca, hoteles, transporte)
- ✅ Script de migración para datos legacy

### 2. API Backend

**Archivo**: `admin/ajax/service_providers.php`

**Endpoints disponibles**:

| Acción | Método | Parámetros | Descripción |
|--------|--------|------------|-------------|
| `list` | GET | `active_only`, `type` | Lista proveedores (con filtros) |
| `get` | GET | `id` | Obtener un proveedor |
| `create` | POST | provider_data | Crear nuevo proveedor |
| `update` | POST | `id` + provider_data | Actualizar proveedor |
| `delete` | POST | `id` | Eliminar (valida servicios) |
| `toggle_status` | POST | `id` | Activar/desactivar |

**Ejemplo de uso**:
```javascript
// Listar proveedores activos de tipo hotel
$.get('ajax/service_providers.php?action=list&active_only=1&type=hotel', function(response) {
    console.log(response.data);
});
```

### 3. Frontend HTML

**Archivo**: `admin/medtravel_services.php`

**Cambios en tab Provider**:
```html
<!-- ANTES -->
<input type="text" id="provider_name" name="provider_name">

<!-- AHORA -->
<select id="provider_id" name="provider_id">
    <option value="">Seleccionar proveedor...</option>
    <!-- Cargado dinámicamente vía AJAX -->
</select>
<button id="btnNewProvider">+ Nuevo</button>

<!-- Campos de contacto son READONLY (auto-fill) -->
<input type="text" id="provider_name_display" readonly>
<input type="text" id="provider_contact_display" readonly>
```

### 4. JavaScript Frontend

**Archivo**: `admin/js/medtravel_services.js`

**Nuevas funciones**:

```javascript
loadProviders()           // Carga dropdown con proveedores activos
onProviderSelect()        // Auto-fill al seleccionar proveedor
getProviderTypeIcon(type) // Iconos emoji por tipo
openProviderModal()       // Placeholder para creación rápida
```

**Flujo de datos**:
1. `$(document).ready()` → `loadProviders()` carga dropdown
2. Usuario selecciona proveedor → `onProviderSelect()` llena campos readonly
3. Al guardar servicio → envía `provider_id` en lugar de campos individuales
4. Backend guarda FK en `medtravel_services_catalog.provider_id`

### 5. Backend API de Servicios

**Archivo**: `admin/ajax/medtravel_services.php`

**Cambios en `buildServiceData()`**:
```php
// ANTES
'provider_name' => $_POST['provider_name'],
'provider_contact' => $_POST['provider_contact'],

// AHORA
'provider_id' => intval($_POST['provider_id']),
```

**Cambios en `listServices()`**:
```sql
-- AHORA hace JOIN para obtener provider_name
SELECT s.*, p.provider_name 
FROM medtravel_services_catalog s
LEFT JOIN service_providers p ON s.provider_id = p.id
```

---

## 🚀 Instalación

### Paso 1: Ejecutar SQL

```bash
mysql -u root -p medtravel < sql/service_providers_table.sql
```

O desde phpMyAdmin:
1. Seleccionar base de datos `medtravel`
2. Ir a pestaña SQL
3. Copiar y pegar contenido de `service_providers_table.sql`
4. Ejecutar

### Paso 2: Verificar Estructura

```sql
-- Verificar tabla creada
DESCRIBE service_providers;

-- Verificar FK agregada
SHOW CREATE TABLE medtravel_services_catalog;

-- Verificar proveedores de ejemplo
SELECT * FROM service_providers;
```

### Paso 3: Limpiar Caché (si aplica)

```bash
# Si usas opcache
php -r "opcache_reset();"

# O reinicia Apache/Nginx
sudo service apache2 restart
```

---

## 🧪 Pruebas

### Caso 1: Crear Servicio con Proveedor Existente

1. Ir a Admin → MedTravel Services
2. Click "Nuevo Servicio"
3. Tab "Provider":
   - Seleccionar "✈️ Avianca" del dropdown
   - Verificar que los campos se llenan automáticamente
4. Completar datos básicos y precios
5. Guardar
6. Verificar en BD:
   ```sql
   SELECT s.service_name, p.provider_name 
   FROM medtravel_services_catalog s
   LEFT JOIN service_providers p ON s.provider_id = p.id
   WHERE s.id = [ID_NUEVO];
   ```

### Caso 2: Editar Servicio Existente (Legacy)

1. Editar servicio antiguo (sin `provider_id`)
2. Los campos readonly deberían estar vacíos
3. Seleccionar proveedor del dropdown
4. Guardar
5. Ahora el servicio tiene `provider_id` y deja de usar campos legacy

### Caso 3: Intentar Eliminar Proveedor con Servicios

1. Crear servicio con Avianca
2. Intentar eliminar Avianca desde API:
   ```javascript
   $.post('ajax/service_providers.php', {
       action: 'delete',
       id: 1 // ID de Avianca
   });
   ```
3. Debería responder:
   ```json
   {
       "ok": false,
       "message": "Cannot delete provider with associated services. Found 1 service(s)."
   }
   ```

---

## 📊 Vista Auxiliar

Se creó una vista para simplificar consultas:

```sql
CREATE VIEW v_services_with_provider AS
SELECT 
    s.*,
    p.provider_name,
    p.provider_type,
    p.contact_name AS provider_contact,
    p.contact_email AS provider_email,
    p.contact_phone AS provider_phone,
    p.rating AS provider_rating
FROM medtravel_services_catalog s
LEFT JOIN service_providers p ON s.provider_id = p.id;
```

**Uso**:
```sql
-- En lugar de hacer JOIN manualmente
SELECT * FROM v_services_with_provider WHERE service_type = 'flight';
```

---

## 🔄 Migración de Datos Legacy

Si tienes servicios con datos en los campos antiguos (`provider_name`, `provider_contact`, etc.):

```sql
-- 1. Crear proveedores desde servicios únicos
INSERT INTO service_providers (provider_name, contact_name, contact_email, contact_phone, provider_type, notes)
SELECT DISTINCT
    provider_name,
    provider_contact,
    provider_email,
    provider_phone,
    'other' AS provider_type,
    'Migrado automáticamente' AS notes
FROM medtravel_services_catalog
WHERE provider_name IS NOT NULL
  AND provider_name != ''
  AND provider_id IS NULL;

-- 2. Asignar provider_id a servicios legacy
UPDATE medtravel_services_catalog s
INNER JOIN service_providers p ON s.provider_name = p.provider_name
SET s.provider_id = p.id
WHERE s.provider_id IS NULL
  AND s.provider_name IS NOT NULL;

-- 3. Verificar servicios migrados
SELECT 
    COUNT(*) as total_services,
    SUM(CASE WHEN provider_id IS NOT NULL THEN 1 ELSE 0 END) as with_provider,
    SUM(CASE WHEN provider_id IS NULL THEN 1 ELSE 0 END) as without_provider
FROM medtravel_services_catalog;
```

---

## 🎯 Próximas Mejoras (Opcionales)

### 1. Modal de Creación Rápida

Implementar formulario inline para crear proveedores sin salir del modal de servicios:

```javascript
function openProviderModal() {
    // TODO: Abrir modal con formulario simplificado
    // Campos básicos: nombre, tipo, contacto
    // Al guardar → recargar dropdown y seleccionar nuevo
}
```

### 2. Página de Gestión de Proveedores

Crear `admin/providers.php` con DataTable completo:
- CRUD completo de proveedores
- Filtros por tipo y estado
- Vista de servicios asociados
- Importación masiva desde CSV

### 3. Estadísticas de Proveedores

```sql
SELECT 
    p.provider_name,
    p.provider_type,
    COUNT(s.id) as total_services,
    SUM(s.is_active) as active_services,
    AVG(s.commission_percentage) as avg_commission
FROM service_providers p
LEFT JOIN medtravel_services_catalog s ON p.id = s.provider_id
GROUP BY p.id
ORDER BY total_services DESC;
```

### 4. Validación de Email/Teléfono

En `service_providers.php`, agregar validación:
```php
if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid email format']);
    return;
}
```

---

## 🐛 Troubleshooting

### Error: "Unknown column 'provider_id'"

**Causa**: No se ejecutó el ALTER TABLE
**Solución**:
```sql
ALTER TABLE medtravel_services_catalog 
ADD COLUMN provider_id INT NULL AFTER description;
```

### Error: "Cannot add foreign key constraint"

**Causa**: Ya existen datos en `medtravel_services_catalog` con `provider_id` inválidos
**Solución**:
```sql
-- Limpiar valores inválidos
UPDATE medtravel_services_catalog SET provider_id = NULL 
WHERE provider_id NOT IN (SELECT id FROM service_providers);

-- Ahora agregar FK
ALTER TABLE medtravel_services_catalog 
ADD CONSTRAINT fk_service_provider 
FOREIGN KEY (provider_id) REFERENCES service_providers(id);
```

### Dropdown no carga proveedores

**Verificar**:
1. Archivo existe: `admin/ajax/service_providers.php`
2. Sesión válida: Verificar que `$_SESSION['id_usuario']` esté activo
3. Permisos: `chmod 644 admin/ajax/service_providers.php`
4. Console del navegador: Buscar errores AJAX
5. Network tab: Ver respuesta de `service_providers.php?action=list`

---

## 📝 Notas Técnicas

### Retrocompatibilidad

Los campos legacy (`provider_name`, `provider_contact`, etc.) se mantienen por:
- Servicios existentes que no tienen `provider_id`
- Posible rollback si es necesario
- Datos históricos

**Recomendación**: Después de migrar todos los servicios, evaluar deprecar campos legacy:
```sql
-- En futuras versiones
ALTER TABLE medtravel_services_catalog 
DROP COLUMN provider_name,
DROP COLUMN provider_contact,
DROP COLUMN provider_email,
DROP COLUMN provider_phone;
```

### Performance

- La vista `v_services_with_provider` **no** usa caché
- Si tienes >10,000 servicios, considera índice adicional:
  ```sql
  CREATE INDEX idx_provider_id ON medtravel_services_catalog(provider_id);
  ```

### Seguridad

- Todas las entradas se sanitizan con `mysqli_real_escape_string()`
- Validación de sesión en ambos APIs
- DELETE protegido por FK constraint

---

## 👥 Contacto y Soporte

Este módulo fue desarrollado para MedTravel como parte del sistema de gestión de servicios complementarios.

**Archivos clave para revisar**:
- `sql/service_providers_table.sql` - Esquema completo
- `admin/ajax/service_providers.php` - API backend
- `admin/js/medtravel_services.js` - Lógica frontend

**Última actualización**: 2024
**Versión**: 1.0.0
