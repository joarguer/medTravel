# Technical Architecture & Runtime (alias)

Alias al documento canónico que describe contexto técnico y runtime.

- Ver: [DEV_CONTEXT.md](../../DEV_CONTEXT.md)

## Arquitectura operativa complementaria: caso, cita y staff medico

Esta capa complementa el runtime existente y redefine el marco tecnico esperado para MedTravel sin invalidar componentes legacy.

### Separacion formal de dominios

- Caso:
  - dimension operativa principal del paciente
  - agrupa contexto general, items, conversacion transversal y coordinacion
- Cita:
  - evento operativo separado
  - puede ser propuesta, confirmada, reprogramada o cancelada sin redefinir por completo el caso
- Coordinacion / pago:
  - capa operativa o comercial posterior a ciertos hitos del item
  - puede incluir desbloqueos, validaciones, pagos o tareas administrativas

### Separacion entre prestador y medico

- `providers` representa al prestador responsable del item.
- El sistema separa prestador y medico / staff interno mediante la tabla `provider_medical_staff` (operativa desde 2026-03-20).
- La relacion de acceso es `provider_medical_staff.linked_user_id → usuarios.id`.
- La documentacion tecnica no debe asumir que prestador = medico.

## Proyecto satelite de generacion documental: `generadorDocumentos`

### Estado actual

- Existe un proyecto auxiliar separado para generacion de documentos HTML formateados (guias, cuentas de cobro, propuestas y piezas editoriales/operativas).
- Su arquitectura esta desacoplada para reutilizacion (`templates`, `documents`, `shared`, `assets`, `launcher/index`, JSON por documento).
- El proyecto esta preparado para evolucionar hacia alimentacion por datos dinamicos.

### Frontera de runtime

- `generadorDocumentos` NO esta integrado hoy al runtime productivo principal de MedTravel.
- No se declara como modulo core del producto ni como dependencia obligatoria de los flujos actuales de paciente/provider/staff.
- Cualquier integracion futura debe evaluarse solo si la operacion real de MedTravel exige generacion documental online desde datos vivos del sistema.

## Arquitectura de identidad y onboarding administrativo del dominio medico

Este frente queda documentado distinguiendo explicitamente estado actual real, decision canónica, deuda tecnica y transicion esperada.

### Estado actual real del runtime

- `admin/providers.php` y `admin/ajax/providers.php` ya funcionan de facto como alta inicial del provider medico y de una cuenta administrativa inicial asociada.
- Ese flujo hoy crea:
  - `providers`
  - `provider_verification`
  - una cuenta en `usuarios` scoped por `provider_id`
- `admin/crear_usuario.php` y `admin/ajax/crear_usuario.php` crean usuarios scoped del sistema, incluyendo usuarios medicos asociados a un `provider_id` existente.
- `admin/staff_medico.php` y su stack asociado ya asumen que el staff medico puede aprovisionar su propio acceso mediante `provider_medical_staff.linked_user_id`.
- El runtime actual mezcla tres mecanismos para identidad / ownership medico:
  - `usuarios.provider_id`
  - `provider_users`
  - inferencias legacy sobre "primer usuario del provider"
- La sesion y varios componentes operativos hoy leen primero `usuarios.provider_id` y dejan `provider_users` como fallback legacy.

### Incoherencias actuales reconocidas

- Existen multiples puertas de entrada para identidad medica administrativa.
- `providers.php` y `crear_usuario.php` no responden a una sola fuente de verdad para el owner/admin del provider medico.
- `providers.php` crea provider + cuenta inicial, pero no formaliza todavia una relacion canónica explicita de ownership.
- `crear_usuario.php` puede seguir creando usuarios medicos scoped y por tanto competir semanticamente con el onboarding canónico del owner/admin del provider.
- `staff_medico.php` ya inicio un camino canónico distinto para el staff, lo cual hace todavia mas evidente que `crear_usuario.php` no debe seguir siendo puerta general del dominio medico principal.
- `provider_users` sigue existiendo, pero hoy no es fuente de verdad consistente para ownership medico.

### Decision canónica / target tecnico

- `providers.php` queda declarado como origen canónico del onboarding del provider medico y de su owner/admin inicial.
- `staff_medico.php` queda declarado como origen canónico del onboarding del staff medico y de su acceso al panel cuando aplique.
- `crear_usuario.php` deja de ser flujo canónico para onboarding medico principal.
- La relacion owner/admin del provider medico debe ser explicita y consistente.
- El superusuario global `usuarios.id = 1` queda fuera de cualquier logica de reciclaje, reutilizacion o scope medico.
- El sistema no debe seguir inferiendo ownership por:
  - `LIMIT 1`
  - "primer usuario encontrado"
  - heuristicas ambiguas entre tablas legacy y nuevas

### Fuente de verdad objetivo

- `providers` = entidad canónica del prestador medico
- mecanismo explicito de ownership = relacion canónica entre provider y owner/admin inicial
- `usuarios` = identidad/autenticacion del usuario
- `provider_medical_staff` = entidad canónica del staff
- `provider_medical_staff.linked_user_id` = vinculacion canónica entre staff y usuario con acceso

Nota de gobernanza:
- esta decision no declara aun la forma final de persistencia del ownership explicito
- solo fija que el canon ya no admite ownership inferido o ambiguo

### Transicion esperada

- Reducir `crear_usuario.php` a flujo restringido de usuarios adicionales / auxiliares o mantenerlo como legacy controlado.
- Alinear `providers.php` con un modelo explicito de owner/admin inicial del provider.
- Revisar y normalizar la convivencia entre `usuarios.provider_id` y `provider_users`.
- Mantener compatibilidad transitoria mientras el runtime deja de depender de inferencias legacy.

### Politica de lookup de identidad en autenticacion (desde 2026-04-10, commit `22a5230`)

- `login.php` resuelve identidad por ranking cuando un email tiene multiples candidatos en `usuarios`.
- Orden de prioridad: `username` exacto > `usrlogin` exacto > `email` exacto.
- `LIMIT 1` sin orden explicito esta prohibido en el punto de autenticacion.
- Esta politica extiende la regla de ownership no-inferido al flujo de login.
- Mientras convivan tablas legacy y nuevas, el candidato seleccionado debe ser determinista y del mayor rol relevante, no el primero por insercion.

### Resultado operativo esperado por item medico

Cada item medico debe poder exponer, por columna nativa o por derivacion segura:

- prestador asignado
- medico o staff asignado
- sede o clinic
- fecha propuesta
- fecha confirmada
- estado de cita
- event log / timeline

### Event log y trazabilidad

- La trazabilidad debe poder diferenciar eventos a nivel caso y a nivel item.
- La UI operativa debe mostrar eventos de negocio, no solo mutaciones tecnicas.
- Appointment proposal, confirmation, reschedule, cancelacion, solicitud de informacion y desbloqueos comerciales deben poder representarse en timeline.

## Arquitectura inicial canónica para Google Calendar / Google Meet

### Fase 1 — Integración base desde admin MedTravel

- La integracion base se resuelve con Google Calendar API.
- La autenticacion se modela con OAuth 2.0 Web Server Flow.
- La conexion Google se establece por admin autenticado en MedTravel.
- Los tokens deben separarse por admin y no mezclarse entre sesiones o cuentas administrativas.
- El organizer inicial del evento es la cuenta Google conectada por ese admin MedTravel.
- Paciente y provider / medico / staff se agregan como invitados del evento.
- Paciente y provider / medico / staff no conectan sus cuentas Google en este flujo base; la autorizacion OAuth pertenece solo al admin organizer autenticado.
- MedTravel persiste internamente la trazabilidad de la cita y sus referencias externas.

### Runtime validado de OAuth Google en Fase 1

- El runtime exige como scope operativo real de Calendar: `https://www.googleapis.com/auth/calendar`.
- La solicitud OAuth se emite con `include_granted_scopes=false` para evitar reutilizar una concesion previa incompleta.
- Si la conexion devuelve `invalid_grant` o errores de permisos insuficientes para crear eventos, el criterio operativo correcto es desconectar y reconectar limpiamente la cuenta Google del admin.
- Este criterio de reconexion aplica al organizer admin de Fase 1; no implica pedir OAuth a paciente ni a provider / staff para que la reunion exista.

### Seguridad mínima obligatoria

- `state` validado en OAuth.
- refresh token protegido y cifrado en backend.
- scopes minimos para la fase inicial.
- secretos y tokens fuera de frontend.
- aislamiento estricto entre conexiones Google de distintos admins.

### Fase 2 — Capacidades avanzadas posteriores

- Google Meet API avanzada para participantes, duracion, conference records y artefactos cuando aplique.
- Esta fase amplía metadatos y trazabilidad; no cambia la frontera de producto ni el modelo de cita.

### Compatibilidad legacy

- El runtime actual puede seguir apoyandose en `booking_requests`, `booking_request_items`, inbox, calendar y componentes auxiliares mientras el modelo evoluciona.
- Cuando falten campos dedicados, el sistema debe soportar derivacion segura o alias logicos sin inventar una tabla definitiva que aun no exista.
- La separacion operativa canonica ya es obligatoria a nivel de documentacion, aunque la persistencia siga madurando por fases.

### Publicación técnica SEO del runtime web público

- Para robustez operativa de publicación en entorno real:
  - `/robots.txt` se publica por rewrite hacia endpoint PHP (`robots.php`).
  - `/sitemap.xml` se publica por rewrite hacia endpoint PHP (`sitemap.php`).
- Esta decisión es de publicación técnica (runtime web), no de modelo de negocio ni de esquema SQL.

### Capacidad global de concurrencia en agenda (`calendar_capacity`)

- El runtime actual expone `calendar_capacity` como un atributo configurable del provider en `Mi Empresa`.
- Hoy su semantica real es estrictamente tecnica y operativa: limite global de eventos de agenda tipo `ITEM` que pueden solaparse al mismo tiempo para el provider.
- El valor se persiste en:
  - `providers.calendar_capacity`
  - `service_providers.calendar_capacity`
- Hoy se usa en `admin/ajax/calendar.php` para validar conflictos de concurrencia al crear o editar eventos `ITEM`.
- Si el numero de eventos solapados es mayor o igual a `calendar_capacity`, el runtime devuelve conflicto `409`.

#### Lo que SI significa hoy

- control grueso de concurrencia del prestador en agenda
- limite global por provider para solapamiento de eventos `ITEM`
- guardrail operativo minimo mientras no existe agenda fina por staff / servicio

#### Lo que NO significa todavia

- no equivale a disponibilidad por medico individual
- no equivale a capacidad por staff medico
- no equivale a capacidad por sede o branch formal
- no equivale a capacidad por servicio habilitado
- no redefine por si sola la logica de booking ni la asignacion de staff

#### Tension de arquitectura reconocida

- Esta capacidad global convive hoy con un canon que empuja hacia un modelo mas fino por staff, servicios habilitados y asignacion por item.
- Por tanto, `calendar_capacity` debe tratarse como compatibilidad operativa actual y no como modelo final de capacidad medica del provider.

## Servicios medicos, servicios habilitados del provider y ofertas

Este frente queda documentado en cuatro capas: estado actual real, decision canónica, deuda tecnica y transicion esperada.

### Estado actual real del runtime

- `service_catalog` funciona hoy como maestro global de servicios medicos con taxonomia base por `service_categories`.
- `provider_catalog_services` existe hoy como vinculo minimo entre provider y servicio global.
- `provider_service_offers` funciona hoy como capa comercial / publicable.
- `provider_medical_staff_services` ya orienta semanticamente el staff hacia servicios y no hacia ofertas.

Relacion actual observable:
- `service_catalog` = servicio maestro global
- `provider_catalog_services` = habilitacion minima provider -> servicio global
- `provider_service_offers` = oferta comercial que hoy depende tecnicamente de `service_catalog.id`
- `provider_medical_staff_services` = relacion staff -> servicio global, usada semanticamente como capacidad del provider

### Problema actual

- La UI y el lenguaje de `Mis Servicios` lo presentan como capacidad medica real del provider.
- El flujo actual de `Mis Servicios` crea / edita registros en `service_catalog` y luego los vincula a `provider_catalog_services`.
- Esto mezcla en una misma operacion dos cosas distintas:
  - alta o edicion del diccionario maestro global
  - habilitacion operativa de servicios para un provider
- Ademas, hoy existen dos rutas de habilitacion de servicios:
  - desde `providers`
  - desde `Mis Servicios`
- Esa duplicidad rompe la idea de una sola fuente operativa.

### Decision canónica / target de arquitectura

- `service_catalog` queda declarado como diccionario maestro global.
- `provider_catalog_services` queda declarado como la entidad canónica objetivo de `Mis Servicios`.
- `provider_catalog_services` debe evolucionar desde tabla puente minima a entidad operativa ampliada del servicio habilitado del provider.
- `provider_service_offers` permanece como capa comercial / publicable, subordinada a la existencia previa de un servicio habilitado del provider.
- `Staff` debe depender del servicio habilitado del provider, no de la oferta.

Relacion objetivo:
- `service_catalog` normaliza nombres y taxonomia base
- `provider_catalog_services` expresa capacidad medica real habilitada
- `provider_service_offers` comercializa / publica esa capacidad

### Clasificacion estructural objetivo

La clasificacion operativa efectiva debe vivir en `provider_catalog_services`, no en `provider_service_offers`.

Minimos exigidos por canon:
- nivel de atencion:
  - primer nivel
  - segundo nivel
  - tercer nivel
  - cuarto nivel
- tipo de servicio asistencial:
  - consulta externa
  - hospitalizacion y cirugias
  - apoyo diagnostico / terapeutico

La categoria clinica global actual puede seguir residiendo en `service_catalog`, pero no debe seguir siendo la unica clasificacion disponible para la operacion real del provider.

### Deuda tecnica explicitada

- Hoy `provider_catalog_services` todavia no es una entidad fuerte de negocio; sigue siendo un vinculo minimo.
- Hoy `provider_service_offers` depende tecnicamente de `service_catalog.id` en varios puntos del runtime.
- Hoy `provider_medical_staff_services` tambien depende tecnicamente de `service_catalog.id`, aunque semanticamente ya opera como capacidad del provider.
- El sistema actual todavia no modela `nivel de atencion` ni `tipo de servicio asistencial`.
- La clasificacion por sede tampoco existe todavia como entidad formal a nivel de servicio habilitado; solo hay aproximaciones parciales en provider o staff.

### Transicion esperada

- Desacoplar progresivamente la operacion del provider respecto del catalogo maestro global.
- Evitar que `Mis Servicios` siga creando o editando el diccionario maestro global desde contexto provider.
- Unificar la habilitacion de servicios en una sola fuente operativa.
- Reapuntar progresivamente staff y ofertas a la entidad canónica del servicio habilitado del provider.
- Mantener compatibilidad transitoria mientras existan referencias tecnicas legacy al servicio global.

## Staff medico — tablas y componentes operativos (desde 2026-03-20)

**Tablas**
- `provider_medical_staff` — entidad principal del staff interno del prestador
- `provider_medical_staff_services` — relacion staff ↔ servicios del proveedor; semanticamente ya responde al canon de capacidad real, aunque tecnicamente aun apunte al servicio global en varios puntos del runtime
- `provider_staff_roles` — catalogo de roles/cargos por proveedor (provider_id=NULL = sistema; NOT NULL = personalizado)
- `provider_staff_specialties` — catalogo de especialidades con la misma estructura

**Paginas admin**
- `admin/staff_medico.php` — CRUD operativo del staff por prestador
- `admin/staff_catalogs.php` — gestion de catalogos de roles y especialidades

**Helpers y endpoints**
- `admin/include/provider_medical_staff_helpers.php`
- `admin/ajax/provider_medical_staff.php`
- `admin/js/provider_medical_staff.js`

**Almacenamiento**
- `uploads/staff_photos/` — fotos de profesionales subidas desde el modal de staff (JPG/PNG/WebP, validacion de MIME real via finfo)

**Migraciones**
- `sql/2026_03_12_provider_medical_staff.sql`
- `sql/2026_03_20_provider_staff_catalogs.sql`

### Idioma del admin por rol / contexto

- Front publico internacional y paciente: ingles.
- Admin operativo para proveedores, medicos y servicios complementarios en Colombia: espanol por defecto.
- Los nombres tecnicos del runtime pueden mantenerse en ingles cuando ya existan en codigo o integraciones.

## Commission System

**Tables**
- `provider_commission_settings`
- `commission_payments`

**Key relations**
- `provider_commission_settings.provider_id` → `providers.id`
- `commission_payments.provider_id` → `providers.id`
- `commission_payments.request_id` → `booking_requests.id`
- `commission_payments.item_id` → `booking_request_items.id`

**Runtime components**
- `inc/commission_gate.php`
- `client/ajax/inbox.php`
- `admin/ajax/inbox.php`

**Purpose**
Soportar una capa comercial opcional por proveedor para controlar ciertos desbloqueos o etapas operativas cuando el acuerdo comercial lo requiera.

**Operational framing**
- La configuracion de comision es por proveedor.
- La comision es opcional y administrada desde admin.
- El sistema debe soportar proveedores con comision habilitada y proveedores sin comision.
- El flujo principal del caso, la cita y la atencion no depende de que exista comision.

## Dev Cleanup Reset

**Admin interface location**
- `admin/cleanup.php`

**Operational reset groups**
- `bookings`
- `inbox`
- `calendar`
- optional `full_catalog`

**Booking reset scope**
- `commission_payments`
- `booking_request_items`
- `booking_requests`

**Delete-order guarantee**
- Preview / execute delete order is reliable only inside the selected table subset.
- For `bookings`, the expected child-to-parent order is:
  - `commission_payments`
  - `booking_request_items`
  - `booking_requests`

**Runtime guard**
- Preview now warns when selected parent tables have FK child tables outside the selected reset set.
- The reset flow does not use `SET FOREIGN_KEY_CHECKS=0`.

**Rationale**
- `commission_payments.request_id` is a transactional child of `booking_requests`.
- A cleanup planner that ignores child tables outside the selected subset can produce a misleading “safe” delete order and fail at execution time.

## Provider Commission Administration

**Admin interface location**
- `admin/providers_edit.php`

**Configuration storage**
- `provider_commission_settings`

**Relation**
- `provider_commission_settings.provider_id` → `providers.id`

**Runtime components**
- `inc/commission_gate.php`
- `client/ajax/inbox.php`
- `client/ajax/get_request_detail.php`
- `admin/ajax/inbox.php`

**Purpose**
Allow MedTravel to control monetization terms per provider while preserving the main case workflow independently from commission.

**Policy**
- Admin can enable or disable commission per provider.
- Providers without commission configuration must continue through the normal operational case flow.

### Inbox UI Architecture – Chat Bubble System

**Chat layout standard**
- Own messages aligned RIGHT.
- Received messages aligned LEFT.
- Maximum bubble width ~70%.
- Auto-wrap long content.
- Header inside bubble: DisplayName + Timestamp.

**Display name logic**
- Own message → "Me".
- Client portal other side: admin/patientcare → "Support"; provider → "Provider".
- Admin portal other side: client → "Patient" or "Client".
- If sender name exists in message payload → use it.

**Message classification**
- sender_role normalization: client, admin, provider, patientcare, system.

**DOM structure**
```html
<div class="mt-msg-row mt-msg-row--own|other">
  <div class="mt-msg-bubble">
      <div class="mt-bubble-head">
          <span class="mt-bubble-name">Me</span>
          <span class="mt-bubble-time">timestamp</span>
      </div>
      <div class="mt-bubble-body">
          message content
      </div>
  </div>
</div>
```

**Scroll behavior**
- Auto-scroll only when user is near bottom.
- Do not jump scroll when user is reading history.

**Realtime deduplication**
- Prevent duplicate rendering when sender receives their own socket broadcast.
- Track recent message_id values.
- Ignore socket message.created events if already rendered.

**Realtime UX events**
- `message.created` → triggers incremental fetch (since_id) for active thread.
- `typing` → payload `{ thread_id, role, user_id, state: 'start'|'stop', ts }`.
- Client emits `client_message_committed` after successful AJAX insert (best-effort).

**Typing indicator**
- Emit `typing:start` at most once per 2s while user types.
- Emit `typing:stop` after 1.5s idle or on send.
- Show “X is typing…” on the opposite side only; auto-hide after ~2s.

**Message status (own messages)**
- “Sending…” on optimistic append.
- “Sent” after AJAX returns `message_id`.
- “Failed” on AJAX error (local only).

**Message grouping**
- Group consecutive messages from the same sender within 2 minutes.
- Show sender label once per group.
- System messages remain full-width and ungrouped.

**Admin unread badge**
- Header notification badge reflects unread inbox count when > 0.
- Updated on initial load and on realtime/new message flows.

**Files responsible for UI rendering**
- Admin portal: `admin/js/app_inbox.js`, `admin/app_inbox.php`
- Client portal: `client/js/app_inbox.js`, `client/app_inbox.php`

**Admin quick-reply: preview modal antes de envio (desde 2026-04-10, commit `4c9a142`)**
- El click en `.admin-quick-reply` abre `#adminQuickReplyPreviewModal` para revision antes de enviar.
- El envio directo sin preview ya no es la operacion por defecto del admin.
- `parseReplyTokenAndNote()` extrae token y nota del payload de la respuesta para poblar el modal.

**Confirmacion de reunion desde client inbox (desde 2026-04-10, commit `4c9a142`)**
- El cliente puede confirmar una reunion propuesta directamente desde `client/app_inbox.php`.
- Si el evento tiene modo Google, se invoca `google_calendar_create_event()` y `calendar_events` se actualiza con referencias externas (`google_event_id`, `google_meet_url`, `organizer_email`).
- Si el evento es interno, se confirma sin referencias de Google Calendar.
- Inbox sigue siendo el punto de entrada; Calendar sigue siendo la persistencia de la cita. Esta confirmacion es coordinacion operativa, no mezcla de dominios.

### Realtime Notifications Architecture

**Rooms**
- Thread rooms: `thread_<thread_id>`
- Admin global room: `admins`

**Socket events**

Client → Server
- `join_room`
- `join_admin`
- `client_message_committed`

Server → Client
- `message.created`
- `admin.unread_changed`
- `auth_error`

**Realtime header flow**
1. Patient sends message.
2. Server rebroadcasts `message.created`.
3. Server emits `admin.unread_changed` to room `admins`.
4. `admin/js/header_notifications.js` receives event.
5. It calls:
   - `adminReloadNotificationsDebounced()`
   - fallback `adminReloadNotificationsNow()`
   - fallback `adminReloadNotifications()`
6. Endpoint `/admin/ajax/get_notifications.php` returns `unread_count`.
7. `.admin-notif-badge` is updated.

**Resilience**
- Polling fallback every 60s.
- Manual refresh available.
- Optional debug: `window.MT_DEBUG_NOTIF === true`.

## Blog System

**Primary table**
- `blog_posts`

**Relevant fields**
- `provider_id`: medical contributor / affiliated specialist context
- `author_user_id`: optional platform user that authored the post
- `author_name`: public-facing editorial byline
- `title`, `slug`, `excerpt`, `body`, `cover_image`
- `video_url`: normalized external video URL (`YouTube` / `Vimeo`)
- `video_file`: uploaded local MP4 path
- `status`, `created_at`, `updated_at`, `published_at`

**Indexes**
- `idx_blog_provider`
- `idx_blog_author_user`
- `idx_blog_status_published_at`

**Editorial model**
- The public blog remains a MedTravel editorial property.
- `author_name` is the visible byline shown to end users.
- `provider_id` is not the primary site identity; it represents a medical contributor / affiliated specialist when present.
- Admin users can publish:
  - pure MedTravel editorial posts
  - MedTravel editorial posts with a medical contributor
- Provider users can only create and manage posts within their own `provider_id` scope.
- Provider-side bylines are normalized server-side to avoid arbitrary public author labels.

**Admin runtime**
- `admin/blog_edit.php`
- `admin/ajax/blog_posts.php`
- `admin/include/include.php`
- `admin/include/valida_session.php`

**Public runtime**
- `blog.php`
- `blog_post.php`

**Media behavior**
- `cover_image` supports local uploaded images managed by the blog module.
- `video_url` stores only validated external URLs for `YouTube` / `Vimeo`; no iframe HTML is stored.
- `video_file` stores a managed local MP4 path.
- Public rendering priority in `blog_post.php`:
  1. `video_file`
  2. `video_url`
  3. no video block

**Cleanup behavior**
- Blog post deletion removes the database row first.
- Managed local assets are then deleted on a best-effort basis:
  - `cover_image` under `img/blog/`
  - `video_file` under `img/blog/videos/`
- External video resources are never deleted.

## Commercial Page Header System

**Pattern**
- The project currently uses dedicated header tables per public page rather than one generic global page-header table.
- Each managed public header follows the same runtime pattern:
  - configuration table
  - public helper under `inc/`
  - admin editor page
  - admin AJAX endpoint
  - public template render with fallback values

**Managed header tables**
- `about_header`
- `services_page_header`
- `services_header`
- `offer_detail_header`
- `blog_header`
- `booking_page_header`
- `contact_header`

**Runtime examples**
- `inc/blog_header.php` + `admin/blog_edit.php` + `admin/ajax/blog_header.php`
- `inc/booking_page_header.php` + `admin/booking_header_edit.php` + `admin/ajax/booking_header_edit.php`
- `inc/contact_header.php` + `admin/contact_header_edit.php` + `admin/ajax/contact_header_edit.php`

**Page coverage**
- `about.php` reads `about_header`
- `services.php` reads `services_page_header`
- `offers.php` reads `services_header`
- `offer_detail.php` reads `offer_detail_header`
- `blog.php` and `blog_post.php` share `blog_header`
- `booking.php` reads `booking_page_header`
- `contact.php` reads `contact_header`

**Header fields**
- title
- subtitle / descriptive text
- background image
- `activo` flag where used by the existing page module pattern

## Media Storage

**Managed directories**
- `img/blog/`
- `img/blog/videos/`
- `img/site/blog/`
- `img/site/booking/`
- `img/site/contact/`

**Responsibilities**
- `img/blog/`: uploaded blog cover images
- `img/blog/videos/`: uploaded local MP4 files for blog posts
- `img/site/blog/`: shared public hero/header image for blog listing and blog detail
- `img/site/booking/`: booking page header image
- `img/site/contact/`: contact page header image

**Cleanup rules**
- File deletion is restricted to managed paths owned by the corresponding module.
- Replacement uploads remove the previous managed file only when the old path is within the expected module directory.
- Missing files on disk do not block content updates or deletes.

## Flujo canónico de seleccion de oferta: categoria → servicio → oferta (desde 2026-04-02)

Este flujo es el patron canónico para toda seleccion de oferta en MedTravel, tanto en el wizard publico como en el booking asistido por admin.

### Flujo en dos pasos

**Paso 1 — Selector de servicio (carga inicial)**
- Se consulta `service_catalog` con INNER JOIN `service_categories`.
- Solo se muestran servicios que tengan al menos una oferta activa de un provider activo y no eliminado.
- Orden: `service_categories.sort_order` + `service_catalog.sort_order`.
- Las ofertas huerfanas (sin vinculo a un servicio valido) no aparecen nunca.

**Paso 2 — AJAX get_offers (por servicio seleccionado)**
- Se retornan las ofertas del `service_catalog.id` seleccionado.
- Filtros obligatorios: `provider_service_offers.is_active = 1`, `providers.is_active = 1`.
- Si existen columnas `is_deleted` (columna opcional en algunos environments), se incluye `is_deleted = 0`.
- Ver punto "Patron de compatibilidad para columnas opcionales" sobre como se detecta la existencia de `is_deleted`.

**Validacion en backend (submit)**
- `service_id` es obligatorio y debe existir en `service_catalog` como servicio activo.
- La categoria se deriva del `service_catalog`, nunca del POST directo.
- Cada oferta seleccionada se verifica contra `service_id`: debe ser activa, no eliminada y pertenecer al servicio.
- Si alguna oferta no cumple, el request es rechazado.

### Implementaciones donde aplica este patron

- `booking/wizard.php` + `booking/submit.php` — flujo publico
- `admin/booking_asistido.php` + `admin/ajax/booking_asistido.php` — booking asistido por agente (implementado 2026-04-02)

### Regla canónica

- Este flujo no debe simplificarse a un listado plano de todas las ofertas sin filtro de servicio.
- La estructura categoria → servicio → oferta refleja el modelo de negocio real y debe preservarse en todo punto de entrada.

## Patron de compatibilidad para columnas opcionales — has_column guard (desde 2026-04-02)

Algunas columnas como `is_deleted` en `provider_service_offers` y `providers` pueden no existir en environments que no aplicaron la migracion de soft delete.

### Patron implementado

- Antes de incluir condiciones `AND tabla.is_deleted = 0` en las queries, el runtime ejecuta `SHOW COLUMNS FROM tabla LIKE 'is_deleted'`.
- Si la columna no existe, la condicion se omite sin fallar.
- Este patron previene errores `Unknown column` en environments parciales sin relajar reglas de negocio.

### Helper asociado

- `ab_has_column($conexion, $tabla, $columna)` en `admin/ajax/booking_asistido.php` (local al modulo).
- `google_calendar_table_has_column($conexion, $tabla, $columna)` en `inc/google_calendar.php`.
- `booking/submit.php` implementa el mismo patron como referencia original del proyecto.

### Regla de uso

- Toda nueva query que dependa de columnas introducidas por migraciones opcionales debe aplicar este patron.
- No debe hardcodearse `AND is_deleted = 0` sin verificar existencia si la columna puede no estar presente.

## Sincronizacion minima item ↔ cita (desde 2026-04-02)

### Funcion y alcance

Se implemento un mecanismo de sincronizacion minima en `inc/google_calendar.php` para mantener el `item_status` de los items alineado con las transiciones de estado del evento de calendario asociado.

### Mapeo canónico evento → estado del item

| Estado del evento de calendar  | Estado resultante en item     |
|--------------------------------|-------------------------------|
| `proposed` / `scheduled`       | `appointment_proposed`        |
| `confirmed`                    | `appointment_confirmed`       |
| `cancelled`                    | `appointment_cancelled`       |
| reschedule (proposed/scheduled) | `appointment_requested_change` |

### Funciones implementadas (en `inc/google_calendar.php`)

- `google_calendar_sync_item_status_for_transition($conexion, $itemId, $targetStatus, $options)` — actualiza `item_status` del item y dispara rollup.
- `google_calendar_sync_booking_request_rollups($conexion, $bookingRequestId)` — agrega estados de todos los items del caso para derivar un estado rollup del booking_request cuando aplique.
- `google_calendar_sync_item_status_from_event_status($conexion, $itemId, $eventStatus, $options)` — funcion de entrada que mapea `eventStatus` a `targetStatus` y delega a `sync_item_status_for_transition`.

### Normalizacion de estados

- Los estados `pending_admin` y `pending_review` se normalizan a `pending_provider` para compatibilidad con estados legacy.
- Esta normalizacion aplica tanto en las funciones de sync como en el endpoint del dashboard del paciente.

### Archivos afectados

- `inc/google_calendar.php` — funciones de sync nuevas
- `admin/ajax/calendar.php` — llama sync en mutaciones de eventos ITEM
- `client/ajax/calendar.php` — llama sync en mutaciones de eventos ITEM desde el portal del paciente
- `client/ajax/inbox.php` — usa sync para transiciones de cita disparadas desde el inbox
- `admin/ajax/my_booking_requests.php` — usa rollup para reflejar estado derivado en listado de solicitudes

### Alcance de esta sincronizacion

- Esta sincronizacion es minima e intencional: mueve `item_status` cuando hay un evento de calendario asociado.
- No reemplaza las acciones estructuradas del item (aceptar, rechazar, solicitar info, proponer cita).
- Convive con el lifecycle formalizado del item (`treatment_completed`, `post_treatment_follow_up`) y con modalidad explicita de cita (`appointment_mode`) sin mezclar dominios.

## Runtime staff reforzado en superficies asignadas (desde 2026-04-02)

**Commit principal:** `69e62dc`

### Implementado

- Navegacion diferenciada para sesion de staff vinculado: operacion asignada, inbox asignado, agenda asignada.
- Agenda staff en `admin/ajax/calendar.php` con scope por `booking_request_items.assigned_staff_id` para eventos ITEM.
- Contexto UI de inbox/agenda ajustado para indicar responsabilidad operativa asignada y evitar ambiguedad con modo supervision.
- Header/menu de provider ajustado para no mostrar superficies no alineadas al scope del staff vinculado.

### Alcance real del scope

- Scope endurecido en superficies principales reforzadas (agenda/inbox/menu operativo del staff).
- La formalizacion RBAC total de `provider_staff` y cobertura 100% de todas las superficies admin sigue como frente pendiente en backlog.

## Regla tecnica uniforme de asignacion inicial de `assigned_staff_id` (desde 2026-04-02)

**Commit principal:** `9c05fdb`

### Regla implementada

- La asignacion inicial del item en booking asistido se alinea con booking publico.
- Si existe exactamente un unico staff elegible para el servicio, se asigna automaticamente ese staff.
- Si hay multiples elegibles o ninguno, el item nace sin `assigned_staff_id`.

### Objetivo operativo

- Evitar asignaciones arbitrarias al crear el caso.
- Mantener consistencia entre canales de creacion del booking.
- Preparar ownership operativo por item desde el inicio sin romper compatibilidad.

## Lifecycle operativo del item formalizado (desde 2026-04-02)

### `appointment_mode` en cita (`calendar_events`)

- Implementado en runtime por commit `32e2c30`.
- Persistencia en `calendar_events.appointment_mode` con valores: `virtual`, `in_person`, `travel`.
- Fallback seguro por inferencia cuando la columna no existe o viene vacia.

### `treatment_completed` en item (`booking_request_items`)

- Implementado por commit `87748d4`.
- Estado formal del item para cierre de fase de tratamiento.
- Compatibilidad legacy: `completed` se normaliza a `treatment_completed`.

### `post_treatment_follow_up` en item (`booking_request_items`)

- Implementado por commit `16cac36`.
- Transicion formal posterior a `treatment_completed`.
- Metadata opcional soportada con guard de columnas: `follow_up_started_at`, `follow_up_started_by_user_id`.

### Regla de separacion de dominio

- El item concentra lifecycle operativo/clínico (`treatment_completed`, `post_treatment_follow_up`).
- La cita mantiene su dominio propio de agenda y modalidad (`appointment_mode`, estado de evento).
- No debe modelarse el lifecycle clinico como estado de la cita.

## Migraciones requeridas antes del smoke integral

Pendientes de ejecucion previa al smoke end-to-end:

- `sql/2026_04_02_calendar_events_appointment_mode.sql`
- `sql/2026_04_02_booking_request_items_treatment_completed.sql`
- `sql/2026_04_02_booking_request_items_post_treatment_follow_up.sql`

Regla de compatibilidad durante despliegue por fases:

- El runtime usa guards `has_column`/`table_has_column` para no romper entornos que aun no hayan corrido migraciones.
- Antes del smoke integral estas migraciones deben ejecutarse para validar persistencia completa, no solo fallback.

## Ownership operativo visible por item en my_booking_requests (MVP, desde 2026-04-02)

**Commit:** `7f67648`

### Alcance implementado

- `admin/ajax/my_booking_requests.php` incluye la funcion `apply_operational_owner_meta()` que enriquece cada fila de item con metadatos de ownership operativo.
- `admin/my_booking_requests.php` y `admin/js/my_booking_requests.js` exponen una columna "Responsable" en la tabla de items con nombre del staff asignado o label de fallback al provider admin.
- Incluye chip de modo: `Responsable actual`, `Supervisión`, `Seguimiento del staff` o `Sin asignación clínica`.
- Aviso contextual (`supervisor_override_message`) antes de acciones cuando el actor en sesion no es el owner del item.
- El campo `linked_staff_auto_claim_available` senala que un staff vinculado puede auto-asignarse al actuar sobre un item `pending_provider` sin staff asignado.

### Campos expuestos por `apply_operational_owner_meta()`

| Campo | Descripcion |
|---|---|
| `operational_owner_type` | `assigned_staff` o `provider_admin` |
| `operational_owner_label` | Nombre del staff o label de fallback |
| `operational_owner_short_label` | Version corta del label |
| `operational_owner_role_label_es` | `Staff asignado` o `Administración del prestador` |
| `operational_owner_note_es` | Nota contextual del estado de ownership |
| `has_operational_owner_staff` | 1 si hay staff asignado, 0 si no |
| `current_user_is_operational_owner` | 1 si el actor en sesion es el staff asignado |
| `supervisor_override_required` | 1 si admin actua sobre item asignado a otro staff |
| `supervisor_override_message` | Texto del aviso de supervision |
| `linked_staff_auto_claim_available` | 1 si staff puede auto-asignarse (estado `pending_provider`, sin staff) |
| `linked_staff_auto_claim_message` | Texto del aviso de auto-claim |
| `ownership_mode_label_es` | Label del chip de modo visible en la UI |

### Lo que este MVP NO cubre todavia

- No implementa scope RBAC duro global para todo el admin; el endurecimiento actual cubre superficies reforzadas principales y no reemplaza el rol tecnico `provider_staff` pendiente.
- La landing operativa de staff existe en superficies reforzadas, pero la formalizacion total de permisos por rol tecnico aun esta pendiente.
- No existe rol tecnico `provider_staff` separado de ROLE_PROVIDER / ROLE_PROVIDER_ADMIN.
- La auto-asignacion (`linked_staff_auto_claim`) senala disponibilidad pero no persiste la asignacion automaticamente; eso es frente pendiente.
- La semantica de ownership visible nacio en `my_booking_requests`; la cobertura y consistencia completa en todas las superficies sigue como frente abierto.

### Aclaracion de gating conversacional operativo en Inbox ITEM (desde 2026-04-09)

- El runtime separa el gate comercial general del provider del acceso conversacional operativo del staff asignado en `Inbox` ITEM.
- `provider` owner/admin mantiene su gating comercial actual de fee/comision cuando aplique.
- El staff vinculado asignado puede entrar y operar en `Inbox` ITEM sin heredar automaticamente ese gate solo si el item pertenece al mismo `provider_id`, `assigned_staff_id` coincide con el staff en sesion y el estado del item es `provider_confirmed`, `client_accepted`, `treatment_completed` o `post_treatment_follow_up`.
- Esta excepcion no cambia por arrastre `app_calendar.php`, `admin/ajax/calendar.php` ni la semantica de agenda: `Inbox` sigue siendo conversacion y `Calendar` sigue siendo agenda.

## Nuevo esquema de trazabilidad (migracion 2026_04_02_agent_assisted_booking)

### Columnas nuevas en `booking_requests`

| Columna             | Tipo        | Default          | Descripcion                                               |
|---------------------|-------------|------------------|-----------------------------------------------------------|
| `creation_source`   | VARCHAR(20) | `public_form`    | `public_form` / `agent_assisted` / `api`                  |
| `created_by_agent`  | INT         | NULL             | FK a `usuarios.id`: agente que creo el caso por el cliente |
| `agent_channel`     | VARCHAR(50) | NULL             | `whatsapp` / `widget_chat` / `phone` / `other`            |

### Columnas nuevas en `usuarios`

| Columna              | Tipo         | Default | Descripcion                                         |
|----------------------|--------------|---------|-----------------------------------------------------|
| `terms_accepted`     | TINYINT(1)   | 0       | 1 = usuario acepto personalmente los Terminos       |
| `terms_accepted_at`  | DATETIME     | NULL    | Timestamp de aceptacion                             |
| `terms_version`      | VARCHAR(20)  | NULL    | Version de Terminos en el momento de aceptacion    |
| `terms_ip`           | VARCHAR(45)  | NULL    | IP del cliente al aceptar                           |
| `terms_user_agent`   | VARCHAR(255) | NULL    | Navegador / dispositivo al aceptar                  |

### Backfill aplicado

- Clientes existentes con bookings previos donde `booking_requests.terms_accepted = 1` recibieron `usuarios.terms_accepted = 1` con valores derivados del booking.
- Esto evita que clientes activos sean forzados a re-aceptar en su proximo login.

## Componentes de booking asistido por agente (Admin, desde 2026-04-02)

| Archivo                              | Rol                                                               |
|--------------------------------------|-------------------------------------------------------------------|
| `admin/booking_asistido.php`         | UI del agente: seleccion de canal, paciente, categoria/servicio/oferta |
| `admin/ajax/booking_asistido.php`    | Backend: lookup/creacion de usuario, creacion de booking + items, envio de credenciales |
| `client/terms_gate.php`              | Pagina de aceptacion de Terminos para pacientes recien creados por agente |
| `client/ajax/accept_terms.php`       | Endpoint AJAX: registra aceptacion de Terminos con auditoria       |
| `sql/2026_04_02_agent_assisted_booking.sql` | Migracion iterativa con ADD COLUMN IF NOT EXISTS + backfill |

## Social links y URLs publicas compartidas — `inc/public_site_links.php` (desde 2026-04-02)

- `inc/public_site_links.php` es la fuente unica de verdad para enlaces sociales, URL de Terminos y URL de Privacidad del frente publico.
- Expone: `mt_public_social_links()`, `mt_public_terms_url()`, `mt_public_privacy_url()`, `mt_pending_terms_notice_payload()`.
- Consumido por `login.php`, `set_password.php` e `inc/include.php`.
- Nuevos modulos y paginas que requieran estos datos deben importar este helper en lugar de hardcodear los valores.
