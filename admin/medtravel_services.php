<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>MedTravel - Services Catalog Management</title>
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
    <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
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
                    <h1>MedTravel Services Catalog
                        <small>Manage company services: flights, hotels, transport, meals, support</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#">Administrative</a></li>
                        <li class="active">MedTravel Services</li>
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
                                    <span class="caption-subject font-blue bold uppercase">Services Catalog</span>
                                </div>
                                <div class="actions">
                                    <button type="button" class="btn btn-success" id="btnNewService">
                                        <i class="fa fa-plus"></i> New Service
                                    </button>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <table class="table table-striped table-bordered table-hover" id="services_table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Type</th>
                                            <th>Service Name</th>
                                            <th>Provider</th>
                                            <th>Cost</th>
                                            <th>Sale Price</th>
                                            <th>Commission</th>
                                            <th>Status</th>
                                            <th>Availability</th>
                                            <th>Actions</th>
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
                    <h4 class="modal-title" id="serviceModalTitle">New Service</h4>
                </div>
                <form id="serviceForm" class="form-horizontal">
                    <input type="hidden" id="service_id" name="id">
                    <div class="modal-body">
                        <div class="tabbable-line">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_basic" data-toggle="tab">Basic Info</a></li>
                                <li><a href="#tab_provider" data-toggle="tab">Provider</a></li>
                                <li><a href="#tab_pricing" data-toggle="tab">Pricing</a></li>
                                <li><a href="#tab_details" data-toggle="tab">Details</a></li>
                                <li><a href="#tab_display" data-toggle="tab">Display</a></li>
                            </ul>
                            <div class="tab-content">
                                <!-- TAB: BASIC INFO -->
                                <div class="tab-pane active" id="tab_basic">
                                    <div class="form-body">
                                        <div class="form-group">
                                            <label class="control-label col-md-3">Service Type <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <select class="form-control" id="service_type" name="service_type" required>
                                                    <option value="">Select type...</option>
                                                    <option value="flight">✈️ Flight</option>
                                                    <option value="accommodation">🏨 Accommodation</option>
                                                    <option value="transport">🚗 Transport</option>
                                                    <option value="meals">🍽️ Meals</option>
                                                    <option value="support">🎧 Support</option>
                                                    <option value="other">📦 Other</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Service Name <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="service_name" name="service_name" required>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Service Code</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="service_code" name="service_code" placeholder="e.g., FLT-MIA-AXM">
                                                <small class="help-block">Internal reference code</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Short Description</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="short_description" name="short_description" maxlength="255">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Full Description</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB: PROVIDER -->
                                <div class="tab-pane" id="tab_provider">
                                    <div class="form-body">
                                        <div class="form-group">
                                            <label class="control-label col-md-3">Provider Name</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="provider_name" name="provider_name">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Contact Person</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="provider_contact" name="provider_contact">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Email</label>
                                            <div class="col-md-9">
                                                <input type="email" class="form-control" id="provider_email" name="provider_email">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Phone</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="provider_phone" name="provider_phone">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Provider Notes</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" id="provider_notes" name="provider_notes" rows="3"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB: PRICING -->
                                <div class="tab-pane" id="tab_pricing">
                                    <div class="form-body">
                                        <div class="alert alert-info">
                                            <i class="fa fa-info-circle"></i> Commission is calculated automatically: <strong>Sale Price - Cost Price</strong>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Currency</label>
                                            <div class="col-md-9">
                                                <select class="form-control" id="currency" name="currency">
                                                    <option value="USD">USD - US Dollar</option>
                                                    <option value="COP">COP - Colombian Peso</option>
                                                    <option value="EUR">EUR - Euro</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Cost Price</label>
                                            <div class="col-md-9">
                                                <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" value="0.00">
                                                <small class="help-block">What MedTravel pays to provider</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="control-label col-md-3">Sale Price <span class="required">*</span></label>
                                            <div class="col-md-9">
                                                <input type="number" step="0.01" class="form-control" id="sale_price" name="sale_price" value="0.00" required>
                                                <small class="help-block">What client pays to MedTravel</small>
                                            </div>
                                        </div>

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
                                            <label class="control-label col-md-3">Image URL</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="image_url" name="image_url">
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Save Service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <?php echo $global_plugins_script;?>
    <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap-select/js/bootstrap-select.min.js" type="text/javascript"></script>
    <?php echo $theme_global_script;?>
    <?php echo $theme_layout_script;?>
    <script src="js/medtravel_services.js" type="text/javascript"></script>
</body>
</html>
