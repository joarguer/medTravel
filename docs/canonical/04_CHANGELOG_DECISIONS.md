# Changelog Decisions

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
