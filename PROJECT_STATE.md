# PROJECT_STATE.md — MedTravel

Estado actual del proyecto. Actualizar al cierre de cada sesión técnica relevante.

---

## Estado general

- **Plataforma:** operativa en desarrollo local
- **Último bundle conocido:** `medtravel_local_backup_20260410.bundle`
- **Base de datos:** `bolsacar_medtravel` (MySQL, local)
- **Fecha última actualización de este archivo:** 2026-04-13

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

- **2026-04-03** — Fase 0–4 SEO/credibilidad en superficies públicas (commits `6d4db96` → `e9466ad`)
- **2026-03-20** — Tabla `provider_medical_staff` operativa; separación proveedor/médico activa
- **Booking wizard** — CTA integration documentada, provider offers, mejoras de wizard

---

## Frentes abiertos (no P0)

- Integración Google Calendar/Meet: spec en `docs/canonical/14_CALENDAR_MEET_INTEGRATION_MODEL.md`
- Portal del paciente (`client/`): funcionalidad básica presente, expansión pendiente
- Panel email settings: documentado en `PANEL_EMAIL_SETTINGS.md`
- Módulo Mi Empresa: documentado en `MODULO_MI_EMPRESA.md`

---

## Variables de entorno (desarrollo)

```
APP_ENV=dev
DB_HOST=127.0.0.1 / DB_PORT=8889
DB_USER=root / DB_PASS=root
DB_NAME=bolsacar_medtravel
CONECTARBOT_API_KEY=mt_cb_live_...
GOOGLE_OAUTH_CLIENT_ID, GOOGLE_OAUTH_CLIENT_SECRET
GOOGLE_OAUTH_REDIRECT_URI, GOOGLE_OAUTH_ENCRYPTION_KEY
```

---

## Arquitectura documental

Última implantación de base documental: 2026-04-13.
Ver `docs/canonical/02_DOC_MAP.md` para estructura completa.
