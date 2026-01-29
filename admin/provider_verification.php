<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>MedTravel - Verificación de Proveedores</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <?php echo $global_first_style;?>
        <!-- DataTables -->
        <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
        <?php echo $theme_global_style;?>
        <?php echo $theme_layout_style;?>
        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
        <style>
            .checklist-item {
                padding: 10px;
                margin-bottom: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #f9f9f9;
            }
            .checklist-item.checked {
                background: #e8f5e9;
                border-color: #4caf50;
            }
            .verification-badge {
                font-size: 14px;
                padding: 5px 10px;
            }
            .trust-score {
                font-size: 24px;
                font-weight: bold;
                color: #0f766e;
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
                        <h1>Verificación de Proveedores</h1>
                        <ol class="breadcrumb">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="#">Administrativo</a></li>
                            <li class="active">Verificación</li>
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
                                        <i class="fa fa-shield font-dark"></i>
                                        <span class="caption-subject font-dark bold uppercase">Estado de Verificación</span>
                                        <span class="caption-helper">Control de calidad y confianza</span>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <table class="table table-striped table-bordered table-hover" id="tabla_verificacion">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Proveedor</th>
                                                <th>Email</th>
                                                <th>Teléfono</th>
                                                <th>Estado</th>
                                                <th>Score</th>
                                                <th>Progreso</th>
                                                <th>Verificado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Se llena vía AJAX -->
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
        </div>
        <!-- END CONTAINER -->
        
        <!-- BEGIN QUICK SIDEBAR -->
        <?php echo $sider_bar;?>
        
        <!-- MODAL: Checklist de Verificación -->
        <div class="modal fade" id="modalVerificacion" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <h4 class="modal-title">
                            <i class="fa fa-shield"></i> 
                            Verificación: <span id="provider_name"></span>
                        </h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="provider_id">
                        
                        <!-- Resumen de Verificación -->
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h5>Estado</h5>
                                <span id="verification_status_badge" class="label label-default verification-badge">Pendiente</span>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>Trust Score</h5>
                                <div class="trust-score" id="trust_score_display">0</div>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>Progreso</h5>
                                <div class="progress" style="margin-top: 10px;">
                                    <div id="progress_bar" class="progress-bar progress-bar-success" role="progressbar" style="width: 0%">
                                        <span id="progress_text">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Controles de Estado -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cambiar Estado</label>
                                    <select class="form-control" id="verification_status">
                                        <option value="pending">Pendiente</option>
                                        <option value="in_review">En Revisión</option>
                                        <option value="verified">Verificado</option>
                                        <option value="rejected">Rechazado</option>
                                        <option value="suspended">Suspendido</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nivel de Verificación</label>
                                    <select class="form-control" id="verification_level">
                                        <option value="basic">Básico</option>
                                        <option value="standard">Estándar</option>
                                        <option value="premium">Premium</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Notas del Administrador</label>
                                    <textarea class="form-control" id="admin_notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Checklist de Items -->
                        <h5><i class="fa fa-list"></i> Checklist de Verificación</h5>
                        <div id="checklist_container">
                            <!-- Se llena dinámicamente con items -->
                        </div>
                        
                        <button type="button" class="btn btn-success btn-block" onclick="initializeChecklist()">
                            <i class="fa fa-plus"></i> Inicializar Checklist Estándar
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="saveVerificationStatus()">
                            <i class="fa fa-save"></i> Guardar Estado
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END MODAL -->
        
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
        <!-- END PAGE LEVEL PLUGINS -->
        <!-- BEGIN THEME GLOBAL SCRIPTS -->
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        <script src="js/provider_verification.js" type="text/javascript"></script>
    </body>
</html>
