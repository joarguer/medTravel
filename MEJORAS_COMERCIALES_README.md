# MEJORAS COMERCIALES - Decisiones de Diseño y Guía de Implementación

**Fecha:** 29 de enero de 2026  
**Proyecto:** MedTravel - Turismo Médico  
**Fase:** 1 - Mejoras Comerciales  

---

## 📊 RESUMEN EJECUTIVO

Este documento describe las mejoras comerciales implementadas sobre el sistema MedTravel para convertir el admin en un **"centro de control del negocio"**, aumentar la conversión mediante confianza verificable, medir adquisición de clientes y evitar errores operativos por zonas horarias.

### Mejoras Implementadas:

1. ✅ **Monetización Explícita** - Cálculo automático de márgenes y fees
2. ✅ **Sistema de Verificación** - Checklist documentado para proveedores
3. ✅ **Tracking de Campañas** - UTM params y análisis de conversión
4. ✅ **Manejo de Timezones** - Almacenamiento en UTC + visualización dual

---

## 1. MONETIZACIÓN EXPLÍCITA

### Decisión de Diseño: Modelo de Cálculo

**Pregunta clave:** ¿El `total_package_cost` incluye la ganancia de MedTravel?

**Respuesta:** **SÍ**

### Fórmulas de Cálculo (Implementadas en Triggers)

```sql
-- Costos reales (lo que MedTravel PAGA)
costos_totales = 
    flight_cost + 
    hotel_total_cost + 
    transport_cost + 
    meals_cost + 
    medical_service_cost + 
    additional_services_cost

-- Fee de MedTravel (calculado automáticamente)
IF medtravel_fee_type = 'fixed' THEN
    medtravel_fee_amount = medtravel_fee_value
ELSE
    medtravel_fee_amount = (total_package_cost * medtravel_fee_value) / 100
END IF

-- Margen bruto (ganancia antes de comisiones)
gross_margin = total_package_cost - costos_totales

-- Margen neto (ganancia después de comisionar al proveedor)
net_margin = gross_margin - provider_commission_value
```

### Campos Agregados a `travel_packages`

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `medtravel_fee_type` | ENUM('fixed','percent') | 'percent' | Tipo de tarifa |
| `medtravel_fee_value` | DECIMAL(10,2) | 0.00 | Valor: $ fijo o % |
| `medtravel_fee_amount` | DECIMAL(10,2) | 0.00 | Monto calculado |
| `provider_commission_value` | DECIMAL(10,2) | 0.00 | Comisión al proveedor |
| `gross_margin` | DECIMAL(10,2) | 0.00 | Margen bruto |
| `net_margin` | DECIMAL(10,2) | 0.00 | Margen neto |

### Triggers Automáticos

Se crearon 2 triggers que calculan automáticamente los márgenes:
- `trg_travel_packages_calc_margins_insert` - Al crear paquete
- `trg_travel_packages_calc_margins_update` - Al actualizar paquete

**Ventaja:** Los cálculos son consistentes y no dependen del código PHP.

### Vista de Reportes

```sql
-- Vista: v_package_margins
SELECT 
    package_name,
    client_name,
    total_package_cost,
    gross_margin,
    net_margin,
    gross_margin_percent,  -- % de ganancia bruta
    net_margin_percent,    -- % de ganancia neta
    status
FROM v_package_margins;
```

### Ejemplo Práctico

```
Cliente: John Doe
Procedimiento: Cirugía Plástica + Paquete Completo

COSTOS (Lo que MedTravel PAGA):
- Vuelo:           $600
- Hotel (5 noches): $500
- Transporte:      $150
- Alimentación:    $250
- Cirugía:         $3,500
------------------------
TOTAL COSTOS:      $5,000

PRICING (Lo que Cobra MedTravel):
- Total al Cliente: $7,000
- Fee MedTravel: 15% = $1,050 (informativo, ya incluido en total)
- Comisión al Provider: $200

MÁRGENES (Calculados Automáticamente):
- Gross Margin: $7,000 - $5,000 = $2,000 (28.57%)
- Net Margin: $2,000 - $200 = $1,800 (25.71%)

✅ MedTravel gana neto: $1,800 USD
```

---

## 2. SISTEMA DE VERIFICACIÓN DE PROVEEDORES

### Objetivo

Generar **confianza verificable** en los proveedores médicos mediante un checklist documentado con evidencia.

### Arquitectura de 3 Tablas

#### 2.1 `provider_verification` (Estado General)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `provider_id` | INT | FK a providers |
| `status` | ENUM | pending, in_review, verified, rejected, suspended |
| `verification_level` | ENUM | basic, standard, premium |
| `trust_score` | INT(0-100) | % de items verificados |
| `verified_at` | DATETIME | Fecha de verificación |
| `verified_by` | INT | Admin que verificó |
| `admin_notes` | TEXT | Notas internas |

#### 2.2 `provider_verification_items` (Checklist)

Checklist estándar con 11 items:

**LEGAL:**
1. ✅ Registro Empresarial (obligatorio)
2. ✅ RUT o Tax ID (obligatorio)

**MEDICAL:**
3. ✅ Licencia Médica (obligatorio)
4. ⭕ Certificaciones Profesionales (opcional)
5. ✅ Acreditación de Clínica (obligatorio)

**FACILITIES:**
6. ✅ Fotos de Instalaciones (obligatorio)
7. ⭕ Certificación de Equipos (opcional)

**IDENTITY:**
8. ✅ Identidad del Responsable (obligatorio)
9. ⭕ Credenciales del Personal (opcional)

**INSURANCE:**
10. ✅ Seguro de Responsabilidad (obligatorio)
11. ⭕ Seguro contra Mala Praxis (opcional)

#### 2.3 `provider_documents` (Evidencia)

Almacena archivos PDF, imágenes, etc. vinculados a items del checklist.

### Stored Procedure

```sql
CALL sp_create_verification_checklist(provider_id);
```

Crea automáticamente los 11 items estándar para un proveedor.

### Trust Score (Cálculo Automático)

```
trust_score = (items_verificados / total_items) * 100
```

El score se actualiza automáticamente al marcar/desmarcar items.

### Interfaz Admin

**Ruta:** `admin/provider_verification.php`

**Características:**
- Tabla con todos los proveedores y su estado
- Modal con checklist interactivo
- Cambio de estado: pending → in_review → verified
- Tracking de quién verificó y cuándo
- Adjuntar evidencia documental (en desarrollo)

### Badges Visuales

| Status | Badge | Color |
|--------|-------|-------|
| pending | Pendiente | Gris |
| in_review | En Revisión | Amarillo |
| verified | ✓ Verificado | Verde |
| rejected | Rechazado | Rojo |
| suspended | Suspendido | Negro |

### Uso en Frontend (Futuro)

```php
// Mostrar solo proveedores verificados
SELECT * FROM providers p
INNER JOIN provider_verification pv ON p.id = pv.provider_id
WHERE pv.status = 'verified' 
  AND pv.trust_score >= 80
```

---

## 3. TRACKING DE CAMPAÑAS (UTM)

### Objetivo

Medir ROI de campañas de marketing y optimizar canales de adquisición.

### Campos Agregados a `clientes`

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| `utm_source` | Origen del tráfico | google, facebook, email |
| `utm_medium` | Medio de marketing | cpc, banner, newsletter |
| `utm_campaign` | Nombre de campaña | summer_promo, black_friday |
| `utm_content` | Variante del anuncio | banner_azul, texto_a |
| `utm_term` | Términos de búsqueda | cirugia plastica colombia |
| `referred_by` | Referido por | nombre o ID |
| `landing_page` | Primera página visitada | /offers/dentistry |
| `conversion_page` | Página de conversión | /contact |

### Captura Automática (Frontend)

```javascript
// En landing page o formulario de contacto
$(document).ready(function() {
    // Capturar UTMs de la URL
    var urlParams = new URLSearchParams(window.location.search);
    
    $('#utm_source').val(urlParams.get('utm_source'));
    $('#utm_medium').val(urlParams.get('utm_medium'));
    $('#utm_campaign').val(urlParams.get('utm_campaign'));
    $('#utm_content').val(urlParams.get('utm_content'));
    $('#utm_term').val(urlParams.get('utm_term'));
    
    // Guardar landing page
    $('#landing_page').val(window.location.pathname);
});
```

### Ejemplo de URL con UTMs

```
https://medtravel.com/?
  utm_source=google&
  utm_medium=cpc&
  utm_campaign=summer_promo_2026&
  utm_content=banner_dental&
  utm_term=cirugia+estetica+colombia
```

### Vista de Análisis

```sql
-- Vista: v_campaign_performance
SELECT 
    utm_source,
    utm_campaign,
    COUNT(*) as total_leads,
    SUM(CASE WHEN status = 'confirmado' THEN 1 ELSE 0 END) as converted,
    ROUND((converted / total_leads) * 100, 2) as conversion_rate
FROM clientes
WHERE utm_source IS NOT NULL
GROUP BY utm_source, utm_campaign
ORDER BY total_leads DESC;
```

### Dashboard de Marketing (Ejemplo de Query)

```sql
-- Top 5 campañas por conversión
SELECT 
    utm_campaign,
    utm_source,
    COUNT(*) as leads,
    SUM(CASE WHEN status IN ('confirmado','finalizado') THEN 1 ELSE 0 END) as conversions,
    ROUND((conversions / leads) * 100, 2) as conv_rate
FROM clientes
WHERE utm_campaign IS NOT NULL
GROUP BY utm_campaign, utm_source
ORDER BY conversions DESC
LIMIT 5;
```

---

## 4. MANEJO DE TIMEZONES

### Problema

- **Clientes:** Mayormente en USA (EST, PST, CST, etc.)
- **Proveedores:** En Colombia (COT - America/Bogota)
- **Riesgo:** Confusión en horarios de citas, no-shows, pérdida de clientes

### Solución: Almacenamiento en UTC + Visualización Dual

### Campos Agregados

#### Tabla `clientes`
- `client_timezone` VARCHAR(60) DEFAULT 'America/New_York'

#### Tabla `providers`
- `provider_timezone` VARCHAR(60) DEFAULT 'America/Bogota'

#### Tabla `appointments`
- `appointment_datetime_utc` DATETIME (nueva, almacena en UTC)
- `appointment_end_utc` DATETIME (nueva, almacena en UTC)
- `client_timezone` VARCHAR(60) (TZ al momento de crear cita)
- `provider_timezone` VARCHAR(60) (TZ al momento de crear cita)

### Flujo de Conversión

```
1. Cliente (Miami, EST): Solicita cita para "2:00 PM EST"

2. Sistema almacena:
   - appointment_datetime_utc: 2026-02-15 19:00:00 (UTC)
   - client_timezone: America/New_York
   - provider_timezone: America/Bogota

3. Vista del Cliente:
   - "Your appointment: 2:00 PM EST"

4. Vista del Proveedor:
   - "Cita: 2:00 PM COT" (mismo momento, diferente TZ)

5. Google Calendar:
   - Envía evento con timezone del proveedor
   - Google Calendar maneja conversión automáticamente
```

### Implementación PHP (Ejemplo)

```php
<?php
// Convertir de local a UTC
$client_tz = new DateTimeZone('America/New_York');
$utc_tz = new DateTimeZone('UTC');

$local_time = new DateTime('2026-02-15 14:00:00', $client_tz);
$utc_time = $local_time->setTimezone($utc_tz);

// Guardar en BD
$appointment_datetime_utc = $utc_time->format('Y-m-d H:i:s');

// Convertir de UTC a timezone del proveedor
$provider_tz = new DateTimeZone('America/Bogota');
$utc_datetime = new DateTime($appointment_datetime_utc, $utc_tz);
$provider_time = $utc_datetime->setTimezone($provider_tz);

echo "Hora cliente: " . $local_time->format('Y-m-d H:i:s T');
echo "Hora proveedor: " . $provider_time->format('Y-m-d H:i:s T');
?>
```

### UI/UX Recomendado

```html
<!-- En admin: mostrar AMBAS horas -->
<div class="appointment-time">
    <strong>Cita #123</strong><br>
    <i class="fa fa-user"></i> Cliente (EST): Feb 15, 2026 - 2:00 PM<br>
    <i class="fa fa-hospital"></i> Proveedor (COT): Feb 15, 2026 - 2:00 PM<br>
    <small class="text-muted">UTC: 2026-02-15 19:00:00</small>
</div>
```

### Google Calendar Integration

```php
<?php
// Al crear evento en Google Calendar
$provider_timezone = 'America/Bogota';
$utc_datetime = '2026-02-15 19:00:00';

$event = new Google_Service_Calendar_Event([
    'summary' => 'Consulta Médica - John Doe',
    'start' => [
        'dateTime' => convertUTCtoTimezone($utc_datetime, $provider_timezone),
        'timeZone' => $provider_timezone
    ],
    'end' => [
        'dateTime' => convertUTCtoTimezone($utc_end, $provider_timezone),
        'timeZone' => $provider_timezone
    ]
]);
?>
```

### Timezones Comunes USA

| Zona | IANA Code | UTC Offset |
|------|-----------|------------|
| Eastern | America/New_York | UTC-5 (EST) / UTC-4 (EDT) |
| Central | America/Chicago | UTC-6 (CST) / UTC-5 (CDT) |
| Mountain | America/Denver | UTC-7 (MST) / UTC-6 (MDT) |
| Pacific | America/Los_Angeles | UTC-8 (PST) / UTC-7 (PDT) |
| Florida | America/New_York | UTC-5 (EST) / UTC-4 (EDT) |

---

## 📁 ARCHIVOS CREADOS/MODIFICADOS

### SQL
- ✅ `sql/FASE_1_MEJORAS_COMERCIALES.sql` (migración completa)

### Módulo de Clientes (Actualizado)
- ✅ `admin/clientes.php` (agregados campos UTM)
- ✅ `admin/ajax/clientes.php` (manejo de UTMs)
- ✅ `admin/js/clientes.js` (formulario extendido)

### Módulo de Verificación (Nuevo)
- ✅ `admin/provider_verification.php`
- ✅ `admin/ajax/provider_verification.php`
- ✅ `admin/js/provider_verification.js`

### Configuración
- ✅ `admin/include/include.php` (menú actualizado)
- ✅ `admin/include/valida_session.php` (permisos)

---

## 🔧 PASOS DE INSTALACIÓN

### 1. Ejecutar Migración SQL

```bash
# Backup primero
mysqldump -u root -p medtravel > backup_antes_mejoras.sql

# Ejecutar migración
mysql -u root -p medtravel < sql/FASE_1_MEJORAS_COMERCIALES.sql
```

### 2. Verificar Tablas Creadas

```sql
SHOW TABLES LIKE 'provider_%';
-- Debe mostrar:
-- provider_verification
-- provider_verification_items
-- provider_documents
```

### 3. Verificar Columnas Agregadas

```sql
DESCRIBE travel_packages;
-- Verificar: medtravel_fee_type, medtravel_fee_value, etc.

DESCRIBE clientes;
-- Verificar: utm_source, utm_medium, client_timezone, etc.

DESCRIBE appointments;
-- Verificar: appointment_datetime_utc, client_timezone, etc.
```

### 4. Probar Triggers

```sql
-- Crear paquete de prueba
INSERT INTO travel_packages (
    client_id, 
    start_date, 
    end_date,
    total_package_cost,
    flight_cost,
    hotel_total_cost,
    medtravel_fee_type,
    medtravel_fee_value,
    provider_commission_value
) VALUES (
    1,
    '2026-03-01',
    '2026-03-10',
    7000.00,  -- Total al cliente
    600.00,   -- Vuelo
    500.00,   -- Hotel
    'percent',
    15.00,    -- 15% fee
    200.00    -- Comisión
);

-- Verificar cálculo automático
SELECT 
    total_package_cost,
    medtravel_fee_amount,  -- Debe ser ~1050
    gross_margin,          -- Debe ser ~5900
    net_margin             -- Debe ser ~5700
FROM travel_packages 
WHERE id = LAST_INSERT_ID();
```

### 5. Acceder a Módulos

- **Verificación:** `http://localhost/medtravel/admin/provider_verification.php`
- **Clientes (con UTM):** `http://localhost/medtravel/admin/clientes.php`

---

## ✅ CRITERIOS DE ACEPTACIÓN

### 1. Monetización
- [x] Crear paquete → márgenes se calculan automáticamente
- [x] Cambiar costos → márgenes se actualizan
- [x] Fee type fixed/percent → fee_amount correcto
- [x] Vista v_package_margins funcional

### 2. Verificación
- [x] Checklist se inicializa con 11 items
- [x] Marcar items → trust_score se actualiza
- [x] Cambiar status a verified → fecha y usuario se guardan
- [x] Badge "Verificado" aparece en tabla

### 3. UTM Tracking
- [x] Campos UTM en formulario de clientes
- [x] Guardar y editar con UTMs
- [x] Vista v_campaign_performance funcional
- [x] Filtros por utm_source y utm_campaign (pendiente en UI)

### 4. Timezones
- [x] Columnas UTC agregadas a appointments
- [x] client_timezone y provider_timezone en clientes/providers
- [ ] UI muestra ambas horas (pendiente implementación)
- [ ] Google Calendar recibe TZ correcto (pendiente integración)

---

## 🚀 PRÓXIMOS PASOS

### Corto Plazo (1-2 semanas)
1. **Módulo de Paquetes**
   - Crear admin/paquetes.php
   - Implementar UI con cálculo de márgenes en vivo
   - Alertas cuando net_margin < 0

2. **Upload de Documentos**
   - Implementar función attachEvidence()
   - Upload múltiple de archivos
   - Viewer de PDFs en modal

### Mediano Plazo (1 mes)
3. **Google Calendar Integration**
   - OAuth 2.0 setup
   - Sync bidireccional
   - Manejo de timezones en eventos

4. **Dashboard de Marketing**
   - Gráficos de conversión por campaña
   - ROI calculator
   - Exportar reportes a Excel

5. **Frontend: Badges de Confianza**
   - Mostrar "Verificado ✓" en cards de proveedores
   - Trust score visible para clientes
   - Galería de documentos verificados

### Largo Plazo (2-3 meses)
6. **Automatización de Notificaciones**
   - Email/SMS automático con horarios en ambos TZ
   - Recordatorios 24h antes
   - Follow-up post-cita

7. **BI y Analytics**
   - Dashboard ejecutivo con métricas clave
   - Predicción de conversión con ML
   - Análisis de rentabilidad por proveedor

---

## 📞 SOPORTE Y CONTACTO

Para dudas técnicas o reportar problemas:
- **Email:** dev@medtravel.com
- **Slack:** #dev-medtravel
- **Documentación:** /docs en este repositorio

---

**Última actualización:** 29 de enero de 2026  
**Versión:** 1.0.0  
**Autor:** MedTravel Development Team
