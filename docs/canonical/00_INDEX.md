# Documentación Canónica - Índice

## ¿Qué es canónico vs derivado?

- **Canónico:** elemento que actúa como la *fuente de verdad* para una decisión, dato o diseño. (Ver ejemplos: `BOOKING_CTA_INTEGRATION.md`, `SERVICES_CATALOG.md`.)
- **Derivado:** documento que resume, comunica o usa decisiones tomadas en los canónicos; no debe redefinirlas.

## Orden de lectura recomendado

1. MODELO DE NEGOCIO — `MODELO_NEGOCIO_ACTUALIZADO.md`
2. Backlog / Ejecución — `NEXT_STEPS_SERVICES.md`
3. Contexto técnico y runtime — `DEV_CONTEXT.md`

## Links canónicos (acceso rápido)

- [MODELO DE NEGOCIO actualizado](../../MODELO_NEGOCIO_ACTUALIZADO.md)
- [NEXT_STEPS_SERVICES (ejecución / backlog)](../../NEXT_STEPS_SERVICES.md)
- [DEV_CONTEXT (contexto técnico y runtime)](../../DEV_CONTEXT.md)

## Docs derivados importantes

- [RESUMEN DE IMPLEMENTACIÓN - Mejoras Comerciales](../../RESUMEN_IMPLEMENTACION.md)
- [MEJORAS_COMERCIALES_README](../../MEJORAS_COMERCIALES_README.md)
- [PROVIDER_SYSTEM_RESUMEN](../../PROVIDER_SYSTEM_RESUMEN.md)


> Nota: Este índice centraliza enlaces. No duplica ni reescribe decisiones.

---

## 🧠 Protocolo Oficial de Selección de Modelos IA (2026)

Este proyecto utiliza múltiples modelos según el tipo de tarea.
La selección de modelo no es aleatoria; responde a complejidad, criticidad y tipo de cambio.

🔹 1. Documentación / Organización

Modelo recomendado:
- GPT-5 mini

Uso:
- Organización de .md
- Creación de estructura docs
- Refactors documentales
- Commits menores

Motivo:
Rápido, suficiente, bajo costo.

🔹 2. Desarrollo estructural y arquitectura

Modelo recomendado:
- GPT-5.2

Uso:
- Diseño de tablas nuevas
- Arquitectura booking multiproveedor
- Seguridad y RBAC
- Cambios que afectan múltiples módulos
- Refactors importantes

Motivo:
Mejor equilibrio entre razonamiento profundo y consistencia técnica.

🔹 3. Backend crítico / Código multi-tenant / Seguridad

Modelo recomendado:
- GPT-5.1-Codex
- GPT-5.2-Codex

Uso:
- Middleware
- Validación JWT
- Control por empresa
- Flujos multi-tenant
- Integraciones sensibles
- Debug profundo de backend

Motivo:
Mayor precisión quirúrgica sobre código real.

🔹 4. Arquitectura estratégica

Modelo recomendado:
- GPT-5.2

Uso:
- Decisiones SaaS
- Diseño de flujos completos
- Modelo de negocio técnico
- Cambios estructurales de producto

🔹 5. Combinación recomendada cuando Codex esté externo

Arquitectura y decisiones:
→ GPT-5.2

Ejecución sobre repo real:
→ Codex (modelo Codex activo)

⚠️ Modelos NO recomendados para tareas críticas
- Modelos preview ligeros
- Versiones “fast” para arquitectura
- Modelos optimizados solo para velocidad

Regla operativa final:

Las decisiones estratégicas y arquitectónicas deben pasar por GPT-5.2 antes de ejecución en Codex.

