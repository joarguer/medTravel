<?php
include('include/include.php');

if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$service_provider_id = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;

if ($provider_id <= 0 && $service_provider_id <= 0) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Mis Solicitudes</title>
    <?php echo $global_first_style;?>
    <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
<div class="wrapper">
    <header class="page-header">
        <nav class="navbar mega-menu" role="navigation">
            <div class="container-fluid">
                <?php echo $top_header;?>
                <?php echo $top_header_2;?>
            </div>
        </nav>
    </header>

    <div class="container-fluid">
        <div class="page-content">
            <div class="breadcrumbs">
                <h1>Mis Solicitudes</h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Home</a></li>
                    <li class="active">Mis Solicitudes</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-list font-blue"></i>
                                    <span class="caption-subject font-blue bold uppercase">Items de Solicitudes</span>
                                </div>
                                <div class="actions">
                                    <button class="btn btn-circle btn-icon-only btn-default" id="btn-reload-my-bookings">
                                        <i class="icon-refresh"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <table class="table table-striped table-bordered table-hover" id="my_booking_requests_table">
                                    <thead>
                                    <tr>
                                        <th>Booking</th>
                                        <th>Fecha</th>
                                        <th>Destino / Timeline</th>
                                        <th>Tipo</th>
                                        <th>Servicio</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
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

<div class="modal fade" id="my_booking_detail_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Detalle de solicitud</h4>
            </div>
            <div class="modal-body" id="my_booking_detail_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="provider_reject_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Rechazar solicitud</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="provider_reject_item_id" value="">
                <div class="form-group">
                    <label for="provider_reject_reason">Motivo (obligatorio)</label>
                    <input type="text" class="form-control" id="provider_reject_reason" maxlength="255" placeholder="Motivo breve del rechazo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-provider-reject-save">Confirmar rechazo</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="provider_propose_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Proponer cambio</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="provider_propose_item_id" value="">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="provider_proposed_date_from">Fecha desde (opcional)</label>
                            <input type="date" class="form-control" id="provider_proposed_date_from">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="provider_proposed_date_to">Fecha hasta (opcional)</label>
                            <input type="date" class="form-control" id="provider_proposed_date_to">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="provider_proposed_price">Precio propuesto (opcional)</label>
                            <input type="number" class="form-control" id="provider_proposed_price" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="provider_proposed_currency">Moneda</label>
                            <select class="form-control" id="provider_proposed_currency">
                                <option value="USD">USD</option>
                                <option value="COP">COP</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="provider_proposed_notes">Notas del proveedor (obligatorio)</label>
                    <textarea class="form-control" id="provider_proposed_notes" rows="3" placeholder="Explica disponibilidad, condiciones o ajuste sugerido"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-provider-propose-save">Enviar propuesta</button>
            </div>
        </div>
    </div>
</div>

<?php echo $theme_layout_script;?>
<script src="../../assets/global/scripts/datatable.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
<script src="js/my_booking_requests.js" type="text/javascript"></script>
</body>
</html>
