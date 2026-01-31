<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);
$busca_carrucel = mysqli_query($conexion,"SELECT * FROM carrucel WHERE activo = '0' ORDER BY id ASC");
$busca_carrucel_2 = mysqli_query($conexion,"SELECT * FROM carrucel WHERE activo = '0' ORDER BY id ASC");
$busca_como_funciona = mysqli_query($conexion,"SELECT * FROM home_como_funciona WHERE activo = '0' ORDER BY step_number ASC");
$busca_services = mysqli_query($conexion,"SELECT * FROM home_services WHERE activo = '0' ORDER BY orden ASC");
$busca_booking = mysqli_query($conexion,"SELECT * FROM home_booking WHERE activo = '1' ORDER BY id DESC");
$initial_site_tab = isset($_GET['tab']) ? $_GET['tab'] : '';
$initial_site_tab = ($initial_site_tab === 'booking') ? 'booking' : '';
mysqli_data_seek($busca_como_funciona, 0);
mysqli_data_seek($busca_services, 0);
?>
<!DOCTYPE html>
<html lang="es">
    <!-- BEGIN HEAD -->
    <head>
        <meta charset="utf-8" />
        <title>medTravel - Home Edit</title>
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
        <link href="../assets/pages/css/about.css" rel="stylesheet" type="text/css" />
        <!-- END PAGE LEVEL STYLES -->
        <style>
            .carrucel-sidebar .carrucel-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .carrucel-list .carrucel-item {
                border: 1px solid #e1e6ef;
                border-radius: 4px;
                background: #fff;
                margin-bottom: 8px;
                transition: border-color .2s ease, box-shadow .2s ease;
                box-shadow: 0 1px 2px rgba(0,0,0,.05);
            }
            .carrucel-list .carrucel-item.active {
                border-color: #00a5df;
                background: #00a5df;
                box-shadow: none;
            }
            .carrucel-item__content {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 10px;
            }
            .carrucel-link {
                font-weight: 600;
                color: #304050;
                display: flex;
                align-items: center;
                text-decoration: none;
            }
            .carrucel-item.active .carrucel-link {
                color: #fff;
            }
            .carrucel-link__icon {
                margin-right: 6px;
                font-size: 16px;
            }
            .carrucel-delete {
                min-width: 90px;
                font-size: 10px;
                letter-spacing: 0.4px;
                text-transform: uppercase;
            }
            /* Estilos para Como Funciona y Services */
            .como-funciona-list, .services-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .como-funciona-item, .service-item {
                border: 1px solid #e1e6ef;
                border-radius: 4px;
                background: #fff;
                margin-bottom: 8px;
                transition: border-color .2s ease, box-shadow .2s ease;
                box-shadow: 0 1px 2px rgba(0,0,0,.05);
            }
            .como-funciona-item.active, .service-item.active {
                border-color: #26C281;
                background: #26C281;
                box-shadow: none;
            }
            .como-funciona-item__content, .service-item__content {
                display: flex;
                align-items: center;
                padding: 8px 10px;
            }
            .como-funciona-link, .service-link {
                font-weight: 600;
                color: #304050;
                display: flex;
                align-items: center;
                text-decoration: none;
                cursor: pointer;
            }
            .como-funciona-item.active .como-funciona-link,
            .service-item.active .service-link {
                color: #fff;
            }
            .como-funciona-link__icon, .service-link__icon {
                margin-right: 8px;
                font-size: 16px;
            }
        </style>
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
                        <h1>Home Edit</h1>
                        <ol class="breadcrumb">
                            <li>
                                <a href="#">Home</a>
                            </li>
                            <li>
                                <a href="#">Pages</a>
                            </li>
                            <li class="active">Home Edit</li>
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
                                    <h3>Carrucel</h3>
                                    <div class="carrucel-sidebar">
                                        <ul class="nav navbar-nav margin-bottom-35 carrucel-list">
                                            <?php 
                                                $n = 0;
                                                while($fil = mysqli_fetch_array($busca_carrucel)){ 
                                                    $id = $fil['id'];
                                                    if($n == 0){ 
                                                        $active = 'active';
                                                    } else {
                                                        $active = '';
                                                    }
                                            ?>
                                            <li class="btn-carrucel <?php echo $active;?> carrucel-item" id="btn-select-<?php echo $n;?>">
                                                <div class="carrucel-item__content">
                                                    <a class="carrucel-link" onclick="open_carrucel(<?php echo $n;?>,<?php echo $id;?>)">
                                                        <span class="carrucel-link__icon"><i class="icon-picture"></i></span>
                                                        <span>Carrucel <?php echo $n+1;?></span>
                                                    </a>
                                                    <?php if($es_admin){ ?>
                                                    <button type="button" class="btn btn-xs red-sunglo btn-carrucel-delete" onclick="confirmDeleteCarrucel(<?php echo $n;?>,<?php echo $id;?>)">Eliminar</button>
                                                    <?php } ?>
                                                </div>
                                            </li>
                                            <?php $n++; } ?>
                                        </ul>
                                    </div>
                                    <h3>Add Carrucel</h3>
                                    <ul class="nav navbar-nav">
                                        <li>
                                            <a onclick="addCarrucel()">
                                                <i class="icon-plus "></i> Add
                                                <label class="label label-danger">Carrucel</label>
                                            </a>
                                        </li>
                                    </ul>
                                    
                                    <h3>Cómo Funciona</h3>
                                    <div class="como-funciona-sidebar">
                                        <ul class="nav navbar-nav margin-bottom-35 como-funciona-list">
                                            <?php 
                                                $m = 0;
                                                while($fil_como = mysqli_fetch_array($busca_como_funciona)){ 
                                                    $id_como = $fil_como['id'];
                                            ?>
                                            <li class="btn-como-funciona como-funciona-item" id="btn-como-<?php echo $m;?>">
                                                <div class="como-funciona-item__content">
                                                    <a class="como-funciona-link" onclick="open_como_funciona(<?php echo $m;?>,<?php echo $id_como;?>)">
                                                        <span class="como-funciona-link__icon"><i class="<?php echo $fil_como['icon_class'];?>"></i></span>
                                                        <span><?php echo $fil_como['step_number'];?>. <?php echo $fil_como['title'];?></span>
                                                    </a>
                                                </div>
                                            </li>
                                            <?php $m++; } ?>
                                        </ul>
                                    </div>
                                    
                                    <h3>Servicios Detallados</h3>
                                    <div class="services-sidebar">
                                        <ul class="nav navbar-nav margin-bottom-35 services-list">
                                            <?php 
                                                $p = 0;
                                                while($fil_service = mysqli_fetch_array($busca_services)){ 
                                                    $id_service = $fil_service['id'];
                                            ?>
                                            <li class="btn-service service-item" id="btn-service-<?php echo $p;?>">
                                                <div class="service-item__content">
                                                    <a class="service-link" onclick="open_service(<?php echo $p;?>,<?php echo $id_service;?>)">
                                                        <span class="service-link__icon"><i class="<?php echo $fil_service['icon_class'];?>"></i></span>
                                                        <span><?php echo $fil_service['title'];?></span>
                                                    </a>
                                                </div>
                                            </li>
                                            <?php $p++; } ?>
                                        </ul>
                                    </div>
                                    <h3>Booking Widget</h3>
                                    <div class="services-sidebar">
                                        <ul class="nav navbar-nav margin-bottom-35 services-list">
                                            <li class="btn-booking service-item active" id="btn-booking">
                                                <div class="service-item__content">
                                                    <a class="service-link" onclick="open_booking()">
                                                        <span class="service-link__icon"><i class="fa fa-pencil-alt"></i></span>
                                                        <span>Booking widget</span>
                                                    </a>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </nav>
                            </div>
                            <!-- END PAGE SIDEBAR -->
                            <div class="page-content-col">
                            </div>
                        </div>
                    </div>
                    <!-- END SIDEBAR CONTENT LAYOUT -->
                </div>
                <!-- BEGIN FOOTER -->
                <?php echo $footer;?>
                <!-- END FOOTER -->
            </div>
        </div>
        <!-- END CONTAINER -->
        <!-- BEGIN QUICK SIDEBAR -->
        <?php echo $sider_bar;?>
        <!-- BEGIN THEME GLOBAL SCRIPTS (includes jQuery) -->
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        <!-- BEGIN PAGE LEVEL PLUGINS (require jQuery already loaded) -->
        <script src="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.sparkline.min.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL PLUGINS -->
        <!-- BEGIN PAGE LEVEL SCRIPTS -->
        <script src="../../assets/pages/scripts/profile.min.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL SCRIPTS -->
        <script type="text/javascript">
            var homeEditInitialTab = <?php echo json_encode($initial_site_tab); ?>;
        </script>
        <script src="js/home_edit.js" type="text/javascript"></script>
    </body>
</html>
