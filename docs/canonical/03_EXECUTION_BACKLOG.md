# Execution Backlog (alias)

Alias al backlog / pasos de ejecución canónico.

- Ver: [NEXT_STEPS_SERVICES.md](../../NEXT_STEPS_SERVICES.md)

## Frente prioritario — Canonizacion del nuevo modelo operativo MedTravel

### Paso 1 — Canon producto y operacion

- Consolidar en documentacion la separacion formal entre caso, item, cita y coordinacion / pago
- Reubicar la comision como capacidad comercial opcional por proveedor
- Alinear aliases canónicos sin borrar el contexto legacy
- DONE 2026-03-21: queda cerrado a nivel canónico el nuevo modelo de servicios medicos
  - `service_catalog` = catalogo maestro global
  - `provider_catalog_services` = entidad canónica objetivo de `Mis Servicios`
  - `provider_service_offers` = capa comercial / publicable
  - `Staff` = capacidad humana asociada a servicios reales del provider, no a ofertas

### Frente especifico — Servicios medicos, staff y ofertas

#### Canon cerrado

- DONE 2026-03-21: `Mis Servicios` se redefine oficialmente como capacidad medica real habilitada del provider
- DONE 2026-03-21: `service_catalog` se declara diccionario maestro global y deja de interpretarse como `Mis Servicios`
- DONE 2026-03-21: `provider_catalog_services` se declara entidad canónica objetivo para operacion futura
- DONE 2026-03-21: `provider_service_offers` se ratifica como capa comercial / publicable
- DONE 2026-03-21: `Staff` se ratifica como dependiente de `Mis Servicios` y no de ofertas

#### Lo que ya esta razonablemente alineado

- `Mis Ofertas` ya esta razonablemente alineado como capa comercial
- `Staff` ya esta semanticamente orientado a servicios y no a ofertas
- La separacion conceptual entre `Mis Servicios` y `Mis Ofertas` ya existe en copy y navegacion, aunque no este resuelta por completo en persistencia

#### Deuda de modelo

- [ ] Redefinir efectivamente `Mis Servicios` sobre `provider_catalog_services` como entidad operativa fuerte y no solo como tabla puente
- [ ] Ampliar atributos de `provider_catalog_services` para soportar capacidad medica real del provider
- [ ] Introducir clasificacion estructural minima en `provider_catalog_services`
  - nivel de atencion
  - tipo de servicio asistencial
- [ ] Resolver que la clasificacion operativa efectiva viva en el servicio habilitado del provider y no solo en el catalogo maestro global
- [ ] Formalizar la nocion de sede / branch a nivel de servicio habilitado cuando corresponda

#### Tareas de transicion

- [ ] Hacer que ofertas dependan de la entidad canónica del servicio habilitado del provider y no directamente de `service_catalog.id`
- [ ] Hacer que staff dependa tecnicamente de la entidad canónica provider-service y no del servicio global
- [ ] Unificar las rutas de habilitacion de servicios
  - eliminar la ambiguedad entre alta desde `providers`
  - y alta / edicion desde `Mis Servicios`
- [ ] Separar alta del diccionario maestro global vs habilitacion de capacidad real del provider
- [ ] Definir estrategia de compatibilidad transitoria para referencias legacy al servicio global

#### Tareas de integridad

- [ ] Endurecer integridad para impedir que una oferta se cree sobre un servicio no habilitado para ese provider
- [ ] Endurecer integridad para impedir asignaciones de staff fuera del universo real de servicios habilitados del provider
- [ ] Revisar ownership / trazabilidad de servicios globales hoy creados desde contexto provider
- [ ] Consolidar una sola fuente operativa para `Mis Servicios`

#### Tareas UI / copy

- [ ] Ajustar copy de `Mis Servicios` para dejar explicito que no es el catalogo maestro global
- [ ] Ajustar copy de `Mis Ofertas` para reforzar que una oferta no define capacidad medica
- [ ] Ajustar copy de `Staff` para reforzar que atiende servicios reales del provider
- [ ] Revisar labels, ayudas y mensajes de validacion en `providers`, `Mis Servicios`, `Mis Ofertas` y `Staff`
- [ ] Alinear textos de ayuda con la clasificacion futura por nivel de atencion y tipo asistencial

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
- DONE 2026-03-20 (commits `9204a82`, `8321a96`): navegacion del prestador reorganizada por dominios funcionales — Operacion, Servicios, Presencia, Mi Empresa
- SUPERSEDED 2026-03-21 por canon formal: `Mis Servicios` ya no debe describirse como simple catalogo habilitado del proveedor, sino como capacidad medica real habilitada con `provider_catalog_services` como entidad canónica objetivo
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

### Paso 7 — Servicios medicos del provider como entidad operativa fuerte (PENDIENTE)

- [ ] Declarar e implementar `provider_catalog_services` como entidad operativa fuerte de `Mis Servicios`
- [ ] Desacoplar progresivamente staff y ofertas de `service_catalog.id`
- [ ] Introducir clasificacion por nivel de atencion y tipo asistencial
- [ ] Resolver integridad y UX de la transicion sin romper runtime legacy

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
