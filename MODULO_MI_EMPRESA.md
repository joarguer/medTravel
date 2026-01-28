# Módulo: Mi Empresa (Gestión de Perfil de Proveedor)

**Fecha de implementación:** 28 de enero de 2026  
**Estado:** ✅ Implementado y Funcional

## Descripción
Permite a los prestadores (providers) editar su propia información de empresa sin acceso a otros prestadores. Integrado con el sistema de creación de usuarios para asignar empresas automáticamente.

## Archivos Creados

### Frontend
- `admin/mi_empresa.php` - Página principal con formulario de edición
- `admin/js/mi_empresa.js` - Lógica JavaScript y validaciones

### Backend
- `admin/ajax/mi_empresa.php` - Endpoint AJAX para actualizaciones

### Assets
- `img/providers/.htaccess` - Protección del directorio de logos
- `img/providers/README.txt` - Documentación del directorio

### SQL
- `sql/add_logo_to_providers.sql` - Migración para agregar columna logo

### Modificados
- `admin/include/include.php` - Agregado menú "Mi Empresa" (solo prestadores) + Badge de rol en header
- `admin/crear_usuario.php` - Dropdown de empresas cuando rol es "Proveedor"
- `admin/ajax/crear_usuario.php` - Guardar provider_id al crear usuario prestador
- `admin/js/crear_usuario.js` - Mostrar/ocultar dropdown según rol seleccionado

## Instalación

### 1. Base de Datos
Ejecutar el script completo de configuración:
```bash
# Desde MAMP o línea de comandos
mysql -u root -proot bolsacar_medtravel < sql/setup_empresas.sql
```

Este script automáticamente:
- Agrega columna `logo` a tabla `providers`
- Agrega columna `provider_id` a tabla `usuarios`
- Crea empresa demo si no existe ninguna
- NO asigna provider_id al admin (el admin gestiona empresas, no pertenece a una)

**Importante:** El admin principal NO debe tener `provider_id`. Es el dueño del sitio que registra empresas clientes.

### 2. Permisos de Directorios
```bash
chmod 755 img/providers
```

### 3. Crear Usuarios Prestadores
1. Login como admin
2. Ir a **Administrativo → Crear Usuarios**
3. Activar toggle **"Proveedor"**
4. Seleccionar empresa del dropdown **"Prestador / Empresa"**
5. Completar datos y guardar
6. El usuario tendrá automáticamente `provider_id` asignado

## Seguridad Implementada

### Control de Acceso
- ✅ Requiere sesión activa
- ✅ Bloqueo si NO existe `provider_id` en sesión
- ✅ Forzar `provider_id` desde sesión (ignora POST/GET)
- ✅ Menú visible SOLO a prestadores (no admin)

### Validación de Datos
- ✅ Whitelist estricta de campos editables
- ✅ Prepared statements en todas las consultas
- ✅ Validación de tipos MIME (FILEINFO_MIME_TYPE)
- ✅ Límite de tamaño: 2MB
- ✅ Formatos permitidos: JPG, PNG, WEBP

### Protección de Archivos
- ✅ Nombres de archivo con timestamp (evita sobreescritura)
- ✅ Directorio aislado por provider_id
- ✅ .htaccess niega listado de directorios
- ✅ No permite paths arbitrarios

## Campos Editables

Los prestadores pueden editar:
- ✅ Nombre
- ✅ Descripción
- ✅ Ciudad
- ✅ Dirección
- ✅ Teléfono
- ✅ Email
- ✅ Website
- ✅ Logo (imagen)

**NO editables** (solo admin):
- Tipo (medico/clinica)
- Slug
- is_verified
- is_active

## Flujo de Trabajo

1. **Carga de datos**: Se obtienen desde DB usando provider_id de sesión
2. **Edición**: Usuario modifica campos en formulario
3. **Validación**: JavaScript valida formato email/URL
4. **Guardado**: AJAX envía datos a `ajax/mi_empresa.php`
5. **Respuesta**: JSON con {ok:true} o {ok:false, error:"..."}
6. **Feedback**: Toastr muestra notificación

## Logo Upload

1. Usuario selecciona archivo
2. Validación cliente (tamaño/tipo)
3. Upload via FormData
4. Validación servidor (MIME real)
5. Crear directorio `img/providers/{provider_id}/`
6. Guardar como `logo_{timestamp}.{ext}`
7. Actualizar BD
8. Actualizar preview en frontend

## Testing

### Caso 1: Usuario Admin
- Badge: **ADMIN** (rojo)
- Menú visible: "Prestadores" (CRUD completo)
- NO ve: "Mi Empresa"
- Función: Registrar y gestionar todas las empresas clientes

### Caso 2: Usuario Prestador
- Badge: **PRESTADOR** (azul)
- Menú visible: "Mi Empresa", "Mis Ofertas"
- NO ve: "Prestadores", "Categorías", etc.
- Función: Autogestión de su empresa y ofertas

### Caso 3: Crear Usuario Prestador
1. Admin va a "Crear Usuarios"
2. Selecciona rol "Proveedor"
3. Aparece dropdown "Prestador / Empresa"
4. Selecciona empresa y crea usuario
5. Usuario prestador puede login y ver "Mi Empresa"

### Caso 3: Acceso Directo
Si usuario sin `provider_id` intenta acceder a `/admin/mi_empresa.php`:
- Debe redirigir a `index.php`

### Caso 4: Upload Logo
- Archivo > 2MB: rechazado
- Archivo .exe renombrado a .jpg: rechazado (MIME validation)
- JPG válido: aceptado y guardado

## Mantenimiento

### Backup de Logos
Los logos están en `img/providers/{provider_id}/`
Incluir en backups regulares.

### Limpieza
Si un proveedor se elimina, sus logos quedan en disco.
Considerar tarea cron para limpiar directorios huérfanos.

## Notas Técnicas

- Se mantiene compatibilidad con estructura Metronic existente
- Se usa mismo patrón de código que `mis_datos.php`
- No se modificó `providers.php` (admin)
- Aislamiento total por `provider_id`
- Sin console.log ni código debug en producción

## Próximos Pasos (Opcional)

- [ ] Agregar validación de dimensiones de imagen
- [ ] Implementar crop/resize automático
- [ ] Historial de cambios (audit log)
- [ ] Notificación al admin cuando prestador actualiza perfil
- [ ] Dashboard con estadísticas para prestadores
- [ ] Sistema de aprobación de cambios

---

**Relacionado:** Ver `ANALISIS_MULTIUSUARIO.md` para arquitectura completa del sistema.
