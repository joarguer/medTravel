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

## Provider Commission Administration

**Admin interface location**
- `admin/providers_edit.php`

**Configuration storage**
- `provider_commission_settings`

**Relation**
- `provider_commission_settings.provider_id` → `providers.id`

**Runtime components**
- `inc/commission_gate.php`
- `client/ajax/inbox.php`
- `client/ajax/get_request_detail.php`
- `admin/ajax/inbox.php`

**Purpose**
Allow MedTravel to control monetization terms per provider while preserving free negotiation in Stage 1.

### Inbox UI Architecture – Chat Bubble System

**Chat layout standard**
- Own messages aligned RIGHT.
- Received messages aligned LEFT.
- Maximum bubble width ~70%.
- Auto-wrap long content.
- Header inside bubble: DisplayName + Timestamp.

**Display name logic**
- Own message → "Me".
- Client portal other side: admin/patientcare → "Support"; provider → "Provider".
- Admin portal other side: client → "Patient" or "Client".
- If sender name exists in message payload → use it.

**Message classification**
- sender_role normalization: client, admin, provider, patientcare, system.

**DOM structure**
```html
<div class="mt-msg-row mt-msg-row--own|other">
  <div class="mt-msg-bubble">
      <div class="mt-bubble-head">
          <span class="mt-bubble-name">Me</span>
          <span class="mt-bubble-time">timestamp</span>
      </div>
      <div class="mt-bubble-body">
          message content
      </div>
  </div>
</div>
```

**Scroll behavior**
- Auto-scroll only when user is near bottom.
- Do not jump scroll when user is reading history.

**Realtime deduplication**
- Prevent duplicate rendering when sender receives their own socket broadcast.
- Track recent message_id values.
- Ignore socket message.created events if already rendered.

**Files responsible for UI rendering**
- Admin portal: `admin/js/app_inbox.js`, `admin/app_inbox.php`
- Client portal: `client/js/app_inbox.js`, `client/app_inbox.php`
