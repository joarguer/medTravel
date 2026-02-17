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

### B) Jerarquía oficial de roles

| role_id | slug | alcance |
|---:|---|---|
| 1 | `principal` | Admin Global |
| 2 | `administrative` | Admin Global |
| 4 | `provider` | Proveedor Médico |
| 12 | `provider_admin` | Admin Prestador Médico |
| 13 | `complementary_admin` | Admin Proveedor Complementario |

- Roles 1 y 2: no requieren empresa asignada para login.
- Roles 4 y 12: requieren `provider_id` válido.
- Rol 13: requiere `service_provider_id` válido y activo.
- El login valida empresa según dominio del rol.
- No se permite mezclar `provider_id` y `service_provider_id` en el mismo usuario.

### C) Validación de login
- Admin global (1/2): acceso sin exigencia de empresa.
- Médico (4/12): exige `provider_id` válido y existente.
- Complementario (13): exige `service_provider_id` válido en `service_providers` con `is_active=1`.
- La redirección `error=empresa` solo aplica cuando el rol requiere empresa y no tiene asignación válida.

### D) Hardening aplicado hoy
- `packages.manage` se mantiene como permiso canónico (sin alias inseguro heredado).
- `admin/ajax/paquetes.php` exige explícitamente `packages.manage` para todas sus acciones.
- Se formalizó rol separado `complementary_admin` (id=13) para dominio complementario.
- En create/update de usuarios se forzan NULLs cruzados:
  - Rol médico (4/12) -> `service_provider_id = NULL`
  - Rol complementario (13) -> `provider_id = NULL`
  - Admin global (1/2) -> `provider_id = NULL` y `service_provider_id = NULL`

### E) Modelo de dominio resultante

```text
Usuario
 ├── role_id
 ├── provider_id (solo médico)
 └── service_provider_id (solo complementario)
```

- `role_id` + ownership (`provider_id`/`service_provider_id`) son la fuente de verdad de empresa.
- `provider_id` y `service_provider_id` son mutuamente excluyentes por diseño.

### F) Nota de compatibilidad legacy
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

### G) Ayuda UI en creación de usuarios (roles y scope)
- Se añadió ayuda informativa en `admin/crear_usuario.php` para prevenir errores de asignación de rol/dominio al crear usuarios.
- El bloque "Roles y accesos" se muestra solo para sesiones administrativas o con permisos de usuarios (`users.manage` / `users.create`).
- La tabla toma roles reales de BD (`roles`) y explica por rol:
  - Scope requerido (médico/complementario/ninguno).
  - Campo obligatorio (`provider_id` vs `service_provider_id`).
  - Resumen de módulos visibles según RBAC.
- Además, el selector de rol muestra una ayuda dinámica en tiempo real (frontend) usando `window.ROLES_HELP`.
- Este cambio no altera autenticación, endpoints ni estructura de menús; solo mejora la guía operativa en UI.
