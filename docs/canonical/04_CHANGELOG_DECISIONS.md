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
