<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);
$busca_services_header = mysqli_query($conexion,"SELECT * FROM services_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
$rst_services_header = mysqli_fetch_array($busca_services_header);
$id = isset($rst_services_header['id']) ? $rst_services_header['id'] : 0;
$n = 1;
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>medTravel - Services Edit</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <?php echo $global_first_style;?>
        <link href="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.css" rel="stylesheet" type="text/css" />
        <?php echo $theme_global_style;?>
        <style>
            .services-header {
                background-color: rgba(0, 0, 0, 0.5);
                background-size: cover;
                background-position: center;
                height: 300px;
                padding: 80px 20px;
                border-radius: 10px;
                margin-bottom: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
            }
            .services-header h1 {
                font-weight: 800;
                color: #fff;
                text-align: center;
                margin: 0 0 10px 0;
            }
            .services-header p {
                font-size: 18px;
                font-weight: 400;
                color: #fff;
                text-align: center;
                margin: 0;
            }
            .services-header p span {
                color: orange;
            }
        </style>
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
                        <h1>Services Page Edit</h1>
                        <ol class="breadcrumb">
                            <li><a href="#">Site</a></li>
                            <li class="active">Services</li>
                        </ol>
                        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".page-sidebar">
                            <span class="sr-only">Toggle navigation</span>
                            <span class="toggle-icon">
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                            </span>
                        </button>
                    </div>
                    
                    <div class="page-content-container">
                        <div class="page-content-row">
                            <div class="page-sidebar">
                                <nav class="navbar" role="navigation">
                                    <ul class="nav navbar-nav">
                                        <li class="btn-header active" id="btn-select-<?php echo $n;?>">
                                            <a onclick="open_header(<?php echo $id;?>)">
                                                <i class="icon-picture"></i> Header
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                            <div class="page-content-col">
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo $footer;?>
            </div>
        </div>
        
        <?php echo $sider_bar;?>
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.js" type="text/javascript"></script>
        <?php echo $theme_layout_script;?>
        <script src="js/services_edit.js" type="text/javascript"></script>
    </body>
</html>
