# Changelog Decisions

## 2026-03-21 — Formalizacion oficial del onboarding medico, ownership del provider e identidad administrativa

**Outcome**
- `providers.php` queda declarado oficialmente como flujo canónico para alta inicial del provider medico.
- `providers.php` queda declarado oficialmente como origen canónico de la cuenta owner/admin inicial del provider medico.
- `staff_medico.php` queda declarado oficialmente como flujo canónico para alta de staff medico y aprovisionamiento de acceso del staff cuando aplique.
- `crear_usuario.php` deja de ser flujo canónico para onboarding del dominio medico principal.
- `usuarios.id = 1` queda protegido oficialmente como superusuario global del sistema.

**Decision**
- El dominio medico principal ya no debe tener multiples puertas canónicas de onboarding para identidad administrativa.
- La relacion owner/admin del provider debe existir de forma explicita y consistente.
- El canon ya no admite ownership inferido por:
  - `LIMIT 1`
  - "primer usuario del provider"
  - coexistencia ambigua entre `usuarios.provider_id` y `provider_users`
- `crear_usuario.php` puede seguir existiendo solo como flujo restringido / adicional / legacy mientras se completa la transicion.
- El staff medico no debe volver a nacer desde `crear_usuario.php`; su alta canónica pertenece a `staff_medico.php`.
- El superusuario global debe permanecer aislado de cualquier logica de reciclaje o reutilizacion de usuarios del dominio provider / staff.

**Transition note**
- Esta decision fija el norte canónico, no declara aun que el runtime ya este completamente alineado.
- El estado actual sigue mezclando:
  - `providers.php` como alta de provider + cuenta inicial
  - `crear_usuario.php` como alta de usuarios scoped todavia utilizable en dominio medico
  - `staff_medico.php` como flujo ya orientado a provisión propia del staff
- La forma tecnica final de ownership explicito queda como deuda / siguiente transicion documentada en backlog.

**Operational effect**
- Las futuras implementaciones deben tratar `providers.php` como onboarding canónico del provider medico y su owner/admin inicial.
- Las futuras implementaciones deben tratar `staff_medico.php` como onboarding canónico del staff medico.
- Las futuras decisiones tecnicas no deben volver a reabrir `crear_usuario.php` como flujo principal del dominio medico.

---

## 2026-03-21 — Redefinicion oficial de Mis Servicios, Staff y Mis Ofertas

**Outcome**
- `service_catalog` queda declarado oficialmente como el diccionario maestro global de servicios medicos de MedTravel.
- `service_catalog` deja de interpretarse como `Mis Servicios` del provider.
- `provider_catalog_services` queda declarado oficialmente como la entidad canónica objetivo de `Mis Servicios`.
- `Mis Servicios` se redefine como la capacidad medica real habilitada del provider.
- `provider_service_offers` se mantiene oficialmente como la capa comercial / publicable.
- `Staff` queda formalmente ligado a `Mis Servicios` del provider y no a ofertas.

**Decision**
- La relacion canónica correcta del dominio medico queda definida como:
  - `service_catalog` -> diccionario maestro global
  - `provider_catalog_services` -> servicio habilitado del provider / capacidad medica real
  - `provider_service_offers` -> publicacion comercial derivada de esa capacidad
- La clasificacion operativa efectiva debe vivir en `Mis Servicios`, no en `Mis Ofertas`.
- Cada servicio habilitado del provider debe poder clasificarse, como minimo, por:
  - nivel de atencion
  - tipo de servicio asistencial

**Transition note**
- Se reconoce explicitamente que el runtime actual sigue siendo ambiguo en este frente.
- Hoy `provider_catalog_services` todavia opera en muchos puntos como tabla puente minima.
- Hoy ofertas y staff siguen apuntando tecnicamente al servicio global en varios componentes.
- Esa condicion queda registrada como deuda tecnica y no invalida la decision canónica ya tomada.

**Operational effect**
- La documentacion, el vocabulario de producto y las futuras decisiones de implementacion no deben volver a mezclar catalogo maestro global con capacidad especifica del provider.
- Las siguientes iteraciones tecnicas deben cerrar la brecha entre:
  - estado actual del runtime
  - y target canónico ya aprobado

---

## 2026-03-20 — Catalogos persistentes de roles y especialidades del staff por proveedor

**Commits**: `0e5a97f`, `183c84d`

**Outcome**
- Se introducen las tablas `provider_staff_roles` y `provider_staff_specialties` con migracion idempotente (`sql/2026_03_20_provider_staff_catalogs.sql`).
- `provider_id = NULL` = entrada de sistema disponible a todos los proveedores. `provider_id NOT NULL` = entrada personalizada del proveedor, gestionable desde su cuenta.
- CRUD admin en nueva pagina `staff_catalogs.php`, accesible desde el menu Mi Empresa (solo flujo medico).
- El AJAX `list_staff_catalogs` sirve desde BD con fallback a arrays hardcoded si las tablas no existen aun.
- Los campos `role_title` y `specialty` de `provider_medical_staff` se mantienen como VARCHAR por compatibilidad legacy. El valor guardado es el `.name` del catalogo, sin FK todavia.
- Las entradas de sistema no son editables ni eliminables por el proveedor desde la UI (proteccion a nivel AJAX: UPDATE/DELETE filtran por `provider_id = ?`).

**Validation**
- Pendiente smoke test funcional post-migracion: alta de entradas personalizadas, disponibilidad en modal, proteccion de entradas de sistema.

---

## 2026-03-20 — Navegacion del prestador reorganizada por dominios funcionales

**Commits**: `b96bb3e`, `ca4c634`, `9204a82`, `8321a96`

**Outcome**
- `staff_medico.php` queda como pagina independiente separada formalmente de `mi_empresa.php`.
- La navegacion del prestador medico se reorganiza en cuatro dominios: Operacion, Servicios, Presencia, Mi Empresa.
- Se instala una primera separacion semantica entre `Mis Servicios` y `Mis Ofertas` en UI y textos de ayuda.
- La definicion de detalle queda posteriormente cerrada de forma oficial por la decision del 2026-03-21.

---

## 2026-03-20 — Decisiones sobre acceso del staff al panel admin

**Rationale**
La existencia de `linked_user_id` en `provider_medical_staff` abre la puerta al acceso del staff al panel. Antes de implementarlo, se consolidan las restricciones de diseno.

**Decisiones**
- El staff medico NO debe autenticarse con `ROLE_PROVIDER` ni `ROLE_PROVIDER_ADMIN`. Debe tener su propio rol dedicado (`provider_staff`).
- La relacion de autenticacion es `usuarios.id -> provider_medical_staff.linked_user_id`.
- El acceso del staff al panel debe estar restringido por asignacion de items/casos (`booking_request_items.assigned_staff_id`), no solo por pertenecer al mismo `provider_id`.
- La landing para staff con acceso propio debe ser una vista de "Mis solicitudes asignadas", no el dashboard general del prestador.

**Estado**: decisiones tomadas, implementacion no iniciada.

---

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
