# Integración de Ofertas de Proveedores en Booking Wizard

## 🎯 Resumen
Se integró el catálogo de ofertas activas de proveedores médicos en el wizard de reservas, reemplazando la selección genérica de categorías por tarjetas visuales con información real de servicios, precios y proveedores certificados.

**Fecha:** 31 de enero de 2026  
**Commit:** Integración ofertas de proveedores en booking wizard

---

## 📦 Cambios Implementados

### 1. Wizard de Booking (`booking/wizard.php`)

#### Consulta de Ofertas Activas
```php
// Cargar ofertas con información completa de proveedores
$offers_sql = "SELECT 
                o.id, o.title, o.description, o.price_from, o.currency, o.provider_id,
                p.name AS provider_name, p.city AS provider_city, p.logo AS provider_logo,
                sc.name AS service_name, sc.category_id,
                cat.name AS category_name
               FROM provider_service_offers o
               INNER JOIN providers p ON o.provider_id = p.id
               INNER JOIN service_catalog sc ON o.service_id = sc.id
               LEFT JOIN service_categories cat ON sc.category_id = cat.id
               WHERE o.is_active = 1
               ORDER BY cat.name ASC, sc.sort_order ASC, o.id DESC";
```

#### Características de la UI:
- ✅ **Tarjetas por categoría:** Ofertas agrupadas por categoría médica
- ✅ **Logo del proveedor:** Muestra logo o inicial si no hay imagen
- ✅ **Información del proveedor:** Nombre y ciudad
- ✅ **Precio visible:** "From USD $X" o "Price on request"
- ✅ **Selección múltiple:** Checkbox con efecto visual
- ✅ **Hover effects:** Animaciones smooth al pasar mouse
- ✅ **Responsive:** Adaptable a móvil/tablet/desktop

#### Estilos Agregados:
- `.offer-card` - Tarjeta de oferta con bordes y hover
- `.offer-card.selected` - Estado visual cuando está seleccionada
- `.provider-logo-small` - Logo redondo del proveedor (40x40px)
- `.offer-price` - Badge con gradiente morado para el precio
- `.category-header` - Header con gradiente por categoría

---

### 2. Backend de Submission (`booking/submit.php`)

#### Campos Capturados:
```php
$selected_offers = isset($_POST['selected_offers']) 
    ? array_values(array_filter(array_map('intval', $_POST['selected_offers']))) 
    : [];
```

#### Almacenamiento:
- **Campo:** `selected_offers` (TEXT, JSON)
- **Formato:** Array de IDs: `[1, 5, 12]`
- **Ejemplo:** Usuario selecciona 3 ofertas → se guarda como `"[1,5,12]"`

#### Mensaje de Confirmación:
```php
$offers_count = count($selected_offers);
$_SESSION['booking_request_message'] = 
    "Your request with {$offers_count} selected service(s) was saved...";
```

---

### 3. Base de Datos

#### Nueva Columna en `booking_requests`
```sql
ALTER TABLE `booking_requests` 
ADD COLUMN `selected_offers` TEXT DEFAULT NULL 
COMMENT 'JSON array de IDs de provider_service_offers seleccionadas'
AFTER `special_request`;
```

**Migración:**
- ✅ Archivo: `sql/ALTER_booking_requests_add_selected_offers.sql`
- ✅ Backward compatible (campos antiguos se mantienen)
- ✅ NULL por defecto (no afecta registros existentes)

---

## 🔍 Flujo de Usuario

### Paso 1: Formulario Inicial
Usuario ingresa en `booking.php` o `packages.php`:
1. Completa nombre y email
2. Selecciona fecha, destino, personas
3. Submit → va a `booking/step-1.php`

### Paso 2: Selección de Servicios (NUEVO)
Usuario ve en `booking/wizard.php`:
1. **Categorías colapsables:** Odontología, Dermatología, etc.
2. **Tarjetas de ofertas:** Con logo, proveedor, precio
3. **Selección visual:** Click en tarjeta = checkbox + cambio de color
4. **Múltiples servicios:** Puede seleccionar varios

### Paso 3: Finalización
1. Budget opcional
2. Timeline preferido
3. Notas adicionales
4. Submit → guarda en BD con IDs de ofertas seleccionadas

---

## 📊 Estructura de Datos

### Tabla: `provider_service_offers`
```sql
id | provider_id | service_id | title | description | price_from | currency | is_active
1  | 1           | 5          | Basic Dental Cleaning | ... | 120.00 | USD | 1
2  | 2           | 5          | Professional Cleaning | ... | 150.00 | USD | 1
```

### Tabla: `booking_requests` (actualizada)
```sql
id | name | email | selected_offers | budget | timeline | created_at
1  | John | j@ex.com | [1,5,12] | 3000.00 | March 10-15 | 2026-01-31...
```

### Relación:
```
booking_requests.selected_offers → JSON array de IDs
   ↓ (deserializar)
[1, 5, 12]
   ↓ (JOIN)
provider_service_offers WHERE id IN (1,5,12)
   ↓ (obtener datos completos)
Oferta 1: "Basic Cleaning" - Provider A - $120
Oferta 5: "Botox Treatment" - Provider B - $250
Oferta 12: "Hair Transplant" - Provider C - $2500
```

---

## 🎨 UI/UX Mejoras

### Antes (Genérico):
```
☐ Odontología
☐ Dermatología
☐ Cirugía Estética
```
- Solo nombres de categorías
- Sin información de precios
- Sin proveedores visibles
- Poco atractivo visualmente

### Después (Dinámico):
```
┌─────────────────────────────────────┐
│ 🏥 Odontología                      │
└─────────────────────────────────────┘

┌──────────────────────┐  ┌──────────────────────┐
│ [A] Dr. Smith Clinic │  │ [B] Dental Care Pro  │
│ 📍 Bogotá            │  │ 📍 Medellín          │
│                      │  │                      │
│ Basic Dental Cleaning│  │ Professional Clean   │
│ Full exam & polish   │  │ Deep scaling + ...   │
│                      │  │                      │
│ From USD $120        │  │ From USD $150        │
└──────────────────────┘  └──────────────────────┘
```
- Información completa
- Precios transparentes
- Proveedores identificables
- Diseño profesional

---

## ✅ Ventajas del Nuevo Sistema

### 1. **Transparencia de Precios**
- Clientes ven precios desde el inicio
- Evita sorpresas en cotización final
- Mejora tasa de conversión

### 2. **Confianza en Proveedores**
- Logo y nombre visibles
- Ciudad/ubicación mostrada
- Usuarios eligen proveedores específicos

### 3. **Flexibilidad**
- Pueden combinar múltiples servicios
- Un solo wizard para paquetes completos
- Ejemplo: Limpieza dental + Blanqueamiento + Hotel

### 4. **Datos Estructurados**
- IDs específicos guardados
- Fácil generar cotización exacta
- Admin puede ver qué ofertas son populares

### 5. **Escalabilidad**
- Proveedores agregan/editan ofertas en `admin/provider_offers.php`
- Se reflejan automáticamente en wizard
- Sin necesidad de actualizar código

---

## 🚀 Próximos Pasos Recomendados

### 1. Panel Admin para Ver Solicitudes
Crear `admin/booking_requests.php`:
- DataTable con solicitudes
- Columna "Services Selected" con desglose
- Botón "Generate Quote" que pre-llena paquete

### 2. Cálculo Automático de Total
En wizard, mostrar suma en vivo:
```javascript
// Al seleccionar ofertas
Total: USD $270 (2 services)
```

### 3. Filtros en Wizard
Agregar sidebar con:
- ☐ Filter by price range
- ☐ Filter by city
- ☐ Sort by: price, rating, popularity

### 4. Vista de Detalle
Modal al hacer click en oferta:
- Descripción completa
- Fotos del proveedor
- Reviews de pacientes
- Botón "Select & Continue"

### 5. Email de Confirmación
Al submit, enviar email con:
- Resumen de servicios seleccionados
- Proveedores contactados
- Timeline estimado
- Next steps

### 6. Integración con `travel_packages`
Cuando admin aprueba solicitud:
- Botón "Create Package from Request"
- Auto-llena costos desde ofertas seleccionadas
- Calcula margen automáticamente

---

## 📈 Métricas a Monitorear

### Después de Deploy:
1. **Tasa de conversión wizard:**
   - % que completa step 1
   - % que selecciona al menos 1 oferta
   - % que hace submit final

2. **Ofertas más populares:**
   ```sql
   SELECT offer_id, COUNT(*) as selections
   FROM booking_requests, 
        JSON_TABLE(selected_offers, '$[*]' COLUMNS(offer_id INT PATH '$')) jt
   GROUP BY offer_id
   ORDER BY selections DESC
   LIMIT 10;
   ```

3. **Proveedores con más solicitudes:**
   - JOIN entre `booking_requests` y `provider_service_offers`
   - Ranking por provider_id

4. **Ticket promedio:**
   - Suma de `price_from` de ofertas seleccionadas
   - Comparar con `budget` declarado

---

## 🛠️ Comandos de Instalación

### 1. Ejecutar Migración SQL
```bash
mysql -u usuario -p medtravel < sql/ALTER_booking_requests_add_selected_offers.sql
```

### 2. Verificar Columna
```sql
DESCRIBE booking_requests;
-- Debe aparecer: selected_offers | text | YES | | NULL |
```

### 3. Probar Wizard
```
1. Ir a: http://localhost/medtravel/booking.php
2. Llenar formulario inicial
3. Seleccionar 2-3 ofertas en wizard
4. Submit
5. Verificar en BD:
   SELECT id, name, selected_offers FROM booking_requests ORDER BY id DESC LIMIT 1;
```

---

## 🐛 Troubleshooting

### Problema: No aparecen ofertas en wizard
**Causa:** No hay ofertas activas en `provider_service_offers`

**Solución:**
```sql
-- Verificar ofertas
SELECT COUNT(*) FROM provider_service_offers WHERE is_active = 1;

-- Si es 0, insertar demo
INSERT INTO provider_service_offers (provider_id, service_id, title, price_from)
VALUES (1, 1, 'Demo Service', 100.00);
```

### Problema: Error al guardar en submit.php
**Causa:** Campo `selected_offers` no existe en BD

**Solución:**
```bash
# Ejecutar migración
mysql -u usuario -p medtravel < sql/ALTER_booking_requests_add_selected_offers.sql
```

### Problema: Logos de proveedores no se muestran
**Causa:** Path incorrecto o archivo no existe

**Solución:**
```php
// Verificar en wizard.php línea ~XX
<?php if (!empty($offer['provider_logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $offer['provider_logo'])): ?>
    <img src="/<?php echo $offer['provider_logo']; ?>" ...>
<?php else: ?>
    <!-- Fallback: inicial -->
<?php endif; ?>
```

---

## 📝 Notas Técnicas

### Compatibilidad con Sistema Antiguo
- ✅ Campos `service_categories` y `medical_services` se mantienen
- ✅ Formularios antiguos siguen funcionando
- ✅ Migración gradual sin breaking changes

### Performance
- Query de ofertas ejecuta 1 JOIN con 3 tablas
- Índices recomendados:
  ```sql
  CREATE INDEX idx_active ON provider_service_offers(is_active);
  CREATE INDEX idx_provider ON provider_service_offers(provider_id);
  ```

### Seguridad
- ✅ IDs sanitizados con `intval()`
- ✅ JSON_encode para prevenir injection
- ✅ prepared statements en submit.php

---

## 🎉 Conclusión

Se implementó exitosamente la integración de ofertas reales de proveedores en el wizard de booking, proporcionando:

1. ✅ **UI profesional** con tarjetas visuales
2. ✅ **Datos reales** de precios y proveedores
3. ✅ **Backend robusto** con JSON storage
4. ✅ **Backward compatible** con sistema existente
5. ✅ **Escalable** para futuras mejoras

**Estado:** 🟢 Listo para pruebas y deploy

---

**Desarrollado:** 31 de enero de 2026  
**Archivos modificados:** 3 (wizard.php, submit.php, + 1 SQL)  
**Líneas agregadas:** ~300 líneas
