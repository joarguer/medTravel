# 30_MARKETING_AND_TRACKING — Marketing y Tracking MedTravel

Fuente canónica para decisiones de campañas, medición publicitaria y privacidad de tracking en MedTravel.

---

## Principio operativo

MedTravel conecta pacientes con profesionales y coordina solicitudes. MedTravel no cobra directamente al paciente por el booking público.

Por esa razón, el evento de conversión publicitaria para campañas de solicitud de atención es:

- **Evento definitivo:** `Lead` / Cliente potencial
- **No usar:** `Purchase`
- **No usar:** `BookingIntent`

WhatsApp puede existir como canal de soporte del sitio, pero no es el objetivo principal de la campaña Face Up Thread Lift. Esta campaña debe optimizar a conversión en sitio web usando `Lead`.

---

## Meta Pixel Oficial

- **Dataset / Pixel:** MedTravel Pixel Oficial
- **Pixel ID:** `2206493506836761`
- **Evento base:** `PageView`
- **Evento de conversión:** `Lead`
- **Modo de producción:** Browser Pixel + Conversions API
- **Modo test:** no usar `test_event_code` para producción normal

### Browser Pixel

El evento de navegador debe dispararse solo después de que el booking exista en BD y la página final consuma la bandera de sesión:

```js
fbq('track', 'Lead', {}, { eventID: eventId });
```

No se debe enviar payload de servicio, valor, moneda ni datos del paciente.

### Conversions API

El evento servidor-servidor debe enviarse después del guardado exitoso de `booking_requests` y debe usar el mismo `event_id` que el Browser Pixel para deduplicación.

Datos permitidos en `user_data`:

- `client_ip_address`, si está disponible
- `client_user_agent`, si está disponible
- `_fbp`, si existe cookie
- `_fbc`, si existe cookie

No incluir `custom_data` salvo decisión futura explícita. Si alguna vez se incluye, debe ser genérico y no sensible.

### Deduplicación

Browser Pixel y Conversions API deben compartir exactamente el mismo `event_id`.

El `event_id` debe:

- Generarse solo después de que el booking se guarde exitosamente
- Ser único por booking
- Ser aleatorio o no derivable
- No incluir email, teléfono, nombre ni `booking_id` plano

---

## Privacidad

Queda prohibido enviar al Pixel o a Conversions API:

- nombre
- email
- teléfono
- notas
- diagnóstico
- procedimiento
- precio
- moneda
- IDs de servicio o procedimiento
- datos médicos, estéticos o personales del paciente
- nombre del tratamiento como parámetro de evento

El tracking debe ser tolerante a fallos: si Meta falla, el booking, el correo al paciente y la experiencia del usuario deben continuar normalmente.

---

## Variables de entorno

Requerida para Conversions API:

- `META_CAPI_ACCESS_TOKEN`

Opcional:

- `META_PIXEL_ID=2206493506836761`

Si `META_CAPI_ACCESS_TOKEN` falta, el booking no debe romperse. Debe registrarse:

```text
meta_capi_skipped_missing_token
```

---

## Logs Operativos

Los logs no sensibles del flujo de conversión viven en:

```text
admin/logs/booking_submit_runtime.log
```

Entradas esperadas:

- `pixel_lead_event_id_created event_id=...`
- `pixel_lead_pending_set`
- `meta_capi_lead_send_start event_id=...`
- `meta_capi_lead_sent status=200`
- `meta_capi_lead_error status=...` si falla
- `meta_capi_skipped_missing_token` si falta token
- `pixel_lead_rendered event_id=...`
- `pixel_lead_session_cleared`

---

## Prueba en Producción

Checklist mínimo:

1. Completar un booking real.
2. Confirmar que el booking se guarda en BD.
3. Confirmar que llega el correo al paciente.
4. En DevTools Network, verificar request a:

```text
/tr/?id=2206493506836761&ev=Lead
```

con status `200`.

5. En logs, verificar `meta_capi_lead_sent status=200` o `meta_capi_skipped_missing_token`.
6. Confirmar que `pixel_lead_rendered` y `pixel_lead_session_cleared` aparecen después del submit exitoso.

---

## Campaña Meta Ads — Face Up Thread Lift

### Estado

- **Campaña:** `MedTravel | Face Up Thread Lift | Leads Booking`
- **Estado actual:** borrador / pendiente de método de pago
- **Motivo del borrador:** falta resolver o agregar método de pago en Ads Manager

### Configuración

- **Objetivo:** clientes potenciales / Lead
- **Conversión:** sitio web
- **Dataset:** MedTravel Pixel Oficial
- **Evento:** Cliente potencial (`Lead`)
- **Optimización:** Lead en sitio web
- **No optimizar a:** WhatsApp
- **No usar evento:** Purchase
- **No usar evento:** BookingIntent

### URL del anuncio

```text
https://medtravel.com.co/offer_detail.php?id=9
```

### Público

- **Ubicación:** Florida, United States
- **Género:** mujeres
- **Edad sugerida:** 30-60
- **Edad mínima/control:** 25 si Meta no permite restringir más desde la configuración disponible

### Creatividad

- **Idioma:** inglés
- **CTA recomendado:** `See details` o `Learn More`
- **Oferta:** Face Up Thread Lift

La creatividad puede describir el servicio en la página de destino, pero los eventos de tracking no deben enviar nombre del tratamiento ni datos médicos/estéticos individuales.

### Presupuesto y límites

- **Presupuesto inicial recomendado:** `$30.000 COP/día`
- **Límite de gasto detectado:** `$180.000 COP`

No publicar la campaña hasta resolver el método de pago y verificar que el evento `Lead` aparezca correctamente con Browser Pixel y Conversions API en producción.
