# PROJECT_STATE.md — MedTravel

Estado actual del proyecto. Actualizar al cierre de cada sesión técnica relevante.
Workspace operativo actual: `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/workspaces/medtravel`.

---

## Estado general

- **Plataforma:** operativa en desarrollo local
- **Último bundle conocido:** `medtravel_local_backup_20260410.bundle`
- **Base de datos:** `medtravel` (MySQL, local de este frente). Producción: `medtravelcom_medtravel`
- **Fecha última actualización de este archivo:** 2026-04-14

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
| **Siguiente acción** | Mantener canon y smoke alineados en cada entorno; verificar migraciones pendientes donde aún no existan |

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
DB_NAME=medtravel
CONECTARBOT_API_KEY=mt_cb_live_...
GOOGLE_OAUTH_CLIENT_ID, GOOGLE_OAUTH_CLIENT_SECRET
GOOGLE_OAUTH_REDIRECT_URI, GOOGLE_OAUTH_ENCRYPTION_KEY
```

---

## Arquitectura documental

Última implantación de base documental: 2026-04-13.
Ver `docs/canonical/02_DOC_MAP.md` para estructura completa.
