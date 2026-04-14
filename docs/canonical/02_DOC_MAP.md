# 02_DOC_MAP — Mapa Documental MedTravel

Mapa completo de toda la documentación. Define qué existe, dónde vive y por qué.

---

## Estructura base (fija — no variar)

```
app/
├── AGENTS.md                        # Contexto IA agnóstico — leer antes de actuar
├── PROJECT_STATE.md                 # Estado actual: deuda, frentes, variables
├── CLAUDE.md                        # Shim opcional de compatibilidad; no fuente principal
└── docs/
    ├── canonical/                   # Fuente de verdad — no mezclar con historia
    │   ├── 00_INDEX.md              # Índice y protocolo documental
    │   ├── 01_SCOPE_AND_RULES.md    # Alcance y reglas operativas
    │   ├── 02_DOC_MAP.md            # Este archivo
    │   ├── 10_PRODUCT_MODEL.md      # Modelo de producto canónico
    │   ├── 11_TECH_ARCH_AND_RUNTIME.md  # Arquitectura técnica
    │   ├── 12_EXECUTION_BACKLOG.md  # Backlog de ejecución
    │   ├── 13_CHANGELOG_DECISIONS.md   # Decisiones arquitectónicas
    │   └── 14_CALENDAR_MEET_INTEGRATION_MODEL.md  # Spec Calendar/Meet
    ├── conectarbot_api.md           # Referencia API ConectarBot
    ├── data_deletion_workflow.md    # Flujo de eliminación de datos
    └── ops/                         # Scripts operativos (pendiente revisión)
```

---

## Jerarquía operativa de entrada

1. **Primario:** `AGENTS.md`, `PROJECT_STATE.md`, `docs/canonical/*`
2. **Auxiliar de compatibilidad:** archivos por proveedor/modelo como `CLAUDE.md`, solo como puntero estable
3. **Histórico / auxiliar:** `.md` sueltos de raíz y referencias en `docs/`; no redefinen canon ni estado vivo

---

## Docs de historia / implementación (raíz app/)

Archivos `.md` sueltos en `app/`. Son registros de implementaciones pasadas, análisis y checklists.
No son fuente de verdad. No deben redefinir lo que está en `docs/canonical/`.
Estado actual: pendiente migración a `docs/history/` o archivo.

| Archivo | Tipo | Destino sugerido |
|---------|------|-----------------|
| `ANALISIS_MULTIUSUARIO.md` | Análisis | `docs/history/` |
| `ANALISIS_RAZON_SOCIAL.md` | Análisis | `docs/history/` |
| `BOOKING_CTA_INTEGRATION.md` | Implementación | `docs/history/` |
| `BOOKING_WIZARD_MEJORAS.md` | Implementación | `docs/history/` |
| `BOOKING_WIZARD_PROVIDER_OFFERS.md` | Implementación | `docs/history/` |
| `CAMPO_RAZON_SOCIAL.md` | Implementación | `docs/history/` |
| `CHECKLIST_RAZON_SOCIAL.md` | Checklist | `docs/history/` |
| `DEV_CONTEXT.md` | Contexto técnico auxiliar | `docs/` (solo soporte; no fuente principal) |
| `DIAGNOSTICO_LOGIN.md` | Diagnóstico | `docs/history/` |
| `GUIA_CONFIGURACION_SMTP.md` | Guía operativa | `docs/` |
| `IMPLEMENTACION_ADMIN_HOME.md` | Implementación | `docs/history/` |
| `MANUAL_PRUEBAS.md` | QA | `docs/` |
| `MEJORAS_COMERCIALES_README.md` | Implementación | `docs/history/` |
| `MENU_ESTRUCTURA_ADMIN.md` | Implementación | `docs/history/` |
| `MODELO_NEGOCIO_ACTUALIZADO.md` | Modelo negocio | Referenciado desde `10_PRODUCT_MODEL.md` |
| `MODULO_CLIENTES_README.md` | Implementación | `docs/history/` |
| `MODULO_MI_EMPRESA.md` | Implementación | `docs/history/` |
| `NEXT_STEPS_SERVICES.md` | Backlog auxiliar / legacy | Soporte histórico de `12_EXECUTION_BACKLOG.md` |
| `PANEL_EMAIL_SETTINGS.md` | Implementación | `docs/history/` |
| `PROVIDER_MANAGEMENT_README.md` | Implementación | `docs/history/` |
| `PROVIDER_SYSTEM_CHECKLIST.md` | Checklist | `docs/history/` |
| `PROVIDER_SYSTEM_RESUMEN.md` | Resumen | `docs/history/` |
| `RESUMEN_IMPLEMENTACION.md` | Resumen | `docs/history/` |
| `SERVICES_CATALOG.md` | Implementación | `docs/history/` |
| `SERVICES_DYNAMIC_README.md` | Implementación | `docs/history/` |
| `WIZARD_HEADER_ADMIN.md` | Implementación | `docs/history/` |

---

## Workspace (fuera de app/)

```
medtravel_cleanbase_workspace/
├── app/                             # Código y docs del proyecto
├── compare_notes/                   # Notas de comparación del workspace — no parte del proyecto
├── db_snapshot/                     # Snapshot de BD — no parte del proyecto
├── runtime_snapshot/                # Snapshot de runtime — no parte del proyecto
└── medtravel_local_backup_*.bundle  # Bundle de backup git
```

---

## Reglas para agregar documentación

1. **¿Es fuente de verdad de una decisión?** → `docs/canonical/`
2. **¿Es historia de una implementación, análisis o checklist?** → `docs/history/`
3. **¿Es referencia de API externa?** → `docs/`
4. **¿Es guía operativa de deploy/configuración?** → `docs/`
5. **¿Es contexto para agentes IA?** → `AGENTS.md` (raíz)
6. **¿Es estado actual del proyecto?** → `PROJECT_STATE.md` (raíz)
7. **Nunca crear docs sueltos en la raíz de `app/`**

---

## docs/ops/ — pendiente

Contiene archivos SQL que no pertenecen en `docs/`. Migrar a `sql/` o eliminar en próxima sesión técnica.
