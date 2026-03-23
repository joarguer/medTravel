# Calendar / Meet Integration Model

## 1. Propósito

Este documento fija el modelo canónico de integración con Google Calendar y Google Meet como base del manejo del tiempo limitado de suppliers médicos y de su staff.

La integración no se define como feature cosmética ni como accesorio técnico. Se define como capacidad estructural para coordinar un recurso escaso y crítico: la disponibilidad real de agenda del actor tratante.

MedTravel necesita este modelo porque:

- el tiempo clínico de suppliers médicos y su staff es limitado
- la coordinación de citas no puede depender solo de agenda manual o representación interna incompleta
- una solicitud puede requerir múltiples citas y múltiples actores tratantes
- la trazabilidad del caso exige orquestar agenda real sin convertir a MedTravel en actor clínico tratante

## 2. Principios rectores

- La agenda real vive en el actor tratante, no exclusivamente en MedTravel.
- MedTravel orquesta, coordina y mantiene trazabilidad del caso y de sus citas.
- La validación de no solapamiento debe tender a evaluarse por `provider_medical_staff` asignado y no quedarse solo en `provider` general.
- Una solicitud / caso no equivale a una sola cita.
- Una cita no equivale al caso completo.
- Inbox sigue siendo comunicación.
- Calendar sigue siendo la vista operativa de agenda.
- Google Calendar y Google Meet se integran como infraestructura externa de citas, no como módulo aislado del producto.
- Esta integración no altera la frontera canónica del negocio: MedTravel coordina; el supplier y su staff tratante prestan la atención.

## 3. Modelo canónico

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
- Puede pertenecer a un provider o a un miembro de `provider_medical_staff`, según el nivel de granularidad implementado.

### Evento externo Google Calendar

- Es la representación externa de la cita en la agenda real del actor conectado.
- MedTravel no reemplaza ese evento; mantiene referencia y trazabilidad sobre él.

### Meet link

- Es una capacidad de la cita cuando la modalidad o el flujo requiera atención virtual.
- Debe considerarse derivado del evento o de la integración externa de agenda, no como módulo independiente del producto.

## 4. Regla de ownership de agenda

- El evento clínico idealmente debe vivir en el calendar del actor tratante dueño real del tiempo.
- MedTravel no debe ser dueño único de todas las agendas clínicas.
- MedTravel sí debe mantener una representación interna consistente de la cita y referencias hacia el evento externo.
- Cuando todavía no exista ownership fino por staff, el modelo puede convivir transitoriamente con ownership a nivel provider.
- El target canónico sigue siendo ownership por actor tratante real y no por contenedor administrativo genérico.

## 5. Modelo de integración recomendado

La estrategia canónica recomendada es híbrida.

### Fuente de verdad de agenda real

- La cuenta Google del supplier o del staff tratante conectado actúa como fuente de verdad de agenda real.

### Rol de MedTravel

- MedTravel opera como orquestador central de caso, citas, estado operativo y trazabilidad.
- MedTravel mantiene la representación interna necesaria para coordinación, Inbox, Calendar, timeline y conciliación.

### Granularidad soportada

- El modelo debe soportar evolución a dos niveles:
  - nivel provider
  - nivel `provider_medical_staff`

### Google Meet

- Cuando la cita sea virtual o requiera enlace remoto, Google Meet debe generarse desde la cita o el evento externo cuando aplique.
- Meet se trata como capacidad de la cita, no como producto separado.

## 6. Alcance funcional futuro

El modelo debe soportar como mínimo:

- múltiples citas por caso
- múltiples médicos por caso
- múltiples providers por caso cuando el proceso lo requiera
- citas presenciales y virtuales
- anti-solapamiento por staff
- coordinación de citas con meses de anticipación
- reprogramaciones y reconciliación de cambios sin perder trazabilidad

## 7. Implicaciones técnicas mínimas

Sin definir aún implementación detallada, el canon deja asentado que serán necesarias al menos estas capacidades:

- OAuth por actor conectado, ya sea provider o staff
- tokens y estado de conexión por actor
- mapping entre cita interna y evento externo
- storage de `event_id`, `meet_link`, `owner`, `sync_status` y referencias relacionadas
- estrategia de fallback cuando no exista calendar conectado
- conciliación entre cambios hechos en MedTravel y cambios hechos en agenda externa

## 8. Frontera de producto

- MedTravel coordina agenda, comunicación y trazabilidad.
- MedTravel no actúa como prestador clínico.
- MedTravel no sustituye criterio médico.
- La integración con Google Calendar y Google Meet no cambia esa frontera.
- La agenda clínica real sigue perteneciendo al actor tratante responsable del tiempo y de la atención.

## 9. Backlog derivado

- modelo de datos de integración calendar / meet
- UX para conectar cuenta Google por actor
- selección explícita del actor dueño de la cita
- no solapamiento por staff
- múltiples citas por caso
- Meet por cita virtual
- sincronización y reconciliación de cambios
- convivencia transitoria entre agenda general del provider y agenda fina por staff