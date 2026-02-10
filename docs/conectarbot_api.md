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

## Ejemplos curl
Usa tu API key real:
```bash
curl -i -H "X-ConectarBot-Key: $CONECTARBOT_API_KEY" https://<host>/api/conectarbot/v1/health

curl -i -H "X-ConectarBot-Key: $CONECTARBOT_API_KEY" https://<host>/api/conectarbot/v1/catalog/services

curl -i -H "X-ConectarBot-Key: $CONECTARBOT_API_KEY" https://<host>/api/conectarbot/v1/catalog/service/consulta-dermatologica
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
