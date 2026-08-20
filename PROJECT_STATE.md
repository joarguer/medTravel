# PROJECT_STATE.md — MedTravel

Estado actual del proyecto. Actualizar al cierre de cada sesión técnica relevante.
Workspace operativo actual: `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/workspaces/medtravel`.

---

## Estado general

- **Plataforma:** operativa en desarrollo local
- **Último bundle conocido:** `medtravel_local_backup_20260410.bundle`
- **Base de datos:** entorno local moderno validado en `medtravel_rebuild_20260415` (MySQL, reconstruida desde dump real del servidor). `medtravel` queda preservada solo como referencia/backup local legacy y no debe usarse para validar el dominio moderno de providers/staff/services. Producción: `medtravelcom_medtravel`
- **Fecha última actualización de este archivo:** 2026-06-25 (cierre de sesión documental — canon marketing/tracking Meta Ads)

---

## Deuda técnica activa (P0 — bloquean otro trabajo)

| ID | Descripción |
|----|-------------|
| P0.1 | Unificar campos de `service_catalog` y `service_categories` en todos los scripts SQL |
| P0.2 | Eliminar contenido hardcodeado en `index.php`, `packages.php`; mover a BD |
| P0.3 | Normalizar booking end-to-end: servicios complementarios de texto libre → relacional |
| P0.4 | Completar integración de `paquetes`: el frontend no persiste selecciones de servicios |
| P0.5 | Agregar `require_login_ajax()` + permisos en endpoints AJAX de catálogos |

---

## Frentes completados recientes
- **2026-08-20** — fix(conectarbot/catalog): `GET /api/conectarbot/v1/catalog/service/{slug}` conserva el shape legacy (`id`, `name`, `slug`, `description`, `active`, `price_from_usd`) y agrega contrato aditivo público para `providers`, `staff`, `locations` y `offers`. La existencia del servicio ya no depende de oferta activa: `endolifting` devuelve provider/ubicación pública aun sin precio/staff local. El endpoint excluye contacto directo (`phone`, `email`, WhatsApp) y dirección exacta (`address`). Validado localmente contra `medtravel_rebuild_20260415` con `endolifting` y `evaluacin-cardiolgica-inicial-mt-test-01`; sin deploy ni push.
- **2026-06-25** — docs(marketing/tracking): se canoniza en `docs/canonical/30_MARKETING_AND_TRACKING.md` la decisión operativa de campaña Meta Ads Face Up Thread Lift. Pixel oficial `2206493506836761`, conversión definitiva `Lead`, tracking de producción Browser Pixel + Conversions API con deduplicación por `event_id`, privacidad sin PII ni datos médicos/estéticos, variables `META_CAPI_ACCESS_TOKEN` y `META_PIXEL_ID`, campaña `MedTravel | Face Up Thread Lift | Leads Booking` en borrador por método de pago pendiente, presupuesto inicial recomendado `$30.000 COP/día`, límite detectado `$180.000 COP`, público Florida mujeres 30-60 (mínimo/control 25 si Meta lo exige), creatividad en inglés, CTA `See details` o `Learn More` y URL `https://medtravel.com.co/offer_detail.php?id=9`.
- **2026-06-25** — feat(tracking/lead): tracking definitivo de producción para MedTravel Pixel Oficial `2206493506836761`: conversión estándar `Lead` con Browser Pixel + Conversions API y deduplicación por `event_id` aleatorio. `booking/submit.php` genera `event_id` seguro después de guardar `booking_requests`, lo guarda en sesión, dispara CAPI tolerante a fallos usando `META_PIXEL_ID`/`META_CAPI_ACCESS_TOKEN` y solo envía datos técnicos permitidos (`client_ip_address`, `client_user_agent`, `_fbp`, `_fbc`). `booking/wizard.php` dispara `fbq('track', 'Lead', {}, { eventID })` sin payload sensible y limpia la bandera tras imprimir el script. Sin `BookingIntent`, sin `Purchase`, sin test_event_code.
- **2026-06-25** — debug(tracking/lead): `booking/wizard.php` agrega verificación visible en consola dentro del bloque real de `Lead`: `[MedTravel Pixel] MT_LEAD_RENDERED present` con `document.documentElement.innerHTML.includes('MT_LEAD_RENDERED')`. El evento se mantiene como `fbq('track', 'Lead')` sin payload ni datos sensibles y con retry cuando `fbq` aún no está disponible.
- **2026-06-25** — fix(tracking/lead): verificado en HTML productivo con user-agent de navegador que `/`, `offers.php`, `offer_detail.php?id=9`, `booking.php?offer_id=9` y `booking/wizard.php` cargan el Pixel oficial `2206493506836761` con `MT_PIXEL_DEBUG_VERSION`. El `Lead` sigue atado al guardado exitoso en `booking/submit.php` y `booking/wizard.php` agrega el marcador HTML `<!-- MT_LEAD_RENDERED -->` junto al script `fbq('track', 'Lead')` sin payload para facilitar verificación en código fuente tras un booking real. La prevención de duplicados queda del lado servidor con la bandera de sesión que se limpia después de imprimir el script.
- **2026-06-25** — fix(tracking/booking): Meta Pixel queda simplificado a eventos estándar `PageView` + `Lead` para reducir restricciones de salud/bienestar. Se desactiva/remueve `BookingIntent`, sus listeners globales y atributos en CTAs públicos. `Lead` se mantiene solo después de guardar exitosamente `booking_requests`, se consume una vez en `booking/wizard.php`, dispara sin payload sensible y conserva logs no sensibles de pending/render/fire/clear.
- **2026-06-25** — fix(tracking/booking): se refuerza `Lead` posterior al guardado real del booking. `booking/submit.php` registra sin datos sensibles cuándo prepara payload y setea la bandera de sesión. `booking/wizard.php` registra render/limpieza de sesión, conserva solo payload permitido y usa un wrapper con reintentos breves para disparar `fbq('track','Lead',...)` cuando `fbq` esté disponible. No se toca Pixel base, PageView, Purchase, diseño ni lógica comercial.
- **2026-06-25** — fix(email/booking): tras validar que SMTP autentica correctamente en `admin/email_settings.php`, `booking/submit.php` refuerza el cierre público para que el correo al paciente no dependa del armado completo de plantilla ni de bloques opcionales post-guardado. El envío ahora registra en `admin/logs/booking_submit_runtime.log` inicio/resultado sin datos sensibles, usa fallback transaccional mínimo si falla el payload de plantilla y evita que un error de payload de tracking posterior al guardado corte el flujo antes del correo.
- **2026-06-25** — fix(email/booking): el correo de confirmación al paciente del booking público se envía después de guardar `booking_requests` usando el email persistido del request recién creado como fuente principal. `booking/submit.php` agrega logs no sensibles de inicio, destinatario presente, resultado y error; el envío queda independiente de notificaciones internas. `admin/include/email_config.php` enmascara destinatarios en logs y desactiva el transcript SMTP crudo por defecto.
- **2026-06-25** — UX(booking): los submits del flujo público muestran spinner y bloquean interacción durante el envío sin deshabilitar campos de datos del POST. `inc/booking_form.php` cubre el submit inicial hacia `booking/step-1.php`; `booking/wizard.php` cubre el submit final hacia `booking/submit.php`, evita doble submit y mantiene el estado hasta la respuesta/redirección del servidor.
- **2026-06-25** — fix(tracking/booking): `MT_PIXEL_DEBUG_VERSION` sube a `2026-06-25-02` y expone `window.mtPixelDebug` en páginas públicas modernas para ver versión, Pixel ID y presencia de `fbq` desde consola. `BookingIntent` deja de dispararse en `pointerdown` para no consumir el evento antes del delay de navegación; ahora se dispara en `click`, loguea candidato y evento, y retrasa 500 ms solo enlaces normales. `booking/submit.php` agrega logs no sensibles de `saved flag` y `booking_request_id`. `index.php` elimina un `noscript` legacy del Pixel viejo.
- **2026-06-25** — fix(tracking/booking): se agrega marcador técnico `MT_PIXEL_DEBUG_VERSION: 2026-06-25-01` en el head global para verificar despliegue en producción. El listener global de `BookingIntent` ahora captura tanto CTAs instrumentados como enlaces públicos `booking.php?offer_id=...`; `offers.php` añade payload seguro al CTA real `Book Now`. `booking/submit.php` registra logs no sensibles para confirmar llegada del submit, guardado de `booking_requests` e intento de correo al paciente.
- **2026-06-25** — fix(tracking/booking): `BookingIntent` queda delegado globalmente para CTAs con `data-mt-pixel-booking-intent`, con eventos tempranos (`pointerdown`/`touchstart`) y pausa corta antes de navegación para no perder el evento en `Book This Service`. `Lead` se mueve al punto de guardado real de `booking_requests` en `booking/submit.php` y se consume una sola vez en `booking/wizard.php`. Payloads del Pixel quedan filtrados a `content_name`, `content_ids`, `content_category`, `value`, `currency`. `mt_asset_url()` ahora emite rutas absolutas para evitar 404 de assets en `/booking/`. La confirmación al paciente se intenta en un bloque independiente después del guardado exitoso para que fallas de resumen/notificaciones internas no la omitan.
- **2026-06-25** — chore(tracking): Pixel/Dataset base global actualizado al dataset oficial `MedTravel Pixel Oficial` (`2206493506836761`) en `inc/include.php`. Se mantienen intactos `PageView`, `BookingIntent` y `Lead`; no se agregan eventos de compra ni datos sensibles del paciente al Pixel.
- **2026-06-25** — feat(tracking): Meta Pixel mantiene el base `PageView` existente en `inc/include.php` sin duplicarlo y agrega medición del embudo público de booking. `BookingIntent` se dispara desde el CTA principal de oferta y al iniciar/enviar el formulario global; `Lead` se dispara una sola vez al llegar a `booking/wizard.php` después de validación servidor en `booking/step-1.php` con datos mínimos y consentimientos completos. No se implementan eventos de compra.
- **2026-04-21** — ops(runtime): gating remoto de `admin/cleanup.php` validado en servidor. `APP_ENV=dev` + `ALLOW_DEV_RESET=1` en `.env` no habilitan Execute en host público porque `conexion.php` fuerza `APP_ENV` a `prod` sin `ALLOW_REMOTE_DEV=1`. Triple condición necesaria en remoto: `APP_ENV=dev` + `ALLOW_REMOTE_DEV=1` + `ALLOW_DEV_RESET=1`. Las dos últimas son temporales y deben retirarse del servidor tras completar el reset. Canonizado en `13_CHANGELOG_DECISIONS.md` y `11_TECH_ARCH_AND_RUNTIME.md`.
- **2026-04-20** — feat(booking): `admin/booking_asistido.php` acepta prefill GET completo desde ConectarBot/Chatwoot (10 params: `prefill_email`, `prefill_name`, `prefill_phone`, `prefill_channel`, `prefill_city`, `prefill_country`, `prefill_bio`, `prefill_company`, `cw_conversation_id`, `cw_contact_id`). Banner chatwoot. Auto-lookup no pisa campos ya prefillados. `city`+`country` → `origin`; `bio`+`company` → `special_request`. `cw_conversation_id` persiste en `booking_requests`. Migración: `sql/2026_04_20_booking_cw_conversation.sql`. Flujo sigue siendo 100% agent-assisted.
- **2026-04-18** — feat(provider-profile): `providers` incorpora redes institucionales del prestador (`instagram_url`, `facebook_url`, `linkedin_url`, `youtube_url`, `whatsapp_url`) con edición desde `admin/mi_empresa.php` para dominio médico. `admin/ajax/mi_empresa.php` valida host básico por canal, hace `trim` y guarda vacío como `NULL`. Se crea helper reutilizable `inc/provider_public_links.php` para validar y resolver links públicos. `inc/public_specialists.php` expone links institucionales del provider al payload público del staff; `index.php`, `specialists.php` y `blog.php` muestran de forma discreta máximo 2 iconos (`website`, `instagram`) como señal de credibilidad sin mezclar identidad del staff.
- **2026-04-18** — feat(blog): `blog_posts.video_url` soporta ahora YouTube, Vimeo e Instagram público (post / reel) sin cambiar schema. Se crea helper compartido `inc/blog_media_embed.php` para normalizar y resolver embeds; `admin/ajax/blog_posts.php` deja de validar solo YouTube/Vimeo; `blog_post.php` usa el helper y para Instagram renderiza bloque oficial `instagram-media` con fallback visible `View on Instagram`. `video_file` local mantiene prioridad intacta.
- **2026-04-18** — fix(providers): se corrige la pérdida de `provider_catalog_services` al guardar desde `admin/providers.php`. `admin/js/providers.js` deja de cargar `#prov-services` con `ajax/service_catalog.php?tipo=list` sin contexto y usa un nuevo branch global `tipo=list_master_catalog`. `admin/ajax/providers.php` endurece update: solo borra/reemplaza `provider_catalog_services` cuando el request trae explícitamente la señal `services_field_present`; campo ausente ya no implica wipe silencioso. `admin/service_catalog.php` no cambia semántica: sigue leyendo el catálogo habilitado real del provider.
- **2026-04-17** — fix(provider-staff): `admin/ajax/provider_medical_staff.php` permite reutilizar como `provider_medical_staff` la cuenta existente del owner/admin inicial canónico del mismo `provider_id`. La excepción queda concentrada en `pms_evaluate_staff_access_user()` y solo salta para el usuario exacto resuelto por `pms_fetch_provider_owner_user(...)`; se mantienen vivos los bloqueos para otros providers, `service_provider_id > 0`, usuarios ya vinculados a otro staff y cuentas incompatibles. No se crean usuarios duplicados: el flujo reutiliza `linked_user_id` tanto en validación previa por email como en `save_staff`.
- **2026-04-17** — fix(offers): forzar contexto provider+service en servidor para evitar datasets ambiguos. `offers.php` ahora aplica filtro server-side cuando se provee `service_id` + `provider_id`; si `service_id` llega sin `provider_id` la página muestra un estado controlado y no renderiza cards mezcladas. `admin/js/service_catalog.js` genera URLs públicas con `provider_id+service_id`. Smoke validado contra `medtravel_rebuild_20260415` (provider_id=2, service_id=9). Riesgo UX de degradación a provider-only registrado para decisión futura.
- **2026-04-16** — Validación parcial en servidor de Meet execution evidence: migración aplicada, consumer noop correcto (`{"ok":true,"noop":true}`), env vars Pub/Sub presentes, service account creada, OAuth reconectado con scope Meet. Dry-run backfill ejecutado con `scanned=0` — causa correcta: no hay `calendar_events.status='confirmed'` con Meet activos en producción (todos `cancelled`). Flag sigue OFF. Próximo paso: crear cita Meet confirmed viva y re-ejecutar dry-run. Ver `13_CHANGELOG_DECISIONS.md` (2026-04-16 ops/validación).
- **2026-04-16** — Plan operacional de activación Meet execution evidence canonizado: código Fase 1 completo (`inc/google_meet_execution.php`, `scripts/google_meet_consumer.php`, `scripts/google_meet_backfill_space_names.php`, migración `2026_04_16_google_meet_execution_phase1.sql`). Flag OFF. Ver `13_CHANGELOG_DECISIONS.md` (2026-04-16 ops).
- **2026-04-16** — Canonizada la Fase 2 de evidencia real de ejecución Google Meet en la base documental: se formaliza una capa separada de `agenda/calendar` y de `booking_request_items.item_status` para detectar si la reunión virtual realmente inició o terminó usando Google Workspace Events + Pub/Sub + Google Meet API. Queda explícitamente **no implementada** por ahora. Se actualizan `PROJECT_STATE.md`, `10_PRODUCT_MODEL.md`, `12_EXECUTION_BACKLOG.md`, `13_CHANGELOG_DECISIONS.md`, `14_CALENDAR_MEET_INTEGRATION_MODEL.md` y `16_ACTORS_AND_DOMAINS.md`.
- **2026-04-16** — Smoke Google Calendar / Meet E2E completo validado en local (commits `6a29500`, `8d8e3d0`, `b25e42b`, `d05d451`, `0d5eab4`): flujo completo `provider_propose_change → accept_dates → cancel_meeting` ejecutado de punta a punta con evento real en Google Calendar, Google Meet real, attendees correctos (paciente + staff asignado), y cancelación confirmada por Google API (`status=cancelled`). Regresión `e00a316` (crear evento al proponer) detectada, auditada y revertida con `b25e42b`. Hallazgo de staff sin invitación cerrado como artefacto de datos locales del smoke (`assigned_staff_id=NULL`). Comportamiento correcto del producto validado: el evento real se crea al aceptar el paciente, no al proponer. Ver `docs/canonical/13_CHANGELOG_DECISIONS.md` y `docs/canonical/14_CALENDAR_MEET_INTEGRATION_MODEL.md`.
- **2026-04-16** — Alineación canónica de actores y dominios: se corrige la premisa de que el admin MedTravel es el actor responsable de proponer citas o avanzar el lifecycle clínico. Se crea `docs/canonical/16_ACTORS_AND_DOMAINS.md` (tabla maestra de actores, dominios, fronteras, recorrido correcto del smoke). Se actualiza `10_PRODUCT_MODEL.md` (tabla de actores, estados visibles completos con 7 del ciclo clínico 2026-04-15, acciones con actor asignado), `13_CHANGELOG_DECISIONS.md` (decisión 2026-04-16), `AGENTS.md` (RBAC con función real por actor), `12_EXECUTION_BACKLOG.md` (smoke test con 3 sesiones correctas y queries de validación), `14_CALENDAR_MEET_INTEGRATION_MODEL.md` (dos paths de propuesta de cita), `00_INDEX.md` (puntero a nuevo doc).
- **2026-04-15** — Lifecycle médico completo en admin: ciclo clínico `provider_confirmed → virtual_assessment_pending → virtual_assessment_done → treatment_plan_agreed → procedure_scheduled → treatment_completed → case_closed` implementado en `admin/ajax/my_booking_requests.php` con reversas controladas (`$isActualReversal`) y acciones formales de atención clínica (valoración virtual, plan acordado, procedimiento presencial, cierre de caso). Modal `my_booking_requests` incluye tab "Atención clínica" con panel de guía operativa y acciones por estado.
- **2026-04-15** — Modal detalle solicitud mejorado: visor de documentos (`#adminDocViewerModal`) replicado desde `app_inbox` (PDF/imagen/fallback, preview endpoint, descarga); labels amigables en español para todos los estados lifecycle en `genericStatusLabelEs` y colores en `renderStatusBadge`; fix de scope de documentos (`client_id` vs `client_user_id` desync resuelto — ahora siempre scope por `booking_request_id`).
- **2026-04-15** — `client_documents` canonizado: modelo y flujo documentados en `docs/canonical/15_DOCUMENTS_MODEL.md`. Deuda heredada explícita (DOC-D1 a DOC-D7). `00_INDEX.md` actualizado.
- **2026-04-15** — `timeline_from` / `timeline_to` confirmados como columnas de producción en `booking_requests`. Runtime ya los usa como ventana operativa de fechas del caso. Migración requerida en servidor antes de usar acciones que los escriben.
- **2026-04-15** — Providers archive/restore reversible: `admin/ajax/providers.php` + `admin/js/providers.js` + `admin/providers.php` con schema guards. Migración: `sql/2026_04_15_providers_archive_restore_columns.sql` (idempotente, MySQL 5.7 compat).
- **2026-04-15** — Fix realtime proposal chat: servidor `POST /realtime/internal/inbox-message` exige `{thread_id, message_id, sender_role, created_at}`; PHP enviaba solo los 2 primeros campos → `400 bad_request`. Fix en `inc/realtime.php` y todos los callers (`admin/ajax/inbox.php` ×3, `admin/ajax/my_booking_requests.php`). Emit CARE post-propuesta añadido sin INSERT duplicado.
- **2026-04-15** — Hardening `admin/include/conexion.php`: guard impide que `APP_ENV=dev` se active en host remoto sin `ALLOW_REMOTE_DEV=1` explícito.
- **2026-04-15** — Organizer fallback Google Meet: `client/ajax/inbox.php` resuelve organizer con prioridad sender-propuesta → staff-asignado → cualquier admin OAuth conectado.
- **2026-04-15** — Entorno local realineado al modelo moderno: la BD local previa `medtravel` se confirmó estructuralmente desalineada para el dominio provider; se reconstruyó una nueva BD local `medtravel_rebuild_20260415` a partir de dump real del servidor. El import requirió una copia temporal compatible para MySQL 5.7 eliminando `DEFAULT` incompatibles en tipos `TINYTEXT`/`TEXT`/`MEDIUMTEXT`/`LONGTEXT`/`BLOB`/`JSON`/`GEOMETRY`; el dump original no se alteró. El runtime local ya apunta a la BD nueva vía `.env` local no versionado.
- **2026-04-14** — Onboarding médico admin refinado: `providers.php` se reorganiza en bloques A–E (prestador, owner/admin inicial, categorías, servicios, compliance documental) y `provider_verification.php` compacta su grilla con resumen visual sin depender de scroll horizontal
- **2026-04-14** — Google Calendar / Meet Fase 1 validado con organizer admin autenticado; OAuth corregido para scope real de Calendar y reconexión limpia; cancelación de reunión vuelve a dejar el item reprogramable para provider
- **2026-04-03** — Fase 0–4 SEO/credibilidad en superficies públicas (commits `6d4db96` → `e9466ad`)
- **2026-03-20** — Tabla `provider_medical_staff` operativa; separación proveedor/médico activa
- **Booking wizard** — CTA integration documentada, provider offers, mejoras de wizard

---

## Tablero de frentes abiertos

El bloque “ATACAR AHORA” tiene prioridad operativa inmediata. La numeración inferior conserva el inventario global de frentes abiertos.

Orden de cierre recomendado. Actualizar estado al cerrar cada frente.

---

### ✅ CERRADO — Google Calendar · Meet · cancelaciones

| Campo | Detalle |
|-------|--------|
| **Estado** | **completado** (smoke E2E local cerrado 2026-04-16) |
| **Impacto** | alto |
| **Evidencia** | Runtime validado: el organizer técnico de Google Calendar / Meet es la cuenta Google del admin autenticado en MedTravel; paciente y provider/staff participan como invitados y no conectan Google en este flujo. OAuth corregido con scope real `https://www.googleapis.com/auth/calendar`, `include_granted_scopes=false` y criterio de reconexión limpia cuando aparece `invalid_grant` o permisos insuficientes. Cancelar una reunión ya no cierra el caso: el item vuelve a `appointment_requested_change` y en Inbox operativo se expone como `provider_proposed_change` para permitir reprogramación / nueva propuesta. Las 3 migraciones locales (`appointment_mode`, `treatment_completed`, `post_treatment_follow_up`) siguen siendo punto de contraste si faltan en un entorno. |
| **Siguiente acción** | Validar en producción real con migraciones pendientes por entorno. Smoke local completo: propuesta → aceptación (evento Google real + Meet real + attendees correctos) → cancelación (Google API confirma `cancelled`). Ver `docs/canonical/13_CHANGELOG_DECISIONS.md` (2026-04-16 smoke). |

---

### 1. Google Meet · evidencia real de ejecución

| Campo | Detalle |
|-------|---------|
| **Estado** | desplegada en servidor, flag OFF, pendiente backfill con candidato confirmado |
| **Impacto** | alto |
| **Evidencia** | Migración aplicada en producción. Consumer noop correcto (`MT_GOOGLE_MEET_EXECUTION_ENABLED=0`). Env vars Pub/Sub presentes. Service account activa. OAuth reconectado con scope Meet. Dry-run ejecutado: `scanned=0` — causa: no hay `calendar_events.status='confirmed'` con Meet activos ahora en producción (todos `cancelled`). No es error de código ni de scope. |
| **Siguiente acción** | 1) Crear cita virtual Meet con `status='confirmed'` en producción (propuesta + aceptación paciente); 2) re-ejecutar dry-run `--dry-run --limit=10` y verificar `scanned>0`, `resolved>0`; 3) backfill real; 4) `MT_GOOGLE_MEET_EXECUTION_ENABLED=1`. Ver `docs/canonical/12_EXECUTION_BACKLOG.md` y `13_CHANGELOG_DECISIONS.md` (2026-04-16 ops/validación). |

---

### 2. Homepage · oferta · confianza · empaque comercial

| Campo | Detalle |
|-------|---------|
| **Estado** | en progreso |
| **Impacto** | alto |
| **Evidencia** | Fases 0–4 SEO committeadas (2026-04-03, commits `6d4db96` → `e9466ad`). P0.2 abierto: `index.php` y `packages.php` con contenido hardcodeado. Search Console pendiente. |
| **Siguiente acción** | Enviar `sitemap.xml` a Search Console + ejecutar P0.2 (mover contenido a BD) |

---

### 3. Chat IA RAG MedTravel USA

| Campo | Detalle |
|-------|---------|
| **Estado** | abierto |
| **Impacto** | alto |
| **Evidencia** | Cero mención en docs, código ni backlog. Sin spec, sin entidad, sin decisión canónica. |
| **Siguiente acción** | Sesión documental: definir alcance, modelo RAG y canal de entrada antes de tocar código |

---

### 4. Provider · staff · services semantics

| Campo | Detalle |
|-------|---------|
| **Estado** | en progreso |
| **Impacto** | medio |
| **Evidencia** | Canon cerrado (2026-03-21). El alta admin en `providers.php` ya quedó reordenada como onboarding entendible en bloques A–E y `provider_verification.php` ya resume compliance/trust en una grilla compacta sin exigir scroll horizontal. Sigue abierta la deuda de modelo: `provider_catalog_services` no es entidad fuerte; staff y ofertas siguen ligados a `service_catalog.id` directo; copy de Mis Servicios / Mis Ofertas / Staff aún requiere convergencia semántica. |
| **Siguiente acción** | Paso 7: declarar `provider_catalog_services` como entidad operativa fuerte y desacoplar staff/ofertas |

---

### 5. SEO · perfiles médicos · E-E-A-T

| Campo | Detalle |
|-------|---------|
| **Estado** | en progreso |
| **Impacto** | medio |
| **Evidencia** | Fases 0–4 completas. Superficie pública activa. Pendiente: Search Console, monitoreo de indexación, campañas con URLs definidas. |
| **Siguiente acción** | Enviar sitemap a Search Console → monitorear cobertura → activar campañas |

---

### 6. Mantenimiento documental fino

| Campo | Detalle |
|-------|---------|
| **Estado** | en progreso |
| **Impacto** | bajo |
| **Evidencia** | Base documental implantada 2026-04-13 (commit `e9495b5`). Se detectó drift real en `CLAUDE.md` por duplicación de contexto y referencias viejas; además `12_EXECUTION_BACKLOG.md`, `NEXT_STEPS_SERVICES.md` y `DEV_CONTEXT.md` necesitaban jerarquía explícita para no competir silenciosamente con el canon. |
| **Siguiente acción** | Mantener `CLAUDE.md` como shim de compatibilidad y vigilar que todo contexto vivo nuevo siga entrando solo por `AGENTS.md`, `PROJECT_STATE.md` y `docs/canonical/*` |

---

## Variables de entorno (desarrollo)

```
APP_ENV=dev
DB_HOST=127.0.0.1 / DB_PORT=8889
DB_USER=root / DB_PASS=root
DB_NAME=medtravel_rebuild_20260415
CONECTARBOT_API_KEY=mt_cb_live_...
GOOGLE_OAUTH_CLIENT_ID, GOOGLE_OAUTH_CLIENT_SECRET
GOOGLE_OAUTH_REDIRECT_URI, GOOGLE_OAUTH_ENCRYPTION_KEY
```

Regla operativa local:
- `.env` es override local del workspace y no se versiona.
- `medtravel` se conserva solo como backup/referencia local legacy.
- La base válida para desarrollo y validaciones locales del dominio moderno provider/staff/services es `medtravel_rebuild_20260415`.

---

## Arquitectura documental

Última implantación de base documental: 2026-04-13.
Ver `docs/canonical/02_DOC_MAP.md` para estructura completa.

## Cambios rápidos (audit trail)

- **2026-04-18** — feat(provider-profile): se agregan redes institucionales al master médico `providers` (`instagram_url`, `facebook_url`, `linkedin_url`, `youtube_url`, `whatsapp_url`) con edición desde `admin/mi_empresa.php` y migración `sql/2026_04_18_provider_social_links.sql`. `inc/provider_public_links.php` centraliza validación y resolución de links públicos. Las cards públicas de `index.php`, `specialists.php` y `blog.php` ahora pueden mostrar una señal discreta del provider con máximo 2 iconos (`website`, `instagram`), sin mezclar perfiles personales del staff.
- **2026-04-18** — feat(blog): `video_url` del blog acepta ahora enlaces públicos de YouTube, Vimeo e Instagram (`/p/` y `/reel/`). El parser/render se unifica en `inc/blog_media_embed.php`. Instagram usa bloque oficial `instagram-media` con script `embed.js`; si el embed no resuelve, el post conserva enlace visible `View on Instagram`. No hubo cambio de schema y `video_file` sigue teniendo prioridad.
- **2026-04-18** — fix(providers): `admin/providers.php` deja de perder servicios habilitados al guardar ediciones. `admin/js/providers.js` carga el multiselect de servicios con un nuevo branch global `ajax/service_catalog.php?tipo=list_master_catalog` y el backend `admin/ajax/providers.php` solo reemplaza `provider_catalog_services` cuando el request trae `services_field_present`. Se preserva la semántica de `admin/service_catalog.php` como lectura de servicios efectivamente habilitados por provider.
- **2026-04-18** — feat(catalog): filtrar offers por especialista. Cards de `index.php` y `specialists.php` llevan a `offers.php?staff_id=N`. Query server-side: `offer.provider_id = staff.provider_id AND offer.service_id IN (provider_medical_staff_services)`. Semántica de fallback estricta: staff inválido/no publicado → empty state "Specialist not available"; sin servicios asignados → empty state "No services assigned yet"; nunca catálogo completo. Banner visual con nombre/rol y link para quitar filtro. Ver `docs/canonical/13_CHANGELOG_DECISIONS.md` (2026-04-18).
- **2026-04-18** — fix(home): separar reglas CSS de `home-specialist-photo` y `home-specialist-avatar` para asegurar `object-fit: cover` en imágenes de especialista. Se dejó `home-specialist-photo` con `width:100%`, `height:320px`, `object-fit:cover`, `object-position:center top`, `display:block`. `home-specialist-avatar` mantiene el `display:flex` centrado y estilos de fondo/typography. El contenedor `.home-specialist-card` ya provee `overflow:hidden` y `border-radius:14px`, por tanto no fue necesario tocar wrappers. Validación manual recomendada en navegadores (Chrome, Safari, Firefox) verificando que la imagen no se distorsione ni se sobre-dimensione.
