# Services Page - Dynamic Content Management

## Resumen de Cambios

Se ha implementado un sistema completo de administración dinámica para la página de servicios (`services.php`), permitiendo editar desde el panel admin todos los elementos sin necesidad de modificar código.

## Características Implementadas

### 1. **Gestión de Header**
- ✅ Título personalizable (breadcrumb)
- ✅ **Imagen de fondo del header** (nueva funcionalidad)
- ✅ Subtítulo de sección
- ✅ Título principal
- ✅ Descripción

### 2. **Gestión de Servicios de Coordinación**
- ✅ 6 servicios configurables
- ✅ **Iconos Font Awesome con preview en tiempo real**
- ✅ Títulos y descripciones editables
- ✅ Posicionamiento izquierda/derecha
- ✅ Orden personalizable

### 3. **Renderización Dinámica**
- ✅ Los iconos ahora se renderizan correctamente desde la base de datos
- ✅ La imagen del header es administrable
- ✅ Todos los textos son editables

## Archivos Modificados

### SQL
1. **`sql/services_coordination_table.sql`** - Actualizado con campo `header_image`
2. **`sql/ALTER_services_add_header_image.sql`** - Script para actualizar tablas existentes

### Frontend
3. **`services.php`** - Ahora usa datos dinámicos de la base de datos
   - Consulta `services_page_header` para header
   - Consulta `coordination_services` para servicios
   - Renderiza iconos dinámicamente
   - Imagen de header desde BD

### Admin Panel
4. **`admin/services_edit.php`** - Panel de administración
5. **`admin/js/services_edit.js`** - Funcionalidad JavaScript mejorada
   - Preview de iconos en tiempo real
   - Subida de imágenes para header
   - Validación de campos
6. **`admin/ajax/services_edit.php`** - Endpoint AJAX actualizado
   - Manejo de subida de imágenes
   - Validación de tipos de archivo
   - Creación automática de directorios

## Estructura de Base de Datos

### Tabla: `services_page_header`
```sql
- id (int)
- title (varchar 255) - "Our Services"
- subtitle (varchar 255) - "Comprehensive Services"  
- main_title (varchar 255) - "Complete Coordination & Management"
- description (text)
- header_image (varchar 255) - "img/services/header_xxx.jpg" ← NUEVO
- activo (enum 0,1)
```

### Tabla: `coordination_services`
```sql
- id (int)
- icon_class (varchar 100) - "fa fa-heartbeat" ← Se renderiza correctamente
- title (varchar 255)
- description (text)
- position (enum left,right)
- orden (int)
- activo (enum 0,1)
```

## Instalación

### Opción A: Instalación Nueva
```sql
-- Ejecutar el archivo completo
source sql/services_coordination_table.sql
```

### Opción B: Actualización de Tabla Existente
```sql
-- Si ya tienes la tabla creada, solo agregar el campo
source sql/ALTER_services_add_header_image.sql
```

## Uso del Panel Admin

### Editar Header
1. Ir a `admin/services_edit.php`
2. Clic en "Header" en el sidebar
3. **Subir imagen**: Seleccionar archivo y clic en "Upload Image"
4. Editar título, subtítulo, etc.
5. Los cambios se guardan automáticamente

### Editar Servicios
1. Clic en cualquier servicio del sidebar
2. **Ver preview del icono** en tiempo real
3. Cambiar clase del icono (ej: `fa fa-heart`, `fa fa-plane-departure`)
4. Editar título y descripción
5. Clic en "Save Changes"

## Iconos Font Awesome Disponibles

Ejemplos de clases que funcionan:
- `fa fa-heartbeat` - Coordinación médica
- `fa fa-plane-departure` - Vuelos
- `fa fa-hotel` - Alojamiento
- `fa fa-car` - Transporte
- `fa fa-utensils` - Comidas
- `fa fa-headset` - Soporte 24/7
- `fa fa-user-md` - Doctor
- `fa fa-hospital` - Hospital
- `fa fa-ambulance` - Ambulancia
- `fa fa-map-marked-alt` - Ubicación

Ver más en: https://fontawesome.com/v5/search

## Solución de Problemas

### Los iconos no se muestran
✅ **SOLUCIONADO**: Ahora se renderizan desde la base de datos correctamente

### No puedo subir imagen del header
✅ **SOLUCIONADO**: Sistema de subida implementado
- Verifica permisos de escritura en `img/services/`
- Tipos permitidos: JPG, JPEG, PNG, GIF, WEBP

### Los cambios no se reflejan
1. Verificar que `activo = '1'` en ambas tablas
2. Limpiar caché del navegador
3. Verificar que los datos se guardaron en la BD

## Estructura de Directorios

```
medtravel/
├── img/
│   └── services/          ← NUEVO: Imágenes del header
│       └── header_xxx.jpg
├── admin/
│   ├── services_edit.php
│   ├── js/
│   │   └── services_edit.js
│   └── ajax/
│       └── services_edit.php
└── sql/
    ├── services_coordination_table.sql
    └── ALTER_services_add_header_image.sql
```

## Próximos Pasos Recomendados

1. ✅ Ejecutar el SQL actualizado o el ALTER según corresponda
2. ✅ Verificar que la carpeta `img/services/` tenga permisos de escritura
3. ✅ Subir una imagen para el header desde el admin
4. ✅ Personalizar los iconos y textos de los servicios
5. ⏳ Considerar agregar más servicios si es necesario (expandible)

## Características Técnicas

- **Separación de contenido y presentación**: El diseño está en el template, el contenido en la BD
- **Sistema de archivos organizado**: Imágenes en carpetas dedicadas
- **Validación de seguridad**: Tipos de archivo permitidos, SQL injection prevention
- **Preview en tiempo real**: Los iconos se visualizan mientras se editan
- **Responsive**: El diseño se mantiene responsive con contenido dinámico

## Notas de Desarrollo

- Los iconos requieren Font Awesome 5+ cargado en el frontend
- Las imágenes se suben a `img/services/` con timestamp único
- El sistema soporta hasta 100 servicios (ajustable)
- La posición left/right se maneja automáticamente en el frontend
