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
