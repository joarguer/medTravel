# Product Model (alias)

Este archivo es un alias que apunta al documento canónico del modelo de negocio.

- Ver: [MODELO_NEGOCIO_ACTUALIZADO.md](../../MODELO_NEGOCIO_ACTUALIZADO.md)

## Arquitectura operativa oficial de MedTravel

MedTravel se canoniza como una plataforma de gestion de casos de turismo medico y servicios asociados. El producto no debe describirse solo como un flujo de solicitud + chat + commission unlock, sino como una operacion estructurada donde un caso puede contener multiples items, citas, pasos de coordinacion y capas comerciales opcionales.

### Principios oficiales del modelo

- El eje primario del producto es el caso operativo del paciente, no la comision.
- Caso, item de servicio, cita y coordinacion/pago son dimensiones distintas y deben poder evolucionar por separado.
- Prestador y medico o staff medico interno del prestador no son la misma entidad operativa.
- MedTravel actua como intermediario / facilitador operativo entre paciente y provider; no es prestador directo de actos medicos.
- MedTravel no integra el staff medico del provider, no toma decisiones clinicas y no sustituye la relacion clinica entre paciente y medico / provider tratante.
- La UI operativa debe mostrar estados visibles de negocio comprensibles para operacion, aunque existan estados tecnicos internos adicionales.
- La trazabilidad de cada item debe soportar historial operativo, responsables, citas y eventos relevantes.
- La comision o coordination gate es una capacidad comercial complementaria y configurable, no una regla universal del sistema.

### Fundamento de experiencia del paciente internacional

- MedTravel no debe modelarse como simple catalogo ni como booking aislado de servicios.
- El paciente internacional no confia a MedTravel solo una cita o un procedimiento; confia su salud, su tiempo, su dinero, su viaje y su seguridad operativa.
- Esa confianza no convierte a MedTravel en actor clinico tratante; el limite canónico del producto sigue siendo coordinacion, acompañamiento, orden y trazabilidad operativa.
- La propuesta de valor real del producto se fundamenta en:
  - confianza
  - acompañamiento
  - coordinacion medica
  - coordinacion logistica
  - claridad del proceso
  - seguridad operativa
  - continuidad antes, durante y despues del viaje
- La experiencia objetivo del paciente debe ofrecer como minimo:
  - claridad de quien lo atendera
  - claridad del servicio o proceso clinico
  - trazabilidad del caso
  - comunicacion humana y simple
  - agenda ordenada
  - continuidad del tratamiento
  - no solapamientos ni errores operativos evitables
  - posibilidad de multiples medicos y multiples citas cuando el proceso lo requiera
- Las capacidades actuales del producto ya cubren parcialmente esa promesa mediante provider identificado, servicio y oferta enlazados, staff real asignable, detalle de caso, Inbox, Calendar, asignacion de staff y trazabilidad basica.
- Las futuras decisiones de arquitectura, UX y operacion no deben degradar MedTravel a un simple marketplace transaccional; el norte canónico es una plataforma confiable de coordinacion de procesos medicos internacionales.

### Frontera canónica entre coordinacion y acto medico

- MedTravel no es prestador directo de servicios medicos.
- MedTravel no hace parte del staff medico del provider ni del equipo tratante del paciente.
- MedTravel no presta actos medicos, no emite criterio clinico y no interviene en decisiones medicas.
- Las decisiones medicas pertenecen al provider y al medico / staff tratante responsable del caso o del item clinico.
- MedTravel si coordina y facilita, como minimo:
  - booking
  - comunicacion
  - agenda
  - documentacion operativa
  - trazabilidad
  - acompañamiento logistico / operativo
- Toda futura UX, funcionalidad, integracion o copy debe respetar esta frontera.
- Esta regla aplica especialmente a booking, asignacion de staff, agenda, Google Calendar / Meet, patient journey, comunicaciones y textos del producto.
- Esta frontera debe verificarse de forma recurrente en labels, mensajes guia, nombres de acciones, estados visibles y textos operativos del producto.

### Separacion formal de entidades operativas

#### Caso

- Representa el expediente operativo principal del paciente dentro de MedTravel.
- Agrupa contexto general, notas, items, conversacion transversal y pasos de coordinacion.
- Puede existir aunque todavia no haya una cita confirmada.

#### Item de servicio

- Es la unidad operativa accionable dentro del caso.
- Un caso puede tener multiples items medicos y/o complementarios.
- Cada item debe poder tener prestador asignado, estado visible, acciones oficiales, trazabilidad y potencial relacion con una o varias citas.

#### Cita

- Es un evento operativo separado del item.
- Un caso NO equivale a una sola cita.
- Un caso puede contener multiples citas a lo largo de su evolucion clinica u operativa.
- Una cita puede ser propuesta, confirmada, reprogramada o cancelada sin redefinir por si sola todo el caso.
- Cada cita debe poder asociarse como minimo a:
  - item clinico
  - medico / staff asignado
  - provider responsable
  - fecha y hora
  - modalidad presencial o virtual
  - event id externo de Google Calendar cuando exista integracion
  - Meet link cuando la modalidad o el flujo lo requiera
- El sistema debe soportar trazabilidad entre item y cita aunque la implementacion tecnica siga evolucionando.
- Google Calendar y Google Meet no constituyen un modulo aparte del producto; se integran como capacidad de la cita dentro del dominio Agenda.
- La validacion futura de disponibilidad y no solapamiento debe evolucionar desde control global por provider hacia control por medico / staff asignado.
- Un mismo caso puede involucrar varias citas, varios medicos y, cuando el proceso clinico lo requiera, varios providers.

#### Coordinacion / Pago

- Cubre pasos operativos o comerciales posteriores a la aceptacion del caso o del item.
- Puede incluir desbloqueos de contacto, pagos de coordinacion, validaciones internas o tareas administrativas.
- No reemplaza la atencion medica ni la gestion del caso.

#### Prestador

- Es la entidad proveedora principal visible para asignacion contractual y operativa.
- Puede ser un medico individual, una clinica o un proveedor de servicio complementario.
- Es responsable del item frente al caso, incluso cuando la atencion concreta recaiga en staff interno.

#### Medico / Staff interno

- Es la persona o equipo interno del prestador que ejecuta revision, consulta, procedimiento o seguimiento.
- Se separa formalmente del prestador mediante la tabla `provider_medical_staff` (implementada y operativa desde 2026-03-20).
- Puede tener usuario propio vinculado mediante `provider_medical_staff.linked_user_id → usuarios.id`.
- El staff NO debe reutilizar `ROLE_PROVIDER` ni `ROLE_PROVIDER_ADMIN` para autenticarse.
- El acceso del staff al panel debe ser restringido por asignacion de items/casos (`booking_request_items.assigned_staff_id`), no solo por `provider_id`.

## Actores oficiales con acceso al sistema

### Superusuario MedTravel

- Corresponde al superusuario global del sistema.
- `usuarios.id = 1` queda protegido como cuenta raiz con acceso total.
- No depende de `provider`, `staff` ni relaciones de scope medico.
- No debe ser reciclable ni reutilizable desde flujos de aprovisionamiento de staff o de prestadores.

### Provider / medico principal

- Es la cuenta owner/admin inicial del prestador medico.
- Representa la identidad administrativa primaria del provider frente al sistema.
- Su onboarding canónico nace desde el alta del provider medico.
- No debe inferirse por "primer usuario encontrado" ni por consultas `LIMIT 1`.

### Staff / medico

- Es personal interno del provider, distinto del owner/admin inicial.
- Su alta canónica nace desde `staff_medico.php`.
- Si necesita acceso al panel, ese acceso debe aprovisionarse desde el flujo de staff.
- Su relacion de identidad se formaliza por `provider_medical_staff.linked_user_id`.
- El owner/admin inicial puede mostrarse en el listado operativo de `staff_medico.php` como fila de solo lectura por visibilidad del equipo.
- Esa fila sintética resuelve solo visibilidad operativa y no constituye representación clínica asignable.
- Para booking asignable, enrichment clínico y scope futuro de staff, la representación válida sigue siendo un registro físico en `provider_medical_staff` vinculado por `linked_user_id`.
- El owner/admin NO se asigna manualmente a sí mismo como staff desde `providers.php`; ese flujo pertenece al onboarding administrativo central de MedTravel y no a una UX operativa del provider.
- Cuando el provider sea de tipo `medico` / persona, el sistema debe materializar automáticamente al owner/admin como espejo operativo en `provider_medical_staff` para volverlo recurso clínico asignable.
- Cuando el provider sea de tipo `clinica`, no debe asumirse automáticamente que el owner/admin atiende pacientes ni materializarlo como staff clínico por defecto.
- Para `clinica`, esa conversión queda como acción explícita futura del dominio provider en un módulo operativo como `staff_medico.php` o `mi_empresa.php`, no en onboarding central.
- Ese espejo no reemplaza la separación conceptual owner/admin vs staff; solo resuelve interoperabilidad clínica y de booking.

### Cliente / paciente

- Mantiene flujo propio y separado del onboarding medico.
- No debe mezclarse con ownership del provider ni con alta de staff.

### Provider / servicios suplementarios

- Mantiene flujo propio y separado del provider medico principal.
- No debe compartir onboarding canónico con el dominio medico principal.

## Canon oficial de onboarding e identidad administrativa

### Flujo canónico por dominio

- `providers.php` es el flujo canónico para alta inicial del provider medico y queda reservado a MedTravel admin.
- `providers.php` debe crear o dejar explicitada la cuenta owner/admin inicial del provider medico.
- `staff_medico.php` es el flujo canónico para alta de staff medico, asignacion de servicios del staff y aprovisionamiento de acceso del staff cuando aplique.
- `crear_usuario.php` NO es onboarding canónico del dominio medico principal.

### Rol de `crear_usuario.php`

- Puede seguir existiendo como flujo de usuarios adicionales / auxiliares.
- Puede mantenerse como flujo restringido o legacy mientras se completa la transicion.
- No debe competir con `providers.php` para el alta del owner/admin inicial del provider medico.
- No debe competir con `staff_medico.php` para el alta de staff medico ni para su acceso al panel.

### Ownership oficial del provider

- El sistema necesita una relacion explicita y consistente entre `provider` y su owner/admin inicial.
- Esa relacion no debe seguir dependiendo de inferencias como:
  - `usuarios.provider_id` interpretado implicitamente
  - `provider_users` usado solo de forma parcial
  - "primer usuario del provider"
- El canon exige ownership explicito; la forma tecnica final se define en capa de arquitectura / backlog y no debe volver a quedar ambigua.

### Entidades operativas visibles

- Caso
- Item de servicio
- Cita
- Coordinacion / Pago
- Prestador
- Medico / Staff interno
- Conversacion
- Event log / timeline

### Regla canónica de comunicacion vs agenda

- Inbox sigue siendo el canal oficial de comunicacion del caso.
- Calendar sigue siendo el modulo oficial de agenda y gestion de citas.
- La propuesta, confirmacion, reprogramacion o cancelacion de una cita debe entenderse como accion del dominio Agenda, aunque su contexto tambien deba reflejarse en Inbox y en la trazabilidad del caso.
- Google Calendar y Google Meet deben integrarse como extensiones de la capacidad de cita dentro de Calendar, no como un producto o modulo independiente.

### Regla canónica de relacion caso -> items -> citas

- Un caso puede agrupar multiples items medicos y/o complementarios.
- Un item clinico puede requerir cero, una o multiples citas.
- Una cita pertenece al contexto operativo de un item clinico concreto, aunque el caso completo pueda involucrar varios items y varios responsables.
- El paciente no debe asumirse ligado a un unico medico ni a un unico provider durante toda la vida del caso.

### Estados visibles oficiales del item

Estos estados son de negocio y deben guiar la UI operativa. Pueden convivir con estados tecnicos internos adicionales.

- `pending_provider`
- `provider_reviewing`
- `needs_more_info`
- `doctor_assigned`
- `date_proposed`
- `date_confirmed`
- `rescheduled`
- `completed`
- `cancelled`

### Acciones oficiales del item

Las acciones de negocio oficialmente reconocidas para un item son:

- Aceptar caso
- Rechazar caso
- Solicitar informacion
- Proponer cita

Estas acciones deben reflejarse en vocabulario UI y trazabilidad, incluso si internamente algunas implementaciones actuales aun usan nombres tecnicos legacy.

### Navegacion del prestador por dominios funcionales

Desde 2026-03-20 la navegacion del panel para prestadores medicos esta organizada en cuatro dominios:

- **Operacion**: solicitudes, inbox, agenda, clientes/pacientes.
- **Servicios**: catalogo de servicios habilitados y publicaciones comerciales.
- **Presencia**: blog del prestador.
- **Mi Empresa**: datos de empresa, staff medico, catalogos del staff, perfil.

Semantica canonizada:
- **Mis Servicios** = capacidad medica real habilitada del provider.
- **Mis Ofertas** = publicaciones comerciales visibles al paciente sobre servicios habilitados del provider.

Esta distincion debe mantenerse consistente en UI, textos de ayuda y documentacion.

## Canon oficial de servicios medicos, staff y ofertas

MedTravel separa formalmente tres capas distintas para el dominio medico. Esta separacion ya es obligatoria a nivel de producto y documentacion, aunque el runtime siga cerrando deuda tecnica.

### Diccionario maestro global

- `service_catalog` es el diccionario maestro global de servicios medicos de MedTravel.
- Su funcion es normalizar nombres, sostener taxonomia base y reducir duplicados semanticos.
- `service_catalog` NO debe interpretarse como `Mis Servicios` del provider.
- La categoria clinica global existente puede seguir viviendo aqui como taxonomia base comun.

### Servicio habilitado del provider

- `provider_catalog_services` es la entidad canónica objetivo de `Mis Servicios`.
- `Mis Servicios` representa la capacidad medica real habilitada del provider dentro de MedTravel.
- Esta entidad debe ser la unidad base operativa para staff, matching clinico, ofertas y operacion futura.
- Aunque hoy el runtime la trate en varios puntos como una tabla puente minima, el canon oficial declara que este es el norte correcto del modelo.

### Oferta comercial / publicable

- `provider_service_offers` se mantiene como la capa comercial / publicable.
- Una oferta representa la comercializacion de uno o mas servicios habilitados del provider.
- Una oferta NO define capacidad medica.
- La capacidad medica real del provider define que ofertas se pueden crear.

### Relacion oficial entre las tres capas

- `service_catalog` -> diccionario maestro global
- `provider_catalog_services` -> servicio habilitado del provider / capacidad medica real
- `provider_service_offers` -> capa comercial / publicable derivada de esa capacidad

Formula canónica:
- `service_catalog` normaliza
- `provider_catalog_services` habilita y opera
- `provider_service_offers` publica y comercializa

### Regla oficial para Staff

- El staff medico debe relacionarse con `Mis Servicios`, no con ofertas.
- El staff atiende servicios clinicos reales del provider, no piezas comerciales.
- La separacion entre prestador y medico / staff interno se mantiene como regla de producto y de operacion.

### Regla oficial de clasificacion estructural

- La clasificacion operativa efectiva debe vivir en `Mis Servicios`, no en `Mis Ofertas`.
- Cada servicio habilitado del provider debe poder clasificarse al menos por dos ejes:
  - nivel de atencion: primer nivel, segundo nivel, tercer nivel, cuarto nivel
  - tipo de servicio asistencial: consulta externa, hospitalizacion y cirugias, apoyo diagnostico / terapeutico
- La categoria clinica global puede seguir existiendo en el catalogo maestro, pero no reemplaza la clasificacion operativa del servicio habilitado del provider.

### Naturaleza de la capacidad medica real

`Mis Servicios` representa la capacidad real habilitada de prestacion del provider segun su naturaleza operativa. Esto incluye, como minimo:

- clinica
- medico general
- medico especialista
- prestador diagnostico / terapeutico
- otro actor asistencial valido dentro del modelo MedTravel

La documentacion, el copy y la arquitectura futura no deben volver a mezclar el catalogo maestro global con la capacidad especifica del provider.

### Regla oficial de idioma por contexto

- Front publico internacional / paciente: ingles.
- Admin operativo para proveedores, medicos y servicios complementarios en Colombia: espanol por defecto.
- Se permiten terminos tecnicos en ingles cuando ya forman parte del runtime o del codigo, pero el lenguaje operativo nuevo debe ser comprensible en espanol.

## Commission / coordination gate (compatibilidad legacy y capacidad opcional por proveedor)

La capa de comision o coordination gate no desaparece, pero deja de ser el fundamento universal del producto.

### Canon comercial actualizado

- La comision no es una regla global obligatoria del sistema.
- MedTravel debe soportar proveedores con comision habilitada y proveedores sin comision, sin romper el flujo principal del caso.
- La politica comercial se configura por proveedor desde el panel interno/admin.
- Esta capa puede afectar ciertos desbloqueos o etapas operativas cuando esta habilitada.
- Es una capacidad comercial complementaria, no el eje principal del caso, la cita ni la atencion.

### Compatibilidad con el modelo previo

El modelo previo de `Stage 2 Commission Unlock` se mantiene como compatibilidad legacy para flujos donde la politica comercial del proveedor asi lo requiera.

**Stage 1 (Negotiation / Case review phase)**
- Client and provider can communicate inside the platform.
- Provider identity, specialty or service context may remain visible.
- Sensitive contact details can be redacted only when the provider-specific commercial gate is enabled.

**Stage 2 (Commission / Coordination Unlock)**
- A coordination or commission payment may unlock additional contact details or downstream operational steps.
- This stage exists only for providers or agreements where the gate is enabled.
- All communication and coordination should continue to be representable inside the platform.

### Configuracion por proveedor

MedTravel administrators configure the commission terms individually for each provider when that commercial layer applies.

**Commission parameters**
- `commission_pct`
- `fixed_fee_cop`
- `currency`
- `payment_terms`
- `stripe_account_id`
- `is_active`

If `is_active = 0`, the provider operates with no commission unlock requirement.  
If `is_active = 1`, the optional commission / coordination gate can be enforced according to the provider agreement.
