<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>MedTravel - Gestión de Servicios Complementarios</title>
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
                    <h1>Catálogo de Servicios Complementarios
                        <small>Gestiona servicios de MedTravel: vuelos, hoteles, transporte, comidas, soporte</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="#">Administrativo</a></li>
                        <li class="active">Servicios MedTravel</li>
                    </ol>
                </div>
                <!-- END BREADCRUMBS -->

                <!-- FILTERS -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="fa fa-filter"></i>
                                    <span class="caption-subject">Filters</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select id="filter_type" class="form-control">
                                            <option value="">All Service Types</option>
                                            <option value="flight">✈️ Flights</option>
                                            <option value="accommodation">🏨 Accommodations</option>
                                            <option value="transport">🚗 Transport</option>
                                            <option value="meals">🍽️ Meals</option>
                                            <option value="support">🎧 Support</option>
                                            <option value="other">📦 Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filter_status" class="form-control">
                                            <option value="">All Status</option>
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filter_availability" class="form-control">
                                            <option value="">All Availability</option>
                                            <option value="available">Available</option>
                                            <option value="limited">Limited</option>
                                            <option value="unavailable">Unavailable</option>
                                            <option value="seasonal">Seasonal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" class="btn btn-primary btn-block" onclick="applyFilters()">
                                            <i class="fa fa-search"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MAIN TABLE -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-layers font-blue"></i>
                                    <span class="caption-subject font-blue bold uppercase">Catálogo de Servicios Complementarios</span>
                                    <span class="caption-helper">Vuelos, Hoteles, Transporte, Comidas, Soporte</span>
                                </div>
                                <div class="actions">
                                    <button type="button" class="btn btn-success" id="btnNewService">
                                        <i class="fa fa-plus"></i> Nuevo Servicio
                                    </button>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <table class="table table-striped table-bordered table-hover" id="services_table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tipo</th>
                                            <th>Nombre del Servicio</th>
                                            <th>Proveedor</th>
                                            <th>Costo</th>
                                            <th>Precio Venta</th>
                                            <th>Comisión</th>
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
                    <h4 class="modal-title" id="serviceModalTitle">Nuevo Servicio</h4>
                </div>
                <form id="serviceForm" class="form-horizontal">
                    <input type="hidden" id="service_id" name="id">
                    <div class="modal-body">
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
                                            <i class="fa fa-info-circle"></i> Seleccione un proveedor existente o cree uno nuevo. Los datos se cargarán automáticamente.
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Proveedor</label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <select class="form-control" id="provider_id" name="provider_id">
                                                        <option value="">Seleccionar proveedor...</option>
                                                    </select>
                                                    <span class="input-group-btn">
                                                        <button class="btn btn-success" type="button" id="btnNewProvider">
                                                            <i class="fa fa-plus"></i> Nuevo
                                                        </button>
                                                    </span>
                                                </div>
                                                <small class="help-block">Aerolíneas, hoteles, empresas de transporte, etc.</small>
                                            </div>
                                        </div>

                                        <hr>
                                        <h4 class="form-section">Datos del Proveedor <small>(Solo lectura - editar en catálogo de proveedores)</small></h4>

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
                                            <label class="control-label col-md-3">Notas del Proveedor</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" id="provider_notes" name="provider_notes" rows="3" placeholder="Notas específicas para este servicio con el proveedor"></textarea>
                                                <small class="help-block">Notas adicionales relacionadas con este servicio en particular</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB: PRICING -->
                                <div class="tab-pane" id="tab_pricing">
                                    <div class="form-body">
                                        <div class="alert alert-info">
                                            <i class="fa fa-info-circle"></i> Services are provided in Colombia (COP) and sold in Florida (USD). Commission is calculated automatically.
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Exchange Rate <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <span class="input-group-addon">1 USD =</span>
                                                    <input type="number" step="0.01" class="form-control" id="exchange_rate" name="exchange_rate" value="4150.00">
                                                    <span class="input-group-addon">COP</span>
                                                </div>
                                                <small class="help-block">Current USD to COP exchange rate</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Cost in COP <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <span class="input-group-addon">$</span>
                                                    <input type="number" step="0.01" class="form-control" id="cost_price_cop" name="cost_price_cop" value="0.00">
                                                    <span class="input-group-addon">COP</span>
                                                </div>
                                                <small class="help-block">What MedTravel pays to provider in Colombian Pesos</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Cost in USD (Auto)</label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <span class="input-group-addon">$</span>
                                                    <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" value="0.00" readonly>
                                                    <span class="input-group-addon">USD</span>
                                                </div>
                                                <small class="help-block">Calculated automatically from COP cost</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Sale Price USD <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <span class="input-group-addon">$</span>
                                                    <input type="number" step="0.01" class="form-control" id="sale_price" name="sale_price" value="0.00" required>
                                                    <span class="input-group-addon">USD</span>
                                                </div>
                                                <small class="help-block">What client pays to MedTravel in US Dollars</small>
                                            </div>
                                        </div>

                                        <input type="hidden" id="currency" name="currency" value="USD">

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Commission Preview</label>
                                            <div class="col-md-9">
                                                <div id="commission_preview" class="well" style="background: #f8f9fa; padding: 15px;">
                                                    <strong>Commission:</strong> <span id="preview_amount">$0.00</span> 
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
                                            <label class="control-label col-md-3">Availability Status</label>
                                            <div class="col-md-9">
                                                <select class="form-control" id="availability_status" name="availability_status">
                                                    <option value="available">Available</option>
                                                    <option value="limited">Limited</option>
                                                    <option value="unavailable">Unavailable</option>
                                                    <option value="seasonal">Seasonal</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Stock Quantity</label>
                                            <div class="col-md-9">
                                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" placeholder="Leave empty for unlimited">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Booking Lead Time</label>
                                            <div class="col-md-9">
                                                <input type="number" class="form-control" id="booking_lead_time" name="booking_lead_time" value="0">
                                                <small class="help-block">Days of advance booking required</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Service Details (JSON)</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" id="service_details" name="service_details" rows="6" placeholder='{"key": "value"}'></textarea>
                                                <small class="help-block">Advanced: JSON format for specific service attributes</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Tags</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="tags" name="tags" placeholder="tag1, tag2, tag3">
                                                <small class="help-block">Comma-separated tags</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Internal Notes</label>
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
                                            <label class="control-label col-md-3">Icon Class</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="icon_class" name="icon_class" placeholder="fa fa-plane">
                                                <small class="help-block">Font Awesome icon class</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Image</label>
                                            <div class="col-md-9">
                                                <div id="service_image_preview" class="service-image-preview text-muted">No image selected</div>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-default" id="btnUploadImage"><i class="fa fa-image"></i> Upload / Change</button>
                                                    <button type="button" class="btn btn-default" id="btnClearImage"><i class="fa fa-trash"></i> Remove</button>
                                                </div>
                                                <input type="hidden" id="image_url" name="image_url">
                                                <small class="help-block">Allowed formats: JPG, PNG, GIF, WEBP.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Display Order</label>
                                            <div class="col-md-9">
                                                <input type="number" class="form-control" id="display_order" name="display_order" value="0">
                                                <small class="help-block">Lower numbers appear first</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Status</label>
                                            <div class="col-md-9">
                                                <label class="mt-checkbox mt-checkbox-outline">
                                                    <input type="checkbox" id="is_active" name="is_active" value="1" checked> Active
                                                    <span></span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Featured</label>
                                            <div class="col-md-9">
                                                <label class="mt-checkbox mt-checkbox-outline">
                                                    <input type="checkbox" id="featured" name="featured" value="1"> Mark as featured service
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
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveService" disabled>
                            <i class="fa fa-save"></i> Save Service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <!-- BEGIN CORE PLUGINS -->
    <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
    <!-- END CORE PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap-select/js/bootstrap-select.min.js" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN THEME GLOBAL SCRIPTS -->
    <?php echo $theme_layout_script;?>
    <!-- END THEME GLOBAL SCRIPTS -->
    <script src="js/medtravel_services.js" type="text/javascript"></script>
</body>
</html>
