# Estructura del Menu Admin (Metronic + Bootstrap 3.3)

## Objetivo
Documentar la estructura real del menu del admin para diagnosticar y corregir rapidamente problemas de:
- item activo incorrecto
- submenu que no abre/cierra
- comportamiento inconsistente entre paginas

Este proyecto usa Metronic (layout5) sobre Bootstrap 3.3.

## Fuente de verdad del menu
- Archivo principal: `admin/include/include.php`
- Helpers de estado: `admin/include/menu_helpers.php`

### Como se construye
En `admin/include/include.php` se genera `$top_header_2` con:
- bloques principales: Dashboard, Gestion, Administracion, Contenido Web
- submenus por rol/permisos (`$es_admin`, `$es_prestador`, `$es_complementario`)
- clases activas via `menu_li_class(...)`

El helper `menu_li_class(...)` en `admin/include/menu_helpers.php` solo agrega:
- `active`
- `selected` (cuando recibe lista de targets)

No forzar `open` manualmente en PHP; Metronic lo gestiona en frontend.

## Regla de implementacion por pagina
Cada pagina admin debe seguir este patron:

1. Incluir:
`include('include/include.php');`

2. En `<head>`:
- `<?php echo $global_first_style; ?>`
- `<?php echo $theme_global_style; ?>`
- `<?php echo $theme_layout_style; ?>`
- (solo CSS adicional de la pagina)

3. En el header:
- `<?php echo $top_header; ?>`
- `<?php echo $top_header_2; ?>`

4. Antes de cerrar `</body>`:
- `<?php echo $sider_bar; ?>`
- `<?php echo $theme_layout_script; ?>` (una sola vez)
- JS de la pagina

## Anti-patrones (causan fallas de menu)
- Cargar `theme_layout_script` en `<head>`.
- Cargar `theme_layout_script` dos veces.
- Mezclar `theme_layout_script` + `theme_global_js` + `theme_layout_js` en la misma pagina.
- Cargar `jquery.min.js` o `bootstrap.min.js` manualmente ademas de `theme_layout_script`.
- Forzar clases `open`/`selected` en submenus desde PHP.

## Sintoma tipico y causa real
- "En unas paginas funciona y en otras no":
  - Causa habitual: carga duplicada/desordenada de scripts base (jQuery/Bootstrap/App/Layout).
  - Efecto: handlers de Metronic se inicializan varias veces o fuera de orden.

## Checklist de diagnostico rapido
1. Revisar la pagina que falla:
- verificar cuantas veces aparece `theme_layout_script`
- verificar si aparece `theme_global_js` o `theme_layout_js`
- verificar includes manuales de jQuery/Bootstrap

2. Regla esperada:
- solo `theme_layout_script` una vez, al final del body

3. Confirmar helper:
- `menu_helpers.php` sin logica custom de `open`

4. Despliegue:
- si local funciona y produccion no: limpiar OPcache / reiniciar PHP-FPM y hard refresh.

## Comandos utiles
Buscar cargas potencialmente conflictivas:

```bash
for f in admin/*.php; do
  c1=$(rg -n '\$theme_layout_script' "$f" | wc -l | tr -d ' ')
  c2=$(rg -n '\$theme_global_js' "$f" | wc -l | tr -d ' ')
  c3=$(rg -n '\$theme_layout_js' "$f" | wc -l | tr -d ' ')
  if [ "$c1" -gt 1 ] || { [ "$c1" -gt 0 ] && { [ "$c2" -gt 0 ] || [ "$c3" -gt 0 ]; }; }; then
    echo "$f layout_script=$c1 global_js=$c2 layout_js=$c3"
  fi
done | sort
```

Buscar jQuery/Bootstrap manuales duplicados:

```bash
rg -n 'jquery\.min\.js|bootstrap\.min\.js' admin/*.php
```

## Paginas ya normalizadas con esta logica
- `admin/service_categories.php`
- `admin/service_catalog.php`
- `admin/providers.php`
- `admin/provider_offers.php`
- `admin/data_deletion_requests.php`

## Nota operativa
Si vuelve a aparecer el mismo error:
1. aplicar esta misma regla de carga (script base unico)
2. no tocar clases `open` en PHP
3. validar en local
4. validar despliegue/caches en servidor

