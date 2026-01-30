# MANUAL DE PRUEBAS - MEJORAS COMERCIALES MEDTRAVEL

## INTRODUCCIÓN
Este documento contiene los procedimientos de prueba manual para validar los 3 módulos implementados:
- A) Paquetes (travel_packages)
- B) Upload de Evidencias (provider_documents)
- C) Timezones (dual display)

**Fecha:** 29 de enero de 2026
**Commit:** Mejoras comerciales - Paquetes, Evidencias, Timezones

---

## A) MÓDULO DE PAQUETES

### Pre-requisitos
- SQL ejecutado: `sql/FASE_1_MEJORAS_COMERCIALES_SAFE.sql`
- Al menos 1 cliente creado en tabla `clientes`
- Triggers instalados: `trg_travel_packages_calc_margins_insert/update`

### TEST 1: Crear Paquete Básico
**Objetivo:** Verificar creación y cálculo automático de márgenes

**Pasos:**
1. Ir a `admin/paquetes.php`
2. Clic en "Nuevo Paquete"
3. Llenar campos obligatorios:
   - Cliente: Seleccionar un cliente del dropdown
   - Nombre del Paquete: "Test Cirugía Estética"
   - Fecha Inicio: Mañana
   - Fecha Fin: +7 días desde inicio
   - Estado: "Cotizado"
4. Ir a pestaña "Costos y Márgenes"
5. Configurar:
   - Costo Servicio Médico: `5000.00`
   - Costo Total: `6000.00`
   - Tipo de Tarifa: `Porcentaje`
   - Valor: `10`
6. Observar cálculo en vivo:
   - Total Costos Operativos: $5,000.00
   - Tarifa MedTravel: $600.00 (10% de 6000)
   - Margen Bruto: $1,000.00
   - Margen Neto: $1,000.00
7. Clic en "Guardar Paquete"

**Resultado Esperado:**
- ✅ Paquete creado exitosamente
- ✅ Aparece en datatable con todos los datos
- ✅ Margen neto muestra en verde: $1,000.00
- ✅ Toast de confirmación: "Paquete creado exitosamente"

**Validación en BD:**
```sql
SELECT id, package_name, total_package_cost, 
       medtravel_fee_type, medtravel_fee_value, medtravel_fee_amount,
       gross_margin, net_margin
FROM travel_packages
ORDER BY id DESC LIMIT 1;
```
- `medtravel_fee_amount` debe ser `600.00` (calculado por trigger)
- `gross_margin` debe ser `1000.00`
- `net_margin` debe ser `1000.00`

---

### TEST 2: Paquete con Margen Negativo
**Objetivo:** Verificar warning cuando net_margin < 0

**Pasos:**
1. Crear nuevo paquete
2. Configurar:
   - Costo Servicio Médico: `8000.00`
   - Costo Total: `7000.00` (menor que costos)
   - Tipo de Tarifa: `Monto Fijo`
   - Valor: `500.00`
3. Observar cálculo:
   - Margen Bruto: -$1,000.00 (rojo)
   - Margen Neto: -$1,000.00 (rojo)
   - **DEBE aparecer warning:** "El margen neto es negativo. Revisa los costos y tarifas."
4. Guardar igualmente

**Resultado Esperado:**
- ✅ Warning visible en formulario
- ✅ Permite guardar (no bloquea)
- ✅ Al guardar muestra toast warning: "Advertencia: El paquete tiene un margen neto negativo de -$1,000.00"
- ✅ En datatable aparece en rojo el margen neto

---

### TEST 3: Editar Paquete Existente
**Objetivo:** Verificar actualización y recálculo de márgenes

**Pasos:**
1. En datatable, clic en botón "Editar" (lápiz) de un paquete
2. Modal se abre con todos los datos pre-llenados
3. Cambiar:
   - Costo Total: aumentar en $500
4. Observar recálculo automático en tiempo real
5. Guardar

**Resultado Esperado:**
- ✅ Formulario muestra datos correctos
- ✅ Cálculos se actualizan al modificar
- ✅ Al guardar: "Paquete actualizado exitosamente"
- ✅ Datatable se recarga con valores nuevos

---

### TEST 4: Paquete Completo (con Vuelo/Hotel/Transporte)
**Objetivo:** Verificar suma correcta de todos los costos

**Pasos:**
1. Crear paquete
2. **Tab General:** Datos básicos
3. **Tab Vuelo:**
   - ✅ "Incluir Vuelo"
   - Aerolínea: "American Airlines"
   - Costo Vuelo: `800.00`
4. **Tab Hotel:**
   - ✅ "Incluir Hotel"
   - Noches: `5`
   - Costo/Noche: `100.00`
   - **Verificar:** Hotel Total = `500.00` (auto-calculado)
5. **Tab Transporte:**
   - ✅ "Incluir Transporte"
   - Costo Transporte: `200.00`
6. **Tab Costos:**
   - Costo Servicio Médico: `5000.00`
   - Costo Comidas: `300.00`
   - **Total Costos Operativos debe mostrar:** $6,800.00
   - Costo Total: `8000.00`
7. Guardar

**Resultado Esperado:**
- ✅ Trigger suma correctamente: 800 + 500 + 200 + 5000 + 300 = $6,800
- ✅ Gross Margin = 8000 - 6800 = $1,200
- ✅ Todos los datos se persisten correctamente

---

### TEST 5: Eliminar Paquete
**Objetivo:** Verificar eliminación segura

**Pasos:**
1. Clic en botón "Eliminar" (basura) de un paquete
2. Confirmar en diálogo

**Resultado Esperado:**
- ✅ Muestra confirmación: "¿Está seguro de eliminar..."
- ✅ Al confirmar: "Paquete eliminado exitosamente"
- ✅ Desaparece de datatable
- ✅ En BD: registro eliminado

---

## B) MÓDULO DE UPLOAD DE EVIDENCIAS

### Pre-requisitos
- SQL ejecutado con tablas: `provider_documents`, `provider_verification_items`
- Al menos 1 proveedor con checklist inicializado
- Directorio writable: `admin/uploads/provider_documents/`

### TEST 6: Subir Documento PDF
**Objetivo:** Validar upload y asociación con item de verificación

**Pasos:**
1. Ir a `admin/provider_verification.php`
2. Clic en "Verificar" de un proveedor
3. Si no hay checklist, clic en "Inicializar Checklist Estándar"
4. En un item de la lista (ej: "Licencia Médica"), clic en "Adjuntar Evidencia"
5. Modal de upload se abre
6. Seleccionar:
   - Tipo de Documento: "Licencia Médica"
   - Título: "Licencia 2024"
   - Archivo: Subir PDF de prueba (< 10MB)
7. **Verificar preview:** Debe mostrar nombre, tamaño, ícono PDF
8. Clic en "Subir Documento"

**Resultado Esperado:**
- ✅ Toast: "Documento subido exitosamente"
- ✅ Modal se cierra
- ✅ Modal de verificación se recarga
- ✅ Aparece sección "Documentos Adjuntos" con tabla
- ✅ Documento listado con:
  - Nombre original del archivo
  - Tipo: "medical_license"
  - Tamaño: "1.25 MB" (formateado)
  - Estado: "Pendiente" (no verificado aún)
  - Botones: Descargar, Eliminar

**Validación Física:**
```bash
ls -lh admin/uploads/provider_documents/provider_*/
```
- ✅ Archivo existe con nombre único: `doc_{provider_id}_{timestamp}.pdf`

**Validación en BD:**
```sql
SELECT id, provider_id, document_type, filename, original_filename, 
       file_size, is_verified
FROM provider_documents
ORDER BY id DESC LIMIT 1;
```
- ✅ Registro creado correctamente

---

### TEST 7: Validaciones de Upload
**Objetivo:** Verificar restricciones de seguridad

**Sub-test 7.1: Archivo muy grande**
- Intentar subir archivo > 10MB
- **Esperado:** Error: "El archivo excede el tamaño máximo permitido (10MB)"

**Sub-test 7.2: Tipo no permitido**
- Intentar subir .exe o .zip
- **Esperado:** Error: "Tipo de archivo no permitido. Use: PDF, JPG, PNG, DOC"

**Sub-test 7.3: Sin archivo**
- Clic en "Subir" sin seleccionar archivo
- **Esperado:** Error: "Debe seleccionar un archivo"

---

### TEST 8: Descargar Documento
**Objetivo:** Verificar acceso al archivo

**Pasos:**
1. En lista de documentos, clic en botón "Descargar" (ícono descarga)
2. Se abre en nueva pestaña

**Resultado Esperado:**
- ✅ PDF se abre/descarga correctamente
- ✅ Nombre del archivo corresponde al original

---

### TEST 9: Eliminar Documento
**Objetivo:** Verificar eliminación física y en BD

**Pasos:**
1. Clic en botón "Eliminar" (basura) de un documento
2. Confirmar

**Resultado Esperado:**
- ✅ Confirmación: "¿Está seguro de eliminar..."
- ✅ Toast: "Documento eliminado exitosamente"
- ✅ Desaparece de lista
- ✅ Archivo físico eliminado del servidor
- ✅ Registro eliminado de BD

---

### TEST 10: Múltiples Documentos por Proveedor
**Objetivo:** Verificar organización y preview

**Pasos:**
1. Subir 3 documentos diferentes al mismo proveedor:
   - PDF: Licencia
   - JPG: Foto instalaciones
   - DOC: Certificado
2. Ver lista de documentos

**Resultado Esperado:**
- ✅ Todos los documentos aparecen en tabla
- ✅ Cada uno con su ícono correspondiente (fa-file-pdf-o, fa-file-image-o, fa-file-word-o)
- ✅ Ordenados por fecha de subida (más reciente primero)

---

## C) TIMEZONES (DUAL DISPLAY)

### Pre-requisitos
- SQL ejecutado con columnas: `client_timezone`, `provider_timezone`, `appointment_datetime_utc`
- Helper incluido: `admin/include/timezone_helper.php`

### TEST 11: Validación de Helper
**Objetivo:** Probar funciones de conversión

**Crear archivo de prueba:** `admin/test_timezone.php`

```php
<?php
require_once('include/conexion.php');
require_once('include/timezone_helper.php');

// TEST: Conversión UTC → Local
echo "<h3>Test 1: UTC a New York</h3>";
$utc = '2024-02-15 18:00:00';
$ny_time = convertFromUTC($utc, 'America/New_York');
echo "<pre>";
print_r($ny_time);
echo "</pre>";
// Esperado: 13:00:00 (EST, -5 horas)

echo "<h3>Test 2: UTC a Bogotá</h3>";
$bog_time = convertFromUTC($utc, 'America/Bogota');
echo "<pre>";
print_r($bog_time);
echo "</pre>";
// Esperado: 13:00:00 (COT, -5 horas)

echo "<h3>Test 3: Bogotá → UTC</h3>";
$local = '2024-02-15 10:00:00';
$utc_converted = convertToUTC($local, 'America/Bogota');
echo "Local: $local<br>";
echo "UTC: $utc_converted<br>";
// Esperado: 15:00:00

echo "<h3>Test 4: Dual Display</h3>";
echo displayDualTimezone('2024-02-15 15:00:00', 'America/New_York', 'America/Bogota');
?>
```

**Ejecutar:** `http://localhost/medtravel/admin/test_timezone.php`

**Resultado Esperado:**
- ✅ Conversiones correctas según offset de cada zona
- ✅ HTML dual display muestra ambas horas formateadas
- ✅ No hay errores PHP

---

### TEST 12: Timezones en Clientes (Defaults)
**Objetivo:** Verificar columna `client_timezone`

**Pasos:**
1. Ejecutar en SQL:
```sql
SELECT id, nombre, apellido, client_timezone 
FROM clientes 
LIMIT 5;
```

**Resultado Esperado:**
- ✅ Todos los clientes tienen `client_timezone` = `'America/New_York'` (default)
- ✅ Si es NULL, el script de migración no se ejecutó completamente

---

### TEST 13: Timezones en Proveedores (Defaults)
**Objetivo:** Verificar columna `provider_timezone`

**Pasos:**
1. Ejecutar en SQL:
```sql
SELECT id, name, provider_timezone 
FROM providers 
LIMIT 5;
```

**Resultado Esperado:**
- ✅ Todos los proveedores tienen `provider_timezone` = `'America/Bogota'` (default)

---

### TEST 14: Uso Real en Appointments (Cuando se implemente)
**Objetivo:** Documentar flujo completo

**FLUJO AL CREAR CITA:**
1. Cliente ingresa fecha/hora: "2024-02-20 10:00 AM"
2. Sistema detecta `client_timezone`: "America/New_York"
3. Backend convierte a UTC: `convertToUTC('2024-02-20 10:00:00', 'America/New_York')`
4. Guarda en BD: `appointment_datetime_utc` = '2024-02-20 15:00:00'
5. También guarda: `client_timezone` y `provider_timezone`

**FLUJO AL MOSTRAR CITA:**
1. Lee de BD: `appointment_datetime_utc` = '2024-02-20 15:00:00'
2. Usa `displayDualTimezone()` con timezones guardados
3. Muestra:
   - 👤 Cliente: **20 Feb 2024, 10:00 AM** (EST)
   - 🏥 Proveedor: **20 Feb 2024, 10:00 AM** (COT)

**Resultado Esperado:**
- ✅ Ambos ven la cita en su hora local
- ✅ No hay confusión de horarios
- ✅ UTC en BD asegura consistencia

---

## VALIDACIONES GENERALES

### TEST 15: Permisos y Seguridad
**Objetivo:** Verificar validaciones de sesión

**Pasos:**
1. Cerrar sesión
2. Intentar acceder directamente a:
   - `admin/paquetes.php`
   - `admin/ajax/upload_document.php`
   - `admin/ajax/paquetes.php?action=list`

**Resultado Esperado:**
- ✅ Redirige a login o muestra error de sesión
- ✅ No expone datos sin autenticación

---

### TEST 16: Errores y Logs
**Objetivo:** Verificar manejo de errores

**Pasos:**
1. Revisar logs PHP: `admin/logs/` o `/var/log/php/error.log`
2. Verificar que NO haya:
   - PHP Notices
   - PHP Warnings
   - Uncaught Exceptions

**Resultado Esperado:**
- ✅ Solo logs informativos (si los hay)
- ✅ Errores capturados con try/catch muestran mensajes user-friendly

---

### TEST 17: Mobile Responsive
**Objetivo:** Verificar usabilidad en móvil

**Pasos:**
1. Abrir en Chrome DevTools (F12)
2. Modo responsive: iPhone 12
3. Navegar por:
   - Lista de paquetes
   - Formulario de paquete
   - Modal de upload

**Resultado Esperado:**
- ✅ Datatable adaptable (scroll horizontal si es necesario)
- ✅ Formularios usables (campos no cortados)
- ✅ Botones accesibles (no solapados)

---

## CHECKLIST FINAL

Antes de dar por terminado cada módulo, verificar:

**Módulo Paquetes:**
- [x] CRUD completo funcional
- [x] Cálculos en vivo correctos
- [x] Triggers calculan márgenes
- [x] Warnings para margen negativo
- [x] Validaciones server-side
- [x] Toast notifications claras
- [x] Datatable carga datos
- [x] Sin errores PHP/JS en consola

**Módulo Upload:**
- [x] Upload funciona (PDF, JPG, PNG)
- [x] Validaciones de tamaño/tipo
- [x] Archivo se guarda físicamente
- [x] Metadata en BD correcta
- [x] Lista de documentos se muestra
- [x] Descargar funciona
- [x] Eliminar funciona (físico + BD)
- [x] Sin vulnerabilidades de upload

**Módulo Timezones:**
- [x] Helper creado y documentado
- [x] Funciones de conversión probadas
- [x] Defaults aplicados (NY, Bogotá)
- [x] Ejemplos de uso claros
- [x] Sin errores de timezone

---

## BUGS CONOCIDOS Y LIMITACIONES

### Limitaciones Actuales:
1. **Paquetes:** No valida overlap de fechas de un mismo cliente
2. **Upload:** Sin escaneo antivirus (para producción considerar ClamAV)
3. **Timezones:** Requiere PHP 5.2+ con DateTimeZone
4. **Appointments:** Módulo no implementado aún (solo helper disponible)

### Mejoras Futuras (No Críticas):
- Paquetes: Agregar historial de cambios
- Upload: Implementar compresión de imágenes
- Timezones: Selector visual de zonas horarias en UI
- General: Agregar auditoría de acciones

---

## SOPORTE Y CONTACTO

**Documentación Relacionada:**
- [MEJORAS_COMERCIALES_README.md](MEJORAS_COMERCIALES_README.md) - Arquitectura completa
- [timezone_helper.php](admin/include/timezone_helper.php) - Documentación de funciones
- [sql/FASE_1_MEJORAS_COMERCIALES_SAFE.sql](sql/FASE_1_MEJORAS_COMERCIALES_SAFE.sql) - Migración de BD

**Logs de Errores:**
- PHP Errors: `admin/logs/` o configurar en `php.ini`
- MySQL Errors: Revisar `mysql_error()` en respuestas AJAX

**Fecha del Manual:** 29 de enero de 2026
**Versión:** 1.0
