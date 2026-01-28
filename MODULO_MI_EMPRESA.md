# Módulo: Mi Empresa (Gestión de Perfil de Proveedor)

## Descripción
Permite a los prestadores (providers) editar su propia información de empresa sin acceso a otros prestadores.

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
- `admin/include/include.php` - Agregado menú "Mi Empresa" (solo prestadores)

## Instalación

### 1. Base de Datos
Ejecutar la migración SQL:
```sql
-- Desde MAMP o línea de comandos
mysql -u root -p bolsacar_medtravel < sql/add_logo_to_providers.sql
```

O manualmente:
```sql
ALTER TABLE `providers` 
ADD COLUMN `logo` VARCHAR(255) NULL DEFAULT NULL AFTER `website`;
```

### 2. Permisos de Directorios
```bash
chmod 755 img/providers
```

### 3. Verificar Sesión
Asegurarse de que los usuarios prestadores tengan `$_SESSION['provider_id']` configurado al iniciar sesión.

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
- NO debe ver menú "Mi Empresa"
- Debe ver "Prestadores" en menú

### Caso 2: Usuario Prestador
- Debe ver menú "Mi Empresa"
- Debe ver "Mis Ofertas"
- NO debe ver "Prestadores", "Categorías", etc.

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
