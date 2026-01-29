# Módulo de Gestión de Clientes (CRM) - MedTravel

## Descripción

Módulo de CRM (Customer Relationship Management) para gestionar los clientes de MedTravel que buscan servicios médicos en Colombia desde Estados Unidos (principalmente Florida).

## Estructura de Archivos

```
admin/
├── clientes.php              # Interfaz principal con tabla de clientes
├── ajax/
│   └── clientes.php          # API backend para operaciones CRUD
└── js/
    └── clientes.js           # Lógica frontend y DataTables
```

## Base de Datos

### Tabla: `clientes`

La tabla contiene 65 campos organizados en las siguientes secciones:

#### Información Personal
- `nombre`, `apellido`, `email` (requeridos)
- `fecha_nacimiento`, `telefono`, `whatsapp`
- `idioma_preferido` (en, es, both)

#### Ubicación
- `pais`, `estado`, `ciudad`
- `direccion`, `codigo_postal`

#### Documentación
- `tipo_documento` (passport, license, id, other)
- `numero_pasaporte`

#### Estado y Seguimiento
- `status`: 
  - lead (Interesado)
  - cotizado
  - confirmado
  - en_viaje
  - post_tratamiento
  - finalizado
  - inactivo
- `origen_contacto`: web, whatsapp, telefono, email, referido, redes_sociales, otro

#### Contacto de Emergencia
- `contacto_emergencia_nombre`
- `contacto_emergencia_telefono`
- `contacto_emergencia_relacion`

#### Información Médica
- `condiciones_medicas`
- `alergias`
- `medicamentos_actuales`
- `cirugias_previas`
- `grupo_sanguineo`
- `seguro_medico_internacional`

#### Preferencias de Viaje
- `hotel_preferido`
- `aeropuerto_origen`
- `aeropuerto_destino_preferido`
- `requiere_interprete`
- `necesidades_especiales`

#### Auditoría
- `created_at`, `updated_at`
- `created_by`, `updated_by`
- `activo` (soft delete)

## API Endpoints

### GET - Listar Clientes
```javascript
$.ajax({
    url: 'ajax/clientes.php',
    type: 'POST',
    data: { tipo: 'get' },
    dataType: 'json'
});
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "nombre": "John",
            "apellido": "Smith",
            "email": "john.smith@example.com",
            "telefono": "+1-561-123-4567",
            "pais": "USA",
            "estado": "Florida",
            "ciudad": "Miami",
            "status": "lead",
            "origen_contacto": "web",
            "created_at": "2024-01-15 10:30:00"
        }
    ]
}
```

### GET_ONE - Obtener Cliente
```javascript
$.ajax({
    url: 'ajax/clientes.php',
    type: 'POST',
    data: { 
        tipo: 'get_one',
        id: 1
    },
    dataType: 'json'
});
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "nombre": "John",
        "apellido": "Smith",
        // ... todos los campos del cliente
    }
}
```

### CREATE - Crear Cliente
```javascript
$.ajax({
    url: 'ajax/clientes.php',
    type: 'POST',
    data: {
        tipo: 'create',
        nombre: 'John',
        apellido: 'Smith',
        email: 'john.smith@example.com',
        // ... campos adicionales
    },
    dataType: 'json'
});
```

**Response:**
```json
{
    "success": true,
    "message": "Cliente creado exitosamente",
    "id": 1
}
```

### UPDATE - Actualizar Cliente
```javascript
$.ajax({
    url: 'ajax/clientes.php',
    type: 'POST',
    data: {
        tipo: 'update',
        id: 1,
        nombre: 'John',
        apellido: 'Smith',
        // ... campos a actualizar
    },
    dataType: 'json'
});
```

**Response:**
```json
{
    "success": true,
    "message": "Cliente actualizado exitosamente"
}
```

### DELETE - Eliminar Cliente (Soft Delete)
```javascript
$.ajax({
    url: 'ajax/clientes.php',
    type: 'POST',
    data: {
        tipo: 'delete',
        id: 1
    },
    dataType: 'json'
});
```

**Response:**
```json
{
    "success": true,
    "message": "Cliente eliminado exitosamente"
}
```

## Características

### Interfaz de Usuario
- **DataTables**: Tabla interactiva con paginación, búsqueda y ordenamiento
- **Modal responsivo**: Formulario de creación/edición en modal Bootstrap
- **Vista detallada**: Modal de solo lectura para ver información completa
- **Badges de estado**: Visualización codificada por colores
- **Notificaciones**: toastr para feedback al usuario

### Validaciones

#### Frontend (JavaScript)
- Campos requeridos: nombre, apellido, email
- Formato de email válido
- Prevención de doble envío

#### Backend (PHP)
- Email único en la base de datos
- Escape de strings con `mysqli_real_escape_string`
- Validación de sesión con `require_login_ajax()`
- Verificación de duplicados antes de crear/actualizar

### Estados del Cliente

| Estado | Badge | Descripción |
|--------|-------|-------------|
| `lead` | Azul | Cliente interesado, contacto inicial |
| `cotizado` | Amarillo | Cotización enviada, esperando respuesta |
| `confirmado` | Azul primario | Viaje confirmado, pago recibido |
| `en_viaje` | Verde | Cliente actualmente en Colombia |
| `post_tratamiento` | Gris | Seguimiento post-procedimiento |
| `finalizado` | Verde | Proceso completado exitosamente |
| `inactivo` | Rojo | Cliente inactivo |

### Origen de Contacto

| Origen | Badge | Icono |
|--------|-------|-------|
| `web` | Azul | 🌐 Globe |
| `whatsapp` | Verde | 💬 WhatsApp |
| `telefono` | Azul primario | 📞 Phone |
| `email` | Amarillo | ✉️ Envelope |
| `referido` | Azul info | 👤 User |
| `redes_sociales` | Azul primario | 🔗 Share |
| `otro` | Gris | - |

## Seguridad

### Permisos
- **Solo Administradores**: Acceso completo a CRUD
- Configurado en `admin/include/valida_session.php`
- Array `$admin_only` incluye `'clientes.php'`

### Protección de Datos
- Soft delete: `activo = 0` en lugar de eliminar registro
- Auditoría completa con `created_by`, `updated_by`
- Session-based authentication
- mysqli prepared statements (en desarrollo)

## Integración con Otros Módulos

### Relaciones Futuras
- **Appointments**: Cliente → Citas médicas
- **Travel Packages**: Cliente → Paquetes de viaje
- **Client Documents**: Cliente → Documentos
- **Notifications**: Cliente → Notificaciones

### Campos de Integración
- `google_contact_id`: Para sincronización con Google Contacts
- `hubspot_contact_id`: Para CRM externo
- `asignado_a`: ID del usuario responsable del cliente

## Uso

### Acceder al Módulo
1. Iniciar sesión como administrador
2. En el menú lateral: **Administrativo → Clientes**
3. URL: `admin/clientes.php`

### Crear Cliente
1. Click en botón **"Nuevo Cliente"**
2. Completar campos requeridos (nombre, apellido, email)
3. Agregar información adicional según sea necesario
4. Click en **"Guardar"**

### Editar Cliente
1. En la tabla, click en botón de edición (lápiz azul)
2. Modificar campos necesarios
3. Click en **"Guardar"**

### Ver Cliente
1. En la tabla, click en botón de vista (ojo)
2. Revisar información completa
3. Opción de editar desde vista detallada

### Eliminar Cliente
1. En la tabla, click en botón de eliminación (basura roja)
2. Confirmar eliminación
3. El registro se marca como `activo = 0` (soft delete)

## Próximas Mejoras

### Fase 1 (Actual)
- [x] CRUD básico de clientes
- [x] Validaciones de email único
- [x] Estados y origen de contacto
- [x] Información médica básica

### Fase 2 (Pendiente)
- [ ] Filtros avanzados en DataTable
- [ ] Exportar a Excel/PDF
- [ ] Historial de cambios
- [ ] Carga de documentos
- [ ] Timeline de interacciones

### Fase 3 (Futuro)
- [ ] Integración con Google Contacts
- [ ] Integración con HubSpot
- [ ] Email marketing desde el CRM
- [ ] WhatsApp API integration
- [ ] Dashboard de métricas de clientes

## Troubleshooting

### Error: "Email ya está registrado"
- Verificar que el email no exista en la base de datos
- Consulta: `SELECT * FROM clientes WHERE email = 'email@example.com' AND activo = 1`

### Error: "Sesión no válida"
- Verificar que el usuario esté logueado
- Revisar `$_SESSION['id_usuario']`
- Verificar que `require_login_ajax()` funcione correctamente

### DataTable no carga datos
- Verificar respuesta AJAX en consola del navegador
- Revisar permisos de base de datos
- Verificar que la tabla `clientes` exista

### Modal no se muestra
- Verificar que jQuery esté cargado
- Revisar consola para errores JavaScript
- Verificar que Bootstrap esté cargado correctamente

## Contacto y Soporte

Para soporte técnico o reportar bugs, contactar al equipo de desarrollo de MedTravel.

---

**Última actualización:** 2024-01-15  
**Versión:** 1.0.0  
**Autor:** MedTravel Development Team
