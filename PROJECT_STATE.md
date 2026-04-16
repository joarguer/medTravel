# PROJECT_STATE.md — MedTravel

Estado actual del proyecto. Actualizar al cierre de cada sesión técnica relevante.
Workspace operativo actual: `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/workspaces/medtravel`.

---

## Estado general

- **Plataforma:** operativa en desarrollo local
- **Último bundle conocido:** `medtravel_local_backup_20260410.bundle`
- **Base de datos:** entorno local moderno validado en `medtravel_rebuild_20260415` (MySQL, reconstruida desde dump real del servidor). `medtravel` queda preservada solo como referencia/backup local legacy y no debe usarse para validar el dominio moderno de providers/staff/services. Producción: `medtravelcom_medtravel`
- **Fecha última actualización de este archivo:** 2026-04-15

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

### 🔴 ATACAR AHORA — Google Calendar · Meet · cancelaciones

| Campo | Detalle |
|-------|---------|
| **Estado** | en progreso |
| **Impacto** | alto |
| **Evidencia** | Runtime validado: el organizer técnico de Google Calendar / Meet es la cuenta Google del admin autenticado en MedTravel; paciente y provider/staff participan como invitados y no conectan Google en este flujo. OAuth corregido con scope real `https://www.googleapis.com/auth/calendar`, `include_granted_scopes=false` y criterio de reconexión limpia cuando aparece `invalid_grant` o permisos insuficientes. Cancelar una reunión ya no cierra el caso: el item vuelve a `appointment_requested_change` y en Inbox operativo se expone como `provider_proposed_change` para permitir reprogramación / nueva propuesta. Las 3 migraciones locales (`appointment_mode`, `treatment_completed`, `post_treatment_follow_up`) siguen siendo punto de contraste si faltan en un entorno. |
| **Siguiente acción** | Validar E2E en producción: sesión staff + paciente simultáneas confirman widget propuesta en CARE sin refresh. Verificar migraciones pendientes por entorno. |

---

### 1. Homepage · oferta · confianza · empaque comercial

| Campo | Detalle |
|-------|---------|
| **Estado** | en progreso |
| **Impacto** | alto |
| **Evidencia** | Fases 0–4 SEO committeadas (2026-04-03, commits `6d4db96` → `e9466ad`). P0.2 abierto: `index.php` y `packages.php` con contenido hardcodeado. Search Console pendiente. |
| **Siguiente acción** | Enviar `sitemap.xml` a Search Console + ejecutar P0.2 (mover contenido a BD) |

---

### 2. Chat IA RAG MedTravel USA

| Campo | Detalle |
|-------|---------|
| **Estado** | abierto |
| **Impacto** | alto |
| **Evidencia** | Cero mención en docs, código ni backlog. Sin spec, sin entidad, sin decisión canónica. |
| **Siguiente acción** | Sesión documental: definir alcance, modelo RAG y canal de entrada antes de tocar código |

---

### 3. Provider · staff · services semantics

| Campo | Detalle |
|-------|---------|
| **Estado** | en progreso |
| **Impacto** | medio |
| **Evidencia** | Canon cerrado (2026-03-21). El alta admin en `providers.php` ya quedó reordenada como onboarding entendible en bloques A–E y `provider_verification.php` ya resume compliance/trust en una grilla compacta sin exigir scroll horizontal. Sigue abierta la deuda de modelo: `provider_catalog_services` no es entidad fuerte; staff y ofertas siguen ligados a `service_catalog.id` directo; copy de Mis Servicios / Mis Ofertas / Staff aún requiere convergencia semántica. |
| **Siguiente acción** | Paso 7: declarar `provider_catalog_services` como entidad operativa fuerte y desacoplar staff/ofertas |

---

### 4. SEO · perfiles médicos · E-E-A-T

| Campo | Detalle |
|-------|---------|
| **Estado** | en progreso |
| **Impacto** | medio |
| **Evidencia** | Fases 0–4 completas. Superficie pública activa. Pendiente: Search Console, monitoreo de indexación, campañas con URLs definidas. |
| **Siguiente acción** | Enviar sitemap a Search Console → monitorear cobertura → activar campañas |

---

### 5. Mantenimiento documental fino

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
