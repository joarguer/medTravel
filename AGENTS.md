# AGENTS.md — MedTravel

Contexto operativo para cualquier agente IA que trabaje sobre este repositorio.
Proveedor IA agnóstico. Leer antes de cualquier acción.
Workspace operativo actual del repo: `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/workspaces/medtravel`.

---

## Proyecto

Plataforma de turismo médico que conecta pacientes de EE. UU. (principalmente Florida) con proveedores médicos en Quindío, Colombia. MedTravel actúa como **coordinador operativo**, no como proveedor clínico.

---

## Stack

- **Backend:** PHP procedural con includes; sin framework
- **Frontend:** Bootstrap 4/5, jQuery, vanilla JS
- **Base de datos:** MySQL; baseline local moderno válido `medtravel_rebuild_20260415`, producción `medtravelcom_medtravel`, charset `utf8mb4`. La BD local `medtravel` queda solo como referencia/backup legacy y no debe usarse para validar el dominio moderno de providers/staff/services.
- **APIs externas:** ConectarBot (WhatsApp), Stripe (pagos), Google Calendar + Meet
- **Auth:** Sesiones PHP; tokens CSRF en `admin/include/session_security.php`

---

## Estructura de directorios

```
admin/          Panel de administración (casos, proveedores, contenido)
admin/ajax/     Endpoints AJAX — todos deben llamar require_login_ajax()
admin/include/  Auth, roles, mailer, helpers compartidos
api/            APIs públicas (stripe_webhook.php, conectarbot/)
booking/        Wizard multi-paso (wizard.php → submit.php)
client/         Portal del paciente
config/         Credenciales (gitignored en producción)
docs/canonical/ Fuente de verdad — leer antes de cambios arquitectónicos
docs/           Historia, wiki, progreso, APIs externas
inc/            Helpers frontend (include.php, constants.php, google_calendar.php)
sql/            Esquemas y migraciones
```

---

## Modelo de datos clave

### Caso clínico
- `booking_requests` — caso maestro
- `booking_request_items` — líneas de servicio por caso (multi-proveedor, multi-servicio)
- `booking_request_events` — citas/eventos ligados a ítems
- `booking_request_quotes` — cotizaciones versionadas por ítem

Pipeline de estado por ítem — hoy existen **dos paths separados en código** (validado en smoke 2026-04-16):

**Path A — Appointment-first** (cita propuesta directamente desde triage, sin confirmación previa del caso):
`pending_provider` → `provider_proposed_change` → `appointment_proposed` → `appointment_confirmed` → `appointment_requested_change`

**Path B — Clinical / Confirm-first** (aceptación formal del caso + ciclo clínico):
`pending_provider` → `provider_confirmed` → `virtual_assessment_pending` → `virtual_assessment_done` → `treatment_plan_agreed` → `procedure_scheduled` → `treatment_completed` → `case_closed`

**Decisión de producto pendiente:** el puente `provider_confirmed → appointment_proposed` **no está implementado** en código. Los paths son hoy alternativos, no encadenables. Ver `docs/canonical/13_CHANGELOG_DECISIONS.md` (2026-04-16).

Estados adicionales válidos en ambos paths: `provider_reviewing`, `needs_more_info`, `doctor_assigned`, `client_accepted`, `awaiting_client`, `appointment_cancelled`, `post_treatment_follow_up`, `cancelled`. Ver `docs/canonical/10_PRODUCT_MODEL.md` para lista completa con fases.

### Proveedores vs. personal médico
- `providers` — entidad que presta servicios
- `provider_medical_staff` — profesionales dentro de un proveedor; vinculados a `usuarios` via `linked_user_id`
- El acceso del personal está limitado a los ítems asignados, no a todos los ítems del proveedor

### Jerarquía de servicios
1. `service_catalog` — catálogo maestro
2. `provider_catalog_services` — servicios habilitados por proveedor
3. `provider_service_offers` — precios/disponibilidad publicados
4. `provider_medical_staff_services` — capacidades del personal

---

## RBAC

| Rol | ID | Función operativa real | Frontera |
|-----|----|----------------------|----------|
| ROLE_ADMIN | 1 | **Gestor de plataforma**: alta de providers, catálogo, comisiones, configuración técnica, monitoreo. Puede supervisar cualquier caso. | **No es actor clínico.** No propone citas ni avanza el lifecycle clínico como responsable. |
| ROLE_ADMINISTRATIVE | 2 | **Coordinador PatientCare**: coordina con el paciente vía hilo CARE, crea booking asistido, gestiona su perfil. | Solo dominio CARE y booking asistido. Sin acceso a ítems médicos ni lifecycle clínico. |
| ROLE_PROVIDER_ADMIN | 12 | **Owner del provider médico**: acepta/rechaza caso, propone citas, avanza lifecycle clínico, gestiona staff. Actor clínico responsable del caso. | Scope `provider_id` propio. |
| ROLE_PROVIDER | 4 | **Staff médico asignado** (`linked_user_id` en `provider_medical_staff`): ejecuta valoración, plan clínico, procedimiento, seguimiento. Owner operativo del ítem asignado. | Scope `assigned_staff_id`. Sin acceso a ítems no asignados. |
| ROLE_COMPLEMENTARY_ADMIN | 13 | Admin de servicio complementario (no médico). | Scope `service_provider_id`. |
| ROLE_ACCOUNTING | 11 | Reportes financieros. | Solo lectura comercial. |
| ROLE_CLIENT | 3 | **Paciente**: portal propio, acepta propuestas, comunica. | Portal `client/` separado. Sin acceso al panel admin. |

- Permisos granulares en `admin/include/roles.php`
- El super-admin (`usuarios.id = 1`) está protegido; nunca reutilizar en flujos de proveedores
- Ver `docs/canonical/16_ACTORS_AND_DOMAINS.md` para tabla completa de actores, dominios y fronteras

---

## Convenciones de código

- Tablas: snake_case con prefijo de entidad
- Columnas activas: inconsistencia entre `activo` (0/1) y `is_active` (TINYINT) — respetar el patrón de la tabla al tocarla
- Fechas: DATETIME; incluir `created_at`, `updated_at` con `ON UPDATE CURRENT_TIMESTAMP`
- DB: siempre prepared statements con `mysqli`; nunca interpolación directa en SQL
- Output HTML: `htmlspecialchars()` en todo dato dinámico
- AJAX: siempre retornar JSON con al menos `{"success": bool}`

---

## Seguridad — reglas críticas

1. Nunca construir queries SQL con variables directas — solo prepared statements
2. Todo endpoint admin: validar sesión y permisos antes de cualquier operación
3. Outputs dinámicos en HTML: siempre `htmlspecialchars()`
4. No generar ni exponer tokens/keys en respuestas AJAX
5. Claves de Stripe y OAuth de Google: variables de entorno, no en código

---

## Documentación canónica

- `docs/canonical/00_INDEX.md` — índice y protocolo documental
- `docs/canonical/01_SCOPE_AND_RULES.md` — alcance y reglas del proyecto
- `docs/canonical/02_DOC_MAP.md` — mapa completo de documentación
- `docs/canonical/10_PRODUCT_MODEL.md` — modelo de producto
- `docs/canonical/11_TECH_ARCH_AND_RUNTIME.md` — arquitectura técnica
- `docs/canonical/12_EXECUTION_BACKLOG.md` — backlog de ejecución
- `PROJECT_STATE.md` — estado actual del proyecto

---

## Qué NO hacer

- No usar `ROLE_PROVIDER` / `ROLE_PROVIDER_ADMIN` para personal médico nuevo — usar `provider_medical_staff`
- No eliminar `booking_requests` — usar soft delete
- No asumir que el estado del caso es global — el pipeline vive en cada ítem
- No mezclar lógica de servicios médicos con complementarios sin verificar el campo `type`
- No cambiar `service_catalog` sin actualizar también los scripts en `sql/`
- No usar la BD local legacy `medtravel` como sustituto del dominio moderno provider; para validaciones locales del dominio moderno usar `medtravel_rebuild_20260415`
- No versionar `.env`; es override local del workspace
- No tocar código en una sesión documental

---

## Tipos de sesión

- **Sesión documental:** solo se modifica documentación. No se toca PHP, JS, SQL, auth ni se abre ningún frente técnico.
- **Sesión técnica:** puede modificar código, después de decidirlo explícitamente.
