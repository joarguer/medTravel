<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>medTravel - Booking Requests Management</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <?php echo $global_first_style;?>
        <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />
        <?php echo $theme_global_style;?>
        <?php echo $theme_layout_style;?>
        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
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
                        <h1>Booking Requests Management</h1>
                        <ol class="breadcrumb">
                            <li><a href="index.php">Home</a></li>
                            <li class="active">Booking Requests</li>
                        </ol>
                    </div>

                    <div class="page-content-container">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="portlet light bordered">
                                    <div class="portlet-title">
                                        <div class="caption">
                                            <i class="icon-calendar font-blue"></i>
                                            <span class="caption-subject font-blue bold uppercase">All Booking Requests</span>
                                        </div>
                                        <div class="actions">
                                            <button class="btn btn-circle btn-icon-only btn-default" onclick="location.reload()">
                                                <i class="icon-refresh"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <table class="table table-striped table-bordered table-hover" id="booking_requests_table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Date</th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Destination</th>
                                                    <th>Services</th>
                                                    <th>Status</th>
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
                </div>
                <?php echo $footer;?>
            </div>
        </div>

        <?php echo $sider_bar;?>
        
        <!-- Modal para ver detalles -->
        <div class="modal fade" id="booking_detail_modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">Booking Request Details</h4>
                    </div>
                    <div class="modal-body" id="booking_detail_content">
                        <!-- Content loaded by JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <?php echo $theme_global_js;?>
        <?php echo $theme_layout_js;?>
        <script src="../../assets/global/scripts/datatable.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
        <script src="js/booking_requests.js" type="text/javascript"></script>
    </body>
</html>
