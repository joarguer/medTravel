<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);

// Intentar obtener datos del header
$busca_offer_detail_header = mysqli_query($conexion,"SELECT * FROM offer_detail_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
if($busca_offer_detail_header && mysqli_num_rows($busca_offer_detail_header) > 0){
    $rst_offer_detail_header = mysqli_fetch_array($busca_offer_detail_header);
    $id = $rst_offer_detail_header['id'];
} else {
    $id = 1; // ID por defecto si no existe
}
$n = 1;
?>
<!DOCTYPE html>
<html lang="es">
    <!-- BEGIN HEAD -->
    <head>
        <meta charset="utf-8" />
        <title>medTravel - Offer Detail Edit</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <meta content="" name="description" />
        <meta content="" name="author" />
        <?php echo $global_first_style;?>
        <!-- BEGIN PAGE LEVEL PLUGINS -->
        <link href="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.css" rel="stylesheet" type="text/css" />
        <!-- END PAGE LEVEL PLUGINS -->
        <?php echo $theme_global_style;?>
        <!-- BEGIN PAGE LEVEL STYLES -->
        <link href="../../assets/pages/css/about.css" rel="stylesheet" type="text/css" />
        <!-- END PAGE LEVEL STYLES -->
        <?php echo $theme_layout_style;?>
        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
    </head>
    <!-- END HEAD -->

    <body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
        <!-- BEGIN CONTAINER -->
        <div class="wrapper">
            <!-- BEGIN HEADER -->
            <header class="page-header">
                <nav class="navbar mega-menu" role="navigation">
                    <div class="container-fluid">
                        <?php echo $top_header;?>
                        <!-- BEGIN HEADER MENU -->
                        <?php echo $top_header_2;?>
                        <!-- END HEADER MENU -->
                    </div>
                    <!--/container-->
                </nav>
            </header>
            <!-- END HEADER -->
            
            <div class="container-fluid">
                <div class="page-content">
                    <!-- BEGIN BREADCRUMBS -->
                    <div class="breadcrumbs">
                        <h1>Offer Detail Page Edit</h1>
                        <ol class="breadcrumb">
                            <li>
                                <a href="#">Offer Detail</a>
                            </li>
                            <li>
                                <a href="#">Pages</a>
                            </li>
                            <li class="active">Offer Detail Edit</li>
                        </ol>
                        <!-- Sidebar Toggle Button -->
                        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".page-sidebar">
                            <span class="sr-only">Toggle navigation</span>
                            <span class="toggle-icon">
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                            </span>
                        </button>
                        <!-- Sidebar Toggle Button -->
                    </div>
                    <!-- END BREADCRUMBS -->
                    <!-- BEGIN SIDEBAR CONTENT LAYOUT -->
                    <div class="page-content-container">
                        <div class="page-content-row">
                            <!-- BEGIN PAGE SIDEBAR -->
                            <div class="page-sidebar">
                                <nav class="navbar" role="navigation">
                                    <!-- Brand and toggle get grouped for better mobile display -->
                                    <!-- Collect the nav links, forms, and other content for toggling -->
                                    <ul class="nav navbar-nav">
                                        <li class="btn-header active" id="btn-select-<?php echo $n;?>">
                                            <a onclick="open_header(<?php echo $id;?>)">
                                                <i class="icon-picture"></i> Header
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                            <!-- END PAGE SIDEBAR -->
                            <!-- BEGIN PAGE CONTENT -->
                            <div class="page-content-col">
                                <div class="portlet light bordered">
                                    <div class="portlet-body" id="content_detalle">
                                        <!-- Aquí se carga el contenido dinámicamente -->
                                    </div>
                                </div>
                            </div>
                            <!-- END PAGE CONTENT -->
                        </div>
                    </div>
                    <!-- END SIDEBAR CONTENT LAYOUT -->
                </div>
            </div>
        </div>
        <!-- END CONTAINER -->
        <!-- BEGIN QUICK SIDEBAR -->
        <?php echo $sider_bar;?>
        <!-- BEGIN CORE PLUGINS -->
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
        <!-- END CORE PLUGINS -->
        <!-- BEGIN PAGE LEVEL PLUGINS -->
        <script src="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL PLUGINS -->
        <!-- BEGIN THEME GLOBAL SCRIPTS -->
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        <script src="js/offer_detail_edit.js" type="text/javascript"></script>
    </body>
</html>
