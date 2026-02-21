# DEV_CONTEXT

## Nota de ejecución
- La instrucción original pide descomprimir `/mnt/data/medtravel.zip`, pero en este entorno no existe `/mnt/data`. El análisis se ejecutó sobre el workspace ya montado en `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel`.

**Evidencia**
- `ls /mnt/data` -> `No such file or directory`
- `/Volumes/SSD-SAMSUNG/01_Proyectos_Desarrollo/Desarrollo/htdocs/medtravel` (repo presente y legible)

---

## A) Inventario rápido

### Estructura principal de carpetas
- `.git/`, `.vscode/`
- `admin/` (panel, AJAX, include, JS)
- `api/` (APIs públicas, incluye ConectarBot)
- `assets/` (librerías frontend/admin y recursos de terceros)
- `booking/` (wizard y submit)
- `config/` (configuraciones)
- `css/`, `scss/`, `sass/`, `js/`, `lib/` (frontend)
- `docs/` (documentación funcional/técnica)
- `img/` (medios)
- `inc/` (includes frontend)
- `partials/`, `privacy/`, `demo/`
- `sql/` (esquema/migraciones/scripts)

**Evidencia**
- `find . -maxdepth 1 -type d | sort`
- `find admin -maxdepth 1 -type d | sort`
- `find booking -maxdepth 1 -type f | sort`
- `find sql -maxdepth 1 -type f | sort`

### Inventario de archivos `.md` y propósito
Criterio: se listan **todos** los `.md` detectados, con el primer encabezado/línea no vacía como resumen de propósito.

| Archivo | Primera línea | Resumen (encabezado inicial) |
|---|---:|---|
| `ANALISIS_MULTIUSUARIO.md` | 1 | # Análisis del Sistema Multiusuario - MedTravel |
| `ANALISIS_RAZON_SOCIAL.md` | 1 | # Análisis: Campo Razón Social en Providers |
| `BOOKING_CTA_INTEGRATION.md` | 1 | # Integración de CTAs al Widget de Booking |
| `BOOKING_WIZARD_MEJORAS.md` | 1 | # Mejoras al Wizard de Booking |
| `BOOKING_WIZARD_PROVIDER_OFFERS.md` | 1 | # Integración de Ofertas de Proveedores en Booking Wizard |
| `CAMPO_RAZON_SOCIAL.md` | 1 | # Campo Razón Social en Proveedores |
| `CHECKLIST_RAZON_SOCIAL.md` | 1 | # ✅ CHECKLIST DE IMPLEMENTACIÓN - RAZÓN SOCIAL |
| `DIAGNOSTICO_LOGIN.md` | 1 | # 🔧 DIAGNÓSTICO Y SOLUCIÓN - LOGIN NO FUNCIONA |
| `GUIA_CONFIGURACION_SMTP.md` | 1 | # 📧 Guía de Configuración SMTP - MedTravel |
| `IMPLEMENTACION_ADMIN_HOME.md` | 1 | # Implementación Admin Home Edit - Nuevas Secciones |
| `MANUAL_PRUEBAS.md` | 1 | # MANUAL DE PRUEBAS - MEJORAS COMERCIALES MEDTRAVEL |
| `MEJORAS_COMERCIALES_README.md` | 1 | # MEJORAS COMERCIALES - Decisiones de Diseño y Guía de Implementación |
| `MODELO_NEGOCIO_ACTUALIZADO.md` | 1 | # Modelo de Negocio MedTravel - Actualización 2026 |
| `MODULO_CLIENTES_README.md` | 1 | # Módulo de Gestión de Clientes (CRM) - MedTravel |
| `MODULO_MI_EMPRESA.md` | 1 | # Módulo: Mi Empresa (Gestión de Perfil de Proveedor) |
| `PANEL_EMAIL_SETTINGS.md` | 1 | # 🎛️ Panel de Administración de Email SMTP |
| `PROVIDER_MANAGEMENT_README.md` | 1 | # Sistema de Gestión de Proveedores - MedTravel Services |
| `PROVIDER_SYSTEM_CHECKLIST.md` | 1 | # ✅ Checklist de Validación - Sistema de Proveedores |
| `PROVIDER_SYSTEM_RESUMEN.md` | 1 | # 🎯 Sistema de Gestión de Proveedores - Resumen Ejecutivo |
| `RESUMEN_IMPLEMENTACION.md` | 1 | # 🎯 RESUMEN DE IMPLEMENTACIÓN - Mejoras Comerciales MedTravel |
| `SERVICES_DYNAMIC_README.md` | 1 | # Services Page - Dynamic Content Management |
| `WIZARD_HEADER_ADMIN.md` | 1 | # Sistema de Administración del Header del Wizard de Booking |
| `assets/global/plugins/amcharts/amcharts/plugins/dataloader/readme.md` | 1 | # amCharts Data Loader |
| `assets/global/plugins/amcharts/amcharts/plugins/export/README.md` | 1 | # amCharts Export |
| `assets/global/plugins/amcharts/amcharts/plugins/responsive/readme.md` | 1 | # amCharts Responsive |
| `assets/global/plugins/amcharts/ammap/plugins/dataloader/readme.md` | 1 | # amCharts Data Loader |
| `assets/global/plugins/amcharts/ammap/plugins/export/README.md` | 1 | # amCharts Export |
| `assets/global/plugins/amcharts/ammap/plugins/responsive/readme.md` | 1 | # amCharts Responsive |
| `assets/global/plugins/amcharts/amstockcharts/plugins/dataloader/readme.md` | 1 | # amCharts Data Loader |
| `assets/global/plugins/amcharts/amstockcharts/plugins/export/README.md` | 1 | # amCharts Export |
| `assets/global/plugins/amcharts/amstockcharts/plugins/responsive/readme.md` | 1 | # amCharts Responsive |
| `assets/global/plugins/autosize/readme.md` | 1 | ## Summary |
| `assets/global/plugins/backstretch/README.md` | 1 | # Backstretch |
| `assets/global/plugins/bootbox/LICENSE.md` | 1 | # License |
| `assets/global/plugins/bootbox/README.md` | 1 | # Bootbox - Bootstrap powered alert, confirm and flexible dialog boxes |
| `assets/global/plugins/bootstrap-confirmation/README.md` | 1 | # Bootstrap-Confirmation |
| `assets/global/plugins/bootstrap-contextmenu/README.md` | 1 | Bootstrap Context Menu |
| `assets/global/plugins/bootstrap-datepaginator/README.md` | 1 | # Bootstrap Date Paginator |
| `assets/global/plugins/bootstrap-daterangepicker/README.md` | 1 | # Date Range Picker for Bootstrap |
| `assets/global/plugins/bootstrap-datetimepicker/README.md` | 1 | # Project : bootstrap-datetimepicker |
| `assets/global/plugins/bootstrap-editable/README.md` | 1 | # X-editable |
| `assets/global/plugins/bootstrap-growl/LICENSE.md` | 1 | The MIT License |
| `assets/global/plugins/bootstrap-growl/README.md` | 1 | #bootstrap-growl |
| `assets/global/plugins/bootstrap-hover-dropdown/README.md` | 1 | Bootstrap Hover Dropdown Plugin |
| `assets/global/plugins/bootstrap-markdown/README.md` | 1 | ## Bootstrap Markdown |
| `assets/global/plugins/bootstrap-maxlength/README.md` | 1 | # [Bootstrap MaxLength](http://mimo84.github.com/bootstrap-maxlength/) [![Build Status](https://travis-ci.org/mimo84/bootstrap-maxlength.png?branch=master)](https://travis-ci.org/mimo84/bootstrap-maxlength) [![Total views](https://sourcegraph.com/api/repos/github.com/mimo84/bootstrap-maxlength/counters/views.png)](https://sourcegraph.com/github.com/mimo84/bootstrap-maxlength) |
| `assets/global/plugins/bootstrap-modal/README.md` | 1 | Bootstrap Modal v2.2.5 |
| `assets/global/plugins/bootstrap-pwstrength/README.md` | 1 | # jQuery Password Strength Meter for Twitter Bootstrap |
| `assets/global/plugins/bootstrap-select/README.md` | 1 | bootstrap-select |
| `assets/global/plugins/bootstrap-selectsplitter/README.md` | 1 | # bootstrap-selectsplitter |
| `assets/global/plugins/bootstrap-sessiontimeout/LICENSE.md` | 1 | MIT License (MIT) |
| `assets/global/plugins/bootstrap-sessiontimeout/README.md` | 1 | # bootstrap-session-timeout |
| `assets/global/plugins/bootstrap-switch/README.md` | 1 | # Bootstrap Switch |
| `assets/global/plugins/bootstrap-timepicker/README.md` | 1 | Timepicker for Twitter Bootstrap 2.x |
| `assets/global/plugins/bootstrap-toastr/README.md` | 1 | # toastr |
| `assets/global/plugins/bootstrap-touchspin/LICENSE.md` | 1 | Bootstrap TouchSpin |
| `assets/global/plugins/bootstrap-touchspin/README.md` | 1 | # Bootstrap TouchSpin |
| `assets/global/plugins/bootstrap-typeahead/README.md` | 1 | Bootstrap 3 Typeahead |
| `assets/global/plugins/bootstrap-wizard/README.md` | 1 | Twitter Bootstrap Wizard |
| `assets/global/plugins/ckeditor/CHANGES.md` | 1 | CKEditor 4 Changelog |
| `assets/global/plugins/ckeditor/LICENSE.md` | 1 | Software License Agreement |
| `assets/global/plugins/ckeditor/README.md` | 1 | CKEditor 4 |
| `assets/global/plugins/ckeditor/plugins/scayt/LICENSE.md` | 1 | Software License Agreement |
| `assets/global/plugins/ckeditor/plugins/scayt/README.md` | 1 | CKEditor SCAYT Plugin |
| `assets/global/plugins/ckeditor/plugins/wsc/LICENSE.md` | 1 | Software License Agreement |
| `assets/global/plugins/ckeditor/plugins/wsc/README.md` | 1 | CKEditor WebSpellChecker Plugin |
| `assets/global/plugins/ckeditor/skins/moono/readme.md` | 1 | "Moono" Skin |
| `assets/global/plugins/clockface/README.md` | 1 | # Clockface  |
| `assets/global/plugins/codemirror/README.md` | 1 | # CodeMirror |
| `assets/global/plugins/counterup/README.md` | 1 | Counter-Up |
| `assets/global/plugins/dropzone/README.md` | 1 | <img alt="Dropzone.js" src="http://www.dropzonejs.com/images/new-logo.svg" /> |
| `assets/global/plugins/fancybox/CHANGELOG.md` | 1 | fancyBox - Changelog |
| `assets/global/plugins/fancybox/README.md` | 1 | fancyBox |
| `assets/global/plugins/fuelux/README.md` | 1 | [![Fuel UX](https://s3.amazonaws.com/fuelux/logo-gray.png)](http://exacttarget.github.com/fuelux) |
| `assets/global/plugins/gmaps/README.md` | 1 | GMaps.js - A Javascript library that simplifies your life |
| `assets/global/plugins/icheck/CHANGELOG.md` | 1 | ### Version 1.0.2 - March 03, 2014 |
| `assets/global/plugins/icheck/README.md` | 1 | # [iCheck plugin](http://fronteed.com/iCheck/) <sup>[1.0.2](#march-03-2014)</sup> |
| `assets/global/plugins/jcrop/README.md` | 1 | Jcrop Image Cropping Plugin |
| `assets/global/plugins/jquery-bootpag/README.md` | 1 | bootpag - dynamic pagination  |
| `assets/global/plugins/jquery-cookiebar/README.md` | 1 | # jquery.cookieBar |
| `assets/global/plugins/jquery-easypiechart/Readme.md` | 1 | # easyPieChart |
| `assets/global/plugins/jquery-file-upload/README.md` | 1 | # jQuery File Upload Plugin |
| `assets/global/plugins/jquery-inputmask/README.md` | 1 | # jquery.inputmask 3.x |
| `assets/global/plugins/jquery-knob/README.md` | 1 | jQuery Knob |
| `assets/global/plugins/jquery-minicolors/readme.md` | 1 | # jQuery MiniColors: A tiny color picker built on jQuery |
| `assets/global/plugins/jquery-mixitup/README.md` | 1 | ## MixItUp - A CSS3 and jQuery Filter & Sort Plugin |
| `assets/global/plugins/jquery-nestable/README.md` | 1 | Nestable |
| `assets/global/plugins/jquery-notific8/README.md` | 1 | # jquery-notific8 |
| `assets/global/plugins/jquery-slimscroll/README.md` | 1 | # What is slimScroll? |
| `assets/global/plugins/jquery-ui-touch-punch/README.md` | 1 | # jQuery UI Touch Punch |
| `assets/global/plugins/jquery-validation/README.md` | 1 | [jQuery Validation Plugin](http://jqueryvalidation.org/) - Form validation made easy |
| `assets/global/plugins/jqvmap/README.md` | 1 | ![JQVMap](http://jqvmap.com/img/logo.png "JQVMap") |
| `assets/global/plugins/jstree/README.md` | 1 | # jstree |
| `assets/global/plugins/morris/README.md` | 1 | # Morris.js - pretty time-series line graphs |
| `assets/global/plugins/owl-carousel/README.md` | 1 | ##New version 2.0.0-beta now available for testers. [Check it](http://www.owlgraphic.com/owlcarousel2/) |
| `assets/global/plugins/pace/README.md` | 1 | pace |
| `assets/global/plugins/plupload/readme.md` | 1 | # Plupload |
| `assets/global/plugins/select2 - Copy/LICENSE.md` | 1 | The MIT License (MIT) |
| `assets/global/plugins/select2 - Copy/README.md` | 1 | Select2 |
| `assets/global/plugins/select2/LICENSE.md` | 1 | The MIT License (MIT) |
| `assets/global/plugins/select2/README.md` | 1 | Select2 |
| `assets/global/plugins/socicon/readme.md` | 1 | #Socicon |
| `assets/global/plugins/typeahead/README.md` | 1 | [![build status](https://secure.travis-ci.org/twitter/typeahead.js.png?branch=master)](http://travis-ci.org/twitter/typeahead.js) |
| `docs/conectarbot_api.md` | 1 | # ConectarBot Data Connector v1 (MedTravel) |

---

## B) Contexto de desarrollo actual

### ¿Qué es MedTravel? (producto, objetivo, usuarios)
- El repositorio documenta a MedTravel como plataforma de turismo médico que conecta pacientes en USA (principalmente Florida) con proveedores médicos en Quindío, Colombia.
- El flujo documentado incluye coordinación de servicio médico + componentes de viaje (vuelo, hotel, transporte, alimentación, soporte y seguimiento post-tratamiento).

**Evidencia**
- `MODELO_NEGOCIO_ACTUALIZADO.md:10` → definición del producto y geografía (USA → Quindío)
- `MODELO_NEGOCIO_ACTUALIZADO.md:24-35` → operación origen/destino
- `MODELO_NEGOCIO_ACTUALIZADO.md:45-67` → flujo operativo end-to-end
- `MODELO_NEGOCIO_ACTUALIZADO.md:75-117` → separación servicios médicos vs gestión integral

### Stack tecnológico observado
- Backend: PHP procedural con MySQL/MariaDB (`mysqli`).
- Frontend público: Bootstrap + Owl Carousel + Lightbox + Font Awesome.
- Admin: template Metronic (assets/layout5 + plugins globales).
- Configuración DB por `.env`/`conexion.local.php` + fallback variables de entorno.

**Evidencia**
- `inc/include.php:38-64` → includes frontend (Bootstrap, Owl, Lightbox, FA)
- `admin/include/include.php:96-149` → stack visual/admin (Metronic + plugins)
- `admin/include/conexion.php:13-29` y `admin/include/conexion.php:76-89` → carga env + conexión `mysqli`
- `.env:1-8` y `admin/include/conexion.local.php:2-8` → configuración local

### Módulos/páginas clave (público y admin)

#### Frontend público
- `services.php`: catálogo de servicios complementarios (`medtravel_services_catalog`).
- `offers.php`: catálogo de ofertas médicas por prestador (`provider_service_offers` + `service_catalog`).
- `offer_detail.php`: detalle de una oferta médica.
- `booking/step-1.php`, `booking/wizard.php`, `booking/submit.php`: flujo de booking.
- `index.php`: mezcla sección hardcode y sección dinámica para servicios.

#### Booking: aceptación de términos y privacidad (Terms v1.1, Privacy v1.0)
- La aceptación ocurre en el primer formulario público renderizado por `inc/booking_form.php` (checkbox obligatorio con links a `/terms.php` y `/privacy/`).
- `booking/step-1.php` valida la aceptación y guarda en sesión: `terms_accepted`, `terms_accepted_at`, `terms_version`, `terms_ip`, `terms_user_agent`.
- `booking/submit.php` hace enforcement backend (sin bypass frontend) y persiste los campos en `booking_requests` cuando existen en el schema.
- Las versiones se definen en `inc/constants.php` (`TERMS_VERSION`, `PRIVACY_VERSION`) y se muestran en `/terms.php` y `/privacy/index.php` (URL `/privacy/`).

**Evidencia**
- `services.php:15-23` → consulta catálogo complementario
- `offers.php:41-64` → join ofertas médicas + servicio médico + prestador
- `offer_detail.php:27-35` → detalle de oferta por `provider_service_offers`
- `booking/step-1.php:9-37` → captura paso 1 en sesión
- `booking/wizard.php:345-352` y `booking/wizard.php:437-471` → Stage 2 complementarios / Stage 3 médicos
- `index.php:110-183` vs `index.php:541-552` → bloque hardcode + bloque DB (`home_services`)

#### Admin (servicios y operación)
- Servicios médicos: `admin/service_categories.php`, `admin/service_catalog.php`, `admin/provider_offers.php`.
- Servicios complementarios: `admin/providers_complementary.php`, `admin/medtravel_services.php`, `admin/paquetes.php`.
- Contenido de página servicios: `admin/services_edit.php`.
- Bookings: `admin/booking_requests.php`.

**Evidencia**
- `admin/include/include.php:299-355` → menú “Gestión” con submódulos médicos/complementarios/booking
- `admin/include/include.php:359-363` → acceso a “Mis Ofertas”
- `admin/service_categories.php:42-69` y `admin/service_catalog.php:40-72` → listados CRUD médicos
- `admin/provider_offers.php:119-130` y `admin/provider_offers.php:207-210` → ofertas por prestador
- `admin/providers_complementary.php:41-43` y `admin/medtravel_services.php:95-102` → catálogos complementarios
- `admin/services_edit.php:6-9` y `admin/services_edit.php:97-110` → edición de header/servicios coordinación

### Flujo de autenticación y roles
- Login web envía credenciales a `admin/include/log.php`.
- `log.php` valida password (bcrypt o legado SHA512+token), hidrata sesión (`id_usuario`, `rol`, `ppal`, `provider_id`, etc.).
- `valida_session.php` aplica restricción por página (admin-only y rol mínimo).
- Existe helper de permisos granulares (`user_can`) con tablas `permissions`/`role_permissions`.

**Evidencia**
- `login.php:47` → action a `admin/include/log.php`
- `admin/include/log.php:85-108` → validación de credenciales
- `admin/include/log.php:193-233` → variables de sesión y rol
- `admin/include/valida_session.php:56-60` y `admin/include/valida_session.php:71-83` → control por página
- `admin/include/roles.php:57-87` → helper `user_can`
- `sql/create_roles_and_migrate_users.sql:110-127` y `sql/create_roles_and_migrate_users.sql:130-155` → tablas/seed de permisos

### Configuración y despliegue
- Despliegue por cPanel (`.cpanel.yml`) con `rsync` y exclusiones (`.env`, logs, uploads).
- API ConectarBot v1 de solo lectura sobre `service_catalog`.

**Evidencia**
- `.cpanel.yml:2-18` → tarea de despliegue
- `config/conectarbot_api.php:2-10` → API key/rate/source
- `api/conectarbot/v1/index.php:131-140` y `api/conectarbot/v1/index.php:148-176` → endpoints catálogo de servicios
- `docs/conectarbot_api.md:45-63` → contrato documentado de endpoints catálogo

---

## Hallazgos de coherencia de contexto
- Hay múltiples líneas evolutivas de SQL (scripts de instalación antiguos, scripts de migración y dump), con diferencias de esquema relevantes.
- El repo mezcla módulos productivos y módulos “template/legacy” sin eliminar del todo hardcodes previos.

**Evidencia**
- `sql/service_catalog.sql:6-9` vs `sql/INSTALL.sql:103-106` vs `sql/INSTALL_LOCAL.sql:48-51`
- `index.php:110-183` (hardcode) coexistiendo con `index.php:541-552` (dinámico)
- `admin/include/include.php:1038-1077` → bloque dinámico comentado (legacy)

---

## 📌 Actualización Arquitectónica – Roles, Empresas y Dominios (2026-02-17)

### A) Separación de dominios
- Dominio Médico
  - Tabla: `providers`
  - Relación de usuario: `usuarios.provider_id`
- Dominio Complementario
  - Tabla: `service_providers`
  - Relación de usuario: `usuarios.service_provider_id`
- Regla de negocio vigente: un usuario solo puede pertenecer a un dominio empresarial a la vez (exclusión mutua entre `provider_id` y `service_provider_id`).

### B) Jerarquía actual de roles

| role_id | slug | dominio | scope requerido |
|---:|---|---|---|
| 1 | `principal` | Global | `none` |
| 2 | `administrative` | Global | `none` |
| 4 | `provider` | Médico | `provider_id` |
| 12 | `provider_admin` | Médico | `provider_id` |
| 13 | `complementary_admin` | Complementario | `service_provider_id` |
| 3 | `client` | Público | `none` |

- Roles 1 y 2: no requieren empresa asignada para login.

### C) Regla de ownership en booking_request_items (2026-02-20)
- Medical: `booking_request_items.provider_id` apunta a `providers.id` y `service_provider_id` debe ser NULL.
- Complementary: `booking_request_items.service_provider_id` apunta a `service_providers.id` y `provider_id` debe ser NULL.
- El listado de prestadores debe filtrar por su columna de dominio correspondiente y excluir items con ownership NULL.

**Verificacion SQL**
```sql
SELECT id, item_type, offer_id, medtravel_service_id, provider_id, service_provider_id
FROM booking_request_items
WHERE booking_request_id = <ID>
ORDER BY id ASC;
```
- Roles 4 y 12: requieren `provider_id` válido.
- Rol 13: requiere `service_provider_id` válido y activo.
- Rol 3 (`client`): no requiere empresa/scope en admin.
- El login valida empresa según dominio del rol.
- No se permite mezclar `provider_id` y `service_provider_id` en el mismo usuario.

### C) Validación de login
- Admin global (1/2): acceso sin exigencia de empresa.
- Médico (4/12): exige `provider_id` válido y existente.
- Complementario (13): exige `service_provider_id` válido en `service_providers` con `is_active=1`.
- La redirección `error=empresa` solo aplica cuando el rol requiere empresa y no tiene asignación válida.

### D) Mi Empresa (dominio dual)
- Dominio médico: `admin/ajax/mi_empresa.php` opera sobre `providers` usando `$_SESSION['provider_id']`.
- Dominio complementario: `admin/ajax/mi_empresa.php` opera sobre `service_providers` usando `$_SESSION['service_provider_id']`.
- El scope self se resuelve exclusivamente por sesión:
  - `$_SESSION['provider_id']`
  - `$_SESSION['service_provider_id']`
- Admin global (roles 1/2) puede consultar la vista pero no guardar (`self_edit_forbidden`).
- Todas las acciones de actualización son server-side y scoped al ID de sesión.
- `update_self_company` nunca permite cambiar de ID por payload; ignora cualquier intento de override externo.

### E) Login y hashing actual
- Hash canónico vigente: `sha512(token + password)`.
- Compatibilidad legacy activa:
  - `sha512(password + token)` para cuentas heredadas.
  - `password_verify` para hashes `bcrypt` (`$2y$`/`$2a$`).
- Creación y reset de contraseña usan helper centralizado de password (`admin/include/password_utils.php`).
- Validación de dominio en login:
  - `provider_scope_required`
  - `service_provider_scope_required`
- `error=empresa` se dispara solo cuando el rol requiere scope y no tiene asignación válida.

### F) Seguridad aplicada hoy
- Eliminado bypass en paquetes: `admin/ajax/paquetes.php` exige `packages.manage` en todas las acciones.
- Canonizado `packages.manage` como permiso real; sin alias inseguro heredado.
- Scope obligatorio y server-side en servicios complementarios para usuarios no admin.
- `mi_empresa` respeta dominio dual médico/complementario con validación de empresa activa.
- `admin/ajax/service_providers.php?action=list` se mantiene protegido (rol 13 no lista globalmente).
- En create/update de usuarios se forzan NULLs cruzados:
  - Rol médico (4/12) -> `service_provider_id = NULL`
  - Rol complementario (13) -> `provider_id = NULL`
  - Admin global (1/2) -> `provider_id = NULL` y `service_provider_id = NULL`

### G) Modelo de dominio resultante

```text
Usuario
 ├── role_id
 ├── provider_id (solo médico)
 └── service_provider_id (solo complementario)
```

- `role_id` + ownership (`provider_id`/`service_provider_id`) son la fuente de verdad de empresa.
- `provider_id` y `service_provider_id` son mutuamente excluyentes por diseño.

### H) Nota de compatibilidad legacy
- El modelo legacy complementario por `providers.kind='partner'` queda congelado para entrada nueva.
- El modelo oficial complementario activo en arquitectura es `service_providers`.

### Referencias de implementación
- `admin/include/roles.php`
- `admin/include/log.php`
- `admin/include/valida_session.php`
- `admin/ajax/paquetes.php`
- `admin/ajax/crear_usuario.php`
- `admin/ajax/usuarios.php`
- `admin/crear_usuario.php`
- `admin/js/crear_usuario.js`
- `admin/usuarios.php`
- `admin/js/usuarios.js`

### I) Ayuda UI en creación de usuarios (roles y scope)
- Se añadió ayuda informativa en `admin/crear_usuario.php` para prevenir errores de asignación de rol/dominio al crear usuarios.
- El bloque "Roles y accesos" se muestra solo para sesiones administrativas o con permisos de usuarios (`users.manage` / `users.create`).
- La tabla toma roles reales de BD (`roles`) y explica por rol:
  - Scope requerido (médico/complementario/ninguno).
  - Campo obligatorio (`provider_id` vs `service_provider_id`).
  - Resumen de módulos visibles según RBAC.
- Además, el selector de rol muestra una ayuda dinámica en tiempo real (frontend) usando `window.ROLES_HELP`.
- Este cambio no altera autenticación, endpoints ni estructura de menús; solo mejora la guía operativa en UI.

### J) Soft delete + cleanup DEV (estado vigente)
- Migración idempotente de soft delete centralizada en `sql/2026_02_18_soft_delete.sql` para:
  - `usuarios`
  - `providers`
  - `service_providers`
  - `medtravel_services_catalog`
- Columnas estándar: `is_deleted`, `deleted_at`, `deleted_by` + índice por `is_deleted`.
- Módulo admin de limpieza: `admin/cleanup.php`.
  - Visible solo para admin global (sesión validada con `is_role_admin_session()`).
  - Menú: item `Limpieza (DEV)` dentro de Administración (`admin/include/include.php`).
- Endpoints de cleanup:
  - `admin/ajax/cleanup_users.php`
  - `admin/ajax/cleanup_companies.php`
- Operaciones disponibles:
  - Soft delete (desactiva `activo/is_active` y marca `is_deleted=1`).
  - Restore (reactiva `activo/is_active=1` y limpia `is_deleted/deleted_at/deleted_by`).
- Entidades con restore operativo:
  - Usuarios (`usuarios`)
  - Providers médicos (`providers`)
  - Service providers (`service_providers`)
  - MedTravel services (`medtravel_services_catalog`)

### K) Documentación canónica vigente (operación booking 2026)
- Canónico 1: `MODELO_NEGOCIO_ACTUALIZADO.md`
  - Fuente principal de modelo operativo, roles, reglas previas a pago y backlog por fases.
- Canónico 2: `NEXT_STEPS_SERVICES.md`
  - Secuencia de ejecución técnica y control de avance por etapas.
- Canónico 3: `DEV_CONTEXT.md` (este documento)
  - Contexto técnico y trazabilidad de evidencia del estado real.

Documentos operativos alineados:
- `SERVICES_CATALOG.md` (catálogos y su integración con booking por item).

Reglas operativas actualizadas (documentadas):
- Booking tratado como **caso** con múltiples proveedores.
- Pipeline por **item** (no solo `booking_requests.status`).
- Precondiciones de pago por item:
  1. disponibilidad validada
  2. valoración virtual agendada
  3. valoración completada
  4. cotización ajustada y aceptada
  5. compromiso agendado en calendario
  6. `ready_for_payment` antes de cobro

Nota de alcance:
- Esta actualización es documental; no implica que todas las estructuras nuevas ya existan en base de datos/runtime.

---

## 2026-02 – Booking Multiproveedor (Provider-First Model)

### 1. Modelo operativo actualizado
- MedTravel NO controla disponibilidad.
- Cada item en `booking_request_items` inicia en `pending_provider`.
- Proveedor responde con `provider_confirmed`, `provider_rejected` o `provider_proposed_change`.
- Cliente consumirá estado tipo semáforo por servicio.
- Admin conserva capacidad de cancelación forzada.

### 2. Fuente de verdad
- Fuente canónica: `booking_request_items`.
- Flujo por item independiente del `booking_requests.status` global.

### 3. Seguridad y privacidad
- Scope de proveedor limitado a sus items.
- Se evita exposición de email/teléfono/nombre completo del paciente en vistas de proveedor.

### 4. Email profesional unificado
- Helper activo: `renderMedTravelEmail()`.
- Confirmación de booking usa template profesional unificado.
- Acceso cliente por token seguro; no se envía contraseña en texto plano.

### 5. Expiración y reenvío de acceso
- `set_password.php` cubre token válido, inválido y expirado.
- Reenvío con mensaje genérico anti-enumeración.

### 6. Sesión 24 horas
- Cookie de sesión a 24h.
- Regeneración de ID al autenticar.
- Expiración real server-side.

### 7. Estado actual
- Booking multiproveedor funcional.
- Provider-first operativo.
- Email profesional operativo.
- Portal cliente seguro operativo.

## Estado técnico actual (2026-02)
- `booking_request_items` es la fuente de verdad de items.
- Eliminado flujo `pending_admin` para operación provider-first.
- Migración SQL de estados ejecutable: `2026_02_18_item_status_provider_first.sql`.
- Migración de seguridad cliente ejecutable: `2026_02_18_booking_client_security.sql`.
- Migración de throttling de reenvío ejecutable: `2026_02_18_password_reset_sent_at.sql`.

**Fecha de actualización de esta sección:** 18 de febrero de 2026.

---

## Estado técnico actual (2026-02-19) – Estabilización booking + acceso cliente

### 1) Booking submit robusto contra drift de esquema
- `booking/submit.php` ahora crea/inserta de forma dinámica según columnas reales disponibles en `booking_requests` y `usuarios`.
- Se mantiene `booking_request_items` como fuente de verdad para items médicos y complementarios.
- Se corrige bind dinámico de prepared statements (`mysqli_stmt_bind_param`) para evitar errores fatales al crear/reusar usuario cliente.

### 2) Acceso cliente seguro compatible con esquema legacy
- El flujo de acceso usa token seguro y link `set_password.php?token=...`.
- En instalaciones sin `password_reset_token/password_reset_expires_at`, se usa fallback seguro sobre `usuarios.token`.
- Se protege el caso de conflicto por email privilegiado: si el email del booking coincide con cuenta admin/privilegiada, no se resetea esa cuenta automáticamente.

### 3) set_password.php resiliente
- Soporta token válido/expirado/inválido + reenvío con anti-enumeración.
- Throttling de reenvío con `password_reset_sent_at`.
- Corrección de bind dinámico en consultas de resend/lookup.
- Al guardar password, limpia estado de reset y redirige a login.

### 4) Compatibilidad con login histórico
- `admin/include/password_utils.php` mantiene validación legacy basada en hash histórico del sistema.
- Objetivo: no romper acceso de usuarios administrativos existentes mientras se habilita onboarding de cliente por token.

### 5) Entregabilidad de correo transaccional
- `admin/include/email_config.php` refuerza `AltBody` para incluir URL de creación de contraseña cuando exista `password_reset_url`.
- Se garantiza fallback de texto plano para clientes que no renderizan HTML completo.

### 6) Operación proveedor en admin
- `admin/js/my_booking_requests.js` se endurece para inicializar DataTables solo cuando el markup existe y con headers consistentes, evitando error `Cannot read properties of undefined (reading 'style')`.

### Estado operativo
- Booking guarda solicitud y items.
- Envío de correo de confirmación sigue sin bloquear guardado de booking.
- Flujo de acceso cliente por token queda operativo para emails no privilegiados.
- Riesgo controlado: conflictos por email admin se bloquean explícitamente para evitar takeover de cuenta privilegiada.

---

## Estado técnico actual (2026-02-19) – Portal Cliente mínimo

### 1) Separación de sesión cliente
- Nuevo helper: `inc/auth_client.php`.
- Funciones canónicas:
  - `is_client_session()`
  - `require_client_auth()`
  - `require_client_auth_ajax()`
  - `get_client_user_id()`
- Guard admin actualizado para redirigir clientes a `/client/index.php` y evitar exposición de vistas admin.

### 2) Header/notificaciones cliente sin bloque admin demo
- Se evita renderizar el bloque admin clásico para sesión cliente y se usa `header_notification_bar_client`.
- Fuente de datos de notificaciones cliente:
  - `booking_requests` filtrado por `client_user_id`
  - updates desde `booking_request_items` (status y notas de proveedor cuando existen)
- Endpoint: `client/ajax/get_notifications.php`
- Frontend: `client/js/notifications.js` (carga inicial + refresh simple).

### 3) Portal cliente funcional (MVP)
- Nuevas páginas:
  - `client/index.php` (dashboard)
  - `client/my_requests.php` (tabla de solicitudes)
  - `client/request_detail.php` (detalle + comunicación)
- Nuevos endpoints:
  - `client/ajax/list_requests.php`
  - `client/ajax/get_request_detail.php`
  - `client/ajax/list_messages.php`
  - `client/ajax/send_message.php`
- Seguridad: todas las consultas validan pertenencia por `client_user_id` y usan guard de cliente.

### 4) Comunicación cliente (sin tablas nuevas)
- Para MVP y compatibilidad de esquema real, mensajes del cliente se registran en `booking_requests.additional_notes` con marcador estructurado `[CLIENT_MESSAGE][timestamp]`.
- Mensajes/actualizaciones del proveedor se leen desde `booking_request_items` (ej. `provider_notes`, `provider_reject_reason`, `item_status`) si están disponibles.

### 5) Notificaciones por email en acciones del proveedor
- Endpoint actualizado: `admin/ajax/my_booking_requests.php`.
- Cuando un proveedor ejecuta `provider_confirm`, `provider_reject` o `provider_propose_change`:
  - se envía email al cliente (`booking_requests.email`)
  - se envía email al admin patient care (`loadEmailAccountsFromDB()['patientcare']['reply_to']`, fallback `from_email`)
- El envío es no bloqueante: errores SMTP no revierten el cambio de estado ni la respuesta JSON `ok`.

### 6) Deprecación del chat legacy en detalle de solicitud
- `client/request_detail.php` deja de exponer el bloque legacy de conversación embebida.
- La comunicación se centraliza en `client/app_inbox.php` con hilos por scope:
  - `thread_type=CARE` para hilo general del request.
  - `thread_type=ITEM` para hilo por item cuando aplica.
- Se mantiene el detalle del request y se agrega CTA "Open Inbox" para evitar duplicidad de canales.

### 7) Inbox persistente + unread en header (2026-02-19)
- Se agrega modelo persistente para mensajería Inbox:
  - `inbox_messages`
  - `inbox_thread_reads`
- Endpoints dedicados:
  - Cliente: `client/ajax/inbox.php`
  - Admin/Provider: `admin/ajax/inbox.php`
  - Notificaciones header: `client/ajax/get_notifications.php`, `admin/ajax/get_notifications.php`
- Flujo unread:
  - `list_threads` retorna `unread_count` por hilo.
  - `mark_read` persiste `last_read_message_id` por usuario/rol/hilo.
  - Header muestra badge + dropdown con mensajes no leídos y refresco periódico.
- Seguridad de scope:
  - Cliente solo ve hilos de sus requests/items.
  - Proveedor solo ve `ITEM` de items propios (sin `CARE`).
  - Admin/PatientCare pueden ver `CARE` + `ITEM`.
- Compatibilidad:
  - Si un hilo no tiene mensajes en tablas Inbox, se permite fallback legacy de lectura desde `booking_requests.additional_notes`.

### 8) Calendario scoped (admin/provider/client)
- Nueva tabla: `calendar_events` (migración: `sql/2026_02_19_calendar_events.sql`).
- Páginas:
  - `admin/app_calendar.php`
  - `client/app_calendar.php`
- Endpoints:
  - `admin/ajax/calendar.php` (`list_events`, `create_event`, `update_event`, `delete_event`)
  - `client/ajax/calendar.php` (`list_events` read-only)
- Reglas de scope:
  - Admin/PatientCare: CARE + ITEM.
  - Provider: solo ITEM de sus `item_id` scopeados.
  - Client: solo eventos de sus requests (`client_user_id` y fallback ownership por request).
- Integración:
  - Evento enlaza `request_id`, `item_id` y `thread_id` (`CARE:<request_id>` / `ITEM:<item_id>`) para abrir Inbox y detalle.

### 9) Calendar scope aligned with Inbox (CARE vs ITEM)
- `admin/ajax/calendar.php` refuerza validaciones server-side para mantener paridad con Inbox:
  - `CARE`: solo Admin/PatientCare, `request_id` obligatorio, `item_id` debe ser `NULL/0`.
  - `ITEM`: `item_id` obligatorio, `request_id` derivado desde `booking_request_items` scopeado.
  - `client_user_id` se resuelve siempre desde `booking_requests` y debe existir para crear/editar evento.
- Providers:
  - nunca pueden crear/editar `CARE`.
  - solo ven `ITEM` de items scopeados.
  - eliminación permitida solo para `ITEM` scopeado (nunca `CARE`).
- Clients (`client/ajax/calendar.php`):
  - endpoint read-only (`list_events`).
  - ownership estricto por `client_user_id` y fallback por ownership de `booking_requests`.
  - filtro adicional de integridad: solo devuelve eventos válidos (`CARE` sin `item_id`, `ITEM` con `item_id`).

### 10) Calendar workflow (preferred date + propose/accept)
- `booking/submit.php` ahora siembra automáticamente un evento inicial CARE al guardar booking:
  - título fijo: `Preferred date (patient)`
  - si `booking_datetime` es parseable: slot con hora (`status=proposed`)
  - si no hay fecha exacta y existe `timeline`: evento all-day con timeline en descripción
  - idempotencia por `request_id + event_type=CARE + title`
  - `thread_id` alineado a Inbox (`CARE:<request_id>`) cuando la columna existe
- `admin/ajax/calendar.php`:
  - cuando actor es provider en `create_event`/`update_event`, `status` se fuerza a `proposed`
  - inserta mensaje automático en Inbox `ITEM:<item_id>`
  - envía notificación no bloqueante por email a cliente + patientcare/admin
- `client/ajax/calendar.php`:
  - nueva acción `accept_event` (ownership estricto)
  - cambia estado a `confirmed`
  - registra mensaje `Patient accepted the proposed schedule.` en el thread CARE/ITEM
  - envía notificación no bloqueante a patientcare/admin y al provider (si ITEM)
- `client/app_calendar.php` + `client/js/app_calendar.js`:
  - modal muestra `Accept` y `Request change` cuando evento está en `proposed`
  - `Request change` redirige al Inbox thread correspondiente (negociación por mensaje).

### 11) Calendar create modal UX polish (2026-02-19)
- `admin/app_calendar.php` + `admin/js/app_calendar.js` mejoran la UX del modal de creación sin cambiar contrato backend:
  - título dinámico (`Propose schedule` para provider, `Create event` para admin/patientcare).
  - layout en dos columnas (`Event details` / `Schedule`) con labels y ayudas en inglés.
  - campos condicionales por tipo (`CARE` vs `ITEM`) y por rol (provider fijo en `ITEM` + `proposed`).
  - validaciones inline (item requerido para ITEM, request requerido para CARE, `end > start`).
  - botón primario con estado de carga (spinner) durante AJAX.
- La lista de items del modal reutiliza el mismo pool de `knownItemOptions`/threads del calendario para evitar duplicación de selectores y mantener deep-link por `item_id`.

### 12) Calendar overlap capacity (2026-02-19)
- Se agrega `calendar_capacity` para concurrencia por proveedor (migración: `sql/2026_02_19_provider_calendar_capacity.sql`):
  - `providers.calendar_capacity`
  - `service_providers.calendar_capacity`
- Regla de conflicto (`admin/ajax/calendar.php`):
  - Aplica en `create_event` y `update_event` para `event_type=ITEM`.
  - Se resuelve el proveedor real desde `booking_request_items` (`provider_id` o `service_provider_id`) sin confiar en input del cliente.
  - Si `overlaps_count >= calendar_capacity`, retorna `409` con `code=CONFLICT` y mensaje:
    `This time conflicts with another scheduled event.`
  - Eventos `cancelled` no cuentan para el solape.

### 13) calendar_capacity configurable desde Mi Empresa (2026-02-19)
- `admin/mi_empresa.php` expone el campo **Simultaneous appointments capacity** para proveedores médicos y complementarios.
- Persistencia en el mismo flujo existente (`admin/ajax/mi_empresa.php`, acción `update_self_company`) sin crear endpoints nuevos.
- Significado operativo:
  - `1`: capacidad individual (doctor/vehículo único, sin solapes).
  - `N > 1`: capacidad concurrente (clínica/flota con múltiples atenciones simultáneas).
- Validación backend:
  - cast a entero.
  - mínimo `1` (fallback seguro si llega vacío o inválido).
  - tope operativo `50` para evitar valores fuera de rango.

### 14) Provider header pending services notification (2026-02-19)
- Se extiende `admin/ajax/get_notifications.php` para incluir notificaciones persistentes de **Pending services** para providers:
  - `pending_services_count`
  - `pending_services` (lista limitada, con `item_id`, `request_id`, `service_name`, `destination/timeline`, `created_at`, `url_target`)
- Definición de pendiente: `booking_request_items.item_status = pending_provider` (normalizando legacy `pending_admin` / `pending_review`).
- Scope de provider alineado con `my_booking_requests` (filtro por `provider_id` / `service_provider_id`).
- `admin/js/header_notifications.js` suma badge total = `unread inbox + pending services` y renderiza ambas secciones en el dropdown.
- No hay mark-as-read manual para pending services: desaparecen automáticamente al cambiar el estado del item.

### 15) booking_request_items write-path normalization (2026-02-19)
- Desde `booking/submit.php` (función `insert_booking_items_mvp`), los nuevos `booking_request_items` se crean con `service_provider_id` como fuente de verdad.
- En nuevos inserts, `provider_id` se persiste explícitamente como `NULL` cuando la columna existe.
- `item_status` inicial se mantiene en `pending_provider`.
- Si no se puede resolver `service_provider_id` del item al crear, el item se omite y se registra en log (no se inventa owner).

### 16) Dev cleanup reset with preview/execute (2026-02-19)
- `admin/cleanup.php` ahora incluye un flujo de reset de entorno para desarrollo con dos fases:
  - **Preview (dry-run):** muestra conteos por tabla, FKs detectadas y orden de borrado seguro.
  - **Execute:** ejecuta `DELETE` por tabla en orden child->parent, con transacción y reporte final.
- Modos:
  - **Operational reset (recommended):** bookings/items + inbox + calendar.
  - **Full reset (dangerous):** además limpia catálogo demo de providers/services.
- Guard de seguridad:
  - ejecución real bloqueada salvo `APP_ENV=dev` y `ALLOW_DEV_RESET=true`.
  - doble confirmación obligatoria: texto `RESET` + checkbox irreversible.
  - full reset requiere confirmación adicional explícita.
- Logging:
  - registra preview/execute en `admin/logs/cleanup.log` y `error_log`.

### 17) Data deletion workflow (2026-02-20)
- Documento canónico de operación: `docs/data_deletion_workflow.md`
- Nuevo flujo end-to-end de eliminación de datos:
  - público: `data-deletion.php` -> `api/data_deletion_request.php`
  - admin: `admin/data_deletion_requests.php` + `admin/ajax/data_deletion_requests.php`
- Persistencia en DB:
  - nueva tabla `data_deletion_requests` (migración: `sql/2026_02_20_data_deletion_requests.sql`)
  - estados: `pending`, `processing`, `completed`, `failed`
  - auditoría: `processed_at`, `processed_by_user_id`, `result_summary`, `last_error`
- Proceso real (transaccional) implementado en `admin/include/data_deletion_service.php`:
  - resuelve usuario(s) y booking(s) por email/teléfono/client_user_id
  - elimina mensajes/calendario relacionados (`inbox_messages`, `inbox_thread_reads`, `calendar_events`)
  - anonimiza PII en `booking_requests` y `usuarios`
  - anonimiza CRM de clientes y artefactos asociados (`clientes`, `appointments`, `travel_packages`)
  - limpia vínculos/sesiones y adjuntos asociados (`provider_users`, `sessiones_activas`, `certificado`, `client_documents`)
  - elimina notificaciones CRM de cliente (`notifications`)
  - bloquea doble ejecución (`completed` no re-procesa)
- Seguridad:
  - ejecución restringida a sesión admin
  - sin PII en logs de aplicación ni en resumen técnico
  - preview de notificaciones admin de data deletion ahora usa DB y no expone email/teléfono.
- Prueba E2E en servidor (5 pasos + SQL):
  1. Crear un caso de prueba con cliente/usuario/bookings/mensajes usando email y/o phone de test.
     - SQL base:
       - `SELECT id, email, telefono, whatsapp FROM clientes WHERE email = 'qa+dd@dominio.com' OR telefono = '+15550001111' OR whatsapp = '+15550001111';`
       - `SELECT id, usuario, email, telefono, celular, role_id FROM usuarios WHERE email = 'qa+dd@dominio.com' OR usuario = 'qa+dd@dominio.com' OR telefono = '+15550001111' OR celular = '+15550001111';`
  2. Enviar solicitud en `https://medtravel.com.co/data-deletion.php` y capturar `request_id`.
     - SQL:
       - `SELECT id, request_id, status, created_at FROM data_deletion_requests WHERE request_id = 'DDR-XXXX' LIMIT 1;`
  3. Procesar desde `admin/data_deletion_requests.php` con usuario ADMIN.
     - SQL:
       - `SELECT id, request_id, status, processed_at, processed_by_user_id, result_summary, last_error FROM data_deletion_requests WHERE request_id = 'DDR-XXXX' LIMIT 1;`
  4. Verificar anonimización/borrado en tablas objetivo.
     - SQL:
       - `SELECT id, name, email, phone, additional_notes FROM booking_requests WHERE email = 'qa+dd@dominio.com' OR phone = '+15550001111';`
       - `SELECT id, nombre, apellido, email, telefono, whatsapp, status FROM clientes WHERE email = 'qa+dd@dominio.com' OR telefono = '+15550001111' OR whatsapp = '+15550001111';`
       - `SELECT COUNT(*) AS inbox_messages_remaining FROM inbox_messages WHERE sender_user_id IN (SELECT id FROM usuarios WHERE email = 'qa+dd@dominio.com');`
  5. Reintentar procesamiento del mismo request y validar idempotencia.
     - Esperado: respuesta `ok` con `message=already_completed` o `already_processing`, sin cambiar a `failed`.
     - SQL:
       - `SELECT status, result_summary, last_error FROM data_deletion_requests WHERE request_id = 'DDR-XXXX' LIMIT 1;`

### 18) Provider verification badges in public cards (2026-02-20)
- Se expone el nivel/estado de verificación del provider en frontend público, sin degradar UX cuando no hay datos:
  - `offers.php` (cards de catálogo)
  - `offer_detail.php` (ficha de oferta)
  - `booking/wizard.php` (cards de ofertas médicas del paso wizard)
- Regla de render:
  - `verified` => `Verified <Level>`
  - `in_review` => `In review <Level>`
  - `pending` => `Validation level <Level>`
  - sin `verification_level`/status soportado => no se muestra badge.
- Compatibilidad:
  - si la tabla `provider_verification` no existe en el entorno, las consultas hacen fallback seguro (`verification_status=''`, `verification_level=''`) y no rompen la página.
- Ajustes admin relacionados:
  - `admin/provider_verification.php`: orden de scripts corregido para evitar errores `jQuery is not defined` / `DataTable is not a function`.
  - `admin/js/provider_verification.js`: checklist migrado a `mt-checkbox` y removido doble indicador visual de check.

### 19) Fee Gate for client coordination (2026-02-20)
- Migración DB: `sql/2026_02_20_booking_requests_fee_gate.sql`
  - agrega en `booking_requests`:
    - `fee_status` (`not_required|pending|paid`, default `pending`)
    - `fee_required` (`TINYINT(1)`, default `0`)
- Helper central: `inc/fee_gate.php`
  - `is_booking_fee_paid($conexion, $booking_request_id)`
  - `is_booking_fee_required($conexion, $booking_request_id)`
- En `admin/ajax/my_booking_requests.php`:
  - al cambiar un item por flujo provider (`provider_confirmed/rejected/proposed_change`) se recalcula fee del booking:
    - si hay algún `provider_confirmed` => `fee_required=1`, `fee_status='pending'` (si no estaba `paid`)
    - si todos quedan `provider_rejected/cancelled` y no hay confirmados => `fee_required=0`, `fee_status='not_required'` (si no estaba `paid`)
- Bloqueo server-side:
  - `client/ajax/inbox.php`: para booking con fee requerido y no pagado devuelve `403` + `{ok:false, code:'FEE_REQUIRED'}` al enviar mensajes y al abrir/leer hilo `CARE`.
  - `client/ajax/calendar.php`: para booking con fee requerido y no pagado devuelve `403` + `{ok:false, code:'FEE_REQUIRED'}` en `accept_event`.
- UI mínima:
  - `client/app_inbox.php` + `client/js/app_inbox.js`: aviso y deshabilitado de envío ante `FEE_REQUIRED`.
  - `client/app_calendar.php` + `client/js/app_calendar.js`: aviso y deshabilitado de aceptación ante `FEE_REQUIRED`.
  - `offer_detail.php`: si sesión cliente + `booking_id/request_id` en URL con fee pendiente, oculta phone/email y muestra `Unlock after Coordination Fee`.
  - `admin/app_inbox.php` + `admin/js/app_inbox.js`: si `fee_locked=1`, deshabilita texto libre y muestra quick replies.
  - `admin/ajax/inbox.php`: bloquea `send_message` para providers cuando fee pendiente; permite `send_quick_reply`.
- Pre-fee structured communication: providers deben usar quick replies (fechas + solicitudes médicas) y el cliente ve un CTA de subida de documentos.
- Prueba rápida:
  1. Ejecutar migración SQL.
  2. Confirmar un item desde provider (`provider_confirmed`) y validar:
     - `SELECT id, fee_required, fee_status FROM booking_requests WHERE id = <booking_id>;`
  3. Como cliente, abrir Inbox/Calendar del booking y confirmar `403/FEE_REQUIRED` en acciones bloqueadas.
  4. Simular pago (`fee_status='paid'`) y reintentar: debe permitir interacción.
  5. Si todos items quedan `provider_rejected/cancelled`, validar `fee_status='not_required'`.

### 20) Medical documents pre-fee uploads (2026-02-20)
- Ruta final de uploads: `uploads/medical_docs/` (relativa a la raiz del proyecto).
  - En produccion, el servidor debe tener permisos de escritura sobre `uploads/medical_docs/`.
  - La carpeta se versiona con `uploads/medical_docs/.gitkeep`.
- Scoping estricto por booking/item en `client_documents`:
  - Columnas requeridas: `booking_request_id` (INT NULL), `item_id` (INT NULL).
  - Migracion: `sql/2026_02_20_client_documents_request_item_scope.sql`.
- Descarga segura (provider/admin):
  - Endpoint: `admin/ajax/download_medical_document.php?doc_id=...`.
  - Admin: acceso total.
  - Provider: solo si el documento pertenece a un `booking_request/item` dentro de su scope (`provider_id`/`service_provider_id` + `item_type`).

### 21) Pre-fee structured date proposal (2026-02-20)
- Providers pueden proponer un rango de fechas pre-fee usando `provider_proposed_date_from`/`provider_proposed_date_to`.
- El cliente acepta o rechaza desde Inbox con estado intermedio (no cierra el item).
- Mensajeria estructurada:
  - Provider: `[REPLY] PROPOSED_DATES YYYY-MM-DD to YYYY-MM-DD`
  - Client: `[ACTION] Client accepted proposed dates` / `[ACTION] Client rejected proposed dates`
- Estados:
  - Accept dates => `awaiting_client`
  - Reject dates => `provider_proposed_change`
- Verificacion SQL rapida:
  - `SELECT id, item_status, provider_proposed_date_from, provider_proposed_date_to FROM booking_request_items WHERE id = <item_id>;`
  - `SELECT id, body, created_at FROM inbox_messages WHERE item_id = <item_id> ORDER BY id DESC LIMIT 5;`

### 22) Final agreement pre-fee (2026-02-20)
- Cierre estructurado sin texto libre. El fee sigue en `pending` hasta pago real.
- Estados por accion:
  - Provider `FINAL_APPROVED` => `provider_confirmed`
  - Provider `FINAL_NOT_ELIGIBLE` => `provider_rejected`
  - Client `FINAL_ACCEPT_AND_PAY` => `client_accepted`
  - Client `FINAL_DECLINE` => `client_rejected`
- Mensajeria estructurada:
  - Provider: `[REPLY] FINAL_APPROVED` / `[REPLY] FINAL_NOT_ELIGIBLE: <reason>`
  - Client: `[ACTION] FINAL_ACCEPT_AND_PAY` / `[ACTION] FINAL_DECLINE`
- Verificacion SQL rapida:
  - `SELECT id, item_status FROM booking_request_items WHERE id = <item_id>;`
  - `SELECT id, body, created_at FROM inbox_messages WHERE item_id = <item_id> ORDER BY id DESC LIMIT 5;`

### 23) Ajustes UX/guards pre-fee (2026-02-20)
- En modal provider (`my_booking_requests`), la botonera estructurada se muestra siempre que `fee_locked=1`, sin depender de `item_status`.
- `FINAL_APPROVED` se oculta en UI cuando el item ya esta `provider_confirmed`.
- Guard backend explicito: `FINAL_APPROVED` sobre item ya `provider_confirmed` responde `409` con `code=ALREADY_CONFIRMED` (sin cambios de estado).
- Acciones legacy `CONFIRMAR/RECHAZAR/PROPONER` se movieron del listado al modal de detalle para evitar transiciones fuera de contexto.

### 24) Regla final fee lock modal provider (2026-02-20)
- `fee_locked` en `get_detail` se calcula como: `fee_required=1 AND fee_status!='paid'`.
- Con `fee_locked=1`, el modal muestra quick replies pre-fee y bloquea texto libre (`textarea` + `Send` disabled).
- `send_message` valida server-side la misma regla y responde `403` con `code=FEE_REQUIRED` para providers.

### 25) Regla final fee lock inbox cliente (2026-02-20)
- `client/ajax/inbox.php` (`list_messages`) calcula `fee_locked` con `fee_required=1 AND fee_status!='paid'` desde `booking_requests`, para CARE e ITEM.
- En pre-fee, el cliente ve `Quick actions`, y texto libre queda bloqueado por UI + backend (`send_message` => `403` `code=FEE_REQUIRED`).
- Se exponen `fee_required`, `fee_status` y `fee_locked` en el payload para render consistente del inbox.

### Negotiation Architecture (Canonical – 2026)

#### 1) Thread Types

##### CARE (MedTravel Coordination)
- Canal Cliente ↔ MedTravel.
- Siempre visible para cliente.
- En early stage el cliente puede enviar mensajes libres.
- No aplica bloqueo por stage gate (`FREE_MESSAGE_BLOCKED`) en CARE.
- Puede aplicar fee gate (`FEE_REQUIRED`) según estado comercial de la solicitud.

##### ITEM (Provider Negotiation)
- Canal Cliente ↔ Proveedor (médico o complementario).
- En early stage:
  - Cliente NO envía mensaje libre.
  - Proveedor NO envía mensaje libre.
  - Solo se permiten quick replies y structured actions.
- En etapa avanzada del item (reuso de estados actuales), se habilita mensajería libre.

#### 2) Early Stage Rules (Canonical)
- Early stage se considera con estados iniciales sin aprobación formal.

| Rol | CARE | ITEM |
|---|---|---|
| Cliente | Libre (si no hay fee lock) | Estructurado |
| Proveedor | N/A | Estructurado |
| Admin/PatientCare | Libre en CARE | Según flujo operativo del item |

#### 3) Structured Negotiation Actions

##### Provider → Client
- `REQUEST_ADDITIONAL_INFO`
- `PROPOSE_QUOTE_ADJUSTMENT`

##### Client → Provider
- `ACCEPT_PROPOSAL`
- `REQUEST_CHANGES`
- `REJECT_PROPOSAL`
- `DOCS_NOT_AVAILABLE`

##### Persistencia canónica
- Tabla: `inbox_messages`.
- Prefijos en `body`:
  - `[REQUEST_INFO]`
  - `[PROPOSE_QUOTE]`
  - `[PROPOSAL_RESPONSE]`
- Ownership y seguridad por `item_id`/scope:
  - Provider/admin: scope por `provider_id`/`service_provider_id` en ITEM.
  - Cliente: owner scope por `client_user_id` o email normalizado.
- Cambios de estado asociados:
  - Structured actions provider dejan el item en `awaiting_client`.
  - Respuestas del cliente actualizan a `client_accepted` / `provider_proposed_change` / `client_rejected` según acción.

#### 4) UI Canonical Behavior

##### Provider Inbox
- Compose bloqueado en early stage para ITEM.
- CARE no aplica para provider en negociación de item.
- Panel de ayuda colapsable en español.
- Structured cards renderizadas (request info, quote, proposal response).

##### Client Inbox
- CARE permite mensaje libre en early stage (si no hay `fee_locked`).
- ITEM bloqueado en early stage (solo estructurado).
- Títulos humanizados (sin `Item #`).

#### 5) Canonical UX Principle
- Nunca mostrar IDs técnicos en UI de encabezado de conversación.
- Evitar formato técnico `Item #X - Request #Y`.
- Mostrar nombre de servicio + referencia comercial `Solicitud #`.

#### 6) Canonical End-to-End Flow (booking → negotiation)
1. Cliente crea booking (`booking_requests`) y se generan hilos CARE + ITEM según items.
2. CARE (MedTravel) queda disponible para coordinación cliente-medtravel.
3. ITEM inicia en etapa temprana con negociación estructurada (sin chat libre).
4. Proveedor emite solicitudes/propuestas estructuradas en Inbox.
5. Cliente responde con acciones estructuradas y/o carga documentos.
6. Estados del item evolucionan según aceptación, cambios o rechazo.
7. Al pasar a etapa avanzada del item, mensajería libre se habilita en ITEM.

---

## L) Incidente 2026-02-21: upload de imagenes en Home Edit

### Sintoma
- `admin/ajax/home_edit.php` responde `error2: upload_error` con `error_code = 1` y `file_size = 0` al subir imagenes (ej. Accommodation).

### Causa raiz
- `error_code = 1` corresponde a `UPLOAD_ERR_INI_SIZE` (archivo excede `upload_max_filesize` o el POST supera `post_max_size`).
- La ruta `img/services/` no se valida/crea ni se verifica permisos antes de mover el archivo.

### Solucion aplicada (codigo)
- Manejo explicito de errores de `$_FILES['file']` y reporte de limites (`upload_max_filesize`, `post_max_size`).
- Verificacion/creacion de carpeta `img/services` y chequeo de permisos.

**Archivos**
- `admin/ajax/home_edit.php` (bloque `edit_service_img`).

### Checklist de servidor
- `upload_max_filesize`
- `post_max_size`
- `memory_limit`
- `max_file_uploads`
- `file_uploads`

### Ruta de guardado y permisos
- Ruta real: `img/services/` (relativa al root del sitio).
- Requiere permisos de escritura para el usuario del servidor web.

### Recomendaciones operativas
- Resolucion recomendada: 1200x800.
- Peso recomendado: menor a 2MB.
- Ver limite efectivo con `ini_get('upload_max_filesize')` y `ini_get('post_max_size')`.

---

## M) Testimonials dinamicos (Home + Cliente + Admin)

### Flujo
- Cliente crea o actualiza testimonio -> `status = pending`.
- Admin aprueba/rechaza desde panel.
- Home renderiza solo `status = approved`.

### Regla de negocio
- Un cliente puede tener solo 1 testimonio aprobado a la vez.
- Al aprobar uno nuevo, los anteriores aprobados del mismo cliente pasan a `archived`.

### Tabla y migracion
- Tabla: `testimonials`.
- Migracion: `sql/2026_02_21_testimonials.sql` (idempotente).

### Endpoints
- Cliente: `client/ajax/testimonials.php`
  - `action=get_mine`
  - `action=create_or_update`
- Admin: `admin/ajax/testimonials.php`
  - `action=list`
  - `action=approve`
  - `action=reject`

### Render Home
- Archivo: `index.php`.
- Origen: `inc/testimonials.php` -> `mt_testimonials_fetch_approved()`.

### Avatar neutro (sin imagen)
- Regla: si `avatar_path` es NULL/vacio, se renderiza inicial del nombre en circulo azul.
- Implementado en `index.php` con helper `mt_testimonials_initial()`.
- CSS scoped en `css/style.css` (clase `.testimonial-avatar-default`).
- No se usan fotos de personas por neutralidad/compliance.

### Paginas UI
- Cliente: `client/testimonial.php`.
- Admin: `admin/testimonials.php`.
