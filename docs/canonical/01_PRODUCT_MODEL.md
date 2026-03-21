# Product Model (alias)

Este archivo es un alias que apunta al documento canónico del modelo de negocio.

- Ver: [MODELO_NEGOCIO_ACTUALIZADO.md](../../MODELO_NEGOCIO_ACTUALIZADO.md)

## Arquitectura operativa oficial de MedTravel

MedTravel se canoniza como una plataforma de gestion de casos de turismo medico y servicios asociados. El producto no debe describirse solo como un flujo de solicitud + chat + commission unlock, sino como una operacion estructurada donde un caso puede contener multiples items, citas, pasos de coordinacion y capas comerciales opcionales.

### Principios oficiales del modelo

- El eje primario del producto es el caso operativo del paciente, no la comision.
- Caso, item de servicio, cita y coordinacion/pago son dimensiones distintas y deben poder evolucionar por separado.
- Prestador y medico o staff medico interno del prestador no son la misma entidad operativa.
- La UI operativa debe mostrar estados visibles de negocio comprensibles para operacion, aunque existan estados tecnicos internos adicionales.
- La trazabilidad de cada item debe soportar historial operativo, responsables, citas y eventos relevantes.
- La comision o coordination gate es una capacidad comercial complementaria y configurable, no una regla universal del sistema.

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
- Una cita puede ser propuesta, confirmada, reprogramada o cancelada sin redefinir por si sola todo el caso.
- El sistema debe soportar trazabilidad entre item y cita aunque la implementacion tecnica siga evolucionando.

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

### Entidades operativas visibles

- Caso
- Item de servicio
- Cita
- Coordinacion / Pago
- Prestador
- Medico / Staff interno
- Conversacion
- Event log / timeline

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
- **Mis Servicios** = servicios clinicos que el proveedor realmente puede atender (catalogo habilitado de `service_catalog`).
- **Mis Ofertas** = publicaciones comerciales visibles al paciente sobre esos servicios (`provider_offers`).

Esta distincion debe mantenerse consistente en UI, textos de ayuda y documentacion.

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
