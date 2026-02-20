# Data Deletion Workflow (MedTravel)

Fecha: 2026-02-20

## Objetivo
Implementar el flujo real de eliminación/anonimización de datos personales para cumplimiento de políticas de Data Deletion (Meta/plataformas), con ejecución desde panel admin y auditoría en base de datos.

## Endpoints y pantallas
- Público: `data-deletion.php` -> `api/data_deletion_request.php`
- Admin UI: `admin/data_deletion_requests.php`
- Admin backend: `admin/ajax/data_deletion_requests.php`
- Servicio transaccional: `admin/include/data_deletion_service.php`

## Persistencia y auditoría
Tabla: `data_deletion_requests`
- Estados: `pending`, `processing`, `completed`, `failed`
- Auditoría: `processed_at`, `processed_by_user_id`, `result_summary`, `last_error`
- Migración idempotente: `sql/2026_02_20_data_deletion_requests.sql`

## Seguridad
- Solo ADMIN puede procesar solicitudes (`is_role_admin_session`).
- `process` solo acepta `POST`.
- Idempotencia y concurrencia:
  - lock con `FOR UPDATE`
  - no reprocesa `completed`
  - evita doble proceso en `processing`
- Respuestas JSON y logs sin PII sensible.

## Estrategia por tabla (delete vs anonymize)
- `booking_requests`: anonymize (nombre, email, phone, notas, etc.)
- `usuarios`: anonymize (credenciales/token rotado, PII redactada, desactivación)
- `clientes`: anonymize (PII CRM y campos sensibles)
- `appointments`: anonymize campos de notas sensibles
- `travel_packages`: anonymize notas/special requests
- `inbox_messages`: delete
- `inbox_thread_reads`: delete
- `calendar_events`: delete
- `provider_users`: delete vínculos
- `sessiones_activas`: delete sesiones
- `certificado`: delete filas + archivos asociados
- `client_documents`: delete filas + archivos asociados
- `notifications` (recipient_type=client): delete

## Flujo de ejecución admin
1. Admin abre `admin/data_deletion_requests.php`.
2. Ve solicitudes con `status` y botón `Process` para `pending`/`failed`.
3. Confirmación obligatoria tipeando `DELETE`.
4. Backend ejecuta transacción:
   - marca `processing`
   - resuelve usuario/cliente por email/teléfono
   - ejecuta delete/anonymize por tabla
   - guarda conteos en `result_summary`
   - marca `completed` + `processed_at` + `processed_by_user_id`
5. Si falla: rollback, marca `failed`, guarda `last_error` sanitizado.

## Validación recomendada
1. Crear cliente demo con email/teléfono + booking + mensajes.
2. Enviar solicitud desde `data-deletion.php`.
3. Procesar desde `admin/data_deletion_requests.php`.
4. Verificar:
   - PII anonimizada/eliminada en tablas objetivo.
   - `data_deletion_requests.status = completed`.
   - `result_summary` con conteos.
   - no permite reprocesar la misma solicitud.
