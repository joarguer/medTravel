# Execution Backlog (alias)

Alias al backlog / pasos de ejecución canónico.

- Ver: [NEXT_STEPS_SERVICES.md](../../NEXT_STEPS_SERVICES.md)

## Frente prioritario — Canonizacion del nuevo modelo operativo MedTravel

### Paso 1 — Canon producto y operacion

- Consolidar en documentacion la separacion formal entre caso, item, cita y coordinacion / pago
- Reubicar la comision como capacidad comercial opcional por proveedor
- Alinear aliases canónicos sin borrar el contexto legacy

### Paso 2 — Ajuste de vocabulario UI

- Estandarizar estados visibles de negocio para operacion
- Alinear acciones oficiales del item:
  - Aceptar caso
  - Rechazar caso
  - Solicitar informacion
  - Proponer cita
- Formalizar ingles para front publico / paciente y espanol por defecto para admin operativo en Colombia

### Paso 3 — Modelo operativo del staff medico

- Separar prestador de medico o staff interno
- Definir el equivalente logico futuro para staff medico sin forzar una tabla definitiva prematura
- Asegurar que cada item medico pueda mostrar prestador, doctor o staff, clinic y estado de cita

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
