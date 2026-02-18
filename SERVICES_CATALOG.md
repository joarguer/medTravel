# SERVICES_CATALOG

## Resumen
En el repo no existe un único “catálogo de servicios”; hay **tres** estructuras funcionales relacionadas:
1. **Catálogo médico core** (`service_categories` + `service_catalog` + `provider_service_offers`).
2. **Catálogo complementario MedTravel** (`medtravel_services_catalog` + `service_providers`).
3. **Contenido editorial de coordinación** para `services.php` (`coordination_services` + `services_page_header`).

**Evidencia**
- `offers.php:41-64` → catálogo médico basado en `provider_service_offers` + `service_catalog`
- `services.php:15-23` → catálogo complementario basado en `medtravel_services_catalog`
- `services.php:5-13` y `admin/services_edit.php:6-9` → header/coordination content en tablas propias

---

## 1) Servicios médicos (core)

### 1.1 Fuente de datos y modelo
- Categorías médicas: `service_categories`.
- Servicios médicos normalizados: `service_catalog` (cada servicio pertenece a una categoría).
- Publicación comercial real (precio/oferta): `provider_service_offers` (por prestador y servicio).

**Evidencia**
- `sql/service_categories.sql:2-12` → estructura de categorías
- `sql/service_catalog.sql:2-14` → estructura de servicios médicos
- `sql/provider_offers.sql:15-29` → estructura de ofertas por prestador
- `offers.php:41-64` → query operacional consumida por frontend

### 1.2 Categorías / especialidades encontradas en repo

#### Categorías confirmadas por scripts base/dump
- Odontología
- Dermatología

**Evidencia**
- `sql/service_categories.sql:15-21`
- `sql/bolsacar_medtravel.sql:315-317`

#### Categorías adicionales en script de instalación local (seed demo)
- Dentistry
- Plastic Surgery
- Cardiology
- Orthopedics
- Dermatology

**Evidencia**
- `sql/INSTALL_LOCAL.sql:131-136`

### 1.3 Servicios médicos concretos encontrados

#### Servicios confirmados por dump/script base
- Limpieza dental
- Consulta dermatológica (seed en `service_catalog.sql`)

**Evidencia**
- `sql/bolsacar_medtravel.sql:291-293`
- `sql/service_catalog.sql:18-27`

#### Servicios adicionales en seed local (demo)
- Dental Implants, Teeth Whitening, Orthodontics
- Rhinoplasty, Liposuction, Breast Augmentation
- Cardiac Surgery, Angioplasty
- Hip Replacement, Knee Surgery

**Evidencia**
- `sql/INSTALL_LOCAL.sql:139-149`

### 1.4 Qué campos existen para cada servicio médico
- Identidad: `id`, `name`, `slug`, `category_id`.
- Copy base: `short_description`.
- Publicación: `is_active`, `sort_order`.
- Precio no vive en `service_catalog`; vive en `provider_service_offers.price_from` + `currency`.
- Prestador/ubicación: se obtienen al unir con `providers` (`name`, `city`, `logo`).

**Evidencia**
- `sql/service_catalog.sql:4-10`
- `sql/provider_offers.sql:17-24`
- `offers.php:43-45` y `offers.php:58-60` → precio/proveedor/ciudad en query
- `offer_detail.php:29-31` → datos de proveedor usados en detalle

### 1.5 Requisitos/availability/ubicación
- No existe campo explícito de “requisitos clínicos” por servicio médico en `service_catalog`.
- Disponibilidad médica operativa se representa por `is_active` en catálogo y oferta.
- Ubicación visible se deriva del prestador (`providers.city`), no del servicio.

**Evidencia**
- `sql/service_catalog.sql:2-14` (sin campo de requisitos clínicos)
- `sql/provider_offers.sql:23` (`is_active` oferta)
- `offers.php:44-45` y `offers.php:59-60` (ciudad de proveedor)

---

## 2) Servicios anexos / complementarios

### 2.1 Catálogo complementario principal
- Tabla: `medtravel_services_catalog`.
- Tipos definidos en enum: `flight`, `accommodation`, `transport`, `meals`, `support`, `other`.
- Soporta copy corto/largo, disponibilidad, precios, costo/comisión, icono/imagen, tags y metadata.

**Evidencia**
- `sql/medtravel_services_catalog.sql:7-66`
- `sql/medtravel_services_catalog.sql:11` → enum de tipos
- `sql/medtravel_services_catalog.sql:40-50` → disponibilidad/visualización
- `sql/medtravel_services_catalog.sql:24-30` → costos/precios/comisión

### 2.2 Proveedores complementarios
- Tabla: `service_providers` (aerolíneas/hoteles/transporte/restaurantes/etc.).
- `medtravel_services_catalog` incorpora `provider_id` con FK opcional.

**Evidencia**
- `sql/service_providers_table.sql:7-49` → tabla proveedores complementarios
- `sql/service_providers_table.sql:55-80` → add column `provider_id` + FK
- `sql/service_providers_table.sql:122-125` → migración provider_name -> provider_id

### 2.3 Servicios anexos observables en seeds
- Flight: round-trip Miami/Los Angeles.
- Accommodation: hotel standard/premium.
- Transport: airport transfer / 7-day package.
- Meals: breakfast/full plan.
- Support: basic and 24/7 premium.

**Evidencia**
- `sql/medtravel_services_catalog.sql:108-181`

### 2.4 Contenido de coordinación (copy institucional)
Además del catálogo complementario transaccional, existe un bloque editorial de coordinación:
- Medical Coordination
- Flight Management
- Accommodation
- Local Transportation
- Meals
- 24/7 Support

**Evidencia**
- `sql/services_coordination_table.sql:2-11` → `coordination_services`
- `sql/services_coordination_table.sql:17-23` → 6 servicios de coordinación
- `SERVICES_DYNAMIC_README.md:16-27` → describe gestión dinámica de esos contenidos

### 2.5 Dónde se muestran/consumen
- `services.php` publica catálogo complementario por `service_type`.
- `booking/wizard.php` Stage 2 consume el mismo catálogo para add-ons.
- `booking/submit.php` convierte selección de complementarios en texto dentro de `additional_notes`.

**Evidencia**
- `services.php:17-27` y `services.php:174-233`
- `booking/wizard.php:345-352` y `booking/wizard.php:361-363`
- `booking/submit.php:41-52`

### 2.6 Estado actual de soft delete (2026-02-18)
- `medtravel_services_catalog` opera con soft delete (`is_deleted`, `deleted_at`, `deleted_by`) y desactivación legacy (`is_active=0`).
- El endpoint admin `admin/ajax/medtravel_services.php`:
  - Lista/lecturas filtran `is_deleted=0` cuando la columna existe.
  - Acción `delete` aplica soft delete si existen columnas; si faltan, hace fallback legacy (`is_active=0`) sin romper runtime.
- El módulo `admin/cleanup.php` permite soft delete/restore para:
  - `usuarios`
  - `providers`
  - `service_providers`
  - `medtravel_services_catalog`
- Las operaciones de restore reactivan (`activo/is_active=1`) y limpian metadatos de borrado lógico.

---

## 3) Implementación técnica del catálogo (tablas, CRUD, endpoints)

### 3.1 Tablas MySQL involucradas

#### Catálogo médico
- `service_categories` (categorías)
- `service_catalog` (servicios)
- `providers` (prestadores médicos)
- `provider_service_offers` (oferta/precio por prestador)
- `offer_media` (media de oferta)
- Relacionales auxiliares: `provider_categories`, `provider_catalog_services`, `provider_users`

**Evidencia**
- `sql/service_categories.sql:2-12`
- `sql/service_catalog.sql:2-14`
- `sql/providers.sql:2-17`
- `sql/provider_offers.sql:15-40`
- `sql/providers.sql:19-41`

#### Catálogo complementario
- `service_providers`
- `medtravel_services_catalog`
- `exchange_rates` (soporte de tasa COP)

**Evidencia**
- `sql/service_providers_table.sql:7-49`
- `sql/medtravel_services_catalog.sql:7-66`
- `sql/INSTALL_COP_SYSTEM.sql:11-27`

#### Booking y paquete (integración de catálogo)
- `booking_requests` (captura wizard)
- `travel_packages` y `package_services` (cuando se usa módulo paquetes)

**Evidencia**
- `sql/booking_requests.sql:2-18`
- `sql/ALTER_booking_requests_complete.sql:4-11`
- `sql/FASE_1_CRM_AGENDAMIENTO.sql:133-241`
- `sql/package_services_integration.sql:7-21`

### 3.2 Páginas admin y CRUD

#### Médico
- Categorías: `admin/service_categories.php` + `admin/ajax/service_categories.php` + `admin/js/service_categories.js`.
- Servicios catálogo: `admin/service_catalog.php` + `admin/ajax/service_catalog.php` + `admin/js/service_catalog.js`.
- Ofertas por prestador: `admin/provider_offers.php` + `admin/ajax/provider_offers.php` + `admin/js/provider_offers.js`.

**Evidencia**
- `admin/service_categories.php:58-69` y `admin/service_categories.php:84-127`
- `admin/ajax/service_categories.php:38-49`, `admin/ajax/service_categories.php:51-73`, `admin/ajax/service_categories.php:75-138`, `admin/ajax/service_categories.php:140-151`
- `admin/service_catalog.php:60-72` y `admin/service_catalog.php:88-137`
- `admin/ajax/service_catalog.php:22-37`, `admin/ajax/service_catalog.php:39-75`, `admin/ajax/service_catalog.php:77-113`, `admin/ajax/service_catalog.php:115-125`
- `admin/provider_offers.php:119-130` y `admin/provider_offers.php:207-253`
- `admin/ajax/provider_offers.php:77-110`, `admin/ajax/provider_offers.php:113-153`, `admin/ajax/provider_offers.php:156-170`, `admin/ajax/provider_offers.php:173-214`

#### Complementario
- Proveedores: `admin/providers_complementary.php` + `admin/ajax/service_providers.php` + `admin/js/providers_complementary.js`.
- Servicios: `admin/medtravel_services.php` + `admin/ajax/medtravel_services.php` + `admin/js/medtravel_services.js`.

**Evidencia**
- `admin/providers_complementary.php:75-91` y `admin/providers_complementary.php:103-260`
- `admin/ajax/service_providers.php:39-72`, `admin/ajax/service_providers.php:95-141`, `admin/ajax/service_providers.php:143-182`, `admin/ajax/service_providers.php:184-214`, `admin/ajax/service_providers.php:216-241`
- `admin/medtravel_services.php:173-186` y `admin/medtravel_services.php:214-340`
- `admin/ajax/medtravel_services.php:63-101`, `admin/ajax/medtravel_services.php:106-132`, `admin/ajax/medtravel_services.php:137-228`, `admin/ajax/medtravel_services.php:233-280`

#### Contenido de página servicios
- `admin/services_edit.php` + `admin/ajax/services_edit.php` + `admin/js/services_edit.js`.

**Evidencia**
- `admin/services_edit.php:97-110`
- `admin/ajax/services_edit.php:8-23`, `admin/ajax/services_edit.php:26-32`, `admin/ajax/services_edit.php:35-58`, `admin/ajax/services_edit.php:61-78`, `admin/ajax/services_edit.php:81-122`

### 3.3 Endpoints AJAX y cobertura real
- Cobertura CRUD completa en complementarios (`create/update/delete/toggle/list/get`) y proveedores complementarios.
- En catálogo médico, “delete” en UI está implementado como `toggle` (soft-disable), no borrado físico.
- En ofertas médicas hay CRUD + upload de media.

**Evidencia**
- `admin/js/service_categories.js:70-76` y `admin/ajax/service_categories.php:140-151`
- `admin/js/service_catalog.js:83-85` y `admin/ajax/service_catalog.php:115-125`
- `admin/js/provider_offers.js:155-160` y `admin/ajax/provider_offers.php:173-214`
- `admin/ajax/medtravel_services.php:233-251` (delete físico)

### 3.4 Validaciones y seguridad

#### Controles presentes
- `require_login_ajax()` en endpoints médicos clave.
- Uso de prepared statements en buena parte de catálogo médico/ofertas.
- Validación de uploads en ofertas médicas y servicios complementarios (tipo/tamaño/extensión).

**Evidencia**
- `admin/ajax/service_categories.php:4`
- `admin/ajax/service_catalog.php:6`
- `admin/ajax/provider_offers.php:14`
- `admin/ajax/provider_offers.php:186-201`
- `admin/ajax/medtravel_services.php:292-297`

#### Huecos de seguridad/consistencia
- `admin/ajax/services_edit.php` no llama `require_login_ajax()` (solo `session_start`).
- `admin/ajax/medtravel_services.php` y `admin/ajax/service_providers.php` validan sesión, pero no permisos granulares (`user_can`).
- `admin/ajax/service_providers.php` y `admin/ajax/medtravel_services.php` usan SQL construido con interpolación/escape manual, no prepared statements en gran parte.

**Evidencia**
- `admin/ajax/services_edit.php:2-5`
- `admin/ajax/medtravel_services.php:11-15` y `admin/ajax/medtravel_services.php:167-168`
- `admin/ajax/service_providers.php:9-12` y `admin/ajax/service_providers.php:107-130`
- `admin/ajax/providers.php:25-28`, `admin/ajax/providers.php:117-120`, `admin/ajax/providers.php:401-404` (contraste con endpoint que sí aplica permisos)

### 3.5 Inconsistencias detectadas (hardcode/BD/campos)
1. **Múltiples fuentes de verdad para catálogo**:
- `offers.php` usa catálogo médico;
- `services.php` usa catálogo complementario;
- `index.php` mantiene sección de servicios hardcode aparte de una dinámica.

**Evidencia**
- `offers.php:41-64`
- `services.php:17-23`
- `index.php:110-183` y `index.php:541-552`

2. **Deriva de esquema en `service_catalog`**:
- scripts antiguos (`INSTALL*.sql`) modelan `description/icon`,
- código actual opera con `slug/short_description/sort_order`.

**Evidencia**
- `sql/INSTALL.sql:99-107`
- `sql/INSTALL_LOCAL.sql:44-53`
- `sql/service_catalog.sql:6-9`
- `admin/ajax/service_catalog.php:25` y `admin/ajax/service_catalog.php:66`

3. **Módulo de paquetes parcialmente conectado al catálogo**:
- backend tiene endpoints para `package_services`,
- frontend (`admin/js/paquetes.js`) maneja `selectedServices` solo en cliente y no invoca persistencia `add_service_to_package` al guardar.

**Evidencia**
- `admin/ajax/paquetes.php:60-70` y `admin/ajax/paquetes.php:666-779`
- `admin/js/paquetes.js:6` y `admin/js/paquetes.js:565-605`
- `admin/js/paquetes.js` (sin llamadas a `add_service_to_package`/`remove_service_from_package`/`get_package_services`)

4. **`booking_requests` con evolución fragmentada**:
- script base sin `selected_offers/status/phone`;
- alter separado agrega parte de los campos;
- runtime ya depende de esos campos.

**Evidencia**
- `sql/booking_requests.sql:2-18`
- `sql/ALTER_booking_requests_complete.sql:4-11`
- `booking/submit.php:77-80`
- `admin/ajax/booking_requests.php:14-17`

---

## Conclusión sobre “fuente real” del catálogo
- **Fuente operativa real en ejecución**: tablas MySQL consultadas por páginas/endpoint (`service_catalog`+`provider_service_offers` para médico; `medtravel_services_catalog` para complementario).
- **Fuente de estructura en repo**: dispersa entre varios scripts SQL (instaladores, migraciones y dump), con diferencias importantes.
- **Fuente editorial paralela**: bloques hardcode y tablas de contenido (`coordination_services`, `home_services`) que conviven con catálogos transaccionales.

**Evidencia**
- `offers.php:41-64`, `services.php:17-23`, `booking/wizard.php:345-352`
- `sql/service_catalog.sql:2-14`, `sql/INSTALL.sql:99-107`, `sql/bolsacar_medtravel.sql:276-285`
- `sql/services_coordination_table.sql:2-11` y `index.php:110-183`
