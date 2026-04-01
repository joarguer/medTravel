# CLAUDE.md — MedTravel

Plataforma de turismo médico que conecta pacientes de EE. UU. (principalmente Florida) con proveedores médicos en Quindío, Colombia. MedTravel actúa como **coordinador**, no como proveedor clínico.

---

## Stack técnico

- **Backend:** PHP procedural con includes; sin framework
- **Frontend:** Bootstrap 4/5, jQuery, vanilla JS
- **Base de datos:** MySQL (`bolsacar_medtravel`), charset `utf8mb4`
- **APIs externas:** ConectarBot (widget/WhatsApp), Stripe (pagos), Google Calendar + Meet
- **Auth:** Sesiones PHP; tokens CSRF en `admin/include/session_security.php`

---

## Estructura de directorios

```
admin/          Panel de administración (gestión de casos, proveedores, contenido)
admin/ajax/     Endpoints AJAX; todos deben validar sesión con require_login_ajax()
admin/include/  Auth, roles, mailer, helpers compartidos
api/            APIs públicas (stripe_webhook.php, conectarbot/)
booking/        Wizard multi-paso (wizard.php → submit.php)
client/         Portal del paciente
config/         Credenciales (stripe.php, conectarbot_api.php) — algunos gitignored
docs/canonical/ FUENTE DE VERDAD — leer antes de cualquier cambio arquitectónico
inc/            Helpers frontend (include.php, constants.php, google_calendar.php)
sql/            Esquemas y migraciones
```

---

## Modelo de datos clave

### Caso clínico (no "reserva")
- `booking_requests` — caso maestro (una por viaje médico)
- `booking_request_items` — líneas de servicio por caso (multi-proveedor, multi-servicio)
- `booking_request_events` — citas/eventos ligados a ítems
- `booking_request_quotes` — cotizaciones versionadas por ítem

Pipeline de estado **por ítem** (no global):
`availability_checked` → `virtual_assessment_scheduled` → `assessment_completed` → `quote_sent` → `quote_accepted` → `scheduled` → `ready_for_payment` → `paid` / `pay_on_arrival` / `cancelled`

### Proveedores vs. personal médico
- `providers` — entidad que presta servicios (clínica o médico independiente)
- `provider_medical_staff` — profesionales dentro de un proveedor; vinculados a `usuarios` via `linked_user_id`
- El acceso del personal está limitado a los ítems asignados, NO a todos los ítems del proveedor

### Jerarquía de servicios
1. `service_catalog` — catálogo maestro normalizado
2. `provider_catalog_services` — servicios habilitados por proveedor
3. `provider_service_offers` — precios/disponibilidad publicados
4. `provider_medical_staff_services` — capacidades del personal

---

## RBAC (roles)

| Rol | ID | Alcance |
|-----|----|---------|
| ROLE_ADMIN | 1 | Acceso total |
| ROLE_ADMINISTRATIVE | 2 | Operaciones administrativas |
| ROLE_PROVIDER_ADMIN | 12 | Dueño/admin del proveedor |
| ROLE_PROVIDER | 4 | Personal del proveedor (solo lectura) |
| ROLE_COMPLEMENTARY_ADMIN | 13 | Admin de servicio complementario |
| ROLE_ACCOUNTING | 11 | Reportes financieros |
| ROLE_CLIENT | 3 | Portal del paciente |

- Permisos granulares en `admin/include/roles.php`
- El super-admin (`usuarios.id = 1`) está protegido; nunca reutilizar en flujos de proveedores
- Todo endpoint AJAX debe llamar `require_login_ajax()` al inicio

---

## Convenciones de código

- **Tablas:** snake_case con prefijo de entidad (`provider_medical_staff`, `booking_request_items`)
- **Columnas activas:** hay inconsistencia — algunas usan `activo` (0/1) otras `is_active` (TINYINT); respetar el patrón de la tabla existente al tocarla
- **Fechas:** DATETIME; incluir `created_at`, `updated_at` con `ON UPDATE CURRENT_TIMESTAMP`
- **DB:** Usar **siempre** prepared statements con `mysqli`; nunca interpolación directa de variables en SQL
- **Output HTML:** Usar `htmlspecialchars()` en todo dato dinámico mostrado al usuario
- **AJAX responses:** Siempre retornar JSON con al menos `{"success": bool, ...}`

---

## Seguridad — reglas críticas

1. Nunca construir queries SQL con variables directas — solo prepared statements
2. Todo endpoint admin debe validar sesión y permisos antes de cualquier operación
3. Outputs dinámicos en HTML siempre con `htmlspecialchars()`
4. No generar ni exponer tokens/keys en respuestas AJAX
5. Las claves de Stripe y OAuth de Google van en variables de entorno, no en código

---

## Documentación canónica (leer antes de cambios arquitectónicos)

- `docs/canonical/00_INDEX.md` — índice de referencia
- `docs/canonical/01_PRODUCT_MODEL.md` — principios de producto, separación caso/ítem/cita
- `docs/canonical/02_TECH_ARCH_AND_RUNTIME.md` — arquitectura técnica, separación proveedor/personal
- `docs/canonical/05_CALENDAR_MEET_INTEGRATION_MODEL.md` — spec Google Calendar/Meet
- `MODELO_NEGOCIO_ACTUALIZADO.md` — modelo de negocio completo
- `DEV_CONTEXT.md` — contexto técnico de runtime
- `NEXT_STEPS_SERVICES.md` — backlog priorizado (P0, P1)

---

## Deuda técnica activa (P0 — bloquean otro trabajo)

- **P0.1** — Unificar campos de `service_catalog` y `service_categories` en todos los scripts SQL
- **P0.2** — Eliminar contenido hardcodeado en `index.php`, `packages.php`; mover a BD
- **P0.3** — Normalizar booking end-to-end: servicios complementarios como texto libre → relacional
- **P0.4** — Completar integración de `paquetes`: el frontend no persiste selecciones de servicios
- **P0.5** — Agregar `require_login_ajax()` + permisos en endpoints AJAX de catálogos

---

## Variables de entorno (desarrollo)

```
APP_ENV=dev
DB_HOST=127.0.0.1 / DB_PORT=8889
DB_USER=root / DB_PASS=root
DB_NAME=bolsacar_medtravel
CONECTARBOT_API_KEY=mt_cb_live_...
GOOGLE_OAUTH_CLIENT_ID, GOOGLE_OAUTH_CLIENT_SECRET, GOOGLE_OAUTH_REDIRECT_URI, GOOGLE_OAUTH_ENCRYPTION_KEY
```

---

## Qué NO hacer

- No usar `ROLE_PROVIDER` / `ROLE_PROVIDER_ADMIN` para nuevo personal médico — usar `provider_medical_staff`
- No eliminar `booking_requests` — usar soft delete
- No asumir que el estado del caso es global — el pipeline vive en cada ítem
- No mezclar lógica de servicios médicos con complementarios sin verificar el campo `type` en `medtravel_services_catalog`
- No cambiar la tabla `service_catalog` sin actualizar también los scripts en `sql/`
