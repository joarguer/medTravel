# Changelog Decisions

## 2026-04-16 — ops(meet-execution): validación parcial en servidor; capa desplegada y apagada; dry-run ejecutado sin candidatos confirmados

**Outcome**
- Validación de servidor completada para todos los prerequisitos excepto el backfill real de `google_meet_space_name`.
- La capa Meet execution evidence está desplegada en producción y apagada (`MT_GOOGLE_MEET_EXECUTION_ENABLED=0`).
- La sesión se cierra sin activar el flag. Mañana se continúa desde este punto exacto.

**Qué quedó validado en servidor hoy:**

| Paso | Resultado |
|------|-----------|
| Migración `2026_04_16_google_meet_execution_phase1.sql` aplicada | ✅ confirmado |
| Consumer noop correcto con flag OFF | ✅ `{"ok":true,"noop":true,"reason":"MT_GOOGLE_MEET_EXECUTION_ENABLED=0"}` |
| Env vars Pub/Sub en `.env` | ✅ `GOOGLE_CLOUD_PROJECT_ID=medtravel-calendar`, `GOOGLE_PUBSUB_SUBSCRIPTION=medtravel-meet-events-sub`, `GOOGLE_PUBSUB_SERVICE_ACCOUNT_JSON_PATH=/home/medtravelcom/secure/google/medtravel-calendar-c28004e85efa.json` |
| Service account creada con rol Pub/Sub Subscriber | ✅ confirmado |
| OAuth `medtravelusa@gmail.com` reconectado con permiso Google Meet visible | ✅ scope Meet presente en consentimiento |
| Dry-run backfill `space_name` ejecutado | ✅ `{"ok":true,"scanned":0,"resolved":0}` |

**Por qué `scanned=0` en el dry-run — no es un error:**
- El backfill filtra `WHERE status='confirmed' AND google_meet_space_code <> '' AND google_meet_space_name = ''`.
- Todos los eventos con Meet visibles en `calendar_events` hoy están `status='cancelled'`.
- No existe ninguna cita Meet en estado `confirmed` activa en este momento en producción.
- El dry-run es correcto: no procesa filas que no están disponibles para procesar.
- **No es un bug de código ni de scope. Es ausencia de candidatos.**

**Decisión de cierre de sesión:**
- No activar `MT_GOOGLE_MEET_EXECUTION_ENABLED` hoy.
- El siguiente paso es crear una cita Meet con `status='confirmed'` en producción, ejecutar el dry-run nuevamente y verificar `scanned>0`, `resolved>0` antes de proceder al backfill real y al flag.

**Próximo paso exacto para mañana:**
1. Crear/confirmar cita virtual con Meet link (`accept_dates` o equivalente) en producción → `calendar_events.status='confirmed'`
2. `php scripts/google_meet_backfill_space_names.php --dry-run --limit=10` → verificar `scanned>0`, `resolved>0`
3. Si ok: `php scripts/google_meet_backfill_space_names.php --limit=50`
4. Solo después: evaluar activar `MT_GOOGLE_MEET_EXECUTION_ENABLED=1`

---

## 2026-04-16 — ops(google-meet-execution): plan de activación canonizado; código Fase 1 completo; bloqueante gordo = reconexión OAuth con scope Meet

**Outcome**
- Código de Fase 1 completo: migración, consumer, backfill, schema guard, correlación 3-path, feature flag.
- Plan operacional de activación producido y canonizado en `14_CALENDAR_MEET_INTEGRATION_MODEL.md` (nota operativa 2026-04-16) y `12_EXECUTION_BACKLOG.md`.
- `PROJECT_STATE.md` frente 1 actualizado a "código completo, flag OFF, pendiente activación".

**Decision**
- El flag `MT_GOOGLE_MEET_EXECUTION_ENABLED` permanece OFF hasta completar la cadena de activación.
- La cadena de activación tiene orden estricto de dependencia:
  1. migración aplicada en todos los entornos
  2. SQL backfill `google_meet_space_code` desde `google_meet_url` para eventos existentes
  3. reconexión OAuth `medtravelusa@gmail.com` con scope `meetings.space.readonly` ← **sin esto los pasos 4-8 son no-op**
  4. dry-run backfill `google_meet_space_name`
  5. backfill real
  6. env vars Pub/Sub (`GOOGLE_CLOUD_PROJECT_ID`, `GOOGLE_PUBSUB_SUBSCRIPTION`, `GOOGLE_PUBSUB_SERVICE_ACCOUNT_JSON_PATH`)
  7. consumer smoke (`MT_GOOGLE_MEET_EXECUTION_ENABLED=1 php scripts/google_meet_consumer.php --limit=1`)
  8. flag ON en producción

**Por qué el OAuth es el bloqueante gordo**
- La conexión existente de `medtravelusa@gmail.com` (`admin_google_calendar_connections`) fue autorizada en Fase 1 solo con `https://www.googleapis.com/auth/calendar`.
- `google_meet_execution_connection_has_space_read_scope()` exige uno de: `meetings.space.readonly`, `meetings.space.created`, `meetings.space.settings`. Sin ninguno de estos, `google_meet_execution_fetch_space_name()` devuelve `reason=scope_not_granted` para cada fila. El backfill reporta `unresolved=N`, `resolved=0`. La correlación por path 3 (el más robusto para eventos sin suscripción activa) queda vacía.

**Hallazgo local**
- La BD `medtravel_rebuild_20260415` local no tiene la migración `2026_04_16_google_meet_execution_phase1.sql` aplicada. `calendar_events` carece de las 10 columnas Meet y no existen las tablas `google_meet_event_log` ni `google_meet_subscriptions`. `admin_google_calendar_connections` tiene 0 filas en local (datos de producción no replicados).

---

## 2026-04-16 — canon(google-meet-execution): se formaliza capa futura de evidencia real de ejecución Meet, separada de agenda y lifecycle clínico

**Outcome**
- Se canoniza una propuesta de Fase 2 para detectar si una reunión Google Meet realmente inició o terminó.
- La propuesta se incorpora al canon y al estado vivo del proyecto sin abrir implementación todavía.
- Se confirma el estado actual del runtime:
  - `calendar_events` solo persiste coordinación de agenda y referencias externas (`status`, `google_event_id`, `google_meet_url`, `organizer_email`, `appointment_mode`)
  - `booking_request_items.item_status` sigue siendo el lifecycle clínico / operativo
  - no existe código en repo para Google Workspace Events, Pub/Sub ni consumo de eventos Meet

**Decision**
- Se crea una tercera capa conceptual, distinta de agenda y distinta del lifecycle clínico: **evidencia real de ejecución Meet**.
- La separación canónica queda así:
  - agenda / coordinación: `calendar_events`
  - lifecycle clínico: `booking_request_items.item_status`
  - evidencia técnica de ejecución real de reunión virtual: capa futura anclada en `calendar_events.id`
- La implementación futura mínima debe seguir este flujo:
  1. cita virtual aceptada (`calendar_events.status=confirmed`)
  2. resolver `spaces/{space}` desde `meetingCode`
  3. crear suscripción Google Workspace Events por espacio Meet
  4. consumir `google.workspace.meet.conference.v2.started` / `ended` desde Pub/Sub
  5. llamar `conferenceRecords.get` y persistir `startTime`, `endTime`, `space`, `conferenceRecord`
- La persistencia recomendada queda canonizada como:
  - snapshot mínimo en `calendar_events`
  - tabla append-only de eventos Meet
  - tabla de suscripciones Workspace Events
- La Fase 1 de este frente no puede:
  - cambiar `booking_request_items.item_status` por arrastre
  - redefinir la UI clínica
  - disparar comisiones automáticamente
- La lógica comercial futura podrá leer esta evidencia, pero solo después de estabilizarla en producción.
- Riesgos canonizados:
  - el OAuth actual cubre Calendar, no Meet / Workspace Events
  - las suscripciones expiran y requieren renovación / reactivación
  - `meetingCode` no es identificador estable; debe persistirse `spaces/{space}`
  - un `conference ended` prueba ocurrencia técnica de reunión, no confirmación clínica

---

## 2026-04-16 — smoke(google-calendar): smoke E2E completo validado; create-on-accept correcto; regresión revertida; attendees ok

**Outcome**
- Smoke Google Calendar / Meet ejecutado en local de punta a punta sobre `item_id=2`, BD `medtravel_rebuild_20260415`.
- Commits relevantes del frente: `6a29500`, `8d8e3d0`, `b25e42b`, `d05d451`, `0d5eab4`.

**Flujo validado (Path A):**

| Paso | Actor | Resultado |
|------|-------|-----------|
| `provider_propose_change` | Provider admin (user 16) | `ok: true`, `calendar_event_id=22`, `item_status=provider_proposed_change`, `google_event_id=NULL` (correcto — solo registro local) |
| `accept_dates` | Paciente (user 18) | Evento real creado en Google Calendar (`google_event_id=6fp1cbakt9heskkia2mucl2pk4`), Google Meet real (`https://meet.google.com/cue-kdyv-tvg`), `item_status=appointment_confirmed` |
| `cancel_meeting` | Paciente (user 18) | `calendar_events.status=cancelled`, `item_status=appointment_requested_change`, Google API confirma `status=cancelled` |

**Comportamiento validado del producto:**
- El evento real en Google Calendar se crea al **aceptar el paciente** (`accept_dates`), no al proponer (`provider_propose_change`).
- La propuesta solo crea el registro local en `calendar_events` con `google_event_id=NULL`. Ese es el comportamiento correcto.
- La cancelación deja el ítem en `appointment_requested_change`; el provider puede reproponer sin cerrar el caso.

**Regresión detectada, auditada y revertida:**
- `e00a316`: creaba el evento real en Google Calendar al proponer (en `my_booking_create_proposed_meeting_event()`). Esto era incorrecto (doble evento al aceptar, attendees faltantes en la propuesta).
- Revertido con `b25e42b` (`git revert e00a316`).
- El RFC3339 fix (`str_replace(' ', 'T', ...)`) fue incluido en ese mismo commit y también quedó revertido. Pendiente reaplicar de forma atómica si se decide necesario (ver backlog menor, punto 4 abajo).

**Attendees validados:**
- Con `assigned_staff_id=1` (`linked_user_id=17`, `colfecarga@gmail.com`): el staff asignado entra correctamente como attendee clínico en el evento Google.
- Attendees enviados a la API: `[bolsacarga@gmail.com, colfecarga@gmail.com]`.
- `medtravelusa@gmail.com` (provider / proposal sender) excluido por dedup: coincide con `organizerConnectionEmail` de la cuenta Google OAuth del admin.

**Hallazgo cerrado como artefacto local:**
- En la sesión anterior, el staff no recibió invitación porque `bri.assigned_staff_id=NULL` en los datos del smoke. No era bug de código sino dato de prueba incompleto.
- Con `assigned_staff_id` correctamente poblado, la lógica de resolución `linked_user_id → usuarios.email` funciona según diseño.

**Decision**
- El flujo correcto de creación del evento Google Calendar es: propuesta → registro local únicamente; aceptación del paciente → creación del evento real.
- Cualquier cambio futuro que mueva la creación del evento al momento de la propuesta es una regresión de producto y debe registrarse en este changelog antes de implementarse.
- Backlog menor (no crítico, sin frente técnico activo): si un `provider_medical_staff` tiene `pms.email` directo pero `linked_user_id=NULL`, ese staff nunca entra como attendee porque el código resuelve por `usuarios.email` vía `linked_user_id`. Evaluar ampliar fallback a `pms.email` cuando `linked_user_id=0` si hay casos reales en producción con esa configuración.

---

## 2026-04-16 — smoke(state-machine): dos paths separados confirmados; puente provider_confirmed→appointment_proposed no implementado

**Outcome**
- Smoke E2E local ejecutado sobre item_id=2, BD `medtravel_rebuild_20260415`.
- Se confirma en código que `admin/ajax/my_booking_requests.php` implementa hoy **dos paths separados y mutuamente excluyentes**, no un flujo lineal único.

**Path A — Appointment-first (validado):**
`pending_provider → provider_proposed_change → appointment_proposed → appointment_confirmed → appointment_requested_change`
- Flujo iniciado por `provider_propose_change` desde `pending_provider`.
- Los estados `appointment_proposed`, `appointment_confirmed`, `appointment_requested_change`, `appointment_cancelled` los escribe `google_calendar_sync_item_status_for_transition` (`inc/google_calendar.php:219`).
- Estos cuatro estados **no están en `$canonicalItemStatuses`** del handler principal (`my_booking_requests.php:2056`), por lo que desde ahí no se puede avanzar al ciclo clínico.

**Path B — Clinical (validado `provider_confirmed` ✅; fases clínicas pendientes de smoke en prod):**
`pending_provider → provider_confirmed → virtual_assessment_pending → virtual_assessment_done → treatment_plan_agreed → procedure_scheduled → treatment_completed → case_closed`

**Gap de código confirmado:**
- `$allowedCurrentStatuses` de `provider_proposed_change` acepta: `['pending_provider', 'provider_proposed_change', 'awaiting_client']`.
- `provider_confirmed` **no está en esa lista**. La transición `provider_confirmed → appointment_proposed` retorna `transition_not_allowed_from_provider_confirmed` (HTTP 409).

**Correcciones documentales aplicadas en esta sesión (2026-04-16):**
- `AGENTS.md`: reemplazado happy path lineal por descripción de dos paths separados con nota de decisión pendiente.
- `docs/canonical/10_PRODUCT_MODEL.md`: agregada sección "Dos paths de estado implementados hoy" después de la compatibilidad legacy.
- `docs/canonical/12_EXECUTION_BACKLOG.md`: reemplazado único recorrido de smoke por recorridos separados Path A y Path B, con nota de puente no implementado.
- `docs/canonical/16_ACTORS_AND_DOMAINS.md`: sección 4 (smoke) actualizada para reflejar dos sesiones de provider distintas y queries corregidas.

**Decision**
- Los dos paths son hoy mutuamente excluyentes en código.
- El canon anterior describía un happy path lineal (`provider_confirmed → appointment_proposed → … → virtual_assessment_pending`) que no existe en ningún bloque del handler.
- **Decisión de producto pendiente: ¿los paths deben permanecer alternativos o deben ser encadenables?**
  - Si **alternativos**: el canon queda correcto como está. No se toca código.
  - Si **encadenables**: se requieren 3 cambios quirúrgicos en `admin/ajax/my_booking_requests.php`:
    1. Agregar `appointment_proposed`, `appointment_confirmed`, `appointment_requested_change`, `appointment_cancelled` al `$canonicalItemStatuses` (línea 2056).
    2. Agregar `provider_confirmed`, `client_accepted` a `$allowedCurrentStatuses` de `provider_proposed_change` (línea 3917).
    3. Agregar `appointment_confirmed` como estado origen válido para `virtual_assessment_pending` (línea 3922).
  - Esta decisión debe registrarse aquí antes de abrir un frente técnico.

---

## 2026-04-16 — canon(actors): corrección de premisa — Admin MedTravel es gestor de plataforma, no actor clínico

**Outcome**
- Se detecta y corrige una premisa incorrecta en el contexto operativo: el admin MedTravel no es el actor responsable de proponer citas ni de avanzar el lifecycle clínico de los ítems. Esa responsabilidad pertenece al provider admin y al staff asignado.
- Se crea `docs/canonical/16_ACTORS_AND_DOMAINS.md` como documento centralizado de actores, dominios y fronteras.
- Se actualiza `docs/canonical/10_PRODUCT_MODEL.md` con:
  - tabla de actores por dominio con función, herramienta y frontera explícita
  - acciones oficiales del ítem con actor responsable asignado a cada una
  - estados visibles completos incluyendo los 7 del ciclo clínico 2026-04-15
  - reglas canónicas de separación de dominios
- Se actualiza `AGENTS.md` con función operativa real por actor en la sección RBAC.
- Se actualiza `docs/canonical/14_CALENDAR_MEET_INTEGRATION_MODEL.md` con nota sobre los dos paths de propuesta de cita.

**Decision**
- El **admin MedTravel** es gestor de la plataforma: alta de providers, catálogo, monitoreo, comisiones, configuración técnica. Puede supervisar, pero no es el actor responsable de avanzar el ciclo clínico.
- El **actor clínico responsable** del lifecycle (desde `virtual_assessment_pending` en adelante) es el **provider admin o el staff asignado**.
- El `ROLE_ADMINISTRATIVE` (PatientCare) opera exclusivamente en dominio CARE y booking asistido. Sin acceso a ítems médicos ni al lifecycle clínico.
- El **staff médico es el owner operativo del ítem una vez asignado**. Antes de la asignación, el owner es el provider admin.
- Esta corrección debe aplicarse al ejecutar el smoke test E2E: las sesiones de propuesta de cita y avance clínico deben usar credenciales de provider admin o staff, no de admin MedTravel.

---

## 2026-04-15 — feat(lifecycle): ciclo médico completo en panel admin

**Outcome**
- `admin/ajax/my_booking_requests.php` y `admin/js/my_booking_requests.js` implementan el ciclo clínico completo: `provider_confirmed → virtual_assessment_pending → virtual_assessment_done → treatment_plan_agreed → procedure_scheduled → treatment_completed → case_closed`.
- Acciones nuevas: "Iniciar valoración virtual", "Registrar plan clínico acordado" (Summernote, modal-lg), "Programar procedimiento presencial", "Cerrar caso".
- Tab "Atención clínica" en modal detalle con guía operativa colapsable (Bootstrap data-toggle/Metronic mt-panel) y matriz de botones por estado normalizado.
- Reversas controladas: `$isActualReversal` discrimina avance vs reversa real. Solo reversas reales exigen `reversal_reason`. Fix específico: `virtual_assessment_pending` desde `provider_confirmed` / `client_accepted` / `awaiting_client` es avance, no reversa.

**Decision**
- El pipeline de estados vive en `booking_request_items.item_status`. Los estados nuevos son formales y permanentes; no son alias de estados existentes.
- `normalizeItemStatus()` en JS no mapea los estados clínicos a otros — cada uno tiene su propio label.
- `$isActualReversal` es la regla canónica para discriminar avance vs reversa en `update_item_status`. Toda nueva transición que pueda parecer reversa por estar en `$reversalTargets` debe evaluarse explícitamente.

---

## 2026-04-15 — fix(sql): timeline_from / timeline_to como prerequisito de producción

**Outcome**
- `timeline_from` y `timeline_to` son columnas reales de `booking_requests` usadas por la acción `update_timeline_window` del modal detalle admin.
- El runtime ya las escribe y lee. No existe migración versionada en `sql/` todavía.

**Decision**
- Antes de activar `update_timeline_window` en servidor de producción, ejecutar migración que agregue `timeline_from DATE NULL` y `timeline_to DATE NULL` a `booking_requests` si no existen.
- Crear migración `sql/2026_04_15_booking_requests_timeline_columns.sql` (idempotente) como próxima acción de infra.

---

## 2026-04-15 — fix(documents): scope robusto por booking_request_id en modal detalle

**Outcome**
- `admin/ajax/my_booking_requests.php` (`get_detail`): removida rama `WHERE client_id = ?` que usaba `booking_requests.client_user_id` (`usuarios.id`) como si fuera `client_documents.client_id` (`clientes.id`) — ID spaces distintos, filtrado incorrecto.
- Ahora siempre `WHERE 1=1 AND shared_with_provider = 1 AND booking_request_id = ?`, mismo patrón que `admin/ajax/inbox.php`.

**Decision**
- `booking_request_id` es el anchor canónico de scope para queries de documentos en todas las superficies admin/provider. `client_id` / `client_user_id` son datos de dueño, no de scope de caso.
- Ver `docs/canonical/15_DOCUMENTS_MODEL.md` para modelo completo.

---

## 2026-04-15 — feat(ui): visor de documentos alineado con app_inbox en modal detalle

**Outcome**
- `admin/my_booking_requests.php`: CSS + HTML modal `#adminDocViewerModal` idéntico al de `admin/app_inbox.php`.
- `admin/js/my_booking_requests.js`: helpers `dv*` + `openDocViewer()` replicados desde `app_inbox.js`. Preview: PDF vía iframe, imagen vía `<img>`, fallback con botones abrir/descargar. Endpoint de preview: `/admin/ajax/preview_medical_document.php?doc_id=`.
- Tabla de documentos: nombre clickeable como `<button .mt-doc-preview-btn>` + columna descarga separada.

**Decision**
- El visor de documentos en admin es una capacidad transversal. La implementación canónica vive en `app_inbox`. Otros módulos deben replicar el patrón (modal + helpers + openDocViewer), no inventar uno diferente.

---

## 2026-04-15 — fix(ui): labels amigables en español para estados lifecycle en admin

**Outcome**
- `admin/js/my_booking_requests.js`: `genericStatusLabelEs` completada con los 5 estados nuevos (`virtual_assessment_pending`, `virtual_assessment_done`, `treatment_plan_agreed`, `procedure_scheduled`, `case_closed`) más estados de cita (`appointment_proposed`, `appointment_confirmed`, `appointment_requested_change`, `appointment_cancelled`, `new`).
- `renderStatusBadge` actualizado con colores: warning → `virtual_assessment_pending`; success → `virtual_assessment_done`, `treatment_plan_agreed`, `case_closed`; info → `procedure_scheduled`.
- Mapa JS ahora espeja completamente al mapa PHP `generic_status_label_es` (L513-554).

**Decision**
- Ningún estado raw debe mostrarse visible en el admin sin pasar por `genericStatusLabelEs`. El mapa JS debe mantenerse sincronizado con el PHP. Al agregar un nuevo estado al pipeline, actualizar ambos simultáneamente.

---

## 2026-04-15 — docs(canon): client_documents canonizado en 15_DOCUMENTS_MODEL.md

**Outcome**
- `docs/canonical/15_DOCUMENTS_MODEL.md` creado: modelo de tabla, scope canónico, reglas de visibilidad por actor, superficies de display y upload, diferencias por rol, deuda heredada DOC-D1 a DOC-D7.
- `docs/canonical/00_INDEX.md` actualizado con puntero al nuevo archivo.

**Decision**
- `client_documents` es tabla operativa sin FK forzadas y con dos ID spaces de dueño coexistiendo (`client_id` → `clientes.id`, `client_user_id` → `usuarios.id`). Esta es deuda técnica explícita, no un diseño intencional.
- El modelo canónico declara `booking_request_id` como anchor obligatorio. Toda query de documentos en admin/provider debe incluirlo.

---

## 2026-04-15 — feat(providers): archive/restore reversible de proveedores

**Outcome**
- `admin/ajax/providers.php` implementa `provider_archive` / `provider_restore` con schema guards runtime para columnas `is_deleted`, `deleted_at`, `deleted_by`, `archive_reason`, `restored_at`, `restored_by`.
- `admin/providers.php` y `admin/js/providers.js` exponen el flujo en UI.
- Migración `sql/2026_04_15_providers_archive_restore_columns.sql` agrega columnas de forma idempotente.

**Decision**
- Proveedores nunca eliminados físicamente. Ciclo: `activo → archivado → restaurado`.
- Schema guards permiten despliegue antes de correr migración sin romper runtime.
- Migración debe ejecutarse en producción antes de activar acciones de archivado.

---

## 2026-04-15 — fix(realtime): payload incompleto causaba 400 bad_request en /realtime/internal/inbox-message

**Outcome**
- Servidor Node.js en `medtravel.com.co` rechazaba payload `{thread_id, message_id}` con `{"ok":false,"error":"bad_request"}` (HTTP 400).
- Contrato real del endpoint: `{thread_id, message_id, sender_role, created_at}` (ISO 8601).
- Fix en `inc/realtime.php` (logs diagnóstico enriquecidos con `thread_id`, `message_id`, `url`, `body[:200]`, `curl_errno`) y todos los callers: `admin/ajax/inbox.php` (3 bloques), `admin/ajax/my_booking_requests.php`.
- Emit al hilo `CARE:{request_id}` añadido en `my_booking_requests.php` post-propuesta, sin INSERT duplicado, para notificación live al paciente.

**Decision**
- Todo caller de `mt_realtime_emit_inbox_message()` debe incluir `sender_role` y `created_at`. Sin ellos el servidor rechaza.
- El emit CARE es solo señalización socket; el browser hace fetch incremental desde `client/ajax/inbox.php`.

---

## 2026-04-15 — fix(config): blindar conexion.php contra APP_ENV=dev en hosts remotos

**Outcome**
- `admin/include/conexion.php` fuerza `APP_ENV=prod` si el host no es local y `APP_ENV` resolvía a `dev` sin `ALLOW_REMOTE_DEV=1` en el entorno.
- Elimina riesgo de exposición de errores o configuraciones de desarrollo en producción por variable residual.

**Decision**
- `APP_ENV=dev` en remoto solo válido con `ALLOW_REMOTE_DEV=1` explícito. No setear en producción.

---

## 2026-04-15 — ops(local-db): se canoniza la realineación del entorno local al dump real del servidor

**Outcome**
- Se confirma que la BD local histórica `medtravel` estaba estructuralmente desalineada para el dominio moderno de `providers`, `provider_users`, `provider_medical_staff`, `provider_service_offers` y tablas relacionadas.
- Se reconstruye una nueva BD local `medtravel_rebuild_20260415` a partir de un dump real exportado del servidor `medtravelcom_medtravel`.
- El import local en MySQL 5.7 requirió una copia temporal compatible del dump para eliminar únicamente cláusulas `DEFAULT` incompatibles en columnas `TINYTEXT` / `TEXT` / `MEDIUMTEXT` / `LONGTEXT` / `BLOB` / `JSON` / `GEOMETRY`.
- El dump original del servidor no se modificó.
- El runtime local queda apuntando a la nueva BD reconstruida mediante `.env` local no versionado.

**Decision**
- La referencia local válida para desarrollo y validación del dominio moderno provider/staff/services deja de ser `medtravel`.
- La base local válida pasa a ser la reconstruida desde dump real: `medtravel_rebuild_20260415`.
- Las validaciones locales futuras del dominio provider deben ejecutarse contra la BD reconstruida.
- `.env` es override local del workspace y no debe versionarse.
- La BD `medtravel` se conserva solo como backup/referencia legacy y no debe usarse como sustituto del dominio moderno.

## 2026-04-14 — feat(provider-ui): onboarding admin y verificación médica se compactan sin duplicar dominio documental

**Outcome**
- `providers.php` deja de presentar un modal plano y se reordena como onboarding administrativo entendible por bloques A–E: prestador, owner/admin inicial, categorías, servicios iniciales y compliance documental.
- El bloque de archivos no abre un circuito paralelo; explicita que la evidencia documental vive canónicamente en `provider_verification.php` y enlaza a esa consola.
- `provider_verification.php` deja de depender de una tabla ancha con demasiadas columnas planas y pasa a una grilla compacta con resumen visual superior de prestadores, verificados, pendientes/revisión y trust promedio.
- La tabla resume el contenido en columnas semánticas (`prestador y contacto`, `estado y trust`, `checklist documental`, `última verificación`, `acciones`) para evitar pérdida de contexto en anchos medios del panel admin.

**Decision**
- El onboarding médico administrativo debe ser claro para operación interna de MedTravel, pero no debe duplicar la consola de compliance documental del dominio médico.
- `provider_verification.php` es la única superficie canónica para checklist, evidencia documental y trust score del prestador médico.
- Cuando una tabla administrativa del panel pierda legibilidad por ancho, la corrección preferida es compactar semánticamente la información y usar componentes del template antes que delegar la experiencia a scroll horizontal como solución principal.

---

## 2026-04-14 — docs(governance): se endurece la jerarquía operativa y se desactiva drift multi-modelo

**Outcome**
- `CLAUDE.md` deja de duplicar contexto operativo y queda reducido a shim de compatibilidad.
- `12_EXECUTION_BACKLOG.md` se reafirma explícitamente como backlog canónico vigente.
- `NEXT_STEPS_SERVICES.md` y `DEV_CONTEXT.md` quedan marcados como auxiliares para soporte histórico / operativo y no como fuentes principales.
- `00_INDEX.md` y `02_DOC_MAP.md` explicitan la jerarquía entre contexto IA, estado vivo, canon y auxiliares.

**Decision**
- La continuidad operativa vive solo en `AGENTS.md`, `PROJECT_STATE.md` y `docs/canonical/*`.
- Los archivos específicos de proveedor o modelo solo pueden existir como compatibilidad de baja entropía; no deben duplicar canon, estado ni backlog.
- Los documentos auxiliares pueden conservar detalle histórico o evidencia, pero deben declararse explícitamente no canónicos.

---

## 2026-04-14 — fix(calendar): OAuth Fase 1 se corrige por scope real y reconexión limpia

**Outcome**
- El organizer técnico validado para Google Calendar / Meet Fase 1 es la cuenta Google del admin autenticado en MedTravel.
- Paciente y provider / médico / staff participan como invitados y no conectan sus cuentas Google en este flujo base.
- El hallazgo real de runtime fue doble: conexiones que caían en `invalid_grant` y conexiones que devolvían permisos insuficientes para crear eventos.
- El fix operativo validado usa el scope real `https://www.googleapis.com/auth/calendar`, fuerza `include_granted_scopes=false` en el authorize URL y exige reconexión limpia de la cuenta Google del admin cuando la conexión quedó revocada o concedida con permisos incompletos.

**Decision**
- Fase 1 no usa OAuth de paciente ni de provider / staff.
- La cuenta Google relevante en Fase 1 es exclusivamente la del admin organizer autenticado.
- Si aparece `invalid_grant` o un error de permisos insuficientes para crear eventos, la política operativa correcta es desconectar y reconectar limpiamente la cuenta Google del admin; no reutilizar una conexión degradada.

---

## 2026-04-14 — fix(calendar,inbox): cancelar reunión no cancela el caso y debe dejar reprogramación abierta

**Outcome**
- Se valida el hallazgo real de runtime: cancelar una reunión estaba cerrando de facto la posibilidad de nueva propuesta / reprogramación para provider en Inbox.
- El fix real deja de tratar la cancelación de reunión como cancelación de negocio del caso.
- Al cancelar la reunión, el item vuelve a `appointment_requested_change` y queda otra vez disponible para reprogramación.
- En Inbox operativo admin/provider, el mapeo visible correcto es `appointment_cancelled` -> `provider_proposed_change`.

**Decision**
- Cancelar una reunión no equivale a cancelar el caso ni el item clínico.
- La cancelación de agenda debe reabrir la coordinación cuando el caso siga activo.
- El Inbox operativo debe privilegiar la semántica de reprogramación en este escenario y no una semántica terminal de cancelación de negocio.

---

## 2026-04-10 — feat(inbox): preview modal admin, confirmacion de reunion desde cliente, rendering estructurado

**Commit**: `4c9a142`

## 2026-04-17 — fix(offers): forzar contexto provider+service en servidor para evitar datasets ambiguos

**Outcome**
- `offers.php` ahora valida y aplica filtro server-side cuando se provee `service_id` + `provider_id`. Si `service_id` llega sin `provider_id`, la página muestra un estado controlado (mensaje de contexto incompleto) y no renderiza un dataset ambiguo mezclando proveedores.
- `admin/js/service_catalog.js` genera URLs públicas de campaña con `provider_id` + `service_id` (ej.: `offers.php?provider_id=2&service_id=9`).
- Cabeceras/hero/contadores se alinean al mismo contexto server-side para evitar discrepancias entre dataset cargado y UI.

**Smoke validado (runtime real)**
- BD local usada: `medtravel_rebuild_20260415` vía socket MAMP.
- Pruebas realizadas: `offers.php?service_id=9` (sin provider) → estado controlado, sin cards mezcladas.
- `offers.php?provider_id=2&service_id=9` → contexto coherente, cards y CTAs pertenecen al `provider_id=2`.
- `offers.php?provider_id=2` → contexto coherente, listado por proveedor correcto.
- `offers.php?provider_id=2&service_id=999999` → degradación segura a provider-only (sin crash, sin mezcla).

**Decision / Canon**
- `offers.php` no debe renderizar datasets ambiguos usando solo `service_id`. La URL pública canónica para un contexto específico es `provider_id + service_id`.
- Si falta `provider_id` y viene `service_id`, la página debe mostrar un estado controlado (sin cards ambiguas) y orientar al usuario a seleccionar un proveedor.

**Riesgo residual**
- UX: `service_id` inválido o no existente degrada a contexto provider-only. Es seguro técnicamente pero deja una decisión de UX pendiente: elegir entre mostrar un mensaje explícito de error/404 de servicio vs. degradación silenciosa a proveedor-only. Registrar para decisión de producto.

**Outcome**
- Admin quick-reply ya no se envia directamente desde el listado. El click en `.admin-quick-reply` abre `#adminQuickReplyPreviewModal` para revision antes de enviar.
- El cliente puede confirmar una reunion propuesta directamente desde `client/app_inbox.php`: el sistema invoca `google_calendar_create_event()` o el flujo de confirmacion interna segun el modo del evento.
- Cache-busting por `filemtime()` aplicado a los JS del inbox en admin y cliente.
- Rendering de tokens estructurados (`[REQUEST_INFO]`, `[PROPOSE_QUOTE]`, `[PROPOSAL_RESPONSE]`) mejorado en ambos portales; `parseReplyTokenAndNote()` extrae token y nota por separado.

**Decision**
- El preview modal es la politica operativa del admin; el envio sin preview ya no ocurre como accion directa.
- La confirmacion de reunion desde inbox es coordinacion: Inbox es el punto de entrada, Calendar es la persistencia. El canon `Inbox = conversacion / Calendar = agenda` se mantiene; confirmar una reunion desde inbox es una transicion de coordinacion explicitamente permitida.
- `final_accept_and_pay` y confirmacion de reunion disparan sync de `item_status`; esto responde parcialmente al pendiente de definir que acciones del inbox sincronizan estado.

---

## 2026-04-10 — fix(calendar): sync atomico de item_status en cancelacion restaurado

**Commit**: `fd495be`

**Outcome**
- `google_calendar_sync_item_status_from_event_status()` no ejecutaba el sync cuando el evento transitaba a `cancelled`; `item_status` del item quedaba sin actualizar aunque `calendar_events` si se persistia.
- El fix asegura atomicidad en la transicion `cancelled` → `appointment_cancelled`.

**Decision**
- La canonizacion de 2026-04-02 declaraba esta transicion como implementada; en la practica no lo estaba. Este commit cierra la deuda real.
- La sincronizacion minima item ↔ cita ahora cubre las cuatro transiciones canónicas sin excepcion: `proposed/scheduled` → `appointment_proposed`, `confirmed` → `appointment_confirmed`, `cancelled` → `appointment_cancelled`, reschedule → `appointment_requested_change`.

---

## 2026-04-10 — fix(login): lookup de identidad por ranking en escenario multi-candidato

**Commit**: `22a5230`

**Outcome**
- `login.php` resuelve identidad por ranking cuando un email tiene multiples candidatos en `usuarios`. Orden de prioridad: `username` exacto > `usrlogin` exacto > `email` exacto. `LIMIT 1` sin orden explicito ya no se usa.
- `admin/include/log.php` alineado para consistencia en el registro de sesion.

**Decision**
- El canon de identidad ya excluia ownership inferido por `LIMIT 1` y heuristicas ambiguas. Este fix extiende esa politica al punto de autenticacion.
- Mientras convivan tablas legacy y nuevas en `usuarios`, el lookup debe ser determinista y priorizar el candidato de mayor relevancia, no el primero por orden de insercion.

---

## 2026-04-10 — fix(status): normalizacion de appointment_* y pending_admin completada en flujos restantes

**Commit**: `c828d6b`

**Outcome**
- La normalizacion `pending_admin` / `pending_review` → `pending_provider` declarada en 2026-04-02 no cubria todos los flujos de booking.
- Los estados `appointment_*` tampoco se normalizaban en algunas rutas del inbox.
- Este commit aplica la normalizacion en los flujos restantes identificados.

**Decision**
- El canon de estados del item y su normalizacion queda ahora completo en todas las rutas conocidas del runtime.
- Los estados legacy `pending_admin` / `pending_review` no deben introducirse en nuevas rutas; deben normalizarse en el punto de lectura si aparecen.

---

## 2026-04-09 — Inbox ITEM del staff asignado no hereda automaticamente el gate comercial del provider

**Outcome**
- Se aclara la diferencia entre gating comercial del provider y acceso operativo del staff asignado en `Inbox` ITEM.
- El owner/admin del provider conserva su gate comercial actual cuando aplica fee/comision.
- El staff vinculado asignado puede acceder conversacionalmente al hilo ITEM sin heredar ese gate solo si el item pertenece al mismo `provider_id`, `assigned_staff_id` coincide con la sesion actual y el estado del item es `provider_confirmed`, `client_accepted`, `treatment_completed` o `post_treatment_follow_up`.

**Decision**
- Este bypass es estrictamente operativo para `Inbox` ITEM dentro de scope valido; no abre acceso lateral a otros providers ni a items no asignados.
- La decision no cambia por arrastre agenda ni `Calendar`; la semantica canónica sigue siendo `Inbox = conversacion` y `Calendar = agenda`.

## 2026-04-03 — Cierre cronológico del frente comercial/SEO público (Fase 0 a Fase 4)

**Commits**: `6d4db96`, `71166d4ceaa073318aecb6f6cacdceb3a0d10e69`, `754b29666f01ddf82457157dd5df633044dd4edb`, `eaa5364`, `bbbce46`, `3f38d03`

**Outcome**
- Se consolida la evolución comercial/SEO pública por fases, sin migración de template y sin refactor amplio.
- Se deja operativa la malla de páginas comerciales clave: home, booking, services, specialists, faq, how-it-works y landings de intención.
- Se formaliza la diferenciación semántica de landings para reducir riesgo de canibalización:
  - `medical-travel-colombia.php` (intención país)
  - `medical-travel-armenia-colombia.php` (intención local)
  - `for-us-patients.php` (intención audiencia)

**Decision**
- El frente comercial/SEO público queda cerrado en su fase de implementación en código con microajustes de QA y conversión.
- Se mantiene la frontera canónica: MedTravel coordina/intermedia y no presta acto médico directo.

**SQL impact**
- Sin cambios SQL en este frente.

## 2026-04-03 — Microfixes post-deploy de funnel y XML

**Commit**: `e9466ad`

**Outcome**
- Se corrige ancla de conversión en booking (`#booking-section`) para consistencia de CTA internos.
- Se corrige robustez de salida XML de `sitemap.php` para evitar ruido previo y errores de parser.

**Decision**
- Los fixes se registran como hardening post-despliegue, no como nueva fase funcional.

**SQL impact**
- Sin cambios SQL.

## 2026-04-03 — Publicación técnica robusta de `robots.txt` y `sitemap.xml`

**Commit**: `6362c06`

**Outcome**
- Se formaliza publicación por rewrite:
  - `/robots.txt` → `robots.php`
  - `/sitemap.xml` → `sitemap.php`
- `robots.php` expone `Sitemap: https://medtravel.com.co/sitemap.xml`.
- Se prioriza `sitemap.xml` como enlace público en footer.

**Decision**
- La publicación técnica SEO queda canonizada como mecanismo operativo robusto para entorno real compartido.
- La validación externa confirma 200 para UA navegador y bots principales probados; se registra comportamiento WAF con 403 para algunos agentes no-browser/terceros.

**SQL impact**
- Sin cambios SQL.

## 2026-04-02 — Inbox cliente: filtros de entrada canonizados por ambito de conversacion

**Outcome**
- Se corrige una ambiguedad de UX donde `thread_type=CARE` podia mostrar tambien hilos `ITEM` en la lista lateral.
- Se establece una entrada explicita por 4 comportamientos en `client/request_detail.php`: `ALL`, `CARE`, `ITEM` medico y `ITEM` complementario.
- Se refuerza la separacion operativa entre coordinacion MedTravel y conversaciones con providers por item.

**Decision**
- `thread_type=CARE` debe mostrar solo hilos de coordinacion MedTravel del `request_id`.
- `thread_type=ITEM` debe mostrar solo hilos ITEM del `request_id`; si llega `item_group`, filtrar por `medical` o `complementary`.
- `thread_type=ALL` debe mostrar todos los hilos del `request_id`.
- La seleccion inicial y la lista visible deben respetar el mismo filtro (no solo preseleccion de hilo).

**Operational effect**
- `client/js/app_inbox.js` aplica filtro de lista por URL (`thread_type`, `request_id`, `item_group`, `thread_id`).
- `client/ajax/inbox.php` expone `item_type` en `list_threads` para habilitar filtro por grupo en frontend.
- Se reduce riesgo de mezcla semantica entre coordinacion y mensajeria medico/complementaria al navegar desde detalle de caso.

## 2026-04-02 — `generadorDocumentos` se mantiene como proyecto auxiliar separado

**Outcome**
- Se registra oficialmente la existencia de `generadorDocumentos` como herramienta satelite disponible para generar documentos HTML formateados de uso editorial, comercial u operativo.
- Se deja explicito que su arquitectura desacoplada (templates/documents/shared/assets/launcher + JSON por documento) esta orientada a reutilizacion y preparada para evolucion con datos dinamicos.
- Se evita documentarlo como capacidad ya integrada al runtime productivo principal de MedTravel.

**Decision**
- `generadorDocumentos` permanece como proyecto aparte por ahora.
- No se incorpora como modulo core ni dependencia del flujo actual de paciente, provider o staff.
- Su integracion futura solo debe evaluarse si una necesidad operativa real del producto exige generacion documental online con datos vivos del sistema.

**Operational effect**
- El canon de producto y arquitectura debe tratar `generadorDocumentos` como capacidad satelite disponible, no como funcionalidad runtime ya desplegada en MedTravel.
- El backlog solo mantiene esta linea como evaluacion opcional condicionada por demanda operativa real.

## 2026-04-02 — Runtime staff reforzado + lifecycle clínico-operativo formalizado

**Commits**: `69e62dc`, `9c05fdb`, `32e2c30`, `87748d4`, `16cac36`

**Outcome**

A. **Implementado — Runtime staff reforzado en superficies asignadas**
- Navegacion diferenciada para staff vinculado con foco en operacion asignada.
- `app_inbox` y `app_calendar` presentan contexto de trabajo asignado para staff.
- `admin/ajax/calendar.php` endurece scope de agenda ITEM por `booking_request_items.assigned_staff_id` en sesion staff vinculada.

B. **Implementado — Regla uniforme de asignacion inicial de staff**
- Booking asistido (`admin/ajax/booking_asistido.php`) se alinea con el booking publico.
- Regla aplicada: autoasignar solo cuando hay exactamente un unico staff elegible; en multiples/ninguno el item queda sin asignar.

C. **Implementado — `appointment_mode` formalizado**
- Se formaliza `calendar_events.appointment_mode` con valores `virtual`, `in_person`, `travel`.
- Admin y patient journey consumen modalidad explicita con fallback de compatibilidad.

D. **Implementado — `treatment_completed` formalizado**
- `booking_request_items.item_status` incorpora `treatment_completed` como estado real.
- Compatibilidad legacy: `completed` se normaliza a `treatment_completed`.

E. **Implementado — `post_treatment_follow_up` formalizado**
- `booking_request_items.item_status` incorpora `post_treatment_follow_up` como continuacion natural posterior a `treatment_completed`.
- Se agrega persistencia opcional de metadata de inicio de seguimiento (`follow_up_started_at`, `follow_up_started_by_user_id`) cuando columnas existen.

**Decision**

- Queda consolidado que el lifecycle operativo/clínico vive en el item; la cita mantiene su dominio de agenda separado.
- Queda consolidado que el runtime staff reforzado ya opera en superficies principales, sin declarar cerrado el RBAC total del rol tecnico `provider_staff`.
- Queda consolidado que paciente mantiene UX simple en ingles sin exponer nomenclatura tecnica de `item_status`.

**Migraciones requeridas antes del smoke integral**

- `sql/2026_04_02_calendar_events_appointment_mode.sql`
- `sql/2026_04_02_booking_request_items_treatment_completed.sql`
- `sql/2026_04_02_booking_request_items_post_treatment_follow_up.sql`

**Pendiente**

- Ejecutar las tres migraciones anteriores en el entorno objetivo.
- Ejecutar smoke test end-to-end integral del bloque staff + lifecycle.
- Cerrar formalizacion total de `provider_staff` y RBAC completo por ownership.
- Definir fase terminal clara para cierre final del lifecycle del item.

## 2026-04-02 — Booking asistido, gate de términos, sincronización item/cita y panel de paciente

**Commits**: `d5f1467`, `7f29902`, `ee33eac`, `fac28a7`, `7f8c60e`, `aa52def`, `fad5bed`, `8e97385`, `59e9093`, `8d2a1bf`, `69f85c3`, `f23b9bf`

**Outcome**

A. **Implementado — Booking asistido por agente (Admin)**
- Se implementa el flujo completo de creacion de casos por agente interno para pacientes captados via WhatsApp, widget de chat, telefono u otro canal offline.
- El agente selecciona canal, busca o crea al paciente y elige servicios y ofertas usando el flujo canónico categoria → servicio → oferta.
- El caso se crea con trazabilidad de origen: `creation_source`, `created_by_agent`, `agent_channel` en `booking_requests`.
- El paciente creado por agente tiene `terms_accepted = 0`; recibe credenciales por email para completar la aceptacion de Terminos en su primer login.
- El agente no puede aceptar los Terminos en nombre del paciente; esa accion es exclusiva del paciente.
- Archivos nuevos: `admin/booking_asistido.php`, `admin/ajax/booking_asistido.php`.
- Migracion: `sql/2026_04_02_agent_assisted_booking.sql`.

B. **Implementado — Gate de aceptacion de Terminos del cliente**
- Se implementa una pagina de aceptacion de Terminos obligatoria para clientes creados por agente.
- `client/terms_gate.php`: pagina con Terminos y Privacidad, requiere aceptacion explicita.
- `client/ajax/accept_terms.php`: registra IP, user_agent, version y timestamp de aceptacion.
- `client/include/include.php`: chequeo session-cached que redirige al gate si `terms_accepted = 0`.
- Nuevas columnas en `usuarios`: `terms_accepted`, `terms_accepted_at`, `terms_version`, `terms_ip`, `terms_user_agent`.
- Backfill aplicado: clientes existentes con bookings previos aceptados quedan exentos.

C. **Implementado — Flujo canónico categoria → servicio → oferta en booking asistido**
- El booking asistido aplica exactamente el mismo flujo de dos pasos del wizard publico.
- Solo aparecen servicios con al menos una oferta activa de un provider activo.
- AJAX `get_offers` filtra por `service_catalog.id` seleccionado.
- Backend valida service_id y cada oferta antes de crear el caso.
- Patron `ab_has_column()` para columnas opcionales tipo `is_deleted` (mirror de `booking/submit.php`).

D. **Implementado — Aviso contextual en login y set_password**
- `login.php` expone endpoint AJAX `login_context` que detecta clientes ROLE_CLIENT con `terms_accepted = 0`.
- Se muestra aviso informativo antes / durante el login.
- `set_password.php` implementa el aviso equivalente.
- Los social links de login se unificaron con el footer comercial via `inc/public_site_links.php`.

E. **Implementado — Sincronizacion minima item ↔ cita**
- `inc/google_calendar.php` recibe tres funciones nuevas para sincronizar `item_status` del item con transiciones del evento de calendario.
- Mapeo: proposed/scheduled → `appointment_proposed`, confirmed → `appointment_confirmed`, cancelled → `appointment_cancelled`, reschedule → `appointment_requested_change`.
- `google_calendar_sync_booking_request_rollups` agrega estados de items hacia el `booking_request`.
- Normalizacion: `pending_admin` / `pending_review` → `pending_provider`.
- Afecta: `admin/ajax/calendar.php`, `client/ajax/calendar.php`, `client/ajax/inbox.php`, `admin/ajax/my_booking_requests.php`.

F. **Implementado — Panel unico simplificado del paciente (Patient Journey Panel)**
- `client/ajax/dashboard_overview.php`: nuevo endpoint que resuelve resumen de caso, items, nombres de servicio y estados visibles del paciente.
- `client/index.php` y `client/js/dashboard.js`: actualizados para el nuevo panel unico.
- La vista del paciente ya no es multi-tab; es un panel lineal de journey simplificado.
- Resolucion del nombre del item desde `provider_service_offers` → `service_catalog` / `medtravel_services_catalog` con guards para columnas opcionales.

G. **Implementado — Traduccion portal del paciente al ingles**
- `client/mis_datos.php` migrado semanticamente a "My Profile".
- Nav links del portal actualizados en `client/include/include.php`.

**Decision operativa aprobada + MVP implementado — Ownership por staff asignado**
- Se aprueba que el staff asignado a un item tiende a ser el owner operativo del item despues de la asignacion.
- **MVP visible implementado (commit `7f67648`)**: `admin/my_booking_requests.php` ya expone ownership operativo visible por item con columna "Responsable", chip de modo y avisos contextuales antes de acciones.
- La formalizacion tecnica completa (rol `provider_staff`, landing propia del staff, scope RBAC duro, auto-asignacion persistida, extension a otras superficies) queda como siguiente frente pendiente.

**Decision**
- Las decisiones A-G quedan registradas como implementadas en produccion a partir de 2026-04-02.
- El patron `has_column()` guard queda canonizado como practica obligatoria para columnas introducidas por migraciones opcionales.
- El flujo categoria → servicio → oferta queda canonizado como el unico flujo valido para toda seleccion de oferta en MedTravel (tanto publico como asistido).
- La separacion entre creacion del caso y aceptacion personal de Terminos queda canonizada como regla legal y operativa infranqueable.
- El ownership operativo por staff asignado queda aprobado como decision de producto; la implementacion tecnica es el proximo frente.

**Pendientes generados**
- [x] `appointment_mode` como atributo estructural del item/cita (DONE 2026-04-02, commit `32e2c30`)
- [x] `treatment_completed` como hito del lifecycle del item (DONE 2026-04-02, commit `87748d4`)
- [x] `post_treatment_follow_up` como hito/tarea del lifecycle del item (DONE 2026-04-02, commit `16cac36`)
- [ ] Rol tecnico `provider_staff` y landing "Mis solicitudes asignadas"
- [ ] Scope RBAC por `assigned_staff_id` para acceso del staff al panel
- [ ] Endurecimiento de admin/inbox donde persiste mezcla semantica entre comunicacion y cambio de estado
- [ ] Politica de reenvio de credenciales para casos asistidos con gate de terminos pendiente

**Transition note**
- El commit `7f67648` (posterior a la canonizacion inicial del dia) cierra el MVP de ownership visible en `my_booking_requests`. Los tres archivos afectados estan commiteados y deben tratarse como funcionalidad cerrada para esta superficie.

---

## 2026-03-23 — Aceptación MedTravel no equivale a consentimiento OAuth Google

**Outcome**
- Se deja explícita la separación entre consentimiento funcional dentro de MedTravel y autorización técnica sobre Google Calendar.
- Se resuelve la ambigüedad potencial entre “aceptar una cita” y “autorizar acceso a Google”.
- Se canoniza que Fase 1 puede operar solo con conexión Google del admin organizer de MedTravel.
- Se deja explícito que paciente y provider / médico / staff no están obligados a conectar Google para que exista la reunión en Fase 1.
- Se deja preparada la evolución futura a conexiones OAuth opcionales por actor sin mezclar identidades ni tokens.

**Decision**
- Aceptar una cita, reunión o slot dentro de MedTravel representa consentimiento funcional del flujo de coordinación, no consentimiento OAuth implícito.
- El acceso técnico a Google Calendar requiere autorización OAuth explícita del actor titular de la cuenta Google correspondiente.
- En Fase 1, el sistema debe poder operar aunque solo el admin de MedTravel tenga conexión Google activa.
- En ese escenario, el admin organizer crea el evento y paciente / provider / médico / staff participan como invitados.
- En fases posteriores, cada actor podrá conectar opcionalmente su propia cuenta Google y MedTravel solo podrá usarla dentro del alcance autorizado por ese actor y por los scopes concedidos.
- La aceptación futura de una propuesta de reunión dentro de MedTravel puede disparar sincronización solo si el actor ya conectó Google; si no, la conexión debe pedirse como paso adicional y nunca inferirse.
- Esta decisión no altera la frontera del producto: Calendar / Meet siguen siendo infraestructura de agenda y coordinación, no atención médica.

**Transition note**
- Esta decisión cierra una regla de producto y arquitectura; no implica implementación runtime automática de OAuth por actor.
- La Fase 1 mantiene organizer admin MedTravel como salida mínima viable.
- La granularidad posterior por actor queda permitida, pero condicionada a conexión explícita y aislamiento técnico por usuario.

**Operational effect**
- El canon de Calendar / Meet debe describir de forma explícita que aceptación MedTravel != OAuth Google.
- El backlog debe separar la operación Fase 1 por admin de la futura conexión opcional por actor.
- Las futuras UX de aceptación, propuesta de cita y sincronización no deben inferir consentimiento técnico desde acciones funcionales internas.

## 2026-03-23 — Integración inicial Google Calendar / Meet arranca desde admin MedTravel por fases

**Outcome**
- Se resuelve el conflicto entre el target de agenda fina por actor tratante y la necesidad operativa de arrancar rápido la integración desde admins de MedTravel.
- Se canoniza que la Fase 1 usa Google Calendar API, genera Google Meet al crear el evento y opera con organizer inicial en una cuenta Google conectada por admin MedTravel.
- Se deja explícito que paciente y provider / médico / staff participan como invitados al evento.
- Se deja asentado que la trazabilidad operativa permanece persistida dentro de MedTravel.
- Se reserva una Fase 2 para Google Meet API avanzada y metadatos extendidos.
- Se cierran reglas mínimas de seguridad para OAuth por admin.

**Decision**
- La integración inicial se implementa por fases.
- Fase 1:
  - Google Calendar API como base.
  - Google Meet link generado al crear el evento.
  - OAuth 2.0 Web Server Flow.
  - conexión por admin autenticado en MedTravel.
  - organizer inicial = cuenta Google del admin MedTravel conectado.
  - paciente y provider / médico / staff = invitados.
  - persistencia de trazabilidad y referencias externas dentro de MedTravel.
- Fase 2:
  - uso de Google Meet API avanzada para participantes, duración, conference records y artefactos si aplica.
- Seguridad mínima obligatoria:
  - tokens separados por admin
  - refresh token protegido / cifrado
  - validación `state`
  - scopes mínimos
  - secretos fuera de frontend
  - no mezclar conexiones entre admins
- La integración no altera la frontera canónica del negocio: MedTravel coordina y agenda, pero no se convierte en prestador médico ni decisor clínico.

**Transition note**
- Esta decisión canónica habilita preparar la implementación, pero no implica que la integración ya exista en runtime.
- El target futuro de ownership fino por provider o `provider_medical_staff` se mantiene como evolución posterior, no como requisito de salida para la Fase 1.

**Operational effect**
- El canon de Calendar / Meet debe describir explícitamente Fase 1 y Fase 2.
- El backlog debe separar implementación base vs capacidades avanzadas de Meet.
- Las futuras iteraciones de agenda e integración deben mantener vínculo explícito con booking request, request item, staff asignado cuando aplique, Inbox y timeline.

## 2026-03-22 — MedTravel se canoniza como intermediario / facilitador y no como actor clinico tratante

**Outcome**
- Se deja explicito que MedTravel no es prestador directo de servicios medicos.
- Se deja asentado que MedTravel no integra el staff medico ni sustituye la relacion clinica entre paciente y provider / medico tratante.
- Se explicita que MedTravel no presta actos medicos, no toma decisiones clinicas y no reemplaza el criterio del provider tratante.
- Se ratifica que el valor de MedTravel esta en coordinar, ordenar, acompañar, reducir friccion, dar trazabilidad operativa y facilitar la interaccion entre las partes.
- Se deja asentado que esta frontera aplica transversalmente a booking, asignacion de staff, agenda, Google Calendar / Meet, patient journey, comunicaciones y copy del producto.

**Decision**
- MedTravel se modela canónicamente como intermediario / facilitador operativo entre paciente y provider.
- El producto no debe presentarse ni diseñarse como prestador medico directo.
- Las decisiones medicas pertenecen al provider y al staff clinico tratante responsable del caso o del item.
- Las capacidades de MedTravel deben mantenerse dentro de coordinacion, comunicacion, agenda, documentacion operativa, trazabilidad y acompañamiento logistico / operativo.
- Ninguna futura UX, integracion o funcionalidad debe cruzar esa frontera ni implicar que MedTravel sustituye criterio medico o relacion clinica tratante.

**Transition note**
- Esta decision es transversal y de producto; no implica runtime nuevo.
- El objetivo es proteger el limite del negocio y evitar que evolutivos futuros desplacen a MedTravel desde coordinacion operativa hacia rol clinico impropio.

**Operational effect**
- La documentacion, el copy y las futuras decisiones de UX deben reforzar siempre la frontera entre coordinacion MedTravel y acto medico del provider.
- Las futuras iteraciones en booking, agenda, Inbox, Google Calendar / Meet y patient journey deben revisarse contra esta regla antes de consolidarse como canon o runtime.
- La revision de esta frontera no debe quedar solo como criterio conceptual; debe aplicarse de forma continua sobre labels, estados visibles, CTAs, mensajes guia y copy operativo.

## 2026-03-22 — MedTravel se define como plataforma de coordinacion confiable para el paciente internacional

**Outcome**
- Se deja explicito que MedTravel no debe diseñarse como simple catalogo o booking aislado de servicios.
- Se canoniza que la base del producto es la experiencia del paciente internacional y la coordinacion confiable de su proceso medico.
- Se reconoce que el paciente deposita en MedTravel confianza sobre salud, tiempo, dinero, viaje y seguridad operativa.
- Se deja asentado que la promesa real del producto incluye confianza, acompañamiento, coordinacion medica, coordinacion logistica, claridad del proceso, seguridad operativa y continuidad.
- Se reconoce que el runtime actual ya cubre parcialmente esa promesa mediante provider identificado, servicio y oferta enlazados, staff real asignable, detalle de caso, Inbox, Calendar, asignacion de staff y trazabilidad basica.
- Se deja explicito que todavia faltan capacidades para completar plenamente esa experiencia, incluyendo multiples citas por caso, agenda fina por staff, integracion Google Calendar / Meet, coordinacion multi-medico / multi-provider y mayor claridad del journey completo del paciente.

**Decision**
- MedTravel no se modela canónicamente como un simple marketplace de servicios medicos ni como un booking engine de citas aisladas.
- El producto se define como plataforma de coordinacion confiable de procesos medicos internacionales centrada en el paciente.
- La unidad de valor no es solo el servicio vendido, sino la capacidad de ordenar y dar continuidad al proceso completo del paciente.
- Inbox, Calendar, detalle de caso, asignacion de staff y trazabilidad deben evolucionar como capacidades al servicio de esa experiencia integral y no como modulos desconectados.
- Las futuras decisiones de producto, UX y arquitectura no deben degradar la experiencia a una logica de transaccion simple por servicio.

**Transition note**
- Esta decision es de producto y de canon, no implica implementacion runtime inmediata.
- El estado actual del sistema ya apunta en esa direccion, pero todavia no cumple de forma plena toda la promesa de experiencia internacional.
- El backlog debe seguir cerrando la brecha entre la promesa canónica y la capacidad operativa real del runtime.

**Operational effect**
- La documentacion futura debe describir MedTravel desde la perspectiva del paciente internacional y no desde el servicio aislado.
- Las futuras iteraciones deben priorizar continuidad operativa, claridad del journey, agenda confiable y coordinacion multi-actor como parte del core del producto.

## 2026-03-22 — Modelo canónico minimo de citas y agenda futura

**Outcome**
- Se explicita que una solicitud / caso no equivale a una sola cita.
- Se deja asentado que un caso puede contener multiples citas a lo largo de su evolucion operativa y clinica.
- Se declara que cada cita debe poder asociarse a item clinico, medico / staff asignado, provider, fecha/hora y modalidad presencial o virtual.
- Se deja explicito que Google Calendar y Google Meet se integran como capacidad de cita dentro del dominio Agenda y no como modulo aparte.
- Se ratifica que Inbox sigue siendo comunicacion y Calendar sigue siendo agenda.
- Se deja asentado que la validacion futura de agenda debe evolucionar desde provider global hacia medico / staff asignado.
- Se reconoce que un mismo caso puede involucrar varios medicos y, si aplica, varios providers.

**Decision**
- Caso, item y cita siguen siendo entidades operativas distintas.
- Un caso puede agrupar multiples items y cada item clinico puede requerir cero, una o multiples citas.
- La cita es la unidad operativa sobre la cual debe vivir la integracion futura con Google Calendar event y Meet link cuando aplique.
- Google Calendar / Meet no deben modelarse como modulo funcional independiente; son capacidad integrada del dominio de citas dentro de Calendar.
- Inbox no reemplaza Agenda y Agenda no reemplaza Inbox:
  - Inbox = comunicacion y seguimiento conversacional
  - Calendar = gestion operativa de citas
- La logica futura de disponibilidad, no solapamiento y validacion de agenda debe anclarse en el medico / staff asignado y no quedarse solo en `provider_id`.
- El modelo no debe asumir exclusividad de un solo medico ni de un solo provider por caso.

**Transition note**
- Esta decision es canónica y de producto; no implica todavia runtime nuevo.
- El runtime actual puede seguir operando con agenda simplificada mientras se prepara la entidad de cita y su integracion externa.
- La compatibilidad con `calendar_capacity` global del provider se mantiene como transicion, pero deja explicitamente de representar el modelo final deseado.

**Operational effect**
- La documentacion y el backlog futuro deben describir Agenda como dominio de citas multiples por caso / item.
- Las futuras implementaciones de Google Calendar y Google Meet deben colgarse de la cita, no del caso completo ni de Inbox.

## 2026-03-23 — Inbox libre desde el inicio; acciones estructuradas siguen siendo formales

**Outcome**
- Se deja explícito que Inbox queda libre desde el inicio como canal de comunicación tanto en CARE como en ITEM, dentro de los scopes permitidos.
- Se ratifica que las acciones estructuradas, quick actions y formularios siguen existiendo para registrar decisiones o solicitudes con efecto operativo.
- Se deja asentado que el mensaje libre no cambia estados por sí solo.
- Se confirma que los únicos bloqueos conversacionales válidos son comerciales o de alcance: fee gate, commission gate y ownership/scope/asignación.

**Decision**
- Inbox se trata canónicamente como comunicación libre y trazable, no como compuerta de estado por etapa.
- Los cambios de estado deben seguir dependiendo de acciones formales explícitas y no del texto libre del chat.
- La botonera y las tarjetas estructuradas se mantienen como soporte UX recomendado, no como requisito para poder conversar.
- El runtime no debe usar `booking_requests.status` para bloquear conversación libre por etapa temprana.

**Operational effect**
- UX, ayudas y mensajes del inbox deben explicar que el chat es libre y que las acciones formales sirven para registrar side effects.
- La documentación de ejecución y backlog no debe seguir describiendo ITEM como canal estructurado obligatorio en early stage.
- Las futuras validaciones de agenda deben migrar hacia chequeos por staff asignado, manteniendo compatibilidad transitoria con controles globales legacy.

## 2026-03-22 — Owner/admin visible no equivale a staff clínico asignable

**Outcome**
- Se explicita que la fila sintética del owner/admin en `staff_medico.php` resuelve solo visibilidad operativa del equipo.
- Se deja asentado que esa fila no alcanza para booking asignable ni para el runtime clínico real.
- Se declara como representación válida para asignación clínica el registro físico en `provider_medical_staff` enlazado por `linked_user_id`.
- Se deja explícito que `providers.php` no es una UX donde el provider se agregue manualmente a sí mismo como staff.
- Se recomienda como mínimo espejo automático para providers de tipo `medico` / persona cuando el owner/admin deba actuar como recurso clínico asignable.
- Se deja explícito que providers de tipo `clinica` no deben auto-materializar por defecto al owner/admin como staff clínico.

**Decision**
- Owner/admin y staff siguen siendo entidades distintas en el modelo de MedTravel.
- La fila sintética del owner/admin no reemplaza ni materializa staff; solo mejora visibilidad operativa en el listado.
- Para booking asignable, enrichment clínico y scope futuro de staff, la representación válida sigue siendo `provider_medical_staff` físico.
- `providers.php` queda reservado al onboarding administrativo central de MedTravel y no debe operar como UX del provider para autoconvertirse en staff.
- Cuando el provider sea de tipo `medico` / persona, el owner/admin debe materializarse automáticamente como espejo operativo en `provider_medical_staff`, vinculado por `linked_user_id`.
- Cuando el provider sea de tipo `clinica`, no debe asumirse automáticamente que el owner/admin atiende pacientes ni materializarlo por defecto como staff clínico.
- Para `clinica`, esa conversión queda como acción explícita futura dentro del dominio provider, no en onboarding central.
- Este espejo no elimina la separación conceptual owner/admin vs staff; solo resuelve interoperabilidad clínica y de booking.

**Transition note**
- La decisión canónica queda cerrada aunque el runtime todavía no implemente ese espejo de forma completa.
- El criterio mínimo recomendado es cubrir automáticamente al menos providers de tipo `medico` / persona.
- La siguiente validación funcional debe comprobar que booking y asignación de oferta usen efectivamente esa representación física.
- Para `clinica`, el siguiente frente funcional debe definir una UX operativa explícita para declarar que el administrador también atiende pacientes cuando eso aplique.

**Operational effect**
- `staff_medico.php` puede seguir mostrando owner/admin como fila informativa de solo lectura.
- Las futuras implementaciones de booking asignable no deben tratar esa fila sintética como recurso clínico real.
- La interoperabilidad clínica debe anclarse en `provider_medical_staff.id` y `provider_medical_staff.linked_user_id`.
- El onboarding central de `providers.php` materializa automáticamente el espejo solo para `medico` / persona.
- La conversión equivalente para `clinica` queda fuera de `providers.php` y pendiente de UX explícita del dominio provider.

## 2026-03-22 — `calendar_capacity` se documenta como limite global de concurrencia en agenda

**Outcome**
- Se explicita en canon que `calendar_capacity` ya tiene efecto real en runtime.
- Se aclara que hoy funciona como limite global de concurrencia por provider en agenda para eventos tipo `ITEM`.
- Se evita seguir presentandolo como si ya modelara capacidad fina por medico, staff o sede.

**Decision**
- `calendar_capacity` debe describirse hoy como control grueso de concurrencia del provider en agenda.
- No debe venderse en UI ni en documentacion como disponibilidad fina por staff, medico, sede o servicio.
- Mientras no exista agenda fina por staff / servicio, este campo se mantiene como guardrail operativo global y compatibilidad runtime.

**Transition note**
- Esta decision no cambia la logica operativa actual de `admin/ajax/calendar.php`.
- La tension con el modelo canónico mas fino por staff y servicios queda reconocida como deuda funcional futura.

**Operational effect**
- El copy de `Mi Empresa` y la documentacion deben reflejar que el campo limita solapamientos globales de agenda para el provider.
- Las futuras iteraciones deben decidir si este control convive con capacidad fina por staff / servicio o migra hacia ese modelo.

## 2026-03-22 — Onboarding owner/admin de providers alineado a email-first

**Outcome**
- El alta inicial de owner/admin en `providers.php` queda alineada al patrón email-first ya usado en otros flujos de acceso con invitación segura.
- El formulario de onboarding de providers deja de pedir credenciales manuales inconsistentes para el owner/admin inicial.
- El acceso inicial del owner/admin pasa a depender del email y del enlace seguro de `set_password.php`.

**Decision**
- El onboarding inicial del owner/admin de providers no debe pedir `username` manual ni `password` manual en la UI.
- La identidad de acceso expuesta al usuario debe ser el email owner/admin.
- Si el runtime necesita valores internos de compatibilidad para `usuarios.usuario` o `usuarios.password`, esos valores deben resolverse internamente sin exponerse en el formulario.
- `providers.php` queda alineado a este patrón y no debe reintroducir credenciales manuales en futuras iteraciones.

**Transition note**
- Esta decisión no mezcla el flujo de providers con el flujo de staff; solo alinea el onboarding inicial del owner/admin del provider al mismo principio de acceso por email + set-password.
- En runtime legacy, la compatibilidad con campos internos de `usuarios` puede seguir existiendo, pero deja de ser parte del contrato visible del formulario.

**Operational effect**
- El alta de providers crea la cuenta owner/admin inicial a partir del email owner/admin y envía la invitación segura de acceso.
- Update mantiene el owner/admin sin exponer edición de username ni password manuales en el modal.

## 2026-03-22 — Regla canónica de notificaciones admin con Metronic

**Outcome**
- El admin de MedTravel adopta oficialmente `toastr` de Metronic, o un wrapper equivalente sobre ese mismo sistema, como mecanismo estándar de feedback al usuario.
- Queda prohibido usar `alert()` u otros popups nativos del navegador para feedback normal de usuario en el admin.
- La regla aplica a errores, warnings, success e info.
- `admin/js/providers.js` queda registrado como caso ya corregido y alineado a esta decisión.

**Decision**
- Toda notificación visible del admin debe emitirse mediante el sistema estándar ya adoptado por la plantilla.
- No se admite introducir nuevos módulos admin con `alert()` como UX de operación normal.
- Cuando un módulo legacy todavía use `alert()`, eso debe tratarse como deuda técnica de migración y no como patrón válido.
- Los mensajes técnicos siguen siendo válidos para depuración, pero deben presentarse con `toastr` o wrapper equivalente cuando formen parte del flujo normal del usuario.

**Transition note**
- Esta decisión no obliga a una migración masiva inmediata de todos los módulos legacy.
- La migración puede hacerse de forma progresiva por frentes, empezando por los módulos que se toquen por trabajo funcional o correctivo.
- `providers.js` queda asentado como implementación ya ejecutada bajo esta regla.

**Operational effect**
- Las futuras correcciones y features del admin deben reutilizar `toastr` o el helper local equivalente ya presente en el proyecto.
- Cualquier uso residual de `alert()` en módulos legacy queda explícitamente catalogado como backlog de hardening UX del admin.

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
- `save_staff` trata el catalogo como fuente autoritativa para altas y ediciones normales: solo acepta valores presentes en el catalogo activo o, en modo compatibilidad, el valor legacy ya existente del registro editado.
- El owner/admin inicial del provider se mantiene como identidad distinta del staff canónico, pero `staff_medico.php` puede exponerlo en el listado como fila sintética de solo lectura para visibilidad operativa.
- Esa visibilidad sintética no debe reinterpretarse como representación válida para booking asignable; ese criterio queda cerrado por la decisión específica del 2026-03-22 sobre owner/admin y staff clínico asignable.

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

## 2026-04-03 — RBAC hardening for `administrative`

- `administrative` deja de compartir bypass de admin principal
- scope operativo mínimo del rol:
  - inbox de coordinación CARE
  - calendar de coordinación CARE
  - booking asistido
  - perfil propio
- fuera de scope:
  - administración global de usuarios/roles
  - configuración sensible
  - contenido web
  - gestión global de clientes/bookings
- la restricción debe existir en menú, guards y backend; no solo en ocultamiento visual
- el universo técnico mínimo de “MedTravel Coordination” se acota a superficies CARE existentes, sin introducir un nuevo modelo de scope en esta pasada

### Cierre formal y contraste con fuente de verdad

- fecha de cierre documental: 2026-04-03
- commits relacionados:
  - `127c16c` — Restrict administrative role to coordination scope
  - `b8ba79e` — Allow administrative login without admin bypass
  - `919f92b` — Align admin auth runtime with scoped model
  - `b754344` — Align administrative scope with canonical auth flow
- migración relacionada:
  - `sql/2026_04_03_administrative_role_permissions_scope.sql`
- problema detectado durante la auditoría:
  - el hardening PHP ya restringía `administrative`, pero existía riesgo de desalineación si `role_permissions` reales seguían más amplios que el canon
  - además persistían residuos funcionales menores en `booking_asistido.php`, `clientes.php` y `login_context`
- contraste usado para cerrar el frente:
  - `sql/medtravelcom_medtravel.sql` se tomó como representación de la BD real del servidor
  - los canónicos y el SQL versionado se usaron como rastro formal complementario
  - las bases locales o snapshots con drift no redefinen la verdad del proyecto
- correcciones cerradas en código:
  - `administrative` mantiene login permitido sin recuperar bypass global
  - auth runtime queda alineado al modelo scopeado con compatibilidad legacy guardada
  - `booking_asistido.php` reutiliza el back target correcto para `administrative`
  - `clientes.php` queda bloqueado desde el guard central y mantiene defensa adicional en AJAX
  - `login_context` contempla `usrlogin`, alineado con el contrato real del login
- estado final esperado del rol `administrative`:
  - acceso restringido a inbox CARE
  - calendar CARE
  - booking asistido
  - perfil propio
  - sin acceso global a usuarios, roles, clientes, contenido web o configuración sensible
- estado de BD / despliegue:
  - la migración `sql/2026_04_03_administrative_role_permissions_scope.sql` queda versionada como artefacto formal para alinear entornos rezagados
  - el dump `sql/medtravelcom_medtravel.sql` debe prevalecer sobre cualquier conclusión derivada de una BD local con drift
  - validación operativa posterior a despliegue: login admin principal, login `administrative`, assisted booking y bloqueos de clientes/usuarios para `administrative`
