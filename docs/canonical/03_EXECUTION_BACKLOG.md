# Execution Backlog (alias)

Alias al backlog / pasos de ejecución canónico.

- Ver: [NEXT_STEPS_SERVICES.md](../../NEXT_STEPS_SERVICES.md)

## Frente específico — Comercial / SEO público (Fase 0-4 + microfixes post-deploy)

### Estado consolidado actual

- DONE 2026-04-03 (commit `6d4db96`): Fase 0 SEO/credibilidad baseline en superficies públicas.
- DONE 2026-04-03 (commit `71166d4ceaa073318aecb6f6cacdceb3a0d10e69`): Fase 1A trust copy + hardening de CTA de campaña.
- DONE 2026-04-03 (commit `754b29666f01ddf82457157dd5df633044dd4edb`): Fase 1B/1C autoridad de especialistas + on-page SEO.
- DONE 2026-04-03 (commit `eaa5364`): Fase 2 hardening de conversión + landings de intención:
  - `medical-travel-colombia.php`
  - `medical-travel-armenia-colombia.php`
  - `for-us-patients.php`
- DONE 2026-04-03 (commit `bbbce46`): Fase 3 trust signals + diferenciación anti-canibalización de landings.
- DONE 2026-04-03 (commit `3f38d03`): Fase 4 funnel QA micro polish.
- DONE 2026-04-03 (commit `e9466ad`): microfix post-deploy:
  - ancla `#booking-section` funcional en `booking.php`
  - salida XML limpia en `sitemap.php`
- DONE 2026-04-03 (commit `6362c06`): publicación técnica robusta de `robots.txt` y `sitemap.xml` por rewrite a endpoints PHP.
- DONE 2026-04-03: validación operativa pública:
  - superficie comercial pública activa (home, booking, services, specialists, faq, how-it-works y landings)
  - `robots.txt` y `sitemap.xml` publicados y válidos para UA navegador/bots principales probados

### Pendientes reales de operación (sin refactor)

- [ ] Search Console: envío y monitoreo inicial de `https://medtravel.com.co/sitemap.xml`.
- [ ] Operación de campañas con URLs definidas (general, Colombia, Armenia/Quindío, U.S. patients, booking).
- [ ] Monitoreo inicial de indexación y cobertura de URLs estratégicas.
- [ ] Evaluar ajuste WAF para validadores/crawlers de terceros si negocio lo requiere.

## Frente prioritario — Canonizacion del nuevo modelo operativo MedTravel

### Paso 1 — Canon producto y operacion

- Consolidar en documentacion la separacion formal entre caso, item, cita y coordinacion / pago
- Reubicar la comision como capacidad comercial opcional por proveedor
- Alinear aliases canónicos sin borrar el contexto legacy
- DONE 2026-03-21: queda cerrado a nivel canónico el nuevo modelo de servicios medicos
  - `service_catalog` = catalogo maestro global
  - `provider_catalog_services` = entidad canónica objetivo de `Mis Servicios`
  - `provider_service_offers` = capa comercial / publicable
  - `Staff` = capacidad humana asociada a servicios reales del provider, no a ofertas
- DONE 2026-03-22: queda cerrado a nivel canónico que caso e item no equivalen a una unica cita
- DONE 2026-03-22: queda cerrado a nivel canónico que un caso puede contener multiples citas y multiples responsables clinicos
- DONE 2026-03-22: Google Calendar y Google Meet se canonizan como capacidad de cita dentro de Agenda, no como modulo aparte
- DONE 2026-03-22: queda cerrado a nivel canónico que MedTravel no se diseña como simple booking de servicios sino como plataforma de coordinacion confiable para el paciente internacional
- DONE 2026-03-22: queda cerrada a nivel canónico la frontera de MedTravel como intermediario / facilitador y no como actor clinico tratante

### Frente transversal — Frontera producto vs acto medico

#### Canon cerrado

- DONE 2026-03-22: MedTravel no es prestador directo de servicios medicos
- DONE 2026-03-22: MedTravel no integra el staff medico ni sustituye la relacion clinica paciente-provider
- DONE 2026-03-22: MedTravel no toma decisiones clinicas; esas decisiones pertenecen al provider y al staff tratante
- DONE 2026-03-22: MedTravel si coordina booking, comunicacion, agenda, documentacion operativa, trazabilidad y acompañamiento logistico / operativo
- DONE 2026-03-22: toda futura UX, copy o funcionalidad debe respetar esta frontera

#### Frentes pendientes de alineacion

- [ ] Revisar que booking y detalle de caso no proyecten a MedTravel como actor clinico tratante
- [ ] Revisar que asignacion de staff siga representando decision / estructura del provider y no intervencion clinica de MedTravel
- [ ] Revisar que agenda, Google Calendar y Google Meet se presenten como herramientas de coordinacion de cita y no como sustitutos de la relacion clinica
- [ ] Revisar que patient journey, Inbox y textos de producto mantengan explicita la frontera entre coordinacion operativa y decision medica
- [ ] Aplicar revision continua de labels, mensajes guia, CTAs y estados visibles contra esta frontera antes de cerrar cambios funcionales relevantes
- [ ] Evaluar integracion de generacion documental online solo si el flujo operativo real de MedTravel lo exige (proyecto satelite `generadorDocumentos`)

### Frente transversal — Experiencia del paciente internacional

#### Canon cerrado

- DONE 2026-03-22: la base del producto se define desde la perspectiva del paciente internacional y no desde el servicio aislado
- DONE 2026-03-22: la propuesta de valor canonica incluye confianza, acompañamiento, coordinacion medica, coordinacion logistica, claridad del proceso, seguridad operativa y continuidad
- DONE 2026-03-22: se reconoce canónicamente que el paciente puede requerir multiples medicos, multiples citas y continuidad antes, durante y despues del viaje
- DONE 2026-03-22: se deja asentado que el runtime actual ya cubre parcialmente esta promesa con provider identificado, staff asignable, Inbox, Calendar, detalle de caso y trazabilidad basica
- DONE 2026-04-02: panel unico simplificado del paciente implementado en `client/index.php` + `client/ajax/dashboard_overview.php` + `client/js/dashboard.js`
- DONE 2026-04-02: portal del paciente migrado a ingles (`mis_datos.php` → "My Profile"; nav links actualizados)

#### Frentes pendientes para completar la promesa de experiencia

- [ ] Resolver multiples citas por caso como capacidad operativa nativa
- [ ] Evolucionar agenda fina por medico / staff asignado
- [ ] Integrar Google Calendar y Google Meet como extensiones de la cita
- [ ] Soportar coordinacion multi-medico y multi-provider cuando el caso lo requiera
- [x] Dar mayor claridad al journey completo del paciente (DONE 2026-04-02: panel unico Patient Journey implementado)
- [ ] Mejorar la explicacion visible de quien atiende, como avanza el caso y cual es el siguiente paso para el paciente
- [ ] Endurecer controles para reducir solapamientos, errores de agenda y quiebres de continuidad clinica u operativa

### Frente especifico — Booking asistido por agente

#### Canon cerrado

- DONE 2026-04-02: booking asistido por agente implementado en `admin/booking_asistido.php` + `admin/ajax/booking_asistido.php`
- DONE 2026-04-02: flujo canónico categoria → servicio → oferta aplicado en booking asistido (mismo patron que wizard publico)
- DONE 2026-04-02: trazabilidad de origen del caso: `creation_source`, `created_by_agent`, `agent_channel` en `booking_requests`
- DONE 2026-04-02: gate de aceptacion de terminos del cliente implementado: `client/terms_gate.php` + `client/ajax/accept_terms.php`
- DONE 2026-04-02: campos de auditoria de aceptacion: `terms_accepted`, `terms_accepted_at`, `terms_version`, `terms_ip`, `terms_user_agent` en `usuarios`
- DONE 2026-04-02: aviso contextual en `login.php` y `set_password.php` para clientes con terminos pendientes
- DONE 2026-04-02: social links y URLs publicas unificados en `inc/public_site_links.php`
- DONE 2026-04-02: patron has_column guard canonizado para columnas opcionales tipo `is_deleted`
- DONE 2026-04-03: cierre del frente `administrative` documentado y contrastado contra `sql/medtravelcom_medtravel.sql`
  - scope final: CARE + booking asistido + perfil propio
  - login permitido sin bypass global
  - guard central de clientes consistente
  - migración versionada para `role_permissions`: `sql/2026_04_03_administrative_role_permissions_scope.sql`

#### Deuda pendiente de este frente

- [ ] Agregar campo `agent_channel` al listado de solicitudes admin para visibilidad operativa del origen del caso
- [ ] Definir politica de reenvio de credenciales para casos asistidos cuando el email no llega o el paciente no completa el gate
- [ ] Auditar el flujo de booking asistido cuando el email del paciente ya existe como usuario interno con otro rol
- [ ] Ejecutar smoke post-deploy en servidor real tras aplicar la migración del rol `administrative`

### Frente especifico — Sincronizacion item ↔ cita y atributos pendientes del item

#### Canon cerrado

- DONE 2026-04-02: sincronizacion minima implementada: `google_calendar_sync_item_status_for_transition`, `google_calendar_sync_booking_request_rollups`, `google_calendar_sync_item_status_from_event_status`
- DONE 2026-04-02: mapeo canónico: proposed/scheduled → `appointment_proposed`, confirmed → `appointment_confirmed`, cancelled → `appointment_cancelled`, reschedule → `appointment_requested_change`
- DONE 2026-04-02: normalizacion de estados legacy `pending_admin` / `pending_review` → `pending_provider`

#### Atributos del item/cita formalizados (DONE)

- DONE 2026-04-02 (commit `32e2c30`): `appointment_mode` formalizado en `calendar_events` (`virtual`, `in_person`, `travel`) con fallback de compatibilidad
- DONE 2026-04-02 (commit `87748d4`): `treatment_completed` formalizado como estado real de `booking_request_items`
- DONE 2026-04-02 (commit `16cac36`): `post_treatment_follow_up` formalizado como estado real de `booking_request_items`
- DONE 2026-04-02: pipeline operativo del item extendido para distinguir tratamiento realizado vs seguimiento post tratamiento

#### Pendientes de endurecimiento

- [ ] Endurecimiento del admin/inbox donde aun existe mezcla semantica entre acciones de comunicacion y cambios de estado del item
- [ ] Definir que acciones del inbox pueden disparar sincronizacion de estado del item y cuales son solo comunicacion libre (avance parcial: commit `4c9a142` — confirmacion de reunion desde inbox y `final_accept_and_pay` disparan sync; politica exhaustiva para otras acciones pendiente)

### Frente especifico — Ownership operativo por staff asignado

#### Canon cerrado (2026-04-02)

- El staff asignado debe tender a ser el owner operativo del item despues de la asignacion.
- Antes de la asignacion, el owner operativo es el provider/admin del prestador.
- DONE MVP 2026-04-02 (commit `7f67648`): ownership operativo visible implementado en `admin/my_booking_requests.php`:
  - columna "Responsable" con nombre del staff o fallback al provider admin
  - chip de modo: `Responsable actual`, `Supervisión`, `Seguimiento del staff`, `Sin asignación clínica`
  - aviso contextual antes de acciones cuando el actor no es el owner del item
  - campo `linked_staff_auto_claim_available` preparado para futura auto-asignacion
- DONE 2026-04-02 (commit `69e62dc`): runtime staff reforzado en superficies principales:
  - navegacion operativa asignada
  - inbox asignado
  - agenda asignada
  - scope de agenda por `booking_request_items.assigned_staff_id`
- DONE 2026-04-09: en `Inbox` ITEM, el staff asignado con scope operativo valido no hereda automaticamente el gate comercial del provider; `Calendar` no cambia por arrastre
- DONE 2026-04-02 (commit `9c05fdb`): asignacion inicial de staff alineada entre booking publico y booking asistido:
  - autoasignacion solo cuando existe un unico staff elegible
  - sin asignacion cuando hay multiples elegibles o ninguno

#### Pendientes de formalizacion tecnica completa

- [ ] Formalizar el rol tecnico `provider_staff` (Paso 6 — sin reutilizar ROLE_PROVIDER ni ROLE_PROVIDER_ADMIN)
- [ ] Completar RBAC total de staff en todo el admin (no solo superficies reforzadas)
- [ ] Implementar persistencia de auto-asignacion cuando `linked_staff_auto_claim_available = 1` y el staff actua
- [ ] Extender logica de ownership visible a otras superficies admin: inbox, detalle de solicitud, app_calendar
- [ ] Notificaciones dirigidas al staff asignado tras la asignacion (email / inbox)

### Bloque pre-smoke integral (pendiente obligatorio)

- [ ] Ejecutar `sql/2026_04_02_calendar_events_appointment_mode.sql`
- [ ] Ejecutar `sql/2026_04_02_booking_request_items_treatment_completed.sql`
- [ ] Ejecutar `sql/2026_04_02_booking_request_items_post_treatment_follow_up.sql`
- [ ] Correr smoke test end-to-end integral despues de migraciones
- [ ] Publicar observabilidad Fase 1 del bloque staff/lifecycle (checklist de validacion y resultados)
- [ ] Definir fase terminal/cierre final del lifecycle del item para cierre canónico completo

### Frente especifico — Servicios medicos, staff y ofertas

#### Canon cerrado

- DONE 2026-03-21: `Mis Servicios` se redefine oficialmente como capacidad medica real habilitada del provider
- DONE 2026-03-21: `service_catalog` se declara diccionario maestro global y deja de interpretarse como `Mis Servicios`
- DONE 2026-03-21: `provider_catalog_services` se declara entidad canónica objetivo para operacion futura
- DONE 2026-03-21: `provider_service_offers` se ratifica como capa comercial / publicable
- DONE 2026-03-21: `Staff` se ratifica como dependiente de `Mis Servicios` y no de ofertas

#### Lo que ya esta razonablemente alineado

- `Mis Ofertas` ya esta razonablemente alineado como capa comercial
- `Staff` ya esta semanticamente orientado a servicios y no a ofertas
- La separacion conceptual entre `Mis Servicios` y `Mis Ofertas` ya existe en copy y navegacion, aunque no este resuelta por completo en persistencia

#### Deuda de modelo

- [ ] Redefinir efectivamente `Mis Servicios` sobre `provider_catalog_services` como entidad operativa fuerte y no solo como tabla puente
- [ ] Ampliar atributos de `provider_catalog_services` para soportar capacidad medica real del provider
- [ ] Introducir clasificacion estructural minima en `provider_catalog_services`
  - nivel de atencion
  - tipo de servicio asistencial
- [ ] Resolver que la clasificacion operativa efectiva viva en el servicio habilitado del provider y no solo en el catalogo maestro global
- [ ] Formalizar la nocion de sede / branch a nivel de servicio habilitado cuando corresponda
- [ ] Evaluar convivencia o migracion entre `calendar_capacity` global del provider y una futura capacidad fina por staff / servicio para agenda y solapamientos

### Frente especifico — Citas, agenda y capacidades externas

Documento de referencia:
- Ver [05_CALENDAR_MEET_INTEGRATION_MODEL.md](05_CALENDAR_MEET_INTEGRATION_MODEL.md)

#### Canon cerrado

- DONE 2026-03-22: una solicitud / caso no equivale a una sola cita
- DONE 2026-03-22: un caso puede contener multiples citas
- DONE 2026-03-22: cada cita debe poder asociarse a item clinico, staff asignado, provider, fecha/hora y modalidad
- DONE 2026-03-22: Google Calendar event y Google Meet link se canonizan como atributos / capacidades de cita cuando aplique
- DONE 2026-03-22: Inbox sigue siendo comunicacion y Calendar sigue siendo agenda
- DONE 2026-03-22: la validacion futura de agenda debe operar por medico / staff asignado y no solo por provider global
- DONE 2026-03-22: un caso puede involucrar varios medicos y, si aplica, varios providers
- DONE 2026-03-23: la Fase 1 de integracion Google Calendar / Meet se canoniza con organizer inicial en admin autenticado de MedTravel usando Google Calendar API + Meet al crear evento
- DONE 2026-03-23: la Fase 2 se reserva para Google Meet API avanzada y metadatos extendidos
- DONE 2026-03-23: seguridad minima canonizada para OAuth admin: tokens separados por admin, refresh token cifrado, validacion `state`, scopes minimos y sin secretos en frontend
- DONE 2026-03-23: queda canonizado que aceptacion funcional en MedTravel no equivale a consentimiento OAuth Google
- DONE 2026-03-23: queda canonizado que Fase 1 opera aunque solo admin MedTravel tenga conexion Google; paciente y provider / staff pueden asistir como invitados sin conectar Google
- DONE 2026-03-23: queda canonizado que fases posteriores pueden habilitar conexion Google opcional por actor con aislamiento por usuario y scopes concedidos

#### Deuda de modelo

- [ ] Definir entidad canonica de cita preparada para multiples citas por item clinico
- [ ] Formalizar relacion cita -> item clinico -> staff asignado -> provider
- [ ] Incorporar modalidad de cita presencial / virtual como atributo estructural
- [ ] Preparar soporte para referencia externa de Google Calendar por cita
- [ ] Preparar soporte para Meet link por cita cuando corresponda
- [ ] Persistir organizer admin MedTravel de Fase 1 y referencias externas por cita
- [ ] Persistir conexiones OAuth Google separadas por admin con cifrado de refresh token
- [ ] Definir UX canónica para solicitar conexión Google adicional cuando un actor acepte en MedTravel pero aún no haya autorizado OAuth
- [ ] Definir modelo canónico de conexiones OAuth opcionales por actor manteniendo aislamiento entre admin, provider, staff y paciente
- [ ] Resolver trazabilidad entre acciones del Inbox y estados de cita sin mezclar dominios
- [ ] Definir politica canonica de no solapamiento por medico / staff asignado
- [ ] Definir convivencia transitoria entre `calendar_capacity` global y futura disponibilidad fina por staff

#### Tareas de transicion

- [ ] Evolucionar agenda desde foco global por provider hacia foco operativo por medico / staff asignado
- [ ] Asegurar que una futura cita pueda existir sin asumir unicidad por caso
- [ ] Asegurar que un caso pueda mantener varias citas activas o historicas sin romper Inbox ni timeline
- [ ] Implementar Fase 1 con Google Calendar API + Meet al crear evento desde admin MedTravel
- [ ] Diseñar storage y callbacks OAuth 2.0 Web Server Flow para admins MedTravel
- [ ] Invitar a paciente y provider / staff como attendees sin convertir a MedTravel en actor clinico
- [ ] Diseñar el paso adicional de conexion Google cuando un flujo futuro requiera sincronizacion sobre un actor que ya acepto en MedTravel pero no otorgo OAuth
- [ ] Diseñar Fase 2 para Google Meet API avanzada sin bloquear la salida de Fase 1
- [ ] Diseñar integracion con Google Calendar / Meet como extension del flujo de cita, no como modulo paralelo
- [ ] Definir estrategia de compatibilidad con runtime actual de `app_calendar.php` y `admin/ajax/calendar.php`

#### Tareas de transicion

- [ ] Hacer que ofertas dependan de la entidad canónica del servicio habilitado del provider y no directamente de `service_catalog.id`
- [ ] Hacer que staff dependa tecnicamente de la entidad canónica provider-service y no del servicio global
- [ ] Unificar las rutas de habilitacion de servicios
  - eliminar la ambiguedad entre alta desde `providers`
  - y alta / edicion desde `Mis Servicios`
- [ ] Separar alta del diccionario maestro global vs habilitacion de capacidad real del provider
- [ ] Definir estrategia de compatibilidad transitoria para referencias legacy al servicio global

#### Tareas de integridad

- [ ] Endurecer integridad para impedir que una oferta se cree sobre un servicio no habilitado para ese provider
- [ ] Endurecer integridad para impedir asignaciones de staff fuera del universo real de servicios habilitados del provider
- [ ] Revisar ownership / trazabilidad de servicios globales hoy creados desde contexto provider
- [ ] Consolidar una sola fuente operativa para `Mis Servicios`

#### Tareas UI / copy

- [ ] Ajustar copy de `Mis Servicios` para dejar explicito que no es el catalogo maestro global
- [ ] Ajustar copy de `Mis Ofertas` para reforzar que una oferta no define capacidad medica
- [ ] Ajustar copy de `Staff` para reforzar que atiende servicios reales del provider
- [ ] Revisar labels, ayudas y mensajes de validacion en `providers`, `Mis Servicios`, `Mis Ofertas` y `Staff`
- [ ] Alinear textos de ayuda con la clasificacion futura por nivel de atencion y tipo asistencial
- [ ] Mantener alineado el copy de `calendar_capacity` con su semantica real actual: limite global de concurrencia en agenda, no capacidad por medico / staff / sede

### Frente transversal — UX tecnica de notificaciones admin

#### Canon cerrado

- DONE 2026-03-22: el admin adopta oficialmente `toastr` de Metronic, o wrapper equivalente, como sistema estándar exclusivo para feedback de usuario
- DONE 2026-03-22: queda prohibido introducir `alert()` o popups nativos para errores, warnings, success e info en flujos normales del admin
- DONE 2026-03-22: `providers.js` queda alineado como caso implementado de esta regla

#### Deuda tecnica de migracion

- [ ] Migrar progresivamente modulos legacy del admin que todavia usan `alert()` hacia `toastr` o wrapper equivalente
- [ ] Priorizar la migracion en modulos que se toquen por fixes o evolutivos para evitar refactors amplios sin necesidad
- [ ] Revisar consistencia de severidades y titulos de notificacion entre modulos admin ya migrados

### Frente especifico — Onboarding medico, ownership e identidad administrativa

#### Canon cerrado

- DONE 2026-03-21: `providers.php` queda declarado canónicamente como alta inicial del provider medico y de su owner/admin inicial
- DONE 2026-03-21: `staff_medico.php` queda declarado canónicamente como alta de staff medico y de su acceso al panel cuando aplique
- DONE 2026-03-21: `crear_usuario.php` deja de ser flujo canónico para onboarding del dominio medico principal
- DONE 2026-03-21: `usuarios.id = 1` queda protegido como superusuario global fuera de flujos de reciclaje / reutilizacion
- DONE 2026-03-22: el onboarding owner/admin de `providers.php` queda alineado a patrón email-first + invitación de set-password, sin credenciales manuales en el alta

#### Deuda de modelo

- [ ] Formalizar tecnicamente la relacion explicita provider -> owner/admin inicial
- [ ] Eliminar dependencia de ownership inferido por `LIMIT 1` o por "primer usuario encontrado"
- [ ] Resolver de forma canónica la convivencia entre `usuarios.provider_id` y `provider_users`
- [ ] Unificar el modelo de alta de identidad medica administrativa para que no existan dos puertas principales compitiendo

#### Tareas de transicion

- [ ] Alinear `providers.php` con mecanismo explicito de ownership del provider medico
- [ ] Restringir, deprecatear o marcar como legacy `crear_usuario.php` para el dominio medico principal
- [ ] Mantener `crear_usuario.php` solo para usuarios adicionales / auxiliares mientras exista necesidad operativa
- [ ] Revisar el flujo de lectura de sesion / scope para que el owner/admin del provider deje de depender de heuristicas legacy ambiguas
- [ ] Preparar capa posterior de RBAC / scope fino una vez quede resuelta la fuente de verdad del ownership

#### Tareas de integridad

- [ ] Impedir que el superusuario global entre en flujos de reutilizacion de cuentas para staff
- [ ] Impedir que el owner/admin inicial del provider pueda quedar desalineado entre tablas de identidad / ownership
- [ ] Garantizar que el alta canónica del staff siga naciendo solo desde `staff_medico.php`

### Paso 2 — Ajuste de vocabulario UI

- Estandarizar estados visibles de negocio para operacion
- Alinear acciones oficiales del item:
  - Aceptar caso
  - Rechazar caso
  - Solicitar informacion
  - Proponer cita
- Formalizar ingles para front publico / paciente y espanol por defecto para admin operativo en Colombia

### Paso 3 — Modelo operativo del staff medico

- DONE MVP 2026-03-20 (commits `3f4dd99`, `decc1c5`): se materializa la separacion formal entre prestador y medico o staff interno mediante `provider_medical_staff`
- DONE MVP 2026-03-20: existe persistencia formal, CRUD admin por prestador, activacion / desactivacion y `sort_order`
- DONE MVP 2026-03-20: queda preparado el helper y la forma de exposicion futura para mostrar por item prestador, doctor o staff, clinica / sede y estado de cita
- DONE 2026-03-20 (commit `b96bb3e`): `staff_medico.php` separado formalmente de `mi_empresa.php` como pagina independiente dentro del dominio Mi Empresa
- DONE 2026-03-20 (commit `ca4c634`): integracion visual y navegacion de staff pulidas
- DONE 2026-03-20 (commits `9204a82`, `8321a96`): navegacion del prestador reorganizada por dominios funcionales — Operacion, Servicios, Presencia, Mi Empresa
- SUPERSEDED 2026-03-21 por canon formal: `Mis Servicios` ya no debe describirse como simple catalogo habilitado del proveedor, sino como capacidad medica real habilitada con `provider_catalog_services` como entidad canónica objetivo
- DONE 2026-03-20 (commit `0e5a97f`): catalogos de roles y especialidades del modal de staff alineados con servicios reales del proveedor y servidos por AJAX
- DONE 2026-03-20 (commit `183c84d`): persistencia de catalogos por proveedor mediante `provider_staff_roles` y `provider_staff_specialties`. Entradas de sistema (provider_id=NULL) y personalizadas por proveedor. CRUD admin en `staff_catalogs.php`. Fallback a arrays hardcoded si las tablas no existen
- DONE 2026-03-22: `save_staff` valida `role_title` y `specialty` contra catalogo activo del prestador/sistema; registros legacy pueden conservar su valor historico en edicion, pero ya no se aceptan nuevos valores libres fuera de catalogo
- DONE 2026-03-20: foto del profesional reemplaza campo texto por upload real de archivo (JPG/PNG/WebP ≤ 2 MB), validacion MIME via finfo, preview en modal y almacenamiento en `uploads/staff_photos/`
- DONE 2026-03-22: se cierra a nivel canónico que la fila sintética del owner/admin en `staff_medico.php` sirve solo para visibilidad operativa; no alcanza para booking asignable porque `booking_request_items.assigned_staff_id`, el enrichment clínico y el scope futuro de staff dependen de `provider_medical_staff` físico enlazado por `linked_user_id`
- DONE 2026-03-22: se cierra a nivel canónico que `providers.php` no es UX del provider para autoconvertirse en staff; en provider tipo `medico` / persona el espejo owner/admin → `provider_medical_staff` debe materializarse automáticamente como efecto interno del onboarding administrativo central
- DONE 2026-03-22: se cierra a nivel canónico que en provider tipo `clinica` no debe materializarse automáticamente al owner/admin como staff clínico; esa conversión queda como acción explícita futura del dominio provider
- Scope MVP cerrado:
  - sin agenda compleja
  - sin calendar sync
  - sin cambios al commission system fuera de compatibilidad legacy

#### Siguiente frente funcional de transicion

- [ ] Implementar espejo operativo del owner/admin en `provider_medical_staff` cuando deba ser recurso clínico asignable
- [ ] Recomendacion mínima: espejo automático al menos para providers de tipo `medico` / persona
- [ ] Validar end-to-end booking asignable usando esa representación física
- [ ] Garantizar que la oferta / item quede asignado al staff correcto mediante `booking_request_items.assigned_staff_id`
- [ ] Mantener explícita la separación conceptual entre owner/admin y staff aunque exista espejo operativo
- [ ] Definir para providers tipo `clinica` una UX operativa explícita para convertir owner/admin en staff clínico cuando aplique
- [ ] Evaluar copy y acción futura tipo `Agregarme como staff` o `Este administrador también atiende pacientes` en `staff_medico.php` o `mi_empresa.php`

### Paso 6 — Acceso del staff al panel admin (PENDIENTE — siguiente frente)

Alcance definido, implementacion no iniciada:

- [ ] Smoke test funcional del modulo de catalogos del staff post-`183c84d`:
  - alta de rol personalizado y disponibilidad inmediata en modal de staff
  - alta de especialidad personalizada y disponibilidad inmediata en modal de staff
  - proteccion de entradas de sistema (no editables / no eliminables desde UI de proveedor)
  - compatibilidad con valores legacy ya guardados en `role_title` / `specialty`
- [ ] Nuevo rol `provider_staff` en `roles.php` — no debe reutilizar ROLE_PROVIDER ni ROLE_PROVIDER_ADMIN
- [ ] Landing MVP para staff con acceso propio: "Mis solicitudes asignadas"
- [ ] Scope duro de acceso por `booking_request_items.assigned_staff_id`
- [ ] Relacion de autenticacion: `usuarios.id -> provider_medical_staff.linked_user_id` (campo ya existe en tabla)

### Paso 7 — Servicios medicos del provider como entidad operativa fuerte (PENDIENTE)

- [ ] Declarar e implementar `provider_catalog_services` como entidad operativa fuerte de `Mis Servicios`
- [ ] Desacoplar progresivamente staff y ofertas de `service_catalog.id`
- [ ] Introducir clasificacion por nivel de atencion y tipo asistencial
- [ ] Resolver integridad y UX de la transicion sin romper runtime legacy

### Paso 4 — Trazabilidad estructurada

- Formalizar event log por caso e item
- Asegurar timeline con eventos de negocio y no solo de persistencia tecnica
- Vincular propuesta de cita, confirmacion, reprogramacion, cancelacion y solicitud de informacion

### Paso 5 — Hardening legacy

- Mantener compatibilidad con estados y flujos legacy
- Revisar contradicciones entre gate de comision y modelo operativo principal
- Asegurar que proveedores sin comision sigan operando sin ramas especiales fragiles

## Backlog subordinado — Comision comercial por proveedor

- DONE: Commission payments managed in booking_requests admin modal (admin-only)
- DONE: Persist item price/currency in booking_request_items for commission calculations (no manual SQL)
- Admin UI for provider commission configuration
- Stripe checkout integration for commission payments
- Stripe webhook handling for payment confirmation
- Commission reporting dashboard
- Provider payout reconciliation

Este backlog se mantiene como frente comercial / tecnico complementario. Ya no debe interpretarse como la prioridad arquitectonica principal del producto.
