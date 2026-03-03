# Product Model (alias)

Este archivo es un alias que apunta al documento canónico del modelo de negocio.

- Ver: [MODELO_NEGOCIO_ACTUALIZADO.md](../../MODELO_NEGOCIO_ACTUALIZADO.md)

## Stage 2 Commission Unlock

**Stage 1 (Negotiation Phase)**
- Client and provider can communicate freely within the platform.
- Provider name and specialty remain visible.
- Sensitive contact information (phone, email, external links) may be redacted when commission gate is enabled.

**Stage 2 (Commission Unlock)**
- Client pays a MedTravel commission.
- Payment unlocks full provider contact details.
- All communication and coordination continues through the platform.

**Gate Bypass**
If provider commission is disabled:
- `commission_enabled = 0` or
- `phase2_gate_enabled = 0`

Then the gate is bypassed and Stage 1 operates normally.

## Provider Commission Configuration

MedTravel administrators configure the commission terms individually for each medical provider.

**Commission parameters**
- `commission_pct` (percentage fee charged by the platform)
- `fixed_fee_cop` (optional flat fee)
- `currency`
- `payment_terms`
- `stripe_account_id`
- `is_active` (controls whether the Stage 2 commission gate is enforced)

If `is_active = 0` the provider operates under Stage 1 rules with no commission unlock requirement.  
If `is_active = 1` the system enforces the Stage 2 commission unlock.

Stage 1 communication always remains open inside the platform.  
Only sensitive provider contact details are gated until the commission payment is completed.
