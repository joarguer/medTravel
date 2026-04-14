# 00_INDEX — Documentación Canónica MedTravel

Índice de la documentación canónica. No duplica decisiones; apunta a las fuentes.

---

## Qué es canónico vs derivado

- **Canónico:** fuente de verdad para una decisión, dato o diseño. No se reescribe en otros docs.
- **Derivado:** resume, comunica o aplica lo decidido en canónicos. No redefine.

---

## Archivos de base documental (estructura fija — no variar por proyecto)

| Archivo | Propósito |
|---------|-----------|
| `AGENTS.md` (raíz) | Contexto operativo para agentes IA — agnóstico de proveedor |
| `PROJECT_STATE.md` (raíz) | Estado actual: deuda técnica, frentes abiertos, frentes cerrados |
| `docs/canonical/00_INDEX.md` | Este archivo — índice y protocolo |
| `docs/canonical/01_SCOPE_AND_RULES.md` | Alcance del proyecto y reglas operativas |
| `docs/canonical/02_DOC_MAP.md` | Mapa completo de toda la documentación |

Si existe un archivo específico de proveedor o modelo como `CLAUDE.md`, se considera auxiliar de compatibilidad y no forma parte de la continuidad principal.

---

## Archivos de contenido canónico (específicos de MedTravel)

| Archivo | Propósito |
|---------|-----------|
| `docs/canonical/10_PRODUCT_MODEL.md` | Modelo de producto: caso, ítem, cita, coordinación |
| `docs/canonical/11_TECH_ARCH_AND_RUNTIME.md` | Arquitectura técnica y separaciones de dominio |
| `docs/canonical/12_EXECUTION_BACKLOG.md` | Backlog de ejecución y estado de fases |
| `docs/canonical/13_CHANGELOG_DECISIONS.md` | Decisiones arquitectónicas registradas |
| `docs/canonical/14_CALENDAR_MEET_INTEGRATION_MODEL.md` | Spec Google Calendar/Meet |

---

## Orden de lectura recomendado

1. `AGENTS.md` — quién soy, qué no debo hacer
2. `PROJECT_STATE.md` — qué está pendiente ahora
3. `01_SCOPE_AND_RULES.md` — reglas del juego
4. `10_PRODUCT_MODEL.md` → `11_TECH_ARCH_AND_RUNTIME.md` — modelo y stack
5. `12_EXECUTION_BACKLOG.md` — frentes activos

---

## Protocolo de continuidad

- Este repositorio es la fuente de verdad operativa.
- Workspace operativo actual del repo: `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/workspaces/medtravel`.
- Ningún contexto externo (chat, email, nota) reemplaza lo documentado aquí.
- Los archivos específicos de proveedor/modelo, si existen, nunca reemplazan `AGENTS.md`, `PROJECT_STATE.md` ni `docs/canonical/*`.
- Al cerrar una sesión técnica relevante: actualizar `PROJECT_STATE.md`.
- Al tomar una decisión arquitectónica: registrar en `13_CHANGELOG_DECISIONS.md`.
