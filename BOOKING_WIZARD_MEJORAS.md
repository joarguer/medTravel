# Mejoras al Wizard de Booking

## 📋 Cambios Implementados

### 1. ✅ Corrección de Imágenes (Rutas)

**Problema**: Las imágenes de logos de proveedores no se mostraban en el wizard.

**Solución**: 
- Corregida la ruta de `src="/logo.png"` a `src="../logo.png"` para que funcione desde el subdirectorio `/booking/`
- Agregado fallback visual si la imagen no carga con `onerror`
- Las imágenes ahora cargan correctamente desde la raíz del proyecto

**Archivo modificado**: 
- `booking/wizard.php` (línea ~293)

---

### 2. 🔗 Enlaces a Detalle de Ofertas

**Problema**: El usuario no podía ver más información sobre cada servicio sin perder el contexto del formulario.

**Solución**:
- Agregado botón "More details" en cada tarjeta de oferta
- El enlace abre en **nueva pestaña** (`target="_blank"`)
- Implementado `onclick="event.stopPropagation()"` para evitar que active el checkbox al hacer clic
- El usuario puede ver detalles y regresar al wizard sin perder sus selecciones

**Código agregado**:
```html
<a href="../offer_detail.php?id=<?php echo $offer['id']; ?>" 
   class="btn btn-sm btn-outline-primary mt-2" 
   onclick="event.stopPropagation(); return true;"
   target="_blank">
    <i class="fas fa-info-circle"></i> More details
</a>
```

**Archivos modificados**:
- `booking/wizard.php` (línea ~318, estilos para el botón)

---

### 3. 📊 Módulo de Administración - Booking Requests

**Problema**: El administrador no tenía forma de gestionar las solicitudes de booking enviadas por los clientes.

**Solución**: Creado módulo completo de gestión con:

#### ✨ Características Implementadas:

**Vista Principal** (`booking_requests.php`):
- Tabla DataTable con todas las solicitudes
- Columnas: ID, Fecha, Nombre, Email, Destino, # Servicios, Estado, Acciones
- Filtrado y búsqueda en tiempo real
- Ordenamiento por fecha (más recientes primero)

**Acciones Disponibles**:
- 👁️ **View**: Ver detalles completos de la solicitud
- 📞 **Contact**: Marcar como contactado
- 🗑️ **Delete**: Eliminar solicitud

**Modal de Detalles** - Muestra:
- Información del cliente (nombre, email, teléfono)
- Información del viaje (destino, fecha, personas, timeline)
- **Lista completa de servicios seleccionados** con:
  - Nombre del servicio
  - Proveedor
  - Descripción
  - Precio
- Presupuesto del cliente
- Notas adicionales
- Estado actual
- Fecha de creación

**Estados de Solicitud**:
- 🟡 `pending` - Pendiente
- 🔵 `contacted` - Contactado
- 🟢 `confirmed` - Confirmado
- 🔴 `cancelled` - Cancelado

#### 📁 Archivos Creados:

1. **`admin/booking_requests.php`** - Página principal del módulo
2. **`admin/js/booking_requests.js`** - Lógica JavaScript con DataTables
3. **`admin/ajax/booking_requests.php`** - API backend con endpoints:
   - `get_all` - Listar todas las solicitudes
   - `get_detail` - Obtener detalle de una solicitud
   - `get_offers_details` - Cargar información de las ofertas seleccionadas
   - `update_status` - Actualizar estado
   - `delete` - Eliminar solicitud

#### 🔐 Permisos:

- **Acceso**: Solo usuarios administradores (rol principal)
- Agregado a `valida_session.php` en array de páginas admin-only
- Enlace visible en menú **Administrativo > Booking Requests**

#### 🎯 Menú de Navegación:

Posición en el menú:
```
Administrativo
  ├── Mis datos
  ├── Crear Usuarios
  ├── Informes
  ├── Configuración Email
  ├── Categorías de servicios
  ├── Servicios del catálogo
  ├── Prestadores
  ├── Verificación
  ├── Clientes
  ├── 📅 Booking Requests ← NUEVO
  └── Paquetes
```

**Archivos modificados**:
- `admin/include/include.php` (línea ~280, ~345)
- `admin/include/valida_session.php` (línea ~44)

---

## 🚀 Cómo Usar

### Para Clientes (Frontend):

1. Navegar a `/booking/wizard.php`
2. Ver las ofertas organizadas por categorías
3. Hacer clic en **"More details"** para ver información completa (abre en nueva pestaña)
4. Seleccionar múltiples servicios haciendo clic en las tarjetas
5. Completar presupuesto, timeline y notas
6. Enviar solicitud

### Para Administradores (Backend):

1. Iniciar sesión como usuario principal
2. Ir a **Administrativo > Booking Requests**
3. Ver todas las solicitudes en la tabla
4. Hacer clic en **"View"** para ver detalles completos
5. Marcar como **"Contact"** cuando se contacte al cliente
6. Actualizar estados según el progreso
7. Eliminar solicitudes si es necesario

---

## 📊 Base de Datos

La tabla `booking_requests` contiene:

```sql
- id
- name, email, phone
- destination, booking_datetime, persons
- category, special_request
- selected_offers (JSON array de IDs)
- budget, timeline, additional_notes
- status (pending/contacted/confirmed/cancelled)
- origin (wizard/booking/direct)
- created_at, updated_at
```

---

## 🎨 Mejoras Visuales

- Botón "More details" con estilo consistente
- Badges de estado con colores distintivos
- Modal amplio para ver detalles
- Tablas responsivas con DataTables
- Iconos Font Awesome para mejor UX

---

## ✅ Checklist de Implementación

- [x] Corregir rutas de imágenes en wizard
- [x] Agregar enlaces a detalles de ofertas
- [x] Crear módulo de administración
- [x] Implementar API backend
- [x] Agregar al menú de navegación
- [x] Configurar permisos de acceso
- [x] Estilos CSS para todos los componentes
- [x] Manejo de errores y validaciones
- [x] Documentación completa

---

## 🔄 Próximas Mejoras Sugeridas

1. **Email de notificación** cuando llega una nueva solicitud
2. **Dashboard con métricas** (solicitudes por día/mes, conversión)
3. **Exportar a Excel/PDF** las solicitudes
4. **Asignar coordinador** a cada solicitud
5. **Historial de cambios** de estado
6. **Integración con CRM** existente
7. **Chat en vivo** con el cliente desde el detalle
8. **Cotización automática** según servicios seleccionados
