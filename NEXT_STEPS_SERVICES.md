# NEXT_STEPS_SERVICES

Documento auxiliar / legacy de ejecución.

La fuente de verdad vigente del backlog es `docs/canonical/12_EXECUTION_BACKLOG.md`.
Este archivo conserva detalle histórico y apoyo operativo; si difiere, prevalece el backlog canónico.

## Objetivo
Cerrar el flujo completo de catálogo de servicios con operación consistente:
1. CRUD admin
2. Publicación web
3. Relación prestador-servicio
4. Captura/seguimiento en booking

---

## Estado actual resumido (base para priorización)
- CRUD médico y complementario existe, pero con diferencias de seguridad y de esquema.
- Publicación web existe en dos catálogos separados (médico/complementario) + contenido hardcode residual.
- Booking captura ofertas médicas en JSON y servicios complementarios en texto libre (no relacional).
- Módulo de paquetes tiene backend para `package_services`, pero el frontend no persiste selección del catálogo.

**Evidencia**
- `admin/ajax/service_catalog.php:22-125`, `admin/ajax/medtravel_services.php:63-280`
- `offers.php:41-64`, `services.php:17-23`, `index.php:110-183`
- `booking/submit.php:41-52` y `booking/submit.php:59-80`
- `admin/ajax/paquetes.php:666-779` vs `admin/js/paquetes.js:565-605`

---

## Backlog operativo alineado (booking -> coordinación -> calendario -> pago)

Fuente canónica de detalle:
- `MODELO_NEGOCIO_ACTUALIZADO.md` -> sección `Backlog – Nueva integración booking -> coordinación -> calendario -> pago`.

Resumen de ejecución recomendado:

### Fase 0: hardening del booking actual (sin romper)
- [ ] Endpoints de booking con RBAC homogéneo.
- [ ] Sin borrado físico operativo de solicitudes.
- [ ] Mantener compatibilidad del wizard actual.
Done:
- Operación actual estable y auditable.

### Fase 1: estructurar items (incluye complementarios)
- [ ] Estructura relacional por item del caso.
- [ ] Persistencia de selección médica y complementaria por proveedor.
- [ ] Compatibilidad temporal con campos legacy.
Done:
- Caso con múltiples items trazables por proveedor.

### Fase 2: “Mis Solicitudes” por proveedor
- [ ] Vista médica filtrada por `provider_id`.
- [ ] Vista complementaria filtrada por `service_provider_id`.
- [ ] Pipeline de item editable por proveedor.
Done:
- Cada proveedor opera solo sus items.

### Fase 3: calendario MVP
- [ ] Eventos mínimos: `virtual_assessment`, `service_appointment`, `complementary_service_event`.
- [ ] Vista proveedor y vista global admin.
- [ ] Regla mínima anti-overbooking.
Done:
- Item `scheduled` siempre respaldado por evento válido.

### Fase 4: cotización y ajuste de presupuesto
- [ ] Cotización versionada por item.
- [ ] Flujo `quote_sent` -> `quote_accepted`.
- [ ] Snapshot de precio/moneda por item.
Done:
- Cada item aprobado con cotización vigente.

### Fase 5: pagos por proveedor
- [ ] Habilitar pago solo en `ready_for_payment`.
- [ ] Modo por proveedor: `paypal_provider` o `pay_on_arrival`.
- [ ] Estados de pago por item y trazabilidad.
Done:
- Flujo de cobro híbrido operativo por proveedor.

---

## Prioridad alta (P0)

### P0.1 Unificar esquema real de `service_catalog` y `service_categories`
Problema:
- Código actual usa `slug`, `short_description`, `sort_order`, pero instaladores antiguos crean `description/icon` sin esos campos.

Impacto:
- Riesgo alto de instalaciones inconsistentes y fallos en CRUD/listados/API.

Acción concreta:
- Definir un único script canónico de instalación/migración para estas tablas y alinear todos los scripts de bootstrap.

**Evidencia**
- `sql/service_catalog.sql:6-9`
- `sql/INSTALL.sql:99-107`
- `sql/INSTALL_LOCAL.sql:44-53`
- `admin/ajax/service_catalog.php:25` y `admin/ajax/service_catalog.php:66`

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/INSTALL.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/INSTALL_LOCAL.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/service_catalog.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/service_categories.sql`

### P0.2 Cerrar publicación web para evitar doble verdad hardcode vs BD
Problema:
- `index.php` mantiene una sección de servicios hardcode y otra dinámica; `packages.php` sigue template estático.

Impacto:
- El equipo puede actualizar catálogo en admin y no verlo reflejado en todas las páginas comerciales.

Acción concreta:
- Eliminar/retirar bloques hardcode o marcarlos fuera de producción.
- Publicar siempre desde tablas (médico/complementario según contexto).

**Evidencia**
- `index.php:110-183` (hardcode)
- `index.php:541-552` (dinámico)
- `packages.php:124-267` (cards estáticas de template)

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/index.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/packages.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/services.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/offers.php`

### P0.3 Normalizar booking end-to-end (médico + complementario)
Problema:
- Ofertas médicas se guardan en `selected_offers` (JSON), pero complementarios se pasan a texto (`additional_notes`) y no quedan relacionables.
- El esquema base de `booking_requests` no contiene todos los campos usados por runtime.

Impacto:
- No se puede explotar analíticamente qué servicios complementarios se reservan ni auditar selección detallada.

Acción concreta:
- Definir estructura relacional de selección de servicios complementarios por solicitud (ej. tabla pivot de booking).
- Consolidar migración de `booking_requests` para campos efectivamente usados.

**Evidencia**
- `booking/submit.php:41-52` (complementarios -> texto)
- `booking/submit.php:77-80` (insert usa campos ampliados)
- `sql/booking_requests.sql:2-18`
- `sql/ALTER_booking_requests_complete.sql:4-11`

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/booking_requests.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/ALTER_booking_requests_complete.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/booking/submit.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/booking/wizard.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/booking_requests.php`

### P0.4 Completar integración real de catálogo en `paquetes`
Problema:
- Backend expone `add/remove/get package_services`, pero `admin/js/paquetes.js` no invoca esos endpoints al guardar.

Impacto:
- La selección de servicios de catálogo en UI no garantiza persistencia en DB.

Acción concreta:
- Al guardar paquete, persistir `selectedServices` en `package_services` (alta/edición/baja).
- Al editar paquete, cargar `get_package_services` y rehidratar UI.

**Evidencia**
- `admin/ajax/paquetes.php:60-70` y `admin/ajax/paquetes.php:666-779`
- `admin/js/paquetes.js:6` y `admin/js/paquetes.js:565-605`
- `admin/js/paquetes.js` (sin llamadas a `add_service_to_package`)

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/js/paquetes.js`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/paquetes.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/package_services_integration.sql`

### P0.5 Endurecer seguridad/roles en endpoints de catálogo
Problema:
- Endpoints críticos no usan `require_login_ajax()` o no aplican permisos granulares.

Impacto:
- Riesgo de acceso indebido y de operación sin control por rol.

Acción concreta:
- Homogeneizar autorización con `require_login_ajax()` + `user_can(...)` por operación.
- Migrar operaciones SQL interpoladas a prepared statements en endpoints críticos.

**Evidencia**
- `admin/ajax/services_edit.php:2-5` (sin `require_login_ajax`)
- `admin/ajax/medtravel_services.php:11-15` (solo sesión)
- `admin/ajax/service_providers.php:9-12` (solo sesión)
- `admin/ajax/providers.php:25-28`, `admin/ajax/providers.php:117-120`, `admin/ajax/providers.php:401-404` (patrón recomendado ya existente)

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/services_edit.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/medtravel_services.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/service_providers.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/include/roles.php`

---

## Prioridad media (P1)

### P1.1 Estabilizar semántica de flags de activo (`activo` 0/1)
Problema:
- Algunos módulos usan `activo='0'` como registro vigente y otros `activo='1'`.

**Evidencia**
- `offers.php:5` (`services_header` con `activo = '0'`)
- `services.php:5` (`services_page_header` con `activo = '1'`)
- `sql/services_coordination_table.sql:33` (default `'0'`) + insert `'1'` en `sql/services_coordination_table.sql:17-23`

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/services_coordination_table.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/services.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/offers.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/services_edit.php`

### P1.2 Depurar documentación/instalación para evitar rutas históricas divergentes
Problema:
- Hay múltiples scripts de fase/migración que crean duda sobre cuál ejecutar primero.

**Evidencia**
- `sql/` contiene múltiples variantes: `FASE_1_MEJORAS_COMERCIALES*.sql`, `INSTALL*.sql`, `service_*.sql`, `package_services_integration.sql`

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/INSTALL.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/INSTALL_LOCAL.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/DEV_CONTEXT.md`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/SERVICES_CATALOG.md`

---

## Evidencia faltante para cerrar certeza al 100%
1. No hay acceso al estado real de la BD activa en producción desde este análisis (solo código + SQL del repo).
2. No hay un script único de migración “golden path” que garantice versión final de todas las tablas de catálogo.

Para eliminar esta incertidumbre haría falta:
- Export `SHOW CREATE TABLE` de tablas clave en entorno objetivo (`service_catalog`, `service_categories`, `provider_service_offers`, `medtravel_services_catalog`, `service_providers`, `booking_requests`, `travel_packages`, `package_services`).
- Historial de migración ejecutada (orden real de scripts SQL aplicados).

---

## Secuencia recomendada de ejecución (sin rediseño)
1. Alinear esquema canónico (P0.1) y congelar script base.
2. Cerrar persistencia de selección catálogo en paquetes/booking (P0.3 + P0.4).
3. Normalizar seguridad y permisos en endpoints (P0.5).
4. Eliminar fuente hardcode residual de publicación (P0.2).
5. Ajustar consistencia `activo` y limpiar scripts duplicados (P1).

---

## Plan por etapas: Ownership + RBAC para Servicios Complementarios

### Etapa 0: Alineación de esquema canónico y auditoría de fuentes de verdad
Objetivo:
- Definir un baseline único para complementarios (`service_providers` + `medtravel_services_catalog.provider_id`) y cerrar inconsistencias entre SQL base y CRUD actual.

Cambios técnicos (DB / backend / UI):
- DB: consolidar en un script canónico los campos que hoy están dispersos (`provider_id`, `exchange_rate`, `cost_price_cop`) para `medtravel_services_catalog`.
- Backend: validar que el CRUD de complementarios solo use columnas del esquema canónico.
- UI: sin rediseño; solo bloquear uso de campos legacy no canónicos.

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/medtravel_services_catalog.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/service_providers_table.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/add_cop_pricing_fields.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/INSTALL.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/INSTALL_LOCAL.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/medtravel_services.php`

Criterios de aceptación (checklist):
- [ ] Existe una sola ruta de instalación/migración recomendada para complementarios.
- [ ] `medtravel_services_catalog` incluye `provider_id` y campos COP usados por runtime.
- [ ] El CRUD de complementarios deja de depender de columnas opcionales/no migradas.

Riesgos y mitigación:
- Riesgo: drift entre ambientes por scripts históricos múltiples. Mitigación: congelar orden de ejecución y documentar “golden path” en SQL.
- Riesgo: despliegues con tabla antigua rompen CRUD. Mitigación: migraciones idempotentes previas al deploy.

Evidencia:
- `sql/medtravel_services_catalog.sql:7-66` → tabla base no define `provider_id`, `exchange_rate`, `cost_price_cop`.
- `sql/service_providers_table.sql:55-85` → agrega `provider_id` + FK a `service_providers`.
- `sql/add_cop_pricing_fields.sql:8-15` → agrega `exchange_rate` y `cost_price_cop`.
- `admin/ajax/medtravel_services.php:333-340` → runtime espera `provider_id` y `cost_price_cop`.
- `sql/INSTALL.sql:99-108` y `sql/INSTALL_LOCAL.sql:44-54` → instaladores siguen centrados en `service_catalog` (médico), no en esquema complementario final.

### Etapa 1: Modelo de ownership (DB)
Objetivo:
- Vincular explícitamente usuario/empresa complementaria con `service_providers.id` para habilitar aislamiento real.

Cambios técnicos (DB / backend / UI):
- DB: agregar vínculo mínimo `usuarios.service_provider_id` (nullable, FK a `service_providers.id`, índice).
- Backend: mantener compatibilidad con `usuarios.provider_id` (modelo médico) y separar claramente el scope complementario.
- UI: en gestión de usuarios, habilitar asignación de `service_provider_id` para rol complementario.

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/setup_empresas.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/create_roles_and_migrate_users.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/crear_usuario.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/crear_usuario.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/usuarios.php`

Criterios de aceptación (checklist):
- [ ] Un usuario complementario queda asignado a un único `service_provider_id`.
- [ ] No se rompe el flujo existente de usuarios médicos (`provider_id` en `providers`).
- [ ] Existe plan de backfill ejecutable para usuarios y servicios existentes.

Riesgos y mitigación:
- Riesgo: no hay mapeo automático perfecto usuario↔service_provider. Mitigación: backfill en 2 pasos: automático por `medtravel_services_catalog.created_by/provider_id` y cola manual para ambiguos.
- Riesgo: mezclar dominios médico/complementario en un solo campo. Mitigación: separar columna de ownership complementario (`service_provider_id`) en vez de reutilizar `provider_id`.

Evidencia:
- `admin/ajax/usuarios.php:37` → usuarios hoy se cruzan con `providers` (no `service_providers`).
- `admin/ajax/crear_usuario.php:31-32` y `admin/ajax/crear_usuario.php:57-63` → asignación actual de proveedor usa `providers`.
- `admin/crear_usuario.php:185-189` → dropdown de empresa/prestador consulta `providers`.
- `sql/setup_empresas.sql:7-10` → columna existente `usuarios.provider_id` orientada a `providers`.
- `sql/provider_offers.sql:4-12` → `provider_users` también referencia `providers`.
- `admin/ajax/medtravel_services.php:149` y `admin/ajax/medtravel_services.php:333-334` + `sql/medtravel_services_catalog.sql:57` → existe base para backfill usando `created_by` + `provider_id`.
- Gap explícito: no se encontró en el repo columna `service_provider_id` en `usuarios` ni tabla pivot equivalente para `service_providers`.

### Etapa 2: RBAC y permisos server-side
Objetivo:
- Forzar aislamiento multiempresa en backend para complementarios (lectura y escritura).

Cambios técnicos (DB / backend / UI):
- Backend: estandarizar `require_login_ajax()` + `user_can(...)` en endpoints de complementarios.
- Backend: aplicar filtros obligatorios por sesión (`service_provider_id`) en `list/get/update/delete/toggle`.
- Backend: para usuarios complementarios, ignorar `provider_id` enviado por cliente y forzar el de sesión.

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/service_providers.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/medtravel_services.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/include/conexion.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/include/log.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/include/roles.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/include/valida_session.php`

Criterios de aceptación (checklist):
- [ ] Todos los endpoints complementarios rechazan requests sin sesión válida.
- [ ] Usuario complementario solo ve/edita su `service_providers.id`.
- [ ] Usuario complementario solo ve/edita `medtravel_services_catalog.provider_id = su service_provider_id`.
- [ ] Admin/superadmin conserva vista global para soporte y auditoría.

Riesgos y mitigación:
- Riesgo: bypass por request directo a endpoints sin filtro. Mitigación: controles server-side en cada acción, no solo en JS.
- Riesgo: regresión en operaciones admin globales. Mitigación: rama explícita admin con scope global y pruebas de regresión.

Evidencia:
- `admin/ajax/service_providers.php:9-12` → valida sesión manual, sin `require_login_ajax()` ni `user_can(...)`.
- `admin/ajax/service_providers.php:39-72` y `admin/ajax/service_providers.php:84-240` → CRUD global sin filtro por dueño.
- `admin/ajax/medtravel_services.php:11-15` → valida sesión manual; `admin/ajax/medtravel_services.php:63-85` lista global.
- `admin/ajax/medtravel_services.php:114-217` y `admin/ajax/medtravel_services.php:241-279` → get/update/delete/toggle sin ownership filter.
- `admin/ajax/providers.php:4-5` y `admin/ajax/providers.php:25-28` → patrón objetivo ya implementado (`require_login_ajax` + `user_can`).
- `admin/include/conexion.php:91-160` y `admin/include/log.php:195-217` → base actual de hidratación de sesión por proveedor (a extender para complemento).

### Etapa 3: UI/panel sin rediseño (scope por proveedor complementario)
Objetivo:
- Ajustar pantallas existentes para que un proveedor complementario opere solo su propio catálogo.

Cambios técnicos (DB / backend / UI):
- UI: en `medtravel_services`, ocultar selector global de proveedor para rol complementario y fijarlo al scope de sesión.
- UI: en `providers_complementary`, mostrar solo “mi proveedor” para rol complementario (admin mantiene listado global).
- UI: exponer menú/páginas complementarias al rol correspondiente sin cambiar layout visual.

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/medtravel_services.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/js/medtravel_services.js`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/providers_complementary.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/js/providers_complementary.js`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/include/include.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/include/valida_session.php`

Criterios de aceptación (checklist):
- [ ] Rol “Proveedor Complementario” solo ve su scope en listados y formularios.
- [ ] No puede reasignar servicios a otro proveedor desde UI ni por payload manipulado.
- [ ] Admin/superadmin mantiene operación global y capacidades de soporte.

Riesgos y mitigación:
- Riesgo: ocultar controles en UI sin seguridad real. Mitigación: depender de filtros backend de Etapa 2.
- Riesgo: acceso por URL directa a páginas no protegidas por rol. Mitigación: añadir validación de acceso en `valida_session.php`.

Evidencia:
- `admin/medtravel_services.php:279-285` → selector de `provider_id` editable en formulario.
- `admin/js/medtravel_services.js:642-671` → carga proveedores globales (`service_providers.php?action=list&active_only=1`).
- `admin/providers_complementary.php:69-71` y `admin/js/providers_complementary.js:7-14` → listado y CRUD global de proveedores complementarios.
- `admin/include/include.php:326-341` → menú complementario visible solo para admin.
- `admin/include/valida_session.php:56-60` y `admin/include/valida_session.php:71-74` → páginas complementarias no están en `admin_only` (riesgo de acceso directo).

### Etapa 4: Booking relacional para complementarios (operación real)
Objetivo:
- Persistir selección de complementarios en estructura relacional por solicitud, no solo en texto libre.

Cambios técnicos (DB / backend / UI):
- DB: crear tabla pivot de booking para complementarios (ej. `booking_request_complementary_services`) con `booking_request_id`, `medtravel_service_id`, `service_provider_id`, precio/currency snapshot.
- Backend: en `booking/submit.php`, insertar pivote a partir de `medtravel_services[]` y mantener compatibilidad con `additional_notes` legado.
- Backend admin: en `admin/ajax/booking_requests.php`, devolver detalle relacional de complementarios seleccionados.
- UI pública/admin: mantener formularios actuales; solo cambiar persistencia/lectura.

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/booking_requests.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/ALTER_booking_requests_complete.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/booking/submit.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/booking/wizard.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/booking_requests.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/inc/booking_form.php`

Criterios de aceptación (checklist):
- [ ] Cada booking guarda complementarios en tabla relacional consultable.
- [ ] `additional_notes` sigue funcionando para historial/legado.
- [ ] En detalle de booking se visualizan complementarios con servicio, proveedor y precio snapshot.

Riesgos y mitigación:
- Riesgo: romper reportes que dependen de `additional_notes`. Mitigación: estrategia dual-write temporal (pivot + texto).
- Riesgo: pérdida de contexto de precio al cambiar catálogo. Mitigación: guardar snapshot de precio/moneda al momento del booking.

Evidencia:
- `booking/wizard.php:398` y `booking/wizard.php:606-608` → formulario ya envía `medtravel_services[]` y `additional_notes`.
- `booking/submit.php:41-52` → servicios complementarios se concatenan a `additional_notes`.
- `booking/submit.php:77-80` y `booking/submit.php:94-97` → insert actual solo guarda `selected_offers` + texto.
- `sql/booking_requests.sql:2-18` → tabla base sin relación a `medtravel_services_catalog`.
- `sql/ALTER_booking_requests_complete.sql:4-11` → solo agrega `selected_offers` y `status`.
- `admin/ajax/booking_requests.php:14-17` y `admin/ajax/booking_requests.php:52-67` → gestión actual centrada en `selected_offers` (ofertas médicas).

### Etapa 5: Migración y limpieza (datos + hardcode residual)
Objetivo:
- Dejar todos los complementarios con owner válido y eliminar rutas legacy ambiguas.

Cambios técnicos (DB / backend / UI):
- DB: backfill de `medtravel_services_catalog.provider_id` para registros nulos y validación de integridad referencial.
- Backend: retirar fallback legacy de proveedor por texto donde ya exista FK (`provider_id`).
- UI: limpiar inyecciones de “texto resumen” como única persistencia de complementarios.

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/sql/service_providers_table.sql`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/medtravel_services.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/js/medtravel_services.js`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/inc/booking_form.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/booking/submit.php`

Criterios de aceptación (checklist):
- [ ] No quedan servicios complementarios activos sin `provider_id` válido.
- [ ] Las vistas/CRUD operan por FK, no por `provider_name` legacy.
- [ ] La selección de complementarios no depende de `localStorage` + texto para persistencia final.

Riesgos y mitigación:
- Riesgo: registros huérfanos sin proveedor. Mitigación: proveedor placeholder “sin asignar” + cola de depuración.
- Riesgo: romper compatibilidad histórica. Mitigación: compatibilidad de lectura temporal y script de migración auditado.

Evidencia:
- `sql/service_providers_table.sql:122-125` y `sql/service_providers_table.sql:155-159` → ya existe patrón de migración/diagnóstico de servicios sin proveedor.
- `admin/js/medtravel_services.js:251-257` → fallback retrocompatible basado en datos legacy de proveedor.
- `inc/booking_form.php:198-227` → inyección de complementarios a texto en `book-message`.
- `booking/submit.php:38-52` → persistencia actual en texto de notas.

### Etapa 6: QA + despliegue + monitoreo
Objetivo:
- Liberar ownership/RBAC complementario con pruebas de seguridad y checklist de rollout.

Cambios técnicos (DB / backend / UI):
- QA: ejecutar casos manuales por rol (complementario/admin) y pruebas de bypass por request directo.
- Deploy: aplicar migraciones con backup previo y verificación post-deploy de integridad.
- Monitoreo: registrar intentos `forbidden`, errores de CRUD y fallos de migración/backfill.

Archivos a tocar:
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/MANUAL_PRUEBAS.md`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/NEXT_STEPS_SERVICES.md`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/service_providers.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/medtravel_services.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/ajax/booking_requests.php`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel/admin/logs/` (validación de logs runtime)

Criterios de aceptación (checklist):
- [ ] Usuario complementario: CRUD limitado a su empresa (list/get/create/update/delete).
- [ ] Admin: CRUD global funcional para soporte/auditoría.
- [ ] Booking: selección, persistencia relacional y edición visible en panel.
- [ ] Seguridad: requests directos fuera de scope devuelven `401/403`.
- [ ] Rollout: backup, migración, smoke tests y verificación de datos completados.

Riesgos y mitigación:
- Riesgo: regresiones silenciosas en producción. Mitigación: smoke tests obligatorios + observación de logs post-deploy.
- Riesgo: falta de trazabilidad de errores de permisos. Mitigación: logging estructurado de `forbidden` por endpoint y usuario.

Evidencia:
- `MANUAL_PRUEBAS.md:1-8` → ya existe marco de pruebas manuales, reutilizable para este rollout.
- `admin/ajax/provider_offers.php:14` y `admin/ajax/provider_offers.php:28-47` → patrón existente de control de sesión/scope y `forbidden`.
- `admin/ajax/provider_offers.php:9-12` y `admin/ajax/provider_offers.php:16-27` → patrón de logging técnico reutilizable.

### Definición de ‘Done’ (Definition of Done)
- Aislamiento multiempresa real en backend para complementarios (filtros server-side obligatorios por `service_provider_id`).
- CRUD completo de proveedores/servicios complementarios por empresa, sin intervención operativa de MedTravel.
- Booking guarda complementarios en modelo relacional consultable (con compatibilidad controlada de legado).
- Pruebas funcionales y de seguridad ejecutadas y aprobadas.
- Documentación operativa y de despliegue actualizada en repo.

---

## 2026-02 – Booking Multiproveedor (Provider-First Model)

### 1. Modelo operativo actualizado
- MedTravel NO controla disponibilidad.
- Cada item en `booking_request_items` inicia en: `item_status = pending_provider`.
- Proveedor responde con:
  - `provider_confirmed`
  - `provider_rejected`
  - `provider_proposed_change`
- Cliente verá estado tipo semáforo por servicio.
- Admin puede forzar cancelación.

### 2. Fuente de verdad
- Tabla: `booking_request_items`.
- Estados canónicos definidos.
- Flujo por item independiente.

### 3. Seguridad y privacidad
- Proveedor NO ve email, teléfono ni nombre completo del paciente.
- Proveedor solo ve destino, fechas, notas necesarias y servicio solicitado.

### 4. Email profesional unificado
- Nuevo helper: `renderMedTravelEmail()`.
- Template único reusable.
- Booking confirmation migrado a plantilla profesional.
- Sin contraseñas en texto plano.
- Acceso por token seguro de 24h.

### 5. Expiración y reenvío de acceso
- `set_password.php` gestiona token válido e inválido/expirado.
- Reenvío seguro con respuesta genérica para anti-enumeración.

### 6. Sesión 24 horas
- Cookie en 86400 segundos.
- Regeneración de sesión en login.
- Expiración server-side.

### 7. Estado actual
- Booking multiproveedor funcional.
- Provider-first aprobado.
- Email profesional implementado.
- Portal cliente seguro operativo.

### Negotiation Architecture (Canonical – 2026)

#### 1. Thread Types

##### CARE (MedTravel Coordination)
- Canal Cliente ↔ MedTravel.
- Siempre visible en cliente.
- El inbox funciona como canal libre de comunicación desde el inicio.
- CARE no aplica stage gate conversacional.
- Los cambios de estado no dependen del chat libre.

##### ITEM (Provider Negotiation)
- Canal Cliente ↔ Proveedor (médico o complementario).
- ITEM también queda libre como canal de comunicación desde el inicio.
- Fee gate, commission gate y scope siguen siendo los bloqueos reales.
- Las acciones estructuradas se mantienen como vía formal opcional para registrar decisiones o solicitudes con efecto operativo.

#### 2. Early Stage Rules (Canonical)

| Rol | CARE | ITEM |
|---|---|---|
| Cliente | Libre | Libre |
| Proveedor | N/A | Libre |
| Admin | Libre en CARE | Libre dentro de su scope |

#### 3. Structured Negotiation Actions

##### Provider → Client
- `REQUEST_ADDITIONAL_INFO`
- `PROPOSE_QUOTE_ADJUSTMENT`

##### Client → Provider
- `ACCEPT_PROPOSAL`
- `REQUEST_CHANGES`
- `REJECT_PROPOSAL`
- `DOCS_NOT_AVAILABLE`

##### Persistencia
- `inbox_messages` (sin tablas nuevas).
- Prefijos:
  - `[REQUEST_INFO]`
  - `[PROPOSE_QUOTE]`
  - `[PROPOSAL_RESPONSE]`
- Efecto de estado:
  - Structured actions de provider llevan ITEM a `awaiting_client`.
  - Respuestas cliente mueven a `client_accepted`, `provider_proposed_change` o `client_rejected`.
- Mensaje libre:
  - Sirve para comunicación y trazabilidad conversacional.
  - No cambia estados por sí solo.
- Ownership:
  - Validación por `thread_type=ITEM` + scope de proveedor/cliente por `item_id`.

#### 4. UI Canonical Behavior

##### Provider Inbox
- Chat libre desde el inicio dentro del scope permitido.
- Tarjetas estructuradas visuales para mensajes con prefijo.
- Panel de ayuda colapsable en español.

##### Client Inbox
- Chat libre desde el inicio.
- Fee gate y commission gate siguen bloqueando mensajería libre cuando apliquen.
- Encabezados humanizados (sin `Item #X - Request #Y`).

#### 5. Canonical UX Principle
- No exponer IDs técnicos en encabezado de conversación.
- Mostrar nombre de servicio + referencia comercial (`Solicitud #`).

#### 6. Flujo canónico end-to-end (booking → negociación)
1. Creación de booking por cliente.
2. Generación y visibilidad de hilos CARE e ITEM.
3. Coordinación inicial en CARE (cliente-medtravel).
4. Comunicación libre por ITEM más acciones formales opcionales.
5. Respuestas del cliente (acciones y documentos).
6. Evolución de estado del item a través de acciones estructuradas y formularios formales.

### Próximos pasos
- [ ] Semáforo visual en portal cliente por item.
- [ ] Notificación automática al cliente cuando proveedor responda.
- [ ] Panel cliente para ver estado por servicio.
- [ ] Historial de eventos por booking.
- [ ] Notificación admin cuando proveedor responda.

**Fecha de actualización de esta sección:** 18 de febrero de 2026.
