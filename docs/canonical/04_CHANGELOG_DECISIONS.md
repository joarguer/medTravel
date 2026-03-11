# Changelog Decisions

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
