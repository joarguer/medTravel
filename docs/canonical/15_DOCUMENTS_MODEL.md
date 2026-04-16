# 15_DOCUMENTS_MODEL — Modelo Canónico de Documentos del Caso

Fuente de verdad para `client_documents` y el flujo de documentos del paciente/caso en MedTravel.

---

## Estado

Canonizado: 2026-04-15
Razón: tabla existía en runtime sin documentación canónica.

---

## Tabla canónica: `client_documents`

### Columnas clave

| Columna | Tipo | Nullable | Descripción |
|---------|------|----------|-------------|
| `id` | INT PK | No | Identificador |
| `client_id` | INT | Sí | Referencia legacy a `clientes.id` |
| `client_user_id` | INT | Sí | Referencia moderna a `usuarios.id` (preferida) |
| `booking_request_id` | INT | Sí | Caso al que pertenece — anchor de scope obligatorio para queries admin/provider |
| `item_id` | INT | Sí | Item específico del caso; NULL = documento del caso completo |
| `document_type` | VARCHAR | Sí | Tipo de documento (libre) |
| `file_path` | VARCHAR | No | Ruta relativa desde `uploads/medical_docs/` |
| `filename` | VARCHAR | No | Nombre almacenado en disco |
| `original_filename` | VARCHAR | No | Nombre original subido por el usuario |
| `file_size` | INT | Sí | Tamaño en bytes (columna optional — guarded en runtime) |
| `mime_type` | VARCHAR | Sí | MIME type del archivo (guarded) |
| `file_extension` | VARCHAR | Sí | Extensión (guarded) |
| `title` | VARCHAR | Sí | Título del documento (guarded) |
| `description` | TEXT | Sí | Descripción libre (guarded) |
| `shared_with_provider` | TINYINT | Sí | 1 = visible a admin/provider; 0 = solo paciente |
| `uploaded_by` | INT | Sí | `usuarios.id` del actor que subió el archivo (guarded) |
| `uploaded_at` | DATETIME | Sí | Timestamp de subida (guarded; fallback: `created_at`) |
| `created_at` | DATETIME | Sí | Timestamp de inserción (guarded) |

### Columnas de scope añadidas por migración

`booking_request_id` e `item_id` añadidas por `sql/2026_02_20_client_documents_request_item_scope.sql` (idempotente).
Todo acceso runtime debe verificar existencia de estas columnas antes de usarlas (schema guard).

### Path de almacenamiento

```
uploads/medical_docs/client_{client_user_id}/   (preferido)
uploads/medical_docs/client_{client_id}/         (fallback legacy)
```

---

## Scope canónico

### Anchor obligatorio

`booking_request_id` es el anchor de scope canónico para todas las queries admin y provider.
Sin `booking_request_id` resuelto no debe realizarse ninguna query de documentos.

### Jerarquía de scope completa

```
booking_request_id   → caso (obligatorio)
└── item_id          → item específico (opcional; NULL = pertenece al caso completo)

client_user_id       → dueño del documento (moderno, preferido — usuarios.id)
client_id            → dueño del documento (legacy — clientes.id; usar cuando client_user_id no existe)
```

### Regla de resolución del dueño

1. Si `client_documents.client_user_id` existe (has_column guard) → filtrar por `client_user_id`
2. Si no existe → filtrar por `client_id` (referencia legacy a `clientes`)
3. Si tampoco se puede resolver el dueño → `WHERE 1=1` con anchor `booking_request_id` (fallback mínimo seguro)

El fallback `WHERE 1=1 + booking_request_id` devuelve todos los documentos compartidos del caso sin discriminar dueño. Es aceptable solo cuando el dueño no pudo resolverse.

---

## Reglas de visibilidad por actor

| Actor | Condición |
|-------|-----------|
| Paciente (portal `client/`) | Ve todos sus documentos del caso sin restricción de `shared_with_provider` |
| Admin MedTravel | Ve documentos con `shared_with_provider = 1` del caso |
| Provider / Staff | Ve documentos con `shared_with_provider = 1` del caso, con gate comercial del provider aplicando cuando corresponda |

`shared_with_provider` es columna optional (has_column guarded). Si no existe, el runtime no aplica el filtro (comportamiento permisivo por defecto — deuda pendiente).

---

## Superficies de display

### Admin inbox — `admin/ajax/inbox.php` (`get_thread`)

- Muestra documentos en hilos de tipo `ITEM` y `CARE`.
- Scope: `booking_request_id` obligatorio + `shared_with_provider = 1`.
- Para hilo ITEM: añade `AND (item_id = ? OR item_id IS NULL)` para incluir docs del caso completo.
- Para hilo CARE: scope solo por `booking_request_id` (sin filtro por item).

### Admin modal detalle — `admin/ajax/my_booking_requests.php` (`get_detail`)

- Muestra documentos en tab Paciente del modal de detalle de solicitud.
- Scope: idéntico al inbox admin. Gate: `bookingRequestId > 0`.
- Fix 2026-04-15: removida restricción `clientId > 0` que bloqueaba docs cuando el cliente no resolvía.

### Portal paciente — `client/ajax/inbox.php` (`get_thread`)

- Muestra documentos en hilos ITEM y CARE desde la perspectiva del paciente.
- Sin filtro `shared_with_provider` — el paciente ve todos sus documentos del caso.
- Scope: `booking_request_id` + resolución de `client_user_id` o `client_id`.

---

## Superficie de upload

### Único punto de upload: `admin/ajax/inbox.php` (`upload_documents`)

- Acepta solo en hilos ITEM o CARE.
- Valida: tabla existe, columnas de scope presentes, tipo de hilo válido, gate comercial del provider cuando aplica.
- Almacena en `uploads/medical_docs/client_{id}/`.
- Inserta con `booking_request_id` e `item_id` del contexto del hilo.
- `shared_with_provider` se determina según contexto (admin vs provider).

No existe UI de gestión de documentos dedicada. Los documentos solo se ven y suben desde Inbox y modal de detalle.

---

## Diferencias por rol

| Rol | Upload | Ver | Scope |
|-----|--------|-----|-------|
| ROLE_ADMIN | Sí | Todos con `shared = 1` | `booking_request_id` |
| ROLE_ADMINISTRATIVE | Sí | Todos con `shared = 1` | `booking_request_id` |
| ROLE_PROVIDER_ADMIN | Con gate comercial | `shared = 1` del caso | `booking_request_id` del caso asignado |
| ROLE_PROVIDER (staff) | Con gate comercial + scope operativo | `shared = 1` del item asignado | `booking_request_id` + `item_id` |
| ROLE_CLIENT (paciente) | No (portal actual) | Todos sus docs del caso | `booking_request_id` del propio caso |

---

## Deuda heredada

| ID | Descripción |
|----|-------------|
| DOC-D1 | Dos columnas de identidad del cliente coexisten: `client_id` (legacy → `clientes`) y `client_user_id` (moderno → `usuarios`). No hay migración unificadora. El runtime usa guards para manejar ambas. Deuda de modelo: resolver hacia una sola FK canónica. |
| DOC-D2 | `shared_with_provider` no está declarada en ninguna migración SQL versionada visible. Existe en runtime. Riesgo: entornos nuevos sin esa columna se comportan de forma permisiva sin aviso explícito. |
| DOC-D3 | No hay regla de lifecycle de documentos: cuando un caso se cancela o archiva, los documentos no se limpian ni cambian de estado. |
| DOC-D4 | No existe endpoint dedicado de lectura de documentos. El flujo está embebido en `get_thread` (inbox) y `get_detail` (modal). Dificulta reutilización futura. |
| DOC-D5 | No existe UI de gestión de documentos propia (listar, eliminar, resubir). Solo se accede desde Inbox y modal de detalle. |
| DOC-D6 | `description` existe como columna guarded pero no tiene UI de edición expuesta en ninguna superficie. |
| DOC-D7 | No hay FK declarada en migraciones que relacione `client_documents.booking_request_id → booking_requests.id` ni `item_id → booking_request_items.id`. Integridad referencial no forzada a nivel DB. |
