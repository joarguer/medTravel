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

### Compatibilidad legacy

- El runtime actual puede seguir apoyandose en `booking_requests`, `booking_request_items`, inbox, calendar y componentes auxiliares mientras el modelo evoluciona.
- Cuando falten campos dedicados, el sistema debe soportar derivacion segura o alias logicos sin inventar una tabla definitiva que aun no exista.
- La separacion operativa canonica ya es obligatoria a nivel de documentacion, aunque la persistencia siga madurando por fases.

## Staff medico — tablas y componentes operativos (desde 2026-03-20)

**Tablas**
- `provider_medical_staff` — entidad principal del staff interno del prestador
- `provider_medical_staff_services` — relacion staff ↔ servicios habilitados del proveedor
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
