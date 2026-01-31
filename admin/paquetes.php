<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>MedTravel - Travel Packages Management</title>
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
        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
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
                        <h1>Travel Packages Management
                            <small>Complete client packages with services and itineraries</small>
                        </h1>
                        <ol class="breadcrumb">
                            <li>
                                <a href="index.php">Home</a>
                            </li>
                            <li>
                                <a href="#">Administrative</a>
                            </li>
                            <li class="active">Travel Packages</li>
                        </ol>
                    </div>
                    <!-- END BREADCRUMBS -->
                    
                    <!-- BEGIN CONTENT -->
                    <div class="row">
                        <div class="col-md-12">
                            <!-- BEGIN PORTLET -->
                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-briefcase font-dark"></i>
                                        <span class="caption-subject font-dark bold uppercase">Travel Packages - All-Inclusive Client Solutions</span>
                                    </div>
                                    <div class="actions">
                                        <button type="button" class="btn btn-primary" id="btnNuevoPaquete">
                                            <i class="fa fa-plus"></i> New Package
                                        </button>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <table class="table table-striped table-bordered table-hover" id="tabla_paquetes">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Paquete</th>
                                                <th>Cliente</th>
                                                <th>Fechas</th>
                                                <th>Días</th>
                                                <th>Costo Total</th>
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
                            <!-- END PORTLET -->
                        </div>
                    </div>
                    <!-- END CONTENT -->
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
                        <h4 class="modal-title" id="modalPaqueteTitle">Nuevo Paquete</h4>
                    </div>
                    <form id="formPaquete" class="form-horizontal">
                        <input type="hidden" id="paquete_id" name="id">
                        <div class="modal-body">
                            <div class="tabbable-line">
                                <ul class="nav nav-tabs">
                                    <li class="active"><a href="#tab_general" data-toggle="tab">General</a></li>
                                    <li><a href="#tab_vuelo" data-toggle="tab">Vuelo</a></li>
                                    <li><a href="#tab_hotel" data-toggle="tab">Hotel</a></li>
                                    <li><a href="#tab_transporte" data-toggle="tab">Transporte</a></li>
                                    <li><a href="#tab_costos" data-toggle="tab">Costos y Márgenes</a></li>
                                    <li><a href="#tab_pagos" data-toggle="tab">Pagos</a></li>
                                </ul>
                                <div class="tab-content">
                                    <!-- TAB GENERAL -->
                                    <div class="tab-pane active" id="tab_general">
                                        <div class="form-body">
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
                                                        <label class="control-label col-md-4">Nombre del Paquete</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" id="package_name" name="package_name" placeholder="Ej: Cirugía Plástica + Recuperación">
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
                                <i class="fa fa-save"></i> Guardar Paquete
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
                        <h4 class="modal-title"><i class="fa fa-envelope"></i> Enviar Cotización al Cliente</h4>
                    </div>
                    <div class="modal-body">
                        <form id="formEnviarCotizacion" class="form-horizontal">
                            <input type="hidden" id="quote_package_id" name="package_id">
                            
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Se enviará una cotización detallada al email del cliente con todos los costos del paquete.
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
                                <label class="col-md-3 control-label">Paquete:</label>
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
                                    <input type="text" class="form-control" id="quote_subject" name="subject" value="Cotización de Paquete Turístico Médico - MedTravel" required>
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
                            <i class="fa fa-send"></i> Enviar Cotización
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END MODAL ENVIAR COTIZACIÓN -->

        <!-- BEGIN CORE PLUGINS -->
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
        <!-- END CORE PLUGINS -->
        <!-- BEGIN PAGE LEVEL PLUGINS -->
        <script src="../../assets/global/scripts/datatable.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-select/js/bootstrap-select.min.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL PLUGINS -->
        <!-- BEGIN THEME GLOBAL SCRIPTS -->
        <?php echo $theme_global_script;?>
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        
        <!-- Toastr -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

        <!-- PAGE LEVEL SCRIPTS -->
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
