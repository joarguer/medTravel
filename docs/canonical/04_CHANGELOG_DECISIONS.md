# Changelog Decisions

## 2026-03-20 — Se materializa provider_medical_staff como modelo MVP de staff interno por prestador

**Rationale**
- La separacion canónica entre prestador y medico / staff interno ya no podia seguir solo como definicion documental.
- Operacion necesitaba persistencia formal y gestion admin del staff sin hacks en `providers` ni texto libre.
- La evolucion debia mantener compatibilidad legacy y dejar lista la futura asignacion por item sin abrir agenda compleja.

**Outcome**
- Se adopta `provider_medical_staff` como tabla formal del staff medico interno del prestador.
- El admin del prestador incorpora CRUD para listar, crear, editar, activar / desactivar y ordenar staff.
- `admin/ajax/my_booking_requests.php` queda preparado para enriquecer items con staff asignado mediante helper reutilizable, sin cambiar el alcance funcional a agenda o calendar sync.
- `admin/include/roles.php` reconoce el contexto de staff medico vinculado con tolerancia a variantes legacy de estado (`is_active` / `active`).
- El endpoint de staff se endurece para esquemas reales donde `usuarios` no tiene columna `email`, evitando romper el runtime por asumir un schema mas amplio.

**Validation**
- La migracion `sql/2026_03_12_provider_medical_staff.sql` fue aplicada y validada sobre la BD real `bolsacar_medtravel`.
- Se verificaron en entorno real el CRUD minimo de staff por `provider_id`: crear, listar, editar, activar / desactivar y reordenar.
- En la BD validada no existe `booking_request_items`; por eso `admin/ajax/my_booking_requests.php` solo pudo comprobarse a nivel de compatibilidad aditiva y salida controlada `booking_request_items_not_available`, sin regresion de runtime observable en esa condicion.
- Esta decision cierra el Paso 3 del backlog a nivel MVP y deja fuera de alcance agenda, citas complejas y commission como eje del cambio.

## 2026-03-12 — MedTravel adopta arquitectura operativa de gestion de casos

**Rationale**
- El producto ya no puede describirse correctamente solo como solicitud + chat + commission unlock.
- La operacion real exige separar caso, item, cita y coordinacion / pago como dimensiones distintas.
- Tambien exige separar prestador de medico o staff interno del prestador.

**Outcome**
- Caso, cita y coordinacion / pago quedan formalmente definidos como dimensiones distintas del modelo.
- Prestador y medico / staff interno se separan a nivel canónico, aunque la persistencia tecnica siga evolucionando por fases.
- La UI operativa debe mostrar estados de negocio visibles y comprensibles para operacion.
- Las acciones oficiales del item quedan canonizadas como:
  - Aceptar caso
  - Rechazar caso
  - Solicitar informacion
  - Proponer cita
- El admin operativo para proveedores, medicos y servicios complementarios en Colombia se estandariza en espanol por defecto.
- El modelo previo de commission / unlock se mantiene como:
  - compatibilidad legacy
  - capacidad opcional por proveedor
  - configurable desde admin
  - subflujo comercial complementario
  - no eje principal del producto

**Impact**
- La documentacion canónica ya no debe presentar la comision como regla global obligatoria.
- Los componentes existentes de comision siguen vigentes, pero subordinados al modelo operativo principal.
- Las futuras decisiones tecnicas deben preservar compatibilidad con proveedores con comision habilitada y con proveedores sin comision.

## Decision: Introduce Stage 2 commission unlock system

**Rationale**
MedTravel must monetize provider-client matches while preserving free negotiation inside the platform.

**Outcome**
Stage 1 communication remains open, but sensitive provider contact details are gated behind commission payment.

**Notes**
Stripe integration scaffolding added for future payment processing.

## Decision: Introduce configurable commission per provider

**Rationale**
Different providers may have different commercial agreements with MedTravel.

**Outcome**
Commission parameters are stored per provider and enforced through the Stage 2 commission gate.

## Inbox UX Improvements

- Chat bubble layout
- Sender display names
- Realtime dedupe fix
- Improved scroll behavior
- UI modernization for messaging
- Typing indicator via realtime events
- Message status (sending/sent/failed)
- Consecutive message grouping (2-minute window)
- Admin header unread badge for inbox updates

## 2026-03 — Realtime Admin Header Notifications

Implementación de actualización realtime del badge de notificaciones en el header admin usando Socket.IO.

Cambios principales:
- evento `admin.unread_changed`
- room global `admins`
- listener en `admin/js/header_notifications.js`
- refresh mediante `adminReloadNotificationsDebounced()`
- fallback polling cada 60s

## 2026-03 — Admin commission payment management in booking_requests modal

- Gestión de pagos de comisión (Phase 2) centralizada en el modal de detalle de `admin/booking_requests.php`
- Estado del pago y acciones admin (crear, marcar pagado, eliminar)
- `admin/my_booking_requests.php` (prestador) no renderiza ni llama commission_payments
- Endpoint `admin/ajax/commission_payments.php` permanece admin-only
- realtime_admin_token admin-only con mensaje `forbidden_admin_only`

## 2026-03 — Commission requires persisted item price

- booking_request_items debe persistir `proposed_price`/`currency` al crear items desde ofertas
- ajustes de cotización del proveedor guardan `provider_proposed_price`/`provider_proposed_currency`
- comisión se calcula desde `proposed_price` (fallback `provider_proposed_price`)
- UI admin muestra advertencia cuando falta precio y bloquea crear/confirmar pagos

## 2026-03-11 — Cleanup booking reset must include commission payments

- Problema:
  - `admin/cleanup.php` ejecutaba reset operativo de bookings sin incluir `commission_payments`
  - el preview mostraba orden “safe” solo con las tablas seleccionadas y omitía hijos FK externos
- Causa raíz:
  - `commission_payments.request_id` referencia `booking_requests.id`
  - el planner de delete order solo evaluaba FKs dentro del subconjunto seleccionado
- Decisión:
  - incluir `commission_payments` en el grupo `bookings`
  - mantener delete order child -> parent dentro del subset
  - agregar warning en preview cuando existan child tables por FK fuera del set
  - no adoptar `SET FOREIGN_KEY_CHECKS=0` como estrategia de reset
- Impacto:
  - el reset operativo de bookings ahora contempla pagos de comisión del mismo flujo transaccional
  - para bookings, el orden esperado del planner queda `commission_payments` -> `booking_request_items` -> `booking_requests`
  - el preview deja explícito que la seguridad del orden depende del subconjunto seleccionado

## 2026-03 — Blog and Commercial Content Management Improvements

- Soporte de video en blog mediante `video_url` para YouTube/Vimeo y `video_file` para MP4 local
- Limpieza segura de media gestionada al eliminar entradas del blog
- Normalización del modelo editorial del blog:
  - MedTravel permanece como identidad principal
  - `author_name` se usa como byline editorial visible
  - `provider_id` se interpreta como contribuidor médico / afiliación secundaria
- Header compartido configurable para `blog.php` y `blog_post.php`
- Nuevos headers configurables para `booking.php` y `contact.php`
- Estandarización del patrón de headers públicos:
  - tabla dedicada
  - helper en `inc/`
  - editor admin
  - endpoint AJAX
  - render público con fallback
- Mejoras de UX admin:
  - menú y gating para blog de proveedores
  - feedback con Toastr en `admin/blog_edit.php`
  - formularios y uploads alineados con Metronic
- Ajustes menores de integración pública:
  - link `Blog` en navegación pública
  - testimoniales de `services.php` alineados con homepage
  - filtrado por `provider_id` en `offers.php`
- Mejoras de homepage:
  - hero configurable (carousel / video / disabled)
  - toggle global y por ítem para “Servicios Detallados”
