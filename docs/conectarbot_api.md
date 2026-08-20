# ConectarBot Data Connector v1 (MedTravel)

API JSON de solo lectura para que ConectarBot consulte datos de catálogo en MedTravel.

## Configuración
- Archivo: `config/conectarbot_api.php`
- Variable obligatoria: `API_KEY` (puedes sobreescribirla con la env `CONECTARBOT_API_KEY`).
- Límite simple por IP: `RATE_LIMIT_PER_MIN` (por defecto 60 req/min). Coloca `0` o `null` para desactivarlo.
- Meta `source`: configurable via `SOURCE` (default `medtravel`).

## Ruta base
`/api/conectarbot/v1/`

Todas las respuestas incluyen header `Content-Type: application/json; charset=utf-8` y siguen este contrato:
```json
{
  "ok": true,
  "data": {},
  "meta": { "source": "medtravel", "ts": "2026-02-05T00:00:00Z" },
  "error": null
}
```
En error:
```json
{
  "ok": false,
  "data": null,
  "meta": { "source": "medtravel", "ts": "2026-02-05T00:00:00Z" },
  "error": { "code": "NOT_FOUND", "message": "Service not found" }
}
```

## Autenticación
Header obligatorio en cada request:
`X-ConectarBot-Key: <API_KEY>`

Respuestas:
- Faltante/incorrecta → HTTP 401, `ok:false`, `error.code="UNAUTHORIZED"`.

## Política de datos Provider/Staff para IA
Esta API es fuente de verdad pública para ConectarBot, pero no debe convertir datos declarados por perfil en afirmaciones verificadas. La clasificación canónica para datos de providers y staff médico es:

### PUBLIC_AI
Datos seguros y útiles para que ConectarBot responda al paciente:
- Nombre profesional (`provider_medical_staff.full_name`).
- Cargo/rol (`role_title`).
- Especialidad (`specialty`).
- Bio, descripción o experiencia pública declarada (`bio_short` o campo público equivalente).
- Provider público: nombre, tipo, slug y descripción pública redactada.
- Ciudad/ubicación pública segura.
- Servicios/ofertas asociados por las relaciones canónicas del modelo (`provider_medical_staff_services`, `provider_catalog_services`, `provider_service_offers`, `service_catalog`).
- Estado público de verificación del provider como indicador o label.
- Licencia profesional solo cuando exista política pública aprobada para exponerla. La IA debe evitar repetir números sensibles innecesariamente; cuando sea posible, preferir indicadores seguros como `professional_license_verified`.

### INTERNAL_VERIFICATION
Datos que pueden alimentar indicadores públicos, pero no deben enviarse crudos al LLM ni al canal conversacional:
- `provider_verification.status`.
- `provider_verification.verification_level`.
- Checklist de verificación (`provider_verification_items`).
- Documentos/evidencias (`provider_documents`).
- `trust_score`.
- Fechas, usuarios internos de revisión, notas de validación y trazas administrativas.

Ejemplo permitido: `provider.verification.label = "Verified Premium"`.
Ejemplo no permitido: enviar checklist, rutas de documentos, notas internas o puntajes crudos para que el LLM los interprete.

### PRIVATE
Datos prohibidos en respuestas públicas de ConectarBot:
- `phone`, `email`, WhatsApp, `mobile`, `cell`.
- Dirección exacta o ubicación privada/no aprobada.
- Documentos, rutas de archivos, nombres de archivo y evidencias.
- Identificaciones personales, fiscales o profesionales sensibles.
- `admin_notes`, notas internas, `rejection_reason`, usuarios internos (`verified_by`, `uploaded_by`, `linked_user_id`) y permisos/admin access.
- Contactos/redes directas no aprobadas o equivalentes a contacto directo.

### Declared vs verified
- Bio/experiencia pública NO equivale a experiencia verificada. Si el perfil dice "Two decades of expertise", ConectarBot puede responder: "Her MedTravel profile describes two decades of expertise." No debe responder: "MedTravel verified she has 20 years of experience", salvo que exista evidencia pública específica y aprobada para esa afirmación.
- `provider.verified=true` significa únicamente que el provider pasó el proceso de verificación MedTravel definido. No implica que cada afirmación comercial o biográfica del perfil haya sido auditada una por una.
- `clinic_name` proviene de texto comercial del staff (`provider_medical_staff.clinic_name`); no es entidad formal de clínica, sede acreditada ni location estructurada.
- El contrato debe separar experiencia pública (`public_experience` o descripción pública), especialidad, servicios asociados y verificación. No agrupar todo ambiguamente como "credentials".

## Endpoints
### 1) Health
- `GET /api/conectarbot/v1/health`
- Valida API key. Respuesta `data: {"status":"ok"}`.

### 2) Catálogo de servicios
- `GET /api/conectarbot/v1/catalog/services`
- Devuelve array de servicios de `service_catalog` con formato:
```json
{
  "id": 1,
  "name": "Consulta dermatológica",
  "slug": "consulta-dermatologica",
  "description": "...",
  "active": true,
  "price_from_usd": 100.0
}
```
- Orden: activos primero (desc), luego nombre asc.
- `price_from_usd` toma el mínimo `provider_service_offers.price_from` en USD si existe; de lo contrario `null`.

### 3) Detalle de servicio
- `GET /api/conectarbot/v1/catalog/service/{slug}`
- Slug permitido: `[a-z0-9-]`. Si el slug no existe → HTTP 404, `error.code="NOT_FOUND"`.

### 4) Detalle de oferta por campaña Meta / WhatsApp
- `GET /api/conectarbot/v1/catalog/offer/{id}`
- Estado: **implementado**. `{id}` debe ser numérico (`\d+`); cualquier otro formato cae en `404 NOT_FOUND` genérico de ruta.
- Uso previsto: ConectarBot recibe `offer_id` desde el canal WhatsApp/Chatwoot originado en una campaña Meta y consulta a MedTravel como fuente de verdad.
- Si no llega `offer_id`, ConectarBot debe usar el flujo genérico actual.
- Si llega `offer_id` y la oferta es pública/elegible, el endpoint devuelve contexto específico de la oferta.
- Si el ID es inválido, inexistente, inactivo, eliminado o no elegible, el endpoint devuelve HTTP 404 con `error.code="NOT_FOUND"`; ConectarBot debe caer silenciosamente al flujo genérico.

#### Validaciones obligatorias
- `provider_service_offers.id = {id}` debe existir.
- La oferta debe estar activa: `provider_service_offers.is_active = 1`.
- Si existe `provider_service_offers.is_deleted`, debe ser `0`.
- El servicio relacionado en `service_catalog` debe estar activo: `service_catalog.is_active = 1`.
- Si existe `service_catalog.is_deleted`, debe ser `0`.
- El provider relacionado debe estar activo: `providers.is_active = 1`.
- Si existe `providers.is_deleted`, debe ser `0`.
- Si la oferta tiene `provider_catalog_service_id`, debe corresponder al mismo provider/service y, si existe `provider_catalog_services.is_active`, debe ser `1`.
- El staff público debe estar relacionado al provider y al servicio por `provider_medical_staff_services`; si existe `provider_medical_staff_services.active`, debe ser `1`.
- El staff debe estar activo (`provider_medical_staff.is_active = 1` o columna legacy equivalente cuando aplique) y, si existe `allow_home_publication`, debe ser `1`.
- Si la oferta trae `provider_catalog_service_id` y el esquema modela `provider_medical_staff_services.provider_catalog_service_id`, el staff devuelto debe pertenecer exactamente a ese PCS; staff del mismo provider/servicio pero de un PCS distinto no se incluye. Si esa columna no existe en el esquema, se conserva el fallback histórico por provider+servicio (no se inventa una asociación que el modelo no expresa).

#### Contrato JSON público
Formato esperado cuando el ID es válido/elegible. Incluye el contrato vigente y los campos enriquecidos implementados de forma aditiva; los consumidores deben tolerar la ausencia de campos opcionales en entornos con esquema incompleto:
```json
{
  "ok": true,
  "data": {
    "offer_id": 9,
    "active": true,
    "title": "Face Up Thread Lift",
    "description": "Public offer description without private contact data.",
    "price": {
      "from": 120.0,
      "currency": "USD"
    },
    "service": {
      "id": 1,
      "slug": "face-up-thread-lift",
      "name": "Face Up Thread Lift",
      "description": "Public service description.",
      "active": true
    },
    "provider": {
      "id": 1,
      "name": "Provider public name",
      "slug": "provider-public-slug",
      "type": "clinica",
      "description": "Public provider description without private contact data.",
      "verified": true,
      "verification": {
        "status": "verified",
        "level": "premium",
        "label": "Verified Premium"
      },
      "location": {
        "city": "Armenia"
      }
    },
    "staff": [
      {
        "id": 1,
        "provider_id": 1,
        "provider_name": "Provider public name",
        "full_name": "Doctor Public Name",
        "role_title": "Lead Doctor",
        "specialty": "Aesthetic Medicine",
        "professional_license_verified": true,
        "description": "Public staff description.",
        "public_experience": "Public staff experience/bio text declared in the profile.",
        "clinic_name": "Commercial clinic/staff location text",
        "location": {
          "city": "Armenia"
        },
        "provider_catalog_service_id": 10,
        "services": [
          {
            "id": 1,
            "slug": "face-up-thread-lift",
            "name": "Face Up Thread Lift",
            "offer_id": 9,
            "title": "Face Up Thread Lift",
            "provider_catalog_service_id": 10
          }
        ]
      }
    ],
    "locations": [
      {
        "provider_id": 1,
        "staff_id": null,
        "name": "Provider public name",
        "clinic_name": null,
        "city": "Armenia"
      },
      {
        "provider_id": 1,
        "staff_id": 1,
        "name": "Provider public name",
        "clinic_name": "Commercial clinic/staff location text",
        "city": "Armenia"
      }
    ]
  },
  "meta": { "source": "medtravel", "ts": "2026-08-20T00:00:00Z" },
  "error": null
}
```

Notas de contrato:
- El modelo debe soportar arrays de `staff` y `locations`; no debe colapsar relaciones N:M ni inventar un único doctor, provider o location.
- Una oferta pertenece hoy a un provider por `provider_service_offers.provider_id`; el contrato deja la forma como objeto `provider` para el detalle de oferta. Si el modelo futuro permite múltiples providers por oferta, la evolución debe ser aditiva y no romper consumidores existentes.
- `clinic_name` proviene de texto comercial del staff (`provider_medical_staff.clinic_name`) y no debe presentarse como entidad formal de clínica/location.
- `specialty`, `role_title` y datos equivalentes solo pueden exponerse cuando correspondan a staff activo y publicable. El número crudo de `professional_license` **no** se expone salvo que exista una señal explícita y canónica que autorice su publicación pública; hoy el esquema no tiene esa señal, por lo que el contrato solo expone `professional_license_verified` (booleano, derivado de validación real — ver más abajo). Si en el futuro se agrega una señal pública explícita, el número crudo puede reincorporarse de forma aditiva.
- `description` en staff se conserva por compatibilidad. El campo recomendado para experiencia/bio pública es `public_experience`, para evitar que ConectarBot la trate como credential verificada.
- `professional_license_verified` solo es `true` si existe evidencia real de validación (hoy: `provider_verification_items.item_key='medical_license'` con `is_checked=1` para el provider de la oferta) y el staff tiene un número de licencia cargado. Nunca se infiere solo por tener el campo lleno.
- `staff[].services[]` debe salir de relaciones reales y elegibles del modelo: staff del mismo provider, relación activa en `provider_medical_staff_services`, servicio activo/no eliminado, oferta activa/no eliminada cuando se incluya `offer_id`. Si el esquema modela PCS en ambos lados (`provider_medical_staff_services.provider_catalog_service_id` y `provider_service_offers.provider_catalog_service_id`), el match debe ser exacto y no-NULL (NULL nunca actúa como comodín) y, si existe `provider_catalog_services.is_active`, esa fila debe estar activa. Si el esquema no modela PCS en ambos lados, se mantiene el fallback histórico por provider+servicio.
- `provider.verification` puede exponer un resumen público (`status`, `level`, `label`) derivado del proceso MedTravel. No debe incluir checklist, documentos, `trust_score`, usuarios internos, fechas internas ni notas. `provider.verified` se deriva de la misma fuente canónica que `provider.verification.status` (`provider_verification.status = 'verified'`); no existe una fuente separada, por lo que `verified=false` con `verification.label="Verified Premium"` es estructuralmente imposible.
- `provider.description`, `offer.description`, `service.description`, `staff[].description` y `staff[].public_experience` son textos libres públicos y deben pasar por redacción automática de contacto (emails, teléfonos, handles de WhatsApp/Telegram/redes) antes de responder; el contenido médico/comercial se conserva, solo se enmascara el dato de contacto.

#### Error y fallback genérico
Respuesta esperada para ID inválido, inactivo, eliminado o no elegible:
```json
{
  "ok": false,
  "data": null,
  "meta": { "source": "medtravel", "ts": "2026-08-20T00:00:00Z" },
  "error": { "code": "NOT_FOUND", "message": "Offer not found" }
}
```
- ConectarBot debe tratar este caso como fallback silencioso al flujo genérico.
- Este fallback no cambia la semántica de los endpoints actuales.

## Ejemplos curl
Usa tu API key real:
```bash
curl -i -H "X-ConectarBot-Key: $CONECTARBOT_API_KEY" https://<host>/api/conectarbot/v1/health

curl -i -H "X-ConectarBot-Key: $CONECTARBOT_API_KEY" https://<host>/api/conectarbot/v1/catalog/services

curl -i -H "X-ConectarBot-Key: $CONECTARBOT_API_KEY" https://<host>/api/conectarbot/v1/catalog/service/consulta-dermatologica

curl -i -H "X-ConectarBot-Key: $CONECTARBOT_API_KEY" https://<host>/api/conectarbot/v1/catalog/offer/9
```

## Pruebas manuales sugeridas
1) Con header correcto en `/health` → 200, `ok:true`.
2) Sin header → 401, `ok:false`, `error.code=UNAUTHORIZED`.
3) `/catalog/service/slug-inexistente` → 404, `ok:false`, `error.code=NOT_FOUND`.

## Notas de seguridad
- Usa HTTPS en producción.
- Mantén la API key fuera del repositorio (usa env `CONECTARBOT_API_KEY` en despliegue).
- Respuestas no exponen mensajes SQL; errores se registran en `error_log` del servidor.
- Límite por IP para mitigar abuso básico; ajustar según hosting.
- Endpoints son solo lectura; no exponen datos sensibles de pacientes.
- El contrato público para oferta no debe exponer `phone`, `email`, WhatsApp, `mobile`, `cell`, dirección exacta ni contactos equivalentes de providers, staff, pacientes o terceros.
- Las ubicaciones públicas se limitan a ciudad y textos comerciales ya publicables; no se debe devolver dirección privada/no aprobada.
