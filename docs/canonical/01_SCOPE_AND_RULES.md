# 01_SCOPE_AND_RULES — Alcance y Reglas MedTravel

Fuente de verdad sobre qué es este proyecto, qué no es, y las reglas operativas que no se negocian.

---

## Alcance del producto

MedTravel es una **plataforma de gestión de casos de turismo médico**. Conecta pacientes de EE. UU. (principalmente Florida) con proveedores médicos en Quindío, Colombia.

MedTravel actúa como **coordinador operativo**, no como proveedor clínico ni como tomador de decisiones médicas.

### Lo que MedTravel es

- Coordinador entre paciente y provider
- Plataforma de gestión de casos (multi-ítem, multi-proveedor, multi-cita)
- Intermediario operativo con capacidad comercial configurable
- Gestor de flujo: desde la solicitud inicial hasta el pago o llegada del paciente

### Lo que MedTravel no es

- No es proveedor clínico directo
- No toma decisiones clínicas
- No sustituye la relación médico-paciente
- No es un simple catálogo de servicios
- No es un booking aislado sin contexto operativo

---

## Principios del modelo operativo

1. El eje primario del producto es el **caso clínico del paciente**, no la comisión
2. **Caso, ítem de servicio, cita y coordinación/pago** son dimensiones distintas — evolucionan por separado
3. **Proveedor y médico/staff** no son la misma entidad operativa
4. La UI operativa debe mostrar **estados de negocio comprensibles**, aunque existan estados técnicos internos adicionales
5. La **comisión o coordination gate** es una capacidad comercial complementaria y configurable, no una regla universal
6. La **trazabilidad de cada ítem** debe soportar historial operativo, responsables, citas y eventos relevantes

---

## Reglas de sesión (no negociables)

### Sesión documental
- Solo se modifica documentación
- No se toca PHP, JS, SQL, auth
- No se abren frentes técnicos
- No se hace refactor

### Sesión técnica
- Puede modificar código después de decidirlo explícitamente
- Requiere que el contexto documental esté al día antes de empezar
- Al cerrar: actualizar `PROJECT_STATE.md`

---

## Reglas de documentación

- La estructura base documental es igual en todos los proyectos — no crear variantes
- El contenido cambia por proyecto; la estructura no
- Canon y historia no se mezclan (`docs/canonical/` ≠ `docs/`)
- Todo lo que sea fuente de verdad va en `docs/canonical/`
- Todo lo que sea historia, wiki, progreso, análisis va en `docs/`
- La continuidad vive en el repositorio, no en chats externos
- El sistema es agnóstico de proveedor IA — no hardcodear referencias a modelos específicos

---

## Reglas de seguridad (resumen ejecutivo)

- Prepared statements en todo SQL — sin excepción
- `htmlspecialchars()` en todo output dinámico HTML
- `require_login_ajax()` en todo endpoint AJAX del panel admin
- Claves y credenciales: variables de entorno, nunca en código
- El super-admin (`usuarios.id = 1`) está protegido — nunca reutilizar en flujos de proveedores

---

## Fuera de alcance actual

- Generación documental online (proyecto satélite `generadorDocumentos` — separado)
- Integración directa con sistemas clínicos externos
- App móvil nativa
