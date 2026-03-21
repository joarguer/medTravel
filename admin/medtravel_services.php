<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$is_admin = is_role_admin_session();
$is_complementary = is_complementary_user_session();
$role_id = current_role_id();
$service_provider_session_id = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;
$can_list_complementary_providers = $is_admin || user_can(PERM_PROVIDERS_COMPLEMENTARY_MANAGE);
$page_heading = $is_admin ? 'Servicios Complementarios de MedTravel' : 'Mis Servicios Complementarios';
$page_caption = $is_admin ? 'Catálogo operativo y comercial del dominio complementario' : 'Catálogo operativo y comercial de mis servicios complementarios';
$page_helper = $is_admin
    ? 'Administra servicios complementarios asociados a proveedores ya registrados en el módulo de Proveedores Complementarios.'
    : 'Gestiona los servicios complementarios operativos y comercializables asociados a tu proveedor complementario.';
$cta_label = $is_admin ? 'Nuevo servicio complementario' : 'Nuevo servicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>MedTravel - <?php echo $page_heading; ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style;?>
    <!-- DataTables -->
    <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
    <!-- Bootstrap Toastr -->
    <link href="../../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />
    <!-- Bootstrap Select -->
    <link href="../../assets/global/plugins/bootstrap-select/css/bootstrap-select.min.css" rel="stylesheet" type="text/css" />
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <style>
        .service-type-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-flight { background: #3498db; color: white; }
        .badge-accommodation { background: #e74c3c; color: white; }
        .badge-transport { background: #f39c12; color: white; }
        .badge-meals { background: #27ae60; color: white; }
        .badge-support { background: #9b59b6; color: white; }
        .badge-other { background: #95a5a6; color: white; }
        
        .commission-positive { color: #27ae60; font-weight: bold; }
        .commission-negative { color: #e74c3c; font-weight: bold; }
        
        .status-available { color: #27ae60; }
        .status-limited { color: #f39c12; }
        .status-unavailable { color: #e74c3c; }
        .status-seasonal { color: #3498db; }
        
        /* Validación visual */
        .has-error .form-control {
            border-color: #e74c3c;
        }
        .has-error .control-label {
            color: #e74c3c;
        }
        .tab-error a {
            color: #e74c3c !important;
            font-weight: bold;
        }
        .tab-error a:after {
            content: ' ⚠️';
        }
        .service-image-preview {
            border: 1px dashed #dfe4ea;
            background: #f9fafb;
            height: 200px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .service-image-preview img {
            max-height: 100%;
            max-width: 100%;
            object-fit: cover;
        }
        .module-intro {
            max-width: 980px;
            margin-bottom: 18px;
        }
    </style>
</head>

<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
    <div class="wrapper">
        <!-- BEGIN HEADER -->
        <header class="page-header">
            <nav class="navbar mega-menu" role="navigation">
                <div class="container-fluid">
                    <?php echo $top_header;?>
                    <?php echo $top_header_2;?>
                </div>
            </nav>
        </header>
        <!-- END HEADER -->

        <div class="container-fluid">
            <div class="page-content">
                <!-- BEGIN BREADCRUMBS -->
                <div class="breadcrumbs">
                    <h1><?php echo $page_heading; ?>
                        <small><?php echo $page_caption; ?></small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <?php if ($is_admin): ?>
                        <li><a href="providers_complementary.php">Proveedores Complementarios</a></li>
                        <li class="active">Servicios Complementarios</li>
                        <?php else: ?>
                        <li><a href="providers_complementary.php">Dominio Complementario</a></li>
                        <li class="active">Mis Servicios Complementarios</li>
                        <?php endif; ?>
                    </ol>
                </div>
                <!-- END BREADCRUMBS -->

                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-sidebar">
                            <nav class="navbar" role="navigation">
                                <ul class="nav navbar-nav">
                                    <li class="active"><a href="medtravel_services.php"><i class="icon-layers"></i> <?php echo $page_heading; ?></a></li>
                                </ul>
                            </nav>
                        </div>
                        <div class="page-content-col">
                            <div class="alert alert-info module-intro">
                                <strong>Dominio complementario:</strong> este módulo administra el <strong>catálogo operativo/comercial de servicios complementarios</strong> de MedTravel.
                                <br>
                                <span class="small">Aquí gestionas servicios comercializables basados en <strong>proveedores complementarios ya registrados</strong>. No es el catálogo maestro médico, no administra la entidad del proveedor y no reemplaza módulos como <strong>Proveedores Complementarios</strong> u ofertas del dominio médico.</span>
                            </div>
                            <?php if ($is_complementary && $service_provider_session_id > 0): ?>
                            <div class="alert alert-success module-intro">
                                Tu sesión está acotada al proveedor complementario actual. Los servicios que crees o edites quedarán asociados a ese scope operativo.
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning module-intro">
                                Estás operando desde administración central. Primero administra la entidad del proveedor en <strong>Proveedores Complementarios</strong> y luego asocia aquí los servicios complementarios correspondientes.
                            </div>
                            <?php endif; ?>
                            <p class="text-muted module-intro">
                                <?php echo $page_helper; ?>
                            </p>

                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="fa fa-filter"></i>
                                        <span class="caption-subject font-blue bold uppercase">Filtros del catálogo</span>
                                        <span class="caption-helper">Refina la vista por tipo, estado y disponibilidad operativa</span>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <select id="filter_type" class="form-control">
                                                <option value="">Todos los tipos</option>
                                                <option value="flight">✈️ Vuelos</option>
                                                <option value="accommodation">🏨 Alojamientos</option>
                                                <option value="transport">🚗 Transporte</option>
                                                <option value="meals">🍽️ Alimentación</option>
                                                <option value="support">🎧 Apoyo logístico</option>
                                                <option value="other">📦 Otros</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select id="filter_status" class="form-control">
                                                <option value="">Todos los estados</option>
                                                <option value="1">Activos</option>
                                                <option value="0">Inactivos</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select id="filter_availability" class="form-control">
                                                <option value="">Toda la disponibilidad</option>
                                                <option value="available">Disponible</option>
                                                <option value="limited">Limitado</option>
                                                <option value="unavailable">No disponible</option>
                                                <option value="seasonal">Estacional</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-primary btn-block" onclick="applyFilters()">
                                                <i class="fa fa-search"></i> Aplicar filtros
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-layers font-blue"></i>
                                        <span class="caption-subject font-blue bold uppercase"><?php echo $page_caption; ?></span>
                                        <span class="caption-helper">Servicios complementarios operativos y comercializables vinculados a proveedores complementarios</span>
                                    </div>
                                    <div class="actions">
                                        <button type="button" class="btn btn-success" id="btnNewService">
                                            <i class="fa fa-plus"></i> <?php echo $cta_label; ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <table class="table table-striped table-bordered table-hover" id="services_table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tipo</th>
                                                <th>Servicio complementario</th>
                                                <th>Proveedor complementario</th>
                                                <th>Costo</th>
                                                <th>Precio de venta</th>
                                                <th>Margen</th>
                                                <th>Estado</th>
                                                <th>Disponibilidad</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data loaded by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php echo $footer;?>
        </div>
    </div>

    <?php echo $sider_bar;?>

    <!-- MODAL CREAR/EDITAR SERVICIO -->
    <div class="modal fade" id="serviceModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" style="width: 90%; max-width: 1000px;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="serviceModalTitle">Nuevo servicio complementario</h4>
                </div>
                <form id="serviceForm" class="form-horizontal">
                    <input type="hidden" id="service_id" name="id">
                    <div class="modal-body">
                        <div class="alert alert-info" style="margin-bottom:18px;">
                            Esta ficha administra un <strong>servicio complementario operativo/comercializable</strong> de MedTravel.
                            El <strong>proveedor complementario</strong> se gestiona aparte en <a href="providers_complementary.php"><strong>Proveedores Complementarios</strong></a>.
                        </div>
                        <div class="tabbable-line">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_basic" data-toggle="tab">Información Básica</a></li>
                                <li><a href="#tab_provider" data-toggle="tab">Proveedor</a></li>
                                <li><a href="#tab_pricing" data-toggle="tab">Precios</a></li>
                                <li><a href="#tab_details" data-toggle="tab">Detalles</a></li>
                                <li><a href="#tab_display" data-toggle="tab">Visualización</a></li>
                            </ul>
                            <div class="tab-content">
                                <!-- TAB: BASIC INFO -->
                                <div class="tab-pane active" id="tab_basic">
                                    <div class="form-body">
                                        <div class="form-group">
                                            <label class="control-label col-md-3">Tipo de Servicio <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <select class="form-control" id="service_type" name="service_type" required>
                                                    <option value="">Seleccionar tipo...</option>
                                                    <option value="flight">✈️ Vuelo</option>
                                                    <option value="accommodation">🏨 Alojamiento</option>
                                                    <option value="transport">🚗 Transporte</option>
                                                    <option value="meals">🍽️ Alimentación</option>
                                                    <option value="support">🎧 Soporte</option>
                                                    <option value="other">📦 Otro</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Nombre del Servicio <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="service_name" name="service_name" required>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Código del Servicio</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="service_code" name="service_code" placeholder="ej., FLT-MIA-AXM">
                                                <small class="help-block">Código de referencia interna</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Descripción Corta</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="short_description" name="short_description" maxlength="255">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Descripción Completa</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB: PROVIDER -->
                                <div class="tab-pane" id="tab_provider">
                                    <div class="form-body">
                                        <div class="alert alert-info">
                                            <i class="fa fa-info-circle"></i> El proveedor complementario se administra por separado en <strong>Proveedores Complementarios</strong>. Aquí solo vinculas el servicio a un proveedor ya registrado.
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Proveedor complementario</label>
                                            <div class="col-md-9" id="provider_selector_wrapper">
                                                <div class="input-group">
                                                    <select class="form-control" id="provider_id" name="provider_id">
                                                        <option value="">Seleccionar proveedor complementario...</option>
                                                    </select>
                                                    <span class="input-group-btn">
                                                        <button class="btn btn-success" type="button" id="btnNewProvider">
                                                            <i class="fa fa-external-link"></i> Ir a proveedores
                                                        </button>
                                                    </span>
                                                </div>
                                                <small class="help-block">Selecciona la entidad complementaria ya creada: hoteles, transporte, aerolíneas, restaurantes o apoyos logísticos.</small>
                                                <small class="help-block" id="provider_scope_hint" style="display:none;">El proveedor complementario está fijado por el scope actual de tu sesión.</small>
                                            </div>
                                        </div>

                                        <hr>
                                        <h4 class="form-section">Datos del proveedor complementario <small>(Solo lectura - editar en Proveedores Complementarios)</small></h4>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Nombre Comercial</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="provider_name_display" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Persona de Contacto</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="provider_contact_display" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Email</label>
                                            <div class="col-md-9">
                                                <input type="email" class="form-control" id="provider_email_display" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Teléfono</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="provider_phone_display" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Notas del vínculo</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" id="provider_notes" name="provider_notes" rows="3" placeholder="Notas específicas sobre este servicio con el proveedor complementario"></textarea>
                                                <small class="help-block">Úsalo para registrar condiciones operativas o comerciales de este servicio en particular.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB: PRICING -->
                                <div class="tab-pane" id="tab_pricing">
                                    <div class="form-body">
                                        <div class="alert alert-info">
                                            <i class="fa fa-info-circle"></i> Este módulo registra el costo local del servicio complementario y su precio de venta en USD. El margen se calcula automáticamente.
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Tasa de cambio <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <span class="input-group-addon">1 USD =</span>
                                                    <input type="number" step="0.01" class="form-control" id="exchange_rate" name="exchange_rate" value="4150.00">
                                                    <span class="input-group-addon">COP</span>
                                                </div>
                                                <small class="help-block">Tasa vigente usada para convertir el costo local a USD.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Costo en COP <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <span class="input-group-addon">$</span>
                                                    <input type="number" step="0.01" class="form-control" id="cost_price_cop" name="cost_price_cop" value="0.00">
                                                    <span class="input-group-addon">COP</span>
                                                </div>
                                                <small class="help-block">Valor que MedTravel paga al proveedor complementario en pesos colombianos.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Costo en USD (auto)</label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <span class="input-group-addon">$</span>
                                                    <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" value="0.00" readonly>
                                                    <span class="input-group-addon">USD</span>
                                                </div>
                                                <small class="help-block">Se calcula automáticamente a partir del costo en COP.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Precio de venta en USD <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <span class="input-group-addon">$</span>
                                                    <input type="number" step="0.01" class="form-control" id="sale_price" name="sale_price" value="0.00" required>
                                                    <span class="input-group-addon">USD</span>
                                                </div>
                                                <small class="help-block">Valor comercial que paga el cliente a MedTravel en dólares.</small>
                                            </div>
                                        </div>

                                        <input type="hidden" id="currency" name="currency" value="USD">

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Vista previa del margen</label>
                                            <div class="col-md-9">
                                                <div id="commission_preview" class="well" style="background: #f8f9fa; padding: 15px;">
                                                    <strong>Margen estimado:</strong> <span id="preview_amount">$0.00</span> 
                                                    (<span id="preview_percentage">0.00%</span>)
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB: DETAILS -->
                                <div class="tab-pane" id="tab_details">
                                    <div class="form-body">
                                        <div class="form-group">
                                            <label class="control-label col-md-3">Disponibilidad</label>
                                            <div class="col-md-9">
                                                <select class="form-control" id="availability_status" name="availability_status">
                                                    <option value="available">Disponible</option>
                                                    <option value="limited">Limitado</option>
                                                    <option value="unavailable">No disponible</option>
                                                    <option value="seasonal">Estacional</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Cupo disponible</label>
                                            <div class="col-md-9">
                                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" placeholder="Déjalo vacío si no aplica un límite">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Anticipación mínima de reserva</label>
                                            <div class="col-md-9">
                                                <input type="number" class="form-control" id="booking_lead_time" name="booking_lead_time" value="0">
                                                <small class="help-block">Cantidad de días de anticipación requeridos para reservar este servicio.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Detalle avanzado del servicio (JSON)</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" id="service_details" name="service_details" rows="6" placeholder='{"key": "value"}'></textarea>
                                                <small class="help-block">Opcional. Úsalo solo si necesitas atributos técnicos adicionales para este servicio complementario.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Tags</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="tags" name="tags" placeholder="tag1, tag2, tag3">
                                                <small class="help-block">Etiquetas separadas por coma.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Notas internas</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" id="internal_notes" name="internal_notes" rows="3"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB: DISPLAY -->
                                <div class="tab-pane" id="tab_display">
                                    <div class="form-body">
                                        <div class="form-group">
                                            <label class="control-label col-md-3">Clase de icono</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="icon_class" name="icon_class" placeholder="fa fa-plane">
                                                <small class="help-block">Clase de icono Font Awesome para referencias internas o visuales.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Imagen</label>
                                            <div class="col-md-9">
                                                <div id="service_image_preview" class="service-image-preview text-muted">No hay imagen seleccionada</div>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-default" id="btnUploadImage"><i class="fa fa-image"></i> Subir o cambiar</button>
                                                    <button type="button" class="btn btn-default" id="btnClearImage"><i class="fa fa-trash"></i> Quitar</button>
                                                </div>
                                                <input type="hidden" id="image_url" name="image_url">
                                                <small class="help-block">Formatos permitidos: JPG, PNG, GIF y WEBP.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Orden de visualización</label>
                                            <div class="col-md-9">
                                                <input type="number" class="form-control" id="display_order" name="display_order" value="0">
                                                <small class="help-block">Los números menores aparecen primero.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Estado</label>
                                            <div class="col-md-9">
                                                <label class="mt-checkbox mt-checkbox-outline">
                                                    <input type="checkbox" id="is_active" name="is_active" value="1" checked> Activo
                                                    <span></span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Destacado</label>
                                            <div class="col-md-9">
                                                <label class="mt-checkbox mt-checkbox-outline">
                                                    <input type="checkbox" id="featured" name="featured" value="1"> Marcar como servicio destacado
                                                    <span></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveService" disabled>
                            <i class="fa fa-save"></i> Guardar servicio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- THEME (carga jQuery y núcleo) -->
    <?php echo $theme_layout_script;?>
    <script>
        window.MEDTRAVEL_SERVICES_CTX = {
            isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
            isComplementary: <?php echo $is_complementary ? 'true' : 'false'; ?>,
            roleId: <?php echo $role_id !== null ? (int)$role_id : 'null'; ?>,
            serviceProviderId: <?php echo $service_provider_session_id > 0 ? $service_provider_session_id : 'null'; ?>,
            canListProviders: <?php echo $can_list_complementary_providers ? 'true' : 'false'; ?>
        };
    </script>
    <!-- PAGE LEVEL PLUGINS (después de jQuery) -->
    <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap-select/js/bootstrap-select.min.js" type="text/javascript"></script>
    <!-- PAGE SCRIPT -->
    <script src="js/medtravel_services.js" type="text/javascript"></script>
</body>
</html>
