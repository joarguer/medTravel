# Campo Razón Social en Proveedores

## 📋 Descripción
Se agregó el campo **"Razón Social"** (legal_name) al módulo de proveedores para almacenar el nombre legal o fiscal del centro médico, clínica o médico.

---

## 🗄️ Cambios en Base de Datos

### Nueva Columna: `legal_name`
- **Tabla:** `providers`
- **Tipo:** `VARCHAR(250)`
- **Nullable:** Sí (DEFAULT NULL)
- **Posición:** Después de `name`
- **Descripción:** Razón social o nombre legal del prestador

### Ejecutar Migración

**Opción 1: Script SQL directo**
```bash
cd sql/
mysql -u root -p bolsacar_medtravel < ALTER_providers_add_legal_name.sql
```

**Opción 2: Script bash (recomendado)**
```bash
cd sql/
./run_alter_legal_name.sh
```

**Opción 3: Manualmente en MySQL**
```sql
USE bolsacar_medtravel;
ALTER TABLE providers 
ADD COLUMN legal_name VARCHAR(250) DEFAULT NULL 
COMMENT 'Razón social o nombre legal' 
AFTER name;
```

---

## 🔧 Cambios en el Código

### 1. Frontend (admin/providers.php)
- ✅ Agregado campo "Razón Social" en el formulario
- ✅ Ubicado después del campo "Nombre"
- ✅ Incluye texto de ayuda: "Nombre legal o fiscal de la empresa/profesional"

### 2. Backend (admin/ajax/providers.php)

#### CREATE:
```php
$legal_name = isset($_REQUEST['legal_name']) ? trim($_REQUEST['legal_name']) : null;
// Incluido en INSERT INTO providers
```

#### UPDATE:
```php
$allowed = ['type','name','legal_name','description',...];
// Agregado a la lista de campos permitidos
```

#### GET:
```php
// Devuelve automáticamente todos los campos incluyendo legal_name
```

### 3. JavaScript (admin/js/providers.js)

#### Save:
```javascript
data.legal_name = $('#prov-legal-name').val().trim();
```

#### Edit:
```javascript
$('#prov-legal-name').val(p.legal_name || '');
```

---

## 📝 Ejemplo de Uso

### Formulario Visible:
```
┌─────────────────────────────────────────┐
│ Nombre:         [Dr. Juan Pérez      ] │
│ Razón Social:   [Clínica Pérez S.A.S] │
│                 Nombre legal o fiscal   │
│                 de la empresa/profesional│
└─────────────────────────────────────────┘
```

### Datos Guardados:
```sql
SELECT name, legal_name FROM providers WHERE id = 1;
```
```
+-------------------+----------------------+
| name              | legal_name           |
+-------------------+----------------------+
| Dr. Juan Pérez    | Clínica Pérez S.A.S  |
| Medicis Corporal  | Medicis Corp SAS     |
+-------------------+----------------------+
```

---

## ✅ Checklist de Implementación

- [x] Script SQL de migración creado
- [x] Script bash helper creado
- [x] Campo agregado al formulario HTML
- [x] Backend CREATE actualizado
- [x] Backend UPDATE actualizado
- [x] JavaScript save actualizado
- [x] JavaScript edit actualizado
- [x] INSTALL_LOCAL.sql actualizado
- [ ] **Ejecutar migración en base de datos de producción**
- [ ] Probar creación de nuevo proveedor
- [ ] Probar edición de proveedor existente

---

## 🧪 Testing Manual

### Test 1: Crear Proveedor
1. Ir a `admin/providers.php`
2. Clic en "Nuevo prestador"
3. Llenar:
   - Nombre: "Dr. Carlos López"
   - Razón Social: "Consultorio López SAS"
4. Completar otros campos requeridos
5. Guardar

**Esperado:** 
- ✅ Se crea exitosamente
- ✅ Al editar, muestra "Consultorio López SAS" en Razón Social

### Test 2: Editar Proveedor
1. Abrir proveedor existente
2. Modificar Razón Social: "Nueva Razón Social S.A."
3. Guardar

**Esperado:**
- ✅ Se actualiza correctamente
- ✅ Campo se mantiene al recargar

### Test 3: Campo Opcional
1. Crear proveedor sin llenar Razón Social
2. Guardar

**Esperado:**
- ✅ Se crea correctamente (campo es opcional)

---

## 📌 Notas Adicionales

- **Campo opcional:** No es requerido, pero recomendado para información fiscal
- **Longitud máxima:** 250 caracteres
- **Uso futuro:** Puede usarse para:
  - Facturación electrónica
  - Documentos legales
  - Contratos con proveedores
  - Reportes fiscales

---

## 🔗 Archivos Modificados

```
admin/
├── providers.php                          ← HTML form
├── ajax/
│   └── providers.php                      ← Backend CRUD
└── js/
    └── providers.js                       ← Frontend logic

sql/
├── ALTER_providers_add_legal_name.sql     ← Migration
├── run_alter_legal_name.sh                ← Helper script
└── INSTALL_LOCAL.sql                      ← Schema definition
```

---

**Fecha:** 29 de enero de 2026  
**Versión:** 1.0  
**Estado:** ✅ Código completado - Pendiente migración DB
