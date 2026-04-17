# Calendar / Meet Integration Model

## 1. Propósito

Este documento fija el modelo canónico inicial de integración con Google Calendar y Google Meet para coordinar citas entre paciente y provider / staff clínico, comenzando por operación desde admins autenticados de MedTravel.

La integración no se define como feature cosmética ni como accesorio técnico. Se define como capacidad estructural para coordinar un recurso escaso y crítico: la disponibilidad real de agenda del actor tratante.

MedTravel necesita este modelo porque:

- el tiempo clínico de suppliers médicos y su staff es limitado
- la coordinación de citas no puede depender solo de agenda manual o representación interna incompleta
- una solicitud puede requerir múltiples citas y múltiples actores tratantes
- la trazabilidad del caso exige orquestar agenda real sin convertir a MedTravel en actor clínico tratante

Este canon también fija una transición explícita por fases:

- Fase 1: integración base con Google Calendar API + generación de Google Meet al crear el evento, con organizer inicial en una cuenta Google conectada por admin de MedTravel.
- Fase 2: uso posterior de capacidades avanzadas de Google Meet para metadatos extendidos, conference records y trazabilidad enriquecida.

## 2. Principios rectores

- La agenda real vive en el actor tratante, no exclusivamente en MedTravel.
- MedTravel orquesta, coordina y mantiene trazabilidad del caso y de sus citas.
- La aceptacion funcional de una cita o reunion dentro de MedTravel no equivale a consentimiento OAuth ni a autorizacion tecnica sobre Google Calendar.
- El consentimiento funcional del flujo vive en MedTravel; la autorizacion tecnica sobre Google vive en la cuenta Google de cada actor y requiere OAuth explicito por separado.
- La validación de no solapamiento debe tender a evaluarse por `provider_medical_staff` asignado y no quedarse solo en `provider` general.
- Una solicitud / caso no equivale a una sola cita.
- Una cita no equivale al caso completo.
- Inbox sigue siendo comunicación.
- Calendar sigue siendo la vista operativa de agenda.
- Google Calendar y Google Meet se integran como infraestructura externa de citas, no como módulo aislado del producto.
- Esta integración no altera la frontera canónica del negocio: MedTravel coordina; el supplier y su staff tratante prestan la atención.
- La Fase 1 puede arrancar con organizer en una cuenta Google de admin MedTravel sin redefinir la frontera del negocio ni convertir a MedTravel en actor clínico.
- La evolución futura debe permitir ownership más fino por actor tratante real cuando la madurez operativa y técnica lo justifique.

## 3. Arquitectura por fases

### Fase 1 — Integración base desde admin MedTravel

- La integración base se implementa con Google Calendar API.
- El enlace de Google Meet se genera al crear el evento de Calendar.
- La autenticación se resuelve mediante OAuth 2.0 Web Server Flow.
- La conexión Google se hace por admin autenticado en MedTravel; no por frontend público ni por paciente.
- El sistema debe poder operar aunque solo el admin de MedTravel tenga conexión Google activa.
- Los tokens quedan separados por admin conectado.
- El organizer inicial de la reunión es la cuenta Google del admin MedTravel que autorizó la conexión y ejecuta la creación del evento.
- Paciente y provider / médico / staff se agregan como invitados del evento.
- Paciente y provider / médico / staff no están obligados a conectar Google en Fase 1 para que la cita exista y pueda ser coordinada.
- MedTravel persiste internamente la trazabilidad operativa de la cita y sus referencias externas.

#### Runtime validado de OAuth en Fase 1

- El scope operativo real exigido para crear eventos es `https://www.googleapis.com/auth/calendar`.
- La autorización se solicita además con `openid`, `email` y `profile` para identidad del admin conectado.
- El authorize flow debe forzar `include_granted_scopes=false` para no arrastrar una concesión previa incompleta.
- El criterio de recuperación validado cuando la conexión queda degradada es desconectar y reconectar limpiamente la cuenta Google del admin organizer.
- Los dos hallazgos reales que disparan esa reconexión limpia son:
  - `invalid_grant`
  - permisos insuficientes para crear eventos (`insufficient authentication scopes` / `ACCESS_TOKEN_SCOPE_INSUFFICIENT`)

### Fase 2 — Meet avanzado y trazabilidad extendida

- La Google Meet API avanzada se incorpora después de estabilizar la Fase 1.
- Esta fase cubre primero una salida mínima y de alto valor de negocio: **evidencia real de ejecución de reunión virtual**.
- Esa salida mínima se implementa sin romper Fase 1 y sin mezclar agenda con lifecycle clínico.
- El objetivo inicial de Fase 2 es responder técnicamente:
  - la reunión virtual inició
  - la reunión virtual terminó
  - cuándo ocurrió
  - cuánto duró
- Para esa salida mínima, la integración debe usar Google Workspace Events + Google Meet API para obtener:
  - `space` canónico de Meet
  - `conferenceRecord`
  - `startTime`
  - `endTime`
  - duración derivada
- Después de estabilizar esa evidencia mínima, la fase puede ampliarse a metadatos extendidos y, cuando aplique por permisos y producto:
  - participantes
  - recordings
  - transcripts
  - otros artefactos y trazabilidad avanzada
- La Fase 2 no redefine el modelo de cita; lo enriquece.

### Fase 2+ — Consentimiento OAuth opcional por actor

- Cada actor podrá conectar opcionalmente su propia cuenta Google:
  - admin
  - provider
  - médico / staff asignado
  - paciente
- Si un actor conecta su cuenta, MedTravel podrá usar su Google Calendar solo dentro del alcance autorizado por ese actor y por los scopes efectivamente concedidos.
- La aceptacion de una cita dentro de MedTravel sigue siendo separada del consentimiento OAuth de ese actor.
- Las conexiones Google deben mantenerse aisladas por usuario / actor y no pueden compartirse implicitamente entre perfiles distintos.

## 4. Modelo canónico

### Caso / solicitud

- Es el contenedor operativo principal del paciente.
- Puede agrupar múltiples items clínicos, múltiples citas y múltiples actores responsables.

### Item clínico

- Es la unidad operativa accionable del caso.
- Debe poder relacionarse con cero, una o múltiples citas.

### Cita

- Es la unidad operativa de agenda.
- Debe poder asociarse, como mínimo, a:
  - caso / solicitud
  - item clínico
  - provider responsable
  - `provider_medical_staff` asignado cuando aplique
  - fecha y hora
  - modalidad presencial o virtual
  - referencia interna de estado de coordinación
  - referencia externa de calendar / meet cuando exista

### Provider

- Es la entidad contractual y operativa responsable frente al caso.
- Puede ser owner de agenda en escenarios donde la coordinación todavía opere a nivel general del supplier.

### `provider_medical_staff`

- Es el actor objetivo para la evolución fina de agenda real.
- Debe ser la base futura para no solapamiento, ownership de citas y capacidad real de tiempo clínico.

### Cuenta Google conectada

- Representa la identidad externa autorizada para crear, leer o sincronizar eventos reales de agenda.
- En Fase 1 pertenece a un admin autenticado de MedTravel.
- En fases futuras puede evolucionar hacia granularidad por actor: admin, provider, miembro de `provider_medical_staff` o paciente, cuando ese actor conecte explicitamente su cuenta Google y el modelo de ownership fino de agenda madure.

### Evento externo Google Calendar

- Es la representación externa de la cita en la agenda real del actor conectado.
- En Fase 1 ese evento vive en la cuenta Google conectada por un admin MedTravel.
- MedTravel no reemplaza ese evento; mantiene referencia y trazabilidad sobre él.

### Meet link

- Es una capacidad de la cita cuando la modalidad o el flujo requiera atención virtual.
- Debe considerarse derivado del evento o de la integración externa de agenda, no como módulo independiente del producto.

### Evidencia real de ejecución Meet

- Es una capa técnica separada de la coordinación de agenda y del lifecycle clínico.
- Su propósito es responder si una reunión virtual realmente ocurrió, sin inferir por ello actos clínicos ni comerciales automáticos.
- Debe anclarse en la cita (`calendar_events.id`) porque la ejecución real corresponde a un evento concreto de agenda.
- Debe poder persistir como mínimo:
  - identificador canónico del espacio Meet (`spaces/{space}`)
  - identificador de conference record
  - timestamp de inicio
  - timestamp de fin
  - duración derivada
  - fuente técnica de detección
  - último tipo de evento recibido
- Esta capa no sustituye el `item_status` clínico ni crea por sí sola nuevos estados del item.

## 5. Regla de ownership de agenda

- En Fase 1, MedTravel admin puede ser organizer técnico del evento para acelerar la coordinación inicial y centralizar la operación.
- Ese organizer técnico no implica ownership clínico de la atención ni transforma a MedTravel en prestador.
- El ownership clínico y la responsabilidad asistencial siguen perteneciendo al provider y al staff tratante.
- La aceptacion del slot o de la reunion dentro de MedTravel no autoriza por si sola acceso tecnico a Google Calendar de paciente, provider o staff.
- El target de madurez sigue siendo permitir ownership fino del tiempo por actor tratante real cuando el modelo lo soporte.
- MedTravel sí debe mantener una representación interna consistente de la cita y referencias hacia el evento externo.
- Cuando todavía no exista ownership fino por staff, el modelo puede convivir transitoriamente con organizer admin MedTravel y con referencias operativas al provider o staff asignado.

## 6. Regla canónica de consentimiento y autorizacion por actor

### Separacion entre aceptacion funcional y autorizacion tecnica

- Aceptar una cita, una propuesta de reunion o un slot dentro de MedTravel representa consentimiento funcional del flujo de coordinacion dentro del producto.
- Esa aceptacion funcional no equivale a autorizacion OAuth sobre Google Calendar ni sobre Google Meet.
- El acceso tecnico a Google Calendar o a capacidades asociadas de Google requiere autorizacion OAuth explicita del actor titular de la cuenta Google correspondiente.
- MedTravel no debe inferir consentimiento OAuth implicito a partir de clicks funcionales de aceptacion, confirmacion o reprogramacion dentro del producto.

### Regla operativa de Fase 1

- La existencia de la cita no depende de que paciente, provider o staff hayan conectado Google.
- Si solo el admin de MedTravel tiene conexion Google, el sistema puede crear el evento con ese admin como organizer tecnico e invitar al resto de actores como attendees.
- En Fase 1, la coordinacion por email / invitacion de calendario es suficiente para materializar la reunion sin exigir OAuth a los demas actores.
- Paciente y provider / staff no deben ser llevados a un flujo OAuth Google para esta salida base; su participacion ocurre como invitados del evento creado por el admin organizer.

### Regla operativa de fases posteriores

- Cuando un actor conecte su propia cuenta Google, esa conexion queda limitada a:
  - su propia identidad externa
  - sus propios scopes concedidos
  - los flujos del producto que dependan de esa autorizacion explicita
- La conexion de un actor no habilita acceso a la agenda Google de otro actor.
- El modelo futuro debe soportar conexiones separadas por admin, provider, staff y paciente sin mezclar ownership tecnico ni tokens.

### Regla funcional de aceptacion futura

- Cuando provider / medico / staff proponga una reunion y el paciente la acepte en MedTravel:
  - si el actor correspondiente ya conecto Google, puede ejecutarse la sincronizacion asociada dentro del alcance autorizado;
  - si no la conecto, MedTravel puede solicitar la conexion Google como paso adicional del flujo;
  - la aceptacion del slot en MedTravel no debe tomarse como consentimiento OAuth implicito.
- Esta misma regla aplica simetricamente a cualquier actor cuya cuenta Google futura vaya a intervenir en sincronizacion o ownership fino de agenda.

## 7. Modelo de integración recomendado

La estrategia canónica recomendada es progresiva.

### Fuente de verdad de agenda real

- En Fase 1, la fuente técnica inicial del evento es la cuenta Google conectada por el admin MedTravel organizer.
- La fuente operativa de contexto sigue siendo MedTravel, porque la cita debe quedar vinculada a booking request, request item, actor asignado y timeline interno.
- En fases posteriores, la fuente de verdad de agenda real puede evolucionar hacia cuentas del actor que haya autorizado explicitamente su Google Calendar, incluyendo provider, staff tratante o paciente cuando aplique.

### Rol de MedTravel

- MedTravel opera como orquestador central de caso, citas, estado operativo y trazabilidad.
- MedTravel mantiene la representación interna necesaria para coordinación, Inbox, Calendar, timeline y conciliación.
- MedTravel no asume rol de prestador médico ni de decisor clínico por crear el evento o el Meet link.

### Granularidad soportada

- El modelo debe soportar evolución a dos niveles:
  - Fase 1: organizer por admin MedTravel
  - Fase futura: ownership fino y/o sincronización por actor conectado, incluyendo provider, `provider_medical_staff` y paciente cuando el flujo lo requiera

### Google Meet

- Cuando la cita sea virtual o requiera enlace remoto, Google Meet debe generarse desde la cita o el evento externo cuando aplique.
- Meet se trata como capacidad de la cita, no como producto separado.

### Evidencia de ejecución real propuesta

- Cuando una cita virtual ya fue aceptada y el evento externo existe, MedTravel debe poder resolver el `space` canónico de Meet a partir del `meetingCode`.
- La recomendación canónica inicial es suscripción por cita / espacio Meet:
  - `targetResource = //meet.googleapis.com/spaces/{SPACE_ID}`
  - `eventTypes = conference.started / conference.ended`
  - `notificationEndpoint = Pub/Sub`
- El flujo de alto nivel queda canonizado así:
  - `calendar_events.status=confirmed` = cita aceptada
  - `google.workspace.meet.conference.v2.started` = reunión realmente iniciada
  - `google.workspace.meet.conference.v2.ended` = reunión realmente terminada
  - el consumer toma `conferenceRecord.name`, consulta `conferenceRecords.get` y actualiza MedTravel
- Este modelo no altera la regla de que la cita sigue siendo agenda y el item sigue siendo lifecycle clínico.

## 8. Reglas de seguridad

- Los tokens OAuth se separan por admin conectado.
- No se deben mezclar conexiones, tokens ni calendarios entre admins.
- El refresh token debe almacenarse protegido y cifrado en backend.
- El flujo OAuth debe validar `state` para prevenir CSRF y callbacks inválidos.
- Los scopes deben ser mínimos para la Fase 1 y ampliarse solo cuando la Fase 2 lo exija.
- La ampliación de scopes para Fase 2 debe introducirse de forma incremental y no forzar reconexión masiva de organizers en producción sin rollout controlado.
- No se exponen secretos OAuth ni tokens en frontend.
- La conexión Google se gestiona solo desde backend/admin autenticado.
- En fases posteriores, la misma regla de aislamiento debe extenderse por actor conectado; una aceptacion funcional interna no puede crear ni ampliar scopes OAuth por inferencia.

## Nota operativa — comportamiento validado en smoke E2E local (2026-04-16)

El smoke Google Calendar / Meet fue ejecutado de punta a punta el 2026-04-16 en entorno local sobre `medtravel_rebuild_20260415`. Los comportamientos siguientes quedaron validados en código y API real:

### Cuándo se crea el evento real en Google Calendar

| Momento | Comportamiento correcto |
|---------|------------------------|
| `provider_propose_change` | Solo crea registro local en `calendar_events` con `google_event_id=NULL`. **No se llama a la Google Calendar API.** |
| `accept_dates` (paciente) | Aquí se invoca `client_inbox_confirm_google_meeting()` y se crea el evento real en Google Calendar con Meet link. `google_event_id` se persiste en `calendar_events`. |

Cualquier cambio que mueva la creación del evento al momento de la propuesta es una regresión de producto. La causa de esa regresión (commit `e00a316`) fue detectada, auditada y revertida (`b25e42b`).

### Resolución de attendees validada

| Attendee | Fuente | Resultado en smoke |
|----------|--------|--------------------|
| Paciente | `booking_requests.email` | ✅ siempre incluido |
| Proposal sender | `inbox_messages` → `sender_user_id` → `usuarios.email` | Excluido si coincide con `organizerConnectionEmail` (caso del smoke: `medtravelusa@gmail.com` == organizer) |
| Staff asignado | `bri.assigned_staff_id` → `pms.linked_user_id` → `usuarios.email` | ✅ `colfecarga@gmail.com` incluido cuando `assigned_staff_id=1` poblado |
| Organizer | Cuenta Google OAuth del admin conectado | Organizer nativo Google; no se añade como attendee explícito |

**Nota de backlog menor (no crítico):** si un `provider_medical_staff` tiene `pms.email` directo pero `linked_user_id=NULL`, ese staff nunca entra como attendee. El código resuelve únicamente por `linked_user_id → usuarios.email`. Evaluar ampliar fallback a `pms.email` cuando `linked_user_id=0` si hay casos reales en producción con esa configuración.

### Cancelación validada

- `cancel_meeting` (paciente) → `calendar_events.status=cancelled`, `item_status=appointment_requested_change`.
- Google Calendar API confirma `status=cancelled` en el evento real (`GET /calendars/primary/events/{id}` → HTTP 200, `"status":"cancelled"`).
- La cancelación no cierra el caso; el provider puede reproponer desde `appointment_requested_change`.

---

## Nota operativa — dos paths de propuesta de cita (2026-04-16)

En el runtime actual existen **dos paths** distintos para que un provider / staff proponga una cita. Su comportamiento en `organizer_admin_user_id` es diferente.

| Path | Archivo | Quién puede invocarlo | Comportamiento de `organizer_admin_user_id` |
|------|---------|----------------------|----------------------------------------------|
| **Path modal my_booking_requests** | `admin/ajax/my_booking_requests.php` → acción `propose_appointment` | Provider admin, Staff asignado, Admin MedTravel (excepcionalmente) | Se fija al ejecutar la propuesta. Si el actor es admin MedTravel, `organizer_admin_user_id = id del admin`. Si es provider, queda vacío o se resuelve por fallback al admin OAuth conectado. |
| **Path calendar directo** | `admin/ajax/calendar.php` → creación de evento | Admin MedTravel, Provider admin | `organizer_admin_user_id` se fija al admin OAuth conectado en el momento de crear el evento Google Calendar. |

**Implicaciones operativas:**

- El path correcto para la propuesta de cita dentro del flujo clínico del caso es `my_booking_requests` (propose_appointment), ya que actualiza `item_status`, envía mensaje al inbox y emite socket al hilo CARE.
- El path `calendar` directo crea el evento en Google Calendar pero puede no sincronizar `item_status` si no va acompañado de la acción formal en el ítem.
- En ambos paths, el organizer técnico del evento Google es siempre un admin MedTravel con OAuth conectado. Eso no convierte al admin en el actor clínico responsable de la cita.

---

---

## Nota operativa — Plan de activación Meet execution evidence (2026-04-16)

Estado: desplegada en servidor, **flag OFF**, pendiente backfill con candidato `confirmed` vivo. Sesión 2026-04-16 cerrada en este punto.

### Estado en servidor (validado 2026-04-16)

| Componente | Estado |
|---|---|
| Migración `2026_04_16_google_meet_execution_phase1.sql` | ✅ Aplicada en `medtravelcom_medtravel` |
| `inc/google_meet_execution.php` | ✅ Desplegado (824 líneas). Consumer, backfill helpers, correlación 3-path, Pub/Sub pull/ack, schema guard. |
| `scripts/google_meet_consumer.php` | ✅ Desplegado. Noop correcto: `{"ok":true,"noop":true,"reason":"MT_GOOGLE_MEET_EXECUTION_ENABLED=0"}` |
| `scripts/google_meet_backfill_space_names.php` | ✅ Desplegado. Dry-run ejecutado: `scanned=0` (sin candidatos `confirmed`) |
| Env vars Pub/Sub | ✅ Presentes en `.env` servidor |
| Service account Google Cloud | ✅ Creada con rol Pub/Sub Subscriber |
| OAuth `medtravelusa@gmail.com` | ✅ Reconectado con scope Meet visible |
| Correlación 3-path (commit `2e418e58`) | ✅ Activo en lógica |
| Feature flag `MT_GOOGLE_MEET_EXECUTION_ENABLED` | ✅ Presente. **OFF** |

### Próximo paso (mañana)

```
1. Crear cita virtual Meet en producción con status='confirmed'
   (propuesta provider → aceptación paciente → google_event_id y google_meet_url presentes)
2. Dry-run: php scripts/google_meet_backfill_space_names.php --dry-run --limit=10
   → verificar scanned>0, resolved>0, unresolved=0
3. Backfill real: php scripts/google_meet_backfill_space_names.php --limit=50
4. MT_GOOGLE_MEET_EXECUTION_ENABLED=1 en producción
```

### Por qué el dry-run dio scanned=0 (no es error)

El backfill filtra `WHERE status='confirmed' AND google_meet_space_code <> '' AND google_meet_space_name = ''`. Todos los eventos con Meet en producción están `cancelled` en este momento. No hay filas candidatas. El scope OAuth ya está resuelto (reconexión completada). El único prerequisito que falta es una cita Meet en estado `confirmed`.

### Verificación pre-flag

```sql
-- Columnas Meet presentes (debe devolver 10 filas):
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calendar_events'
  AND COLUMN_NAME IN ('google_meet_space_code','google_meet_space_name',
    'meeting_execution_status','meeting_started_at','meeting_ended_at',
    'meeting_duration_seconds','meeting_detected_source','conference_record_name',
    'meeting_last_event_type','meeting_last_detected_at');

-- Tablas Meet presentes (debe devolver 2 filas):
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME IN ('google_meet_event_log','google_meet_subscriptions');

-- Al menos 1 evento confirmado con space_name backfilleado:
SELECT COUNT(*) FROM calendar_events
WHERE google_meet_space_name IS NOT NULL AND google_meet_space_name <> ''
  AND status = 'confirmed';
```

---

## 9. Encaje funcional con MedTravel

- Calendar / Meet sigue siendo herramienta de coordinación y agenda.
- Inbox sigue siendo comunicación y trazabilidad conversacional; no se convierte en scheduler.
- Cada cita debe quedar vinculada, como mínimo, a:
  - booking request
  - request item cuando aplique
  - provider responsable
  - `provider_medical_staff` asignado cuando exista
  - organizer admin MedTravel de Fase 1
  - referencias externas de Google Calendar / Google Meet
- La trazabilidad operativa debe persistirse dentro de MedTravel aunque el evento viva en Google Calendar.
- El modelo no convierte a MedTravel en prestador médico ni redefine la frontera del negocio.
- Google Calendar y Google Meet siguen siendo infraestructura de agenda y coordinacion; no cambian la naturaleza intermediaria de MedTravel ni convierten la reunion en acto medico prestado por la plataforma.

### Regla de cancelación de reunión en integración Calendar / Inbox

- Cancelar una reunión no debe cerrar el caso ni cancelar el item clínico por arrastre.
- Cuando una reunión se cancela y el caso sigue activo, la integración debe devolver el item al estado interno `appointment_requested_change`.
- En Inbox operativo admin/provider, ese escenario debe exponerse como `provider_proposed_change` para preservar la capacidad de reprogramar o emitir una nueva propuesta.
- La cancelación terminal del caso es una decisión de negocio separada y no debe inferirse desde la cancelación de un evento de agenda.

## 10. Alcance funcional futuro

El modelo debe soportar como mínimo:

- múltiples citas por caso
- múltiples médicos por caso
- múltiples providers por caso cuando el proceso lo requiera
- citas presenciales y virtuales
- anti-solapamiento por staff
- coordinación de citas con meses de anticipación
- reprogramaciones y reconciliación de cambios sin perder trazabilidad
- solicitud adicional de conexion Google cuando un flujo futuro requiera sincronizacion sobre la cuenta de un actor que aun no otorgo OAuth

## 11. Implicaciones técnicas mínimas

Sin definir aún implementación detallada, el canon deja asentado que serán necesarias al menos estas capacidades:

- OAuth 2.0 Web Server Flow por admin conectado en Fase 1
- tokens y estado de conexión por admin
- mapping entre cita interna y evento externo
- storage de `event_id`, `calendar_id`, `meet_link`, `organizer_admin_user_id`, `sync_status` y referencias relacionadas
- estrategia de fallback cuando no exista calendar conectado
- conciliación entre cambios hechos en MedTravel y cambios hechos en agenda externa
- base futura para ampliar metadatos de Meet sin rediseñar el modelo de cita
- base futura para conexiones OAuth opcionales por actor manteniendo aislamiento por usuario y separacion entre aceptacion funcional y autorizacion tecnica
- resolución y persistencia del `space` canónico de Meet al confirmar una cita virtual
- suscripción Google Workspace Events para eventos de conferencia iniciada / finalizada
- consumer backend pull-based desde Pub/Sub con dedupe y reproceso seguro
- persistencia separada de:
  - snapshot mínimo de ejecución en `calendar_events`
  - log crudo append-only de eventos Meet
  - metadatos de suscripción y su lifecycle

## 12. Fuera de Fase 1

- No se canoniza todavía ownership fino de organizer por provider o por `provider_medical_staff` como comportamiento inicial obligatorio.
- No se canoniza todavía sincronización bidireccional completa ni reconciliación avanzada de cambios.
- No se implementa todavía la evidencia real de ejecución Meet; queda solo canonizada como frente futuro por fases.
- No se canoniza todavía explotación avanzada de artefactos Meet como recordings y transcripts más allá de lo que eventualmente exija detectar ejecución real.
- No se canoniza todavía analítica avanzada de participantes.
- No se canoniza todavía exposición pública o gestión de OAuth desde frontend paciente.
- No se canoniza todavía que aceptar una cita dentro de MedTravel baste como consentimiento tecnico sobre Google; esa equivalencia queda explícitamente prohibida.
- No se canoniza todavía cambio automático de lifecycle clínico ni de comisión derivado de señales técnicas `started` / `ended`.

## 13. Frontera de producto

- MedTravel coordina agenda, comunicación y trazabilidad.
- MedTravel no actúa como prestador clínico.
- MedTravel no sustituye criterio médico.
- La integración con Google Calendar y Google Meet no cambia esa frontera.
- La agenda clínica real sigue perteneciendo al actor tratante responsable del tiempo y de la atención.
- El uso de Google Calendar / Meet sigue siendo infraestructura de agenda y coordinacion, no atencion medica prestada por MedTravel.

## 14. Backlog derivado

- Fase 1: conexión OAuth Google por admin MedTravel
- Fase 1: modelo de persistencia de tokens por admin con cifrado de refresh token
- Fase 1: creación de evento Google Calendar con Meet link al crear la cita
- Fase 1: persistencia de referencias externas y trazabilidad interna en MedTravel
- Fase 1: invitación de paciente y provider / staff al evento
- Fase futura: conexiones OAuth opcionales por actor con aislamiento por usuario
- Fase futura: solicitud de conexión Google como paso adicional cuando un actor acepte en MedTravel pero aún no haya autorizado OAuth
- Fase 2: metadatos avanzados de Google Meet
- Fase 2: resolver `space` canónico, crear suscripción por cita y detectar `started` / `ended`
- Fase 2: persistir snapshot mínimo de ejecución real Meet + log append-only + estado de suscripciones
- Fase 2: renovación / reactivación de suscripciones y manejo de lifecycle events
- Fase 2: conference records y artefactos cuando apliquen
- Fase futura: ownership fino de agenda por provider o `provider_medical_staff`
- Fase futura: no solapamiento por staff
- Fase futura: sincronización y reconciliación avanzada de cambios
