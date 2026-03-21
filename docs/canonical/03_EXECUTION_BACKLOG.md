# Execution Backlog (alias)

Alias al backlog / pasos de ejecución canónico.

- Ver: [NEXT_STEPS_SERVICES.md](../../NEXT_STEPS_SERVICES.md)

## Frente prioritario — Canonizacion del nuevo modelo operativo MedTravel

### Paso 1 — Canon producto y operacion

- Consolidar en documentacion la separacion formal entre caso, item, cita y coordinacion / pago
- Reubicar la comision como capacidad comercial opcional por proveedor
- Alinear aliases canónicos sin borrar el contexto legacy

### Paso 2 — Ajuste de vocabulario UI

- Estandarizar estados visibles de negocio para operacion
- Alinear acciones oficiales del item:
  - Aceptar caso
  - Rechazar caso
  - Solicitar informacion
  - Proponer cita
- Formalizar ingles para front publico / paciente y espanol por defecto para admin operativo en Colombia

### Paso 3 — Modelo operativo del staff medico

- DONE MVP 2026-03-20 (commits `3f4dd99`, `decc1c5`): se materializa la separacion formal entre prestador y medico o staff interno mediante `provider_medical_staff`
- DONE MVP 2026-03-20: existe persistencia formal, CRUD admin por prestador, activacion / desactivacion y `sort_order`
- DONE MVP 2026-03-20: queda preparado el helper y la forma de exposicion futura para mostrar por item prestador, doctor o staff, clinica / sede y estado de cita
- DONE 2026-03-20 (commit `b96bb3e`): `staff_medico.php` separado formalmente de `mi_empresa.php` como pagina independiente dentro del dominio Mi Empresa
- DONE 2026-03-20 (commit `ca4c634`): integracion visual y navegacion de staff pulidas
- DONE 2026-03-20 (commits `9204a82`, `8321a96`): navegacion del prestador reorganizada por dominios funcionales — Operacion, Servicios, Presencia, Mi Empresa. Semantica canonizada: Mis Servicios = catalogo habilitado del proveedor; Mis Ofertas = publicaciones comerciales visibles al paciente
- DONE 2026-03-20 (commit `0e5a97f`): catalogos de roles y especialidades del modal de staff alineados con servicios reales del proveedor y servidos por AJAX
- DONE 2026-03-20 (commit `183c84d`): persistencia de catalogos por proveedor mediante `provider_staff_roles` y `provider_staff_specialties`. Entradas de sistema (provider_id=NULL) y personalizadas por proveedor. CRUD admin en `staff_catalogs.php`. Fallback a arrays hardcoded si las tablas no existen
- DONE 2026-03-20: foto del profesional reemplaza campo texto por upload real de archivo (JPG/PNG/WebP ≤ 2 MB), validacion MIME via finfo, preview en modal y almacenamiento en `uploads/staff_photos/`
- Scope MVP cerrado:
  - sin agenda compleja
  - sin calendar sync
  - sin cambios al commission system fuera de compatibilidad legacy

### Paso 6 — Acceso del staff al panel admin (PENDIENTE — siguiente frente)

Alcance definido, implementacion no iniciada:

- [ ] Smoke test funcional del modulo de catalogos del staff post-`183c84d`:
  - alta de rol personalizado y disponibilidad inmediata en modal de staff
  - alta de especialidad personalizada y disponibilidad inmediata en modal de staff
  - proteccion de entradas de sistema (no editables / no eliminables desde UI de proveedor)
  - compatibilidad con valores legacy ya guardados en `role_title` / `specialty`
- [ ] Nuevo rol `provider_staff` en `roles.php` — no debe reutilizar ROLE_PROVIDER ni ROLE_PROVIDER_ADMIN
- [ ] Landing MVP para staff con acceso propio: "Mis solicitudes asignadas"
- [ ] Scope duro de acceso por `booking_request_items.assigned_staff_id`
- [ ] Relacion de autenticacion: `usuarios.id -> provider_medical_staff.linked_user_id` (campo ya existe en tabla)

### Paso 4 — Trazabilidad estructurada

- Formalizar event log por caso e item
- Asegurar timeline con eventos de negocio y no solo de persistencia tecnica
- Vincular propuesta de cita, confirmacion, reprogramacion, cancelacion y solicitud de informacion

### Paso 5 — Hardening legacy

- Mantener compatibilidad con estados y flujos legacy
- Revisar contradicciones entre gate de comision y modelo operativo principal
- Asegurar que proveedores sin comision sigan operando sin ramas especiales fragiles

## Backlog subordinado — Comision comercial por proveedor

- DONE: Commission payments managed in booking_requests admin modal (admin-only)
- DONE: Persist item price/currency in booking_request_items for commission calculations (no manual SQL)
- Admin UI for provider commission configuration
- Stripe checkout integration for commission payments
- Stripe webhook handling for payment confirmation
- Commission reporting dashboard
- Provider payout reconciliation

Este backlog se mantiene como frente comercial / tecnico complementario. Ya no debe interpretarse como la prioridad arquitectonica principal del producto.
