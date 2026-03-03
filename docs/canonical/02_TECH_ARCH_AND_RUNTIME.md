# Technical Architecture & Runtime (alias)

Alias al documento canónico que describe contexto técnico y runtime.

- Ver: [DEV_CONTEXT.md](../../DEV_CONTEXT.md)

## Commission System

**Tables**
- `provider_commission_settings`
- `commission_payments`

**Key relations**
- `provider_commission_settings.provider_id` → `providers.id`
- `commission_payments.provider_id` → `providers.id`
- `commission_payments.request_id` → `booking_requests.id`
- `commission_payments.item_id` → `booking_request_items.id`

**Runtime components**
- `inc/commission_gate.php`
- `client/ajax/inbox.php`
- `admin/ajax/inbox.php`

**Purpose**
Control access to sensitive provider contact details until commission payment is completed.
