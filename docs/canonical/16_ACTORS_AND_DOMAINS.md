# 16_ACTORS_AND_DOMAINS — Actores, Dominios y Fronteras

Documento centralizado de actores del sistema MedTravel, sus dominios operativos y sus fronteras.

Esta es la fuente de verdad para responder: ¿quién hace qué, desde dónde, con qué alcance y qué no puede hacer?

Creado 2026-04-16. Derivado de `10_PRODUCT_MODEL.md` y del análisis de código de `admin/ajax/my_booking_requests.php` y `admin/include/roles.php`.

---

## 1. Tabla maestra de actores

| Actor | Rol técnico | ID rol | Panel / Superficie | Función operativa | Frontera explícita |
|-------|-------------|--------|-------------------|------------------|--------------------|
| Admin MedTravel | `ROLE_ADMIN` | 1 | Panel admin general | Gestiona la plataforma: alta de providers, catálogo, comisiones, configuración técnica (Google OAuth), monitoreo de casos | No es actor clínico. No propone citas como responsable del caso. No avanza el lifecycle clínico en nombre del provider. |
| Coordinador PatientCare | `ROLE_ADMINISTRATIVE` | 2 | Inbox CARE, booking asistido, perfil propio | Coordina comunicación con el paciente vía hilo CARE. Crea bookings asistidos. | Sin acceso a ítems médicos ni al lifecycle clínico. Sin acceso global a usuarios, roles o configuración sensible. |
| Provider admin | `ROLE_PROVIDER_ADMIN` | 12 | `admin/my_booking_requests.php`, Inbox ITEM, Calendar | Owner del prestador médico. Acepta/rechaza caso. Avanza lifecycle clínico. Propone citas. Gestiona su equipo. Responsable contractual. | Scope `provider_id` propio. No accede a providers de terceros. No gestiona configuración global de MedTravel. |
| Staff médico | `ROLE_PROVIDER` con `linked_user_id` en `provider_medical_staff` | 4 | Solicitudes asignadas, Inbox ITEM asignado, Agenda asignada | Ejecuta la atención real: valoración virtual, plan clínico, procedimiento, seguimiento. Owner operativo del ítem una vez asignado. | Scope `assigned_staff_id`. No accede a ítems no asignados. No gestiona configuración del provider. |
| Provider complementario | `ROLE_COMPLEMENTARY_ADMIN` | 13 | Panel provider complementario | Gestiona servicios no médicos vinculados al caso. | Scope `service_provider_id`. Dominio separado del médico. |
| Paciente | `ROLE_CLIENT` | 3 | Portal `client/` | Solicita servicios. Acepta propuestas de cita. Comunica. Dueño del expediente. | Sin acceso al panel admin. No avanza lifecycle clínico. No puede aceptar Términos en nombre de otro. |
| Superusuario | `ROLE_ADMIN` (id=1 protegido) | 1 | Acceso total | Raíz del sistema. | Nunca reutilizable en flujos de providers o staff. Protegido como cuenta raíz. |

---

## 2. Dominios operativos y dueño de cada uno

| Dominio | Dueño operativo | Herramienta principal | Descripción |
|---------|----------------|----------------------|-------------|
| Gestión de plataforma | Admin MedTravel | Panel admin general | Alta de providers, catálogo, comisiones, configuración técnica. |
| Coordinación con paciente (CARE) | Admin / ROLE_ADMINISTRATIVE | Inbox CARE | Comunicación de coordinación con el paciente. No es atención médica. |
| Atención médica del caso | Provider admin + Staff asignado | `my_booking_requests`, Inbox ITEM, Calendar | Ciclo clínico completo desde aceptación hasta cierre. |
| Servicios complementarios | Provider complementario | Panel complementario | Servicios logísticos o de apoyo no clínico. |
| Agenda y citas (técnico) | Transversal — organizador técnico: Admin MedTravel (Fase 1) | `app_calendar`, `calendar_events`, Google Calendar | El admin MedTravel es el organizador técnico de Google Calendar; el actor clínico es el responsable de la cita. |
| Evidencia técnica de ejecución Meet | Transversal — backend MedTravel, con organizer técnico admin en Fase 2 | Google Workspace Events, Pub/Sub, Google Meet API | Capa futura para detectar si la reunión virtual realmente inició o terminó. No reemplaza agenda ni lifecycle clínico. |
| Portal del paciente | Paciente | `client/` | Journey simplificado del paciente. En inglés. |
| Seguimiento comercial | Admin MedTravel | Panel comisiones, reportes | Comisiones configurables por provider. |

---

## 3. Lifecycle clínico — actor responsable por fase

El lifecycle de un ítem clínico vive en `booking_request_items.item_status`.

| Estado | Quién lo avanza | Notas |
|--------|----------------|-------|
| `pending_provider` | Sistema (booking llegó) | — |
| `provider_reviewing` | Provider admin | Inicia la revisión |
| `needs_more_info` | Provider admin | Solicita datos al paciente |
| `doctor_assigned` | Provider admin | Asigna staff al ítem |
| `provider_confirmed` | Provider admin | Confirma aceptación |
| `client_accepted` | Paciente (via portal) | — |
| `awaiting_client` | Sistema / Provider admin | Esperando respuesta del paciente |
| `appointment_proposed` | **Provider admin / Staff asignado** | MedTravel admin NO es el actor responsable |
| `appointment_confirmed` | Paciente (via portal) | — |
| `appointment_requested_change` | Provider admin / Staff / Paciente | Reprogramación solicitada |
| `appointment_cancelled` | Provider admin / Staff / Paciente | Cita cancelada; caso puede seguir activo |
| `virtual_assessment_pending` | **Provider admin / Staff asignado** | Inicio del ciclo clínico formal |
| `virtual_assessment_done` | **Provider admin / Staff asignado** | — |
| `treatment_plan_agreed` | **Provider admin / Staff asignado** | Plan clínico documentado |
| `procedure_scheduled` | **Provider admin / Staff asignado** | Procedimiento presencial programado |
| `treatment_completed` | **Provider admin / Staff asignado** | — |
| `post_treatment_follow_up` | **Provider admin / Staff asignado** | Seguimiento post tratamiento |
| `case_closed` | **Provider admin / Staff asignado** | Cierre formal exitoso |
| `cancelled` | Provider admin / Admin MedTravel | Cancelación del ítem |

**Nota sobre el Admin MedTravel:** el admin puede supervisar todos los estados y tiene permisos técnicos de escritura, pero **no es el actor responsable** de las transiciones clínicas. Ejecutarlas como admin produce registros con `sender_role = ADMIN` en lugar de `PROVIDER`, lo que no refleja el flujo clínico real.

---

## 4. Smoke test — actores correctos por sesión

Al ejecutar el smoke test E2E, usar estas sesiones. **Importante:** el código implementa hoy dos paths separados (smoke 2026-04-16). No se puede ejecutar un recorrido lineal único que combine ambos.

| Sesión | Rol a usar | Acción que valida |
|--------|-----------|------------------|
| Sesión 1 — Admin MedTravel | `ROLE_ADMIN` | Alta de provider, asignación de staff, monitoreo de caso. NO avanzar lifecycle clínico desde esta sesión. |
| Sesión 2 — Provider admin (Path A) | `ROLE_PROVIDER_ADMIN` | Proponer cita desde triage (`pending_provider → appointment_proposed`). |
| Sesión 2 — Provider admin (Path B) | `ROLE_PROVIDER_ADMIN` | Aceptar caso (`provider_confirmed`), luego iniciar valoración virtual, registrar plan, programar procedimiento, completar tratamiento, cerrar caso. |
| Sesión 3 — Paciente | `ROLE_CLIENT` | Confirmar o cancelar cita (Path A). Ver journey desde portal. |

**Path A validado en smoke 2026-04-16:**
```
pending_provider → appointment_proposed → appointment_confirmed → appointment_requested_change
```

**Path B validado parcialmente en smoke 2026-04-16:**
```
pending_provider → provider_confirmed → virtual_assessment_pending → … → case_closed
```

**Puente no implementado:** `provider_confirmed → appointment_proposed`. Decisión de producto pendiente; ver `13_CHANGELOG_DECISIONS.md` (2026-04-16).

Queries de validación post-smoke Path B:
```sql
-- Verificar que las transiciones clínicas llegaron al estado correcto
SELECT id, item_status, updated_at
FROM booking_request_items
WHERE item_status IN ('virtual_assessment_pending','virtual_assessment_done',
                      'treatment_plan_agreed','procedure_scheduled',
                      'treatment_completed','case_closed')
ORDER BY updated_at DESC LIMIT 20;
```

---

## 5. Fronteras que el canon fija explícitamente

1. El admin MedTravel **puede supervisar** el lifecycle de un ítem pero **no es el responsable operativo de avanzarlo**.
2. El `ROLE_ADMINISTRATIVE` (PatientCare) **no tiene acceso a ítems médicos**. Su dominio es exclusivamente el hilo CARE y el booking asistido.
3. El staff médico **es el owner operativo del ítem una vez asignado**. Antes, el owner es el provider admin.
4. Google Calendar organizer es un rol **técnico de infraestructura** (Fase 1: admin MedTravel), no un rol de responsabilidad clínica.
5. Una señal técnica de Meet (`conference started` / `conference ended`) es **evidencia de ejecución de cita**, no transición clínica automática.
6. La comisión es una **capa comercial configurable** que no altera la responsabilidad clínica del provider.
7. El canal de comunicación con el paciente es **Inbox CARE** para coordinación, **Inbox ITEM** para comunicación clínica con el provider.
8. Un agente IA que lea solo `AGENTS.md` o `PROJECT_STATE.md` **no debe asumir que el admin MedTravel es el actor responsable de proponer citas o avanzar el ciclo clínico**. Ese rol pertenece al provider admin o al staff asignado.

---

## 6. Dependencias documentales

Este documento deriva de:
- `docs/canonical/10_PRODUCT_MODEL.md` — modelo de producto y entidades
- `docs/canonical/13_CHANGELOG_DECISIONS.md` — decisión 2026-04-16

Lo usan:
- `AGENTS.md` — RBAC y contexto operativo para agentes IA
- `docs/canonical/12_EXECUTION_BACKLOG.md` — smoke test
- `docs/canonical/14_CALENDAR_MEET_INTEGRATION_MODEL.md` — organizer técnico vs actor clínico
