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

### Fase 2 — Meet avanzado y trazabilidad extendida

- La Google Meet API avanzada se incorpora después de estabilizar la Fase 1.
- Esta fase cubre metadatos extendidos y, cuando aplique por permisos y producto:
  - participantes
  - duración
  - conference records
  - artefactos como recordings o transcripts
  - eventos y trazabilidad avanzada
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

## 8. Reglas de seguridad

- Los tokens OAuth se separan por admin conectado.
- No se deben mezclar conexiones, tokens ni calendarios entre admins.
- El refresh token debe almacenarse protegido y cifrado en backend.
- El flujo OAuth debe validar `state` para prevenir CSRF y callbacks inválidos.
- Los scopes deben ser mínimos para la Fase 1 y ampliarse solo cuando la Fase 2 lo exija.
- No se exponen secretos OAuth ni tokens en frontend.
- La conexión Google se gestiona solo desde backend/admin autenticado.
- En fases posteriores, la misma regla de aislamiento debe extenderse por actor conectado; una aceptacion funcional interna no puede crear ni ampliar scopes OAuth por inferencia.

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

## 12. Fuera de Fase 1

- No se canoniza todavía ownership fino de organizer por provider o por `provider_medical_staff` como comportamiento inicial obligatorio.
- No se canoniza todavía sincronización bidireccional completa ni reconciliación avanzada de cambios.
- No se canoniza todavía explotación de artefactos avanzados de Meet como recordings, transcripts o conference records.
- No se canoniza todavía analítica avanzada de participantes y duración.
- No se canoniza todavía exposición pública o gestión de OAuth desde frontend paciente.
- No se canoniza todavía que aceptar una cita dentro de MedTravel baste como consentimiento tecnico sobre Google; esa equivalencia queda explícitamente prohibida.

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
- Fase 2: conference records y artefactos cuando apliquen
- Fase futura: ownership fino de agenda por provider o `provider_medical_staff`
- Fase futura: no solapamiento por staff
- Fase futura: sincronización y reconciliación avanzada de cambios