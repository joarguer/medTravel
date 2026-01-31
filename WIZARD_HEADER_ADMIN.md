# Sistema de Administración del Header del Wizard de Booking

## Descripción General
Sistema completo para gestionar el contenido del header del wizard de booking desde el panel de administración, siguiendo el mismo patrón de otros headers del sitio (offers, about, services).

## Fecha de Implementación
31 de Enero de 2025

## Archivos Creados

### 1. Base de Datos
**Archivo:** `sql/CREATE_booking_wizard_header.sql`

**Estructura de la tabla:**
```sql
CREATE TABLE booking_wizard_header (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) DEFAULT 'Booking Wizard',
  subtitle_1 VARCHAR(255) DEFAULT 'Home',
  subtitle_2 VARCHAR(255) DEFAULT 'Booking Request',
  bg_image VARCHAR(500) DEFAULT 'img/carousel-1.jpg',
  activo ENUM('0','1') DEFAULT '0',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

**Campos:**
- `title`: Título principal que aparece en el header
- `subtitle_1`: Primer texto del breadcrumb (enlace a home)
- `subtitle_2`: Segundo texto del breadcrumb (página actual)
- `bg_image`: Ruta de la imagen de fondo del header
- `activo`: '0' = activo, '1' = inactivo (solo un registro activo)

### 2. Panel de Administración
**Archivo:** `admin/wizard_header_edit.php`

**Características:**
- Interfaz de una sola página con sidebar mínimo
- Formulario con campos editables en tiempo real
- Previsualización de la imagen actual
- Upload de nueva imagen de fondo
- Basado en el patrón de `offers_header_edit.php`

**Navegación:**
- Acceso desde el menú del admin (pendiente de agregar)
- URL directa: `/admin/wizard_header_edit.php`

### 3. Handler AJAX
**Archivo:** `admin/ajax/wizard_header_edit.php`

**Operaciones disponibles:**

#### a) `get_header`
Obtiene los datos actuales del header
```javascript
{ tipo: 'get_header' }
```
Respuesta:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Booking Wizard",
    "subtitle_1": "Home",
    "subtitle_2": "Booking Request",
    "bg_image": "img/site/wizard_header_1738368000.jpg"
  }
}
```

#### b) `edit_header`
Actualiza un campo de texto individual
```javascript
{
  tipo: 'edit_header',
  field: 'title',
  value: 'Nuevo título'
}
```
**Campos permitidos:** `title`, `subtitle_1`, `subtitle_2`

#### c) `upload_header_image`
Sube una nueva imagen de fondo
```javascript
FormData con:
- tipo: 'upload_header_image'
- file: archivo de imagen
```
**Formatos aceptados:** JPG, JPEG, PNG, GIF  
**Tamaño máximo:** 5MB  
**Destino:** `img/site/wizard_header_[timestamp].[ext]`

### 4. JavaScript Frontend
**Archivo:** `admin/js/wizard_header_edit.js`

**Funciones principales:**
- `open_header()`: Carga los datos del header vía AJAX
- `render_header_form(data)`: Renderiza el formulario con los datos
- `init_file_input()`: Inicializa el plugin Bootstrap FileInput
- `save_header_field(field, value)`: Guarda cambios en tiempo real
- `escapeHtml(text)`: Escapa caracteres especiales para seguridad

**Eventos:**
- `blur` en campos de texto: Guardado automático
- `fileuploaded`: Recarga el formulario tras subir imagen
- `fileuploaderror`: Muestra notificación de error

**Dependencias:**
- jQuery
- Bootstrap FileInput plugin
- Toastr (notificaciones)

## Integración en el Frontend

### Modificaciones en `booking/wizard.php`

**Líneas 12-21:** Query de la base de datos
```php
$wizard_header = [
    'title' => 'Booking Wizard',
    'subtitle_1' => 'Home',
    'subtitle_2' => 'Booking Request',
    'bg_image' => 'img/carousel-1.jpg'
];
$header_query = mysqli_query($conexion, "SELECT title, subtitle_1, subtitle_2, bg_image FROM booking_wizard_header WHERE activo = '0' LIMIT 1");
if ($header_query && mysqli_num_rows($header_query) > 0) {
    $wizard_header = mysqli_fetch_assoc($header_query);
}
```

**Líneas 219-228:** Uso en el template
```php
<div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(19, 53, 123, 0.5), rgba(19, 53, 123, 0.5)), url(../<?php echo htmlspecialchars($wizard_header['bg_image']); ?>);">
    <div class="container text-center py-5">
        <h3 class="text-white display-3 mb-4">
            <?php echo htmlspecialchars($wizard_header['title']); ?>
        </h3>
        <ol class="breadcrumb justify-content-center mb-0">
            <li class="breadcrumb-item">
                <a href="../index.php"><?php echo htmlspecialchars($wizard_header['subtitle_1']); ?></a>
            </li>
            <li class="breadcrumb-item active text-white">
                <?php echo htmlspecialchars($wizard_header['subtitle_2']); ?>
            </li>
        </ol>
    </div>
</div>
```

## Flujo de Trabajo

### Para el Administrador:
1. Acceder a `admin/wizard_header_edit.php`
2. El sistema carga automáticamente los datos actuales
3. Editar cualquier campo de texto → Se guarda automáticamente al salir del campo
4. Subir nueva imagen → Click en "Upload" → Se actualiza inmediatamente
5. Los cambios se reflejan instantáneamente en el frontend

### Guardado en Tiempo Real:
- **Campos de texto:** Se guardan automáticamente al hacer blur (salir del campo)
- **Imagen:** Se guarda al hacer click en el botón "Upload" del plugin fileinput
- **Notificaciones:** Toastr muestra éxito o error en cada operación

## Seguridad

### Validaciones Implementadas:
1. **Sesión:** Verifica `$_SESSION['usuario_id']` en todos los endpoints AJAX
2. **Campos whitelisted:** Solo permite actualizar `title`, `subtitle_1`, `subtitle_2`
3. **SQL Injection:** Usa `mysqli_real_escape_string()` en todos los valores
4. **Upload seguro:**
   - Valida tipo MIME: solo imágenes
   - Valida tamaño: máximo 5MB
   - Genera nombre único con timestamp
   - Valida errores de PHP en el upload
5. **XSS:** Usa `htmlspecialchars()` en el frontend para escapar output

## Instalación

### Paso 1: Crear la tabla
```bash
mysql -u usuario -p nombre_db < sql/CREATE_booking_wizard_header.sql
```

### Paso 2: Verificar archivos
- [x] `admin/wizard_header_edit.php`
- [x] `admin/ajax/wizard_header_edit.php`
- [x] `admin/js/wizard_header_edit.js`
- [x] `booking/wizard.php` (modificado)

### Paso 3: Agregar al menú del admin
Editar `admin/include/menu.php` y agregar:
```php
<li>
    <a href="wizard_header_edit.php">
        <i class="fa fa-magic"></i>
        <span>Wizard Header</span>
    </a>
</li>
```

### Paso 4: Probar funcionalidad
1. Acceder a `/admin/wizard_header_edit.php`
2. Editar título y verificar guardado automático
3. Subir imagen de prueba
4. Visitar `/booking/wizard.php` y verificar cambios

## Patrón de Diseño

Este módulo sigue el patrón establecido en el sitio:

```
[PÁGINA]_header_edit.php
├── Interfaz con sidebar mínimo
├── Carga datos via AJAX al inicio
└── Formulario con campos editables

ajax/[PÁGINA]_header_edit.php
├── get_header: Obtener datos
├── edit_header: Actualizar campos texto
└── upload_header_image: Subir imágenes

js/[PÁGINA]_header_edit.js
├── open_[section](): Carga y renderiza datos
├── render_[section]_form(): Construye el HTML
├── init_file_input(): Configura upload
└── save_[section]_field(): Guarda cambios
```

## Mantenimiento

### Agregar nuevos campos:
1. Agregar columna a la tabla `booking_wizard_header`
2. Agregar campo al array `$allowed_fields` en el AJAX
3. Agregar input en `render_header_form()` en el JS
4. Usar el campo en `booking/wizard.php`

### Solución de problemas:
- **No carga datos:** Verificar que exista un registro con `activo='0'`
- **No guarda:** Revisar permisos de sesión y campos whitelisted
- **No sube imagen:** Verificar permisos de escritura en `img/site/`
- **Imagen no se ve:** Verificar ruta relativa desde `booking/` (debe tener `../`)

## Notas Técnicas

### Diferencias con otros headers:
- Usa `bg_image` en lugar de `img` (más descriptivo)
- Ruta relativa ajustada: `url(../<?php echo $wizard_header['bg_image']; ?>)`
- Breadcrumb dinámico: ambos enlaces usan la BD

### Compatibilidad:
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.2+
- Bootstrap 5.x
- jQuery 3.x
- Bootstrap FileInput plugin

### Performance:
- Query simple con LIMIT 1 (rápida)
- Sin joins innecesarios
- Cacheable con valores por defecto en el array

## Próximas Mejoras (Opcional)
- [ ] Agregar campo `description` para meta tags
- [ ] Permitir múltiples versiones del header (A/B testing)
- [ ] Historial de cambios con timestamps
- [ ] Preview en tiempo real sin recargar wizard.php
- [ ] Selector de imágenes del repositorio existente

## Autor
Desarrollado siguiendo los patrones existentes del sitio MedTravel  
Fecha: 31 de Enero de 2025
