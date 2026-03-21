<?php
include("include/include.php");
// RBAC explícito para gestión de paquetes.
if (!user_can(PERM_PACKAGES_MANAGE)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
$id_usuario = $_SESSION['id_usuario'];
$page_heading = 'Paquetes y Cotizaciones Integrales';
$page_caption = 'Consola central de propuestas integrales para cliente final';
$page_helper = 'Aquí MedTravel arma propuestas compuestas para cliente usando componentes médicos, complementarios y comerciales dentro de un mismo flujo de cotización.';
$page_cta_label = 'Nueva cotización integral';
// Etapa 2: activación automática del catálogo mediante ping query real.
// Evita dependency de information_schema en hosting compartido.
$packages_catalog_schema_ready = false;
if (isset($conexion) && $conexion) {
    $catalog_schema_ping = @mysqli_query($conexion, "SELECT 1 FROM package_services LIMIT 1");
    if ($catalog_schema_ping !== false) {
        $packages_catalog_schema_ready = true;
        mysqli_free_result($catalog_schema_ping);
    }
}
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
        <!-- Bootstrap DateTimePicker -->
        <link href="../../assets/global/plugins/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css" rel="stylesheet" type="text/css" />
        <!-- Bootstrap Select -->
        <link href="../../assets/global/plugins/bootstrap-select/css/bootstrap-select.min.css" rel="stylesheet" type="text/css" />
        <?php echo $theme_global_style;?>
        <?php echo $theme_layout_style;?>
        <link rel="shortcut icon" href="favicon.ico" /> 
        <style>
            .service-selection-container {
                max-height: 400px;
                overflow-y: auto;
            }
            .service-item {
                border: 1px solid #e5e5e5;
                border-radius: 4px;
                padding: 12px;
                margin-bottom: 10px;
                transition: all 0.3s;
            }
            .service-item:hover {
                background: #f9f9f9;
                border-color: #3598dc;
            }
            .service-item.selected {
                background: #e7f3ff;
                border-color: #3598dc;
                border-width: 2px;
            }
            .service-item-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 8px;
            }
            .service-item-title {
                font-weight: bold;
                font-size: 14px;
            }
            .service-item-price {
                color: #27ae60;
                font-weight: bold;
                font-size: 16px;
            }
            .service-item-provider {
                color: #7f8c8d;
                font-size: 12px;
                margin-bottom: 4px;
            }
            .service-item-description {
                color: #555;
                font-size: 12px;
            }
            .service-quantity-control {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-top: 10px;
            }
            .service-quantity-control input {
                width: 60px;
                text-align: center;
            }
            .module-intro {
                max-width: 980px;
                margin-bottom: 18px;
            }
        </style>
    </head>
    <body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
        <!-- BEGIN CONTAINER -->
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
                            <li><a href="#">Gestión</a></li>
                            <li class="active">Paquetes y Cotizaciones Integrales</li>
                        </ol>
                    </div>
                    <!-- END BREADCRUMBS -->
                    
                    <div class="page-content-container">
                        <div class="page-content-row">
                            <div class="page-sidebar">
                                <nav class="navbar" role="navigation">
                                    <ul class="nav navbar-nav">
                                        <li class="active"><a href="paquetes.php"><i class="icon-briefcase"></i> <?php echo $page_heading; ?></a></li>
                                    </ul>
                                </nav>
                            </div>
                            <div class="page-content-col">
                                <div class="alert alert-info module-intro">
                                    <strong>Consola central de MedTravel:</strong> este módulo administra <strong>paquetes y cotizaciones integrales para cliente final</strong>.
                                    <br>
                                    <span class="small">Aquí se arma una propuesta compuesta combinando componentes médicos, complementarios y comerciales dentro de un mismo flujo operativo.</span>
                                </div>
                                <div class="alert alert-warning module-intro">
                                    <strong>Alcance funcional:</strong> esta pantalla <strong>no reemplaza</strong> las <strong>ofertas médicas</strong> del prestador ni el <strong>catálogo de servicios complementarios</strong>.
                                    <br>
                                    <span class="small">Usa esas capas como insumo para construir una cotización integral centrada en el cliente y en la propuesta comercial/operativa de MedTravel.</span>
                                </div>
                                <p class="text-muted module-intro"><?php echo $page_helper; ?></p>

                                <div class="portlet light bordered">
                                    <div class="portlet-title">
                                        <div class="caption">
                                            <i class="icon-briefcase font-dark"></i>
                                            <span class="caption-subject font-dark bold uppercase"><?php echo $page_caption; ?></span>
                                            <span class="caption-helper">Paquetes integrales armados por MedTravel para cotizar, confirmar y dar seguimiento comercial</span>
                                        </div>
                                        <div class="actions">
                                            <button type="button" class="btn btn-primary" id="btnNuevoPaquete">
                                                <i class="fa fa-plus"></i> <?php echo $page_cta_label; ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <table class="table table-striped table-bordered table-hover" id="tabla_paquetes">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Paquete / Cotización</th>
                                                    <th>Cliente</th>
                                                    <th>Fechas</th>
                                                    <th>Días</th>
                                                    <th>Total cotizado</th>
                                                    <th>Margen Neto</th>
                                                    <th>Estado</th>
                                                    <th>Pago</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END CONTAINER -->
        </div>
        <!-- END WRAPPER -->

        <!-- BEGIN MODAL - CREAR/EDITAR PAQUETE -->
        <div class="modal fade" id="modalPaquete" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" style="width: 90%; max-width: 1200px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                        <h4 class="modal-title" id="modalPaqueteTitle">Nueva cotización integral</h4>
                    </div>
                    <form id="formPaquete" class="form-horizontal">
                        <input type="hidden" id="paquete_id" name="id">
                        <div class="modal-body">
                            <div class="alert alert-info" style="margin-bottom:18px;">
                                Esta ficha construye una <strong>propuesta integral para cliente</strong> desde MedTravel.
                                Puedes combinar <strong>componente médico</strong>, <strong>insumos complementarios</strong> y <strong>condiciones comerciales</strong> en un mismo paquete/cotización.
                            </div>
                            <div class="tabbable-line">
                                <ul class="nav nav-tabs">
                                    <li class="active"><a href="#tab_general" data-toggle="tab">Contexto general</a></li>
                                    <li><a href="#tab_catalog_services" data-toggle="tab"><i class="fa fa-shopping-cart"></i> Insumos complementarios</a></li>
                                    <li><a href="#tab_vuelo" data-toggle="tab">Componente vuelo (manual)</a></li>
                                    <li><a href="#tab_hotel" data-toggle="tab">Componente alojamiento (manual)</a></li>
                                    <li><a href="#tab_transporte" data-toggle="tab">Componente transporte (manual)</a></li>
                                    <li><a href="#tab_costos" data-toggle="tab">Costos y Márgenes</a></li>
                                    <li><a href="#tab_pagos" data-toggle="tab">Pagos</a></li>
                                </ul>
                                <div class="tab-content">
                                    <!-- TAB GENERAL -->
                                    <div class="tab-pane active" id="tab_general">
                                        <div class="form-body">
                                            <div class="alert alert-warning">
                                                <i class="fa fa-info-circle"></i>
                                                Este módulo no crea <strong>ofertas médicas</strong> ni administra el <strong>catálogo complementario</strong>. Usa esos insumos para construir una propuesta integral específica para el cliente.
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-4">Cliente <span class="required">*</span></label>
                                                        <div class="col-md-8">
                                                            <select class="form-control selectpicker" data-live-search="true" id="client_id" name="client_id" required>
                                                                <option value="">Seleccionar cliente...</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-4">Nombre de la propuesta</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" id="package_name" name="package_name" placeholder="Ej: Recuperación postoperatoria con alojamiento y traslados">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-4">Fecha Inicio <span class="required">*</span></label>
                                                        <div class="col-md-8">
                                                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-4">Fecha Fin <span class="required">*</span></label>
                                                        <div class="col-md-8">
                                                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-4">Estado</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" id="status" name="status">
                                                                <option value="quoted">Cotizado</option>
                                                                <option value="confirmed">Confirmado</option>
                                                                <option value="in_progress">En Progreso</option>
                                                                <option value="completed">Completado</option>
                                                                <option value="cancelled">Cancelado</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-4">Moneda</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" id="currency" name="currency">
                                                                <option value="USD" selected>USD</option>
                                                                <option value="COP">COP</option>
                                                                <option value="EUR">EUR</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="control-label col-md-2">Notas Internas</label>
                                                <div class="col-md-10">
                                                    <textarea class="form-control" rows="3" id="internal_notes" name="internal_notes"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB CATALOG SERVICES -->
                                    <div class="tab-pane" id="tab_catalog_services">
                                        <div class="form-body">
                                            <?php if ($packages_catalog_schema_ready): ?>
                                            <div class="alert alert-info">
                                                <i class="fa fa-info-circle"></i> 
                                                <strong>Insumos complementarios del catálogo:</strong> selecciona servicios complementarios ya configurados por MedTravel para incorporarlos a esta cotización integral.
                                                <br><small>Esto no reemplaza el módulo de Servicios Complementarios; aquí solo reutilizas esos insumos dentro del paquete del cliente.</small>
                                            </div>
                                            <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="fa fa-clock-o"></i>
                                                <strong>Insumos complementarios del catálogo:</strong> disponibles próximamente.
                                                <br><small>Por ahora registra los componentes manuales de vuelo, alojamiento y transporte.</small>
                                            </div>
                                            <?php endif; ?>

                                            <div class="form-group">
                                                <div class="col-md-12">
                                                    <label class="mt-checkbox mt-checkbox-outline">
                                                        <input type="checkbox" id="use_catalog_services" name="use_catalog_services" value="1" <?php echo $packages_catalog_schema_ready ? 'onchange="toggleCatalogMode()"' : 'disabled'; ?>>
                                                        <?php echo $packages_catalog_schema_ready ? 'Usar insumos complementarios desde catálogo' : 'Usar insumos complementarios desde catálogo (disponible próximamente)'; ?>
                                                        <span></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php if ($packages_catalog_schema_ready): ?>
                                            <div id="catalog_services_section" style="display:none;">
                                                <h4 class="form-section">Servicios complementarios disponibles para la cotización</h4>
                                                
                                                <!-- Flights -->
                                                <div class="panel panel-default">
                                                    <div class="panel-heading">
                                                        <h4 class="panel-title">
                                                            <a data-toggle="collapse" href="#collapse_flights">
                                                                ✈️ Vuelos
                                                            </a>
                                                        </h4>
                                                    </div>
                                                    <div id="collapse_flights" class="panel-collapse collapse">
                                                        <div class="panel-body">
                                                            <div id="catalog_flights_list" class="service-selection-container">
                                                                <!-- Loaded by JavaScript -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Accommodations -->
                                                <div class="panel panel-default">
                                                    <div class="panel-heading">
                                                        <h4 class="panel-title">
                                                            <a data-toggle="collapse" href="#collapse_accommodations">
                                                                🏨 Alojamientos
                                                            </a>
                                                        </h4>
                                                    </div>
                                                    <div id="collapse_accommodations" class="panel-collapse collapse">
                                                        <div class="panel-body">
                                                            <div id="catalog_accommodations_list" class="service-selection-container">
                                                                <!-- Loaded by JavaScript -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Transport -->
                                                <div class="panel panel-default">
                                                    <div class="panel-heading">
                                                        <h4 class="panel-title">
                                                            <a data-toggle="collapse" href="#collapse_transport">
                                                                🚗 Transporte
                                                            </a>
                                                        </h4>
                                                    </div>
                                                    <div id="collapse_transport" class="panel-collapse collapse">
                                                        <div class="panel-body">
                                                            <div id="catalog_transport_list" class="service-selection-container">
                                                                <!-- Loaded by JavaScript -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Meals -->
                                                <div class="panel panel-default">
                                                    <div class="panel-heading">
                                                        <h4 class="panel-title">
                                                            <a data-toggle="collapse" href="#collapse_meals">
                                                                🍽️ Alimentación
                                                            </a>
                                                        </h4>
                                                    </div>
                                                    <div id="collapse_meals" class="panel-collapse collapse">
                                                        <div class="panel-body">
                                                            <div id="catalog_meals_list" class="service-selection-container">
                                                                <!-- Loaded by JavaScript -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Support -->
                                                <div class="panel panel-default">
                                                    <div class="panel-heading">
                                                        <h4 class="panel-title">
                                                            <a data-toggle="collapse" href="#collapse_support">
                                                                🎧 Apoyo
                                                            </a>
                                                        </h4>
                                                    </div>
                                                    <div id="collapse_support" class="panel-collapse collapse">
                                                        <div class="panel-body">
                                                            <div id="catalog_support_list" class="service-selection-container">
                                                                <!-- Loaded by JavaScript -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Selected Services Summary -->
                                                <div class="well" style="margin-top: 20px;">
                                                    <h4>Resumen de insumos complementarios seleccionados</h4>
                                                    <div id="selected_services_summary">
                                                        <em class="text-muted">Todavía no has agregado insumos complementarios a esta cotización</em>
                                                    </div>
                                                    <div id="catalog_services_total" style="margin-top: 10px; font-size: 18px; font-weight: bold;">
                                                        Total complementario desde catálogo: <span id="catalog_total_amount">$0.00</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- TAB VUELO -->
                                    <div class="tab-pane" id="tab_vuelo">
                                        <div class="form-body">
                                            <div class="form-group">
                                                <div class="col-md-12">
                                                    <label class="mt-checkbox">
                                                        <input type="checkbox" id="flight_included" name="flight_included" value="1"> Incluir Vuelo
                                                        <span></span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div id="flight_details" style="display:none;">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Aerolínea</label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="flight_airline" name="flight_airline">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Costo Vuelo</label>
                                                            <div class="col-md-8">
                                                                <input type="number" step="0.01" class="form-control calculate-cost" id="flight_cost" name="flight_cost" value="0.00">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Aeropuerto Origen</label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="flight_departure_airport" name="flight_departure_airport" placeholder="MIA">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Aeropuerto Destino</label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="flight_arrival_airport" name="flight_arrival_airport" value="AXM">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Fecha Salida</label>
                                                            <div class="col-md-8">
                                                                <input type="date" class="form-control" id="flight_departure_date" name="flight_departure_date">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Fecha Retorno</label>
                                                            <div class="col-md-8">
                                                                <input type="date" class="form-control" id="flight_return_date" name="flight_return_date">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label class="control-label col-md-2">Notas Vuelo</label>
                                                    <div class="col-md-10">
                                                        <textarea class="form-control" rows="2" id="flight_notes" name="flight_notes"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB HOTEL -->
                                    <div class="tab-pane" id="tab_hotel">
                                        <div class="form-body">
                                            <div class="form-group">
                                                <div class="col-md-12">
                                                    <label class="mt-checkbox">
                                                        <input type="checkbox" id="hotel_included" name="hotel_included" value="1"> Incluir Hotel
                                                        <span></span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div id="hotel_details" style="display:none;">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Nombre Hotel</label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="hotel_name" name="hotel_name">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Ciudad</label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="hotel_city" name="hotel_city" value="Quindío">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-6">Noches</label>
                                                            <div class="col-md-6">
                                                                <input type="number" class="form-control" id="hotel_nights" name="hotel_nights" value="0">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-6">Costo/Noche</label>
                                                            <div class="col-md-6">
                                                                <input type="number" step="0.01" class="form-control" id="hotel_cost_per_night" name="hotel_cost_per_night" value="0.00">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-6">Total Hotel</label>
                                                            <div class="col-md-6">
                                                                <input type="number" step="0.01" class="form-control calculate-cost" id="hotel_total_cost" name="hotel_total_cost" value="0.00" readonly>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label class="control-label col-md-2">Notas Hotel</label>
                                                    <div class="col-md-10">
                                                        <textarea class="form-control" rows="2" id="hotel_notes" name="hotel_notes"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB TRANSPORTE -->
                                    <div class="tab-pane" id="tab_transporte">
                                        <div class="form-body">
                                            <div class="form-group">
                                                <div class="col-md-12">
                                                    <label class="mt-checkbox">
                                                        <input type="checkbox" id="transport_included" name="transport_included" value="1"> Incluir Transporte
                                                        <span></span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div id="transport_details" style="display:none;">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Tipo</label>
                                                            <div class="col-md-8">
                                                                <select class="form-control" id="transport_type" name="transport_type">
                                                                    <option value="private_driver">Conductor Privado</option>
                                                                    <option value="taxi">Taxi</option>
                                                                    <option value="rental_car">Auto de Alquiler</option>
                                                                    <option value="van">Van</option>
                                                                    <option value="shuttle">Shuttle</option>
                                                                    <option value="uber">Uber</option>
                                                                    <option value="other">Otro</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label col-md-4">Costo Transporte</label>
                                                            <div class="col-md-8">
                                                                <input type="number" step="0.01" class="form-control calculate-cost" id="transport_cost" name="transport_cost" value="0.00">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label class="control-label col-md-2">Rutas</label>
                                                    <div class="col-md-10">
                                                        <textarea class="form-control" rows="2" id="transport_routes" name="transport_routes" placeholder="Aeropuerto-Hotel, Hotel-Clínica, etc."></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB COSTOS Y MÁRGENES -->
                                    <div class="tab-pane" id="tab_costos">
                                        <div class="form-body">
                                            <div class="alert alert-info">
                                                <i class="fa fa-info-circle"></i> Los márgenes se calculan automáticamente. El cálculo visual es aproximado; los valores finales se calculan en el servidor.
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Costo Servicio Médico</label>
                                                        <div class="col-md-7">
                                                            <input type="number" step="0.01" class="form-control calculate-cost" id="medical_service_cost" name="medical_service_cost" value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Servicios Adicionales</label>
                                                        <div class="col-md-7">
                                                            <input type="number" step="0.01" class="form-control calculate-cost" id="additional_services_cost" name="additional_services_cost" value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Costo Comidas</label>
                                                        <div class="col-md-7">
                                                            <input type="number" step="0.01" class="form-control calculate-cost" id="meals_cost" name="meals_cost" value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5"><strong>PRECIO FINAL AL CLIENTE</strong> <span class="required">*</span></label>
                                                        <div class="col-md-7">
                                                            <div class="input-group">
                                                                <input type="number" step="0.01" class="form-control font-weight-bold" id="total_package_cost" name="total_package_cost" value="0.00" required>
                                                                <span class="input-group-btn">
                                                                    <button class="btn btn-info" type="button" id="btn-auto-price" title="Auto-calcular precio basado en costos + margen">
                                                                        <i class="fa fa-calculator"></i> Auto
                                                                    </button>
                                                                </span>
                                                            </div>
                                                            <span class="help-block">
                                                                <i class="fa fa-lightbulb-o"></i> Se calcula automáticamente. Puedes ajustarlo manualmente.
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>
                                            <h4><i class="fa fa-dollar"></i> Configuración de Ganancia MedTravel</h4>
                                            <p class="text-muted"><small>Define cómo calcular la ganancia incluida en el PRECIO FINAL</small></p>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Tipo de Tarifa</label>
                                                        <div class="col-md-7">
                                                            <select class="form-control" id="medtravel_fee_type" name="medtravel_fee_type">
                                                                <option value="percent" selected>Porcentaje</option>
                                                                <option value="fixed">Monto Fijo</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Valor (<span id="fee_unit">%</span>)</label>
                                                        <div class="col-md-7">
                                                            <input type="number" step="0.01" class="form-control" id="medtravel_fee_value" name="medtravel_fee_value" value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Comisión Proveedor</label>
                                                        <div class="col-md-7">
                                                            <input type="number" step="0.01" class="form-control" id="provider_commission_value" name="provider_commission_value" value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>
                                            <h4><i class="fa fa-calculator"></i> Resumen de Rentabilidad (Calculado Automáticamente)</h4>
                                            <p class="text-muted"><small>Comparación entre costos operativos y el precio final al cliente</small></p>
                                            
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <table class="table table-bordered">
                                                        <tr>
                                                            <th width="40%">Total Costos Operativos (lo que pagamos):</th>
                                                            <td class="text-right"><strong id="display_total_costs">$0.00</strong></td>
                                                        </tr>
                                                        <tr class="info">
                                                            <th>Precio Final al Cliente (lo que cobramos):</th>
                                                            <td class="text-right"><strong id="display_client_price">$0.00</strong></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Tarifa MedTravel:</th>
                                                            <td class="text-right"><strong id="display_medtravel_fee">$0.00</strong></td>
                                                        </tr>
                                                        <tr class="info">
                                                            <th>Margen Bruto (Precio - Costos):</th>
                                                            <td class="text-right"><strong id="display_gross_margin">$0.00</strong></td>
                                                        </tr>
                                                        <tr class="success">
                                                            <th>Margen Neto (después de comisión):</th>
                                                            <td class="text-right"><strong id="display_net_margin">$0.00</strong> <span id="net_margin_percent"></span></td>
                                                        </tr>
                                                    </table>
                                                    <div id="margin_warning" class="alert alert-warning" style="display:none;">
                                                        <i class="fa fa-warning"></i> <strong>Advertencia:</strong> El margen neto es negativo. Revisa los costos y tarifas.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB PAGOS -->
                                    <div class="tab-pane" id="tab_pagos">
                                        <div class="form-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Estado de Pago</label>
                                                        <div class="col-md-7">
                                                            <select class="form-control" id="payment_status" name="payment_status">
                                                                <option value="pending">Pendiente</option>
                                                                <option value="deposit_paid">Depósito Pagado</option>
                                                                <option value="partial_paid">Pago Parcial</option>
                                                                <option value="fully_paid">Pagado Completo</option>
                                                                <option value="refunded">Reembolsado</option>
                                                                <option value="cancelled">Cancelado</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Método de Pago</label>
                                                        <div class="col-md-7">
                                                            <input type="text" class="form-control" id="payment_method" name="payment_method" placeholder="Tarjeta, Transferencia, etc.">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Depósito</label>
                                                        <div class="col-md-7">
                                                            <input type="number" step="0.01" class="form-control" id="deposit_amount" name="deposit_amount" value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="control-label col-md-5">Monto Pagado</label>
                                                        <div class="col-md-7">
                                                            <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="control-label col-md-2">Referencia de Pago</label>
                                                <div class="col-md-10">
                                                    <input type="text" class="form-control" id="payment_reference" name="payment_reference">
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="control-label col-md-2">Notas de Pago</label>
                                                <div class="col-md-10">
                                                    <textarea class="form-control" rows="3" id="payment_notes" name="payment_notes"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="btnGuardarPaquete">
                                <i class="fa fa-save"></i> Guardar cotización
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- END MODAL -->

        <!-- MODAL ENVIAR COTIZACIÓN -->
        <div class="modal fade" id="modalEnviarCotizacion" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-envelope"></i> Enviar cotización integral al cliente</h4>
                    </div>
                    <div class="modal-body">
                        <form id="formEnviarCotizacion" class="form-horizontal">
                            <input type="hidden" id="quote_package_id" name="package_id">
                            
                            <div class="alert alert-info">
                                                <i class="fa fa-info-circle"></i> Se enviará al cliente una cotización integral de MedTravel con el desglose de la propuesta armada en este paquete.
                            </div>
                            
                            <div class="form-group">
                                <label class="col-md-3 control-label">Cliente:</label>
                                <div class="col-md-9">
                                    <p class="form-control-static" id="quote_client_name"></p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="col-md-3 control-label">Email:</label>
                                <div class="col-md-9">
                                    <input type="email" class="form-control" id="quote_client_email" name="client_email" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="col-md-3 control-label">Propuesta:</label>
                                <div class="col-md-9">
                                    <p class="form-control-static" id="quote_package_name"></p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="col-md-3 control-label">Precio Total:</label>
                                <div class="col-md-9">
                                    <p class="form-control-static font-weight-bold text-success" id="quote_total_price"></p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="col-md-3 control-label">Asunto:</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="quote_subject" name="subject" value="Cotización integral MedTravel" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="col-md-3 control-label">Mensaje Adicional:</label>
                                <div class="col-md-9">
                                    <textarea class="form-control" id="quote_message" name="message" rows="4" placeholder="Mensaje personalizado para el cliente (opcional)"></textarea>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="col-md-offset-3 col-md-9">
                                    <label class="mt-checkbox">
                                        <input type="checkbox" id="quote_include_details" name="include_details" checked> 
                                        Incluir desglose detallado de costos
                                        <span></span>
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn default" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn blue" id="btnConfirmarEnvio">
                            <i class="fa fa-send"></i> Enviar cotización
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END MODAL ENVIAR COTIZACIÓN -->

        <!-- THEME (incluye jQuery y núcleo) -->
        <?php echo $theme_global_script;?>
        <?php echo $theme_layout_script;?>
        <!-- PAGE LEVEL PLUGINS (después de jQuery) -->
        <script src="../../assets/global/scripts/datatable.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-select/js/bootstrap-select.min.js" type="text/javascript"></script>
        <!-- PAGE LEVEL SCRIPTS -->
        
        <!-- Toastr -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

        <!-- PAGE LEVEL SCRIPTS -->
        <script>
            window.PACKAGES_CTX = {
                catalogSchemaReady: <?php echo $packages_catalog_schema_ready ? 'true' : 'false'; ?>
            };
        </script>
        <script src="js/paquetes.js" type="text/javascript"></script>

        <script>
            jQuery(document).ready(function() {
                // Configuración toastr
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "3000"
                };
            });
        </script>
    </body>
</html>
