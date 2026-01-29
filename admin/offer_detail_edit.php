<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);
$n = 1;
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>medTravel - Offer Detail Edit</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <?php echo $global_first_style;?>
        <link href="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.css" rel="stylesheet" type="text/css" />
        <?php echo $theme_global_style;?>
        <link href="../../assets/pages/css/about.css" rel="stylesheet" type="text/css" />
        <style>
            .services-header {
                background-size: cover;
                background-position: center;
                color: white;
                padding: 60px 20px;
                border-radius: 10px;
                margin-bottom: 30px;
                min-height: 300px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .services-header h1 {
                font-size: 2.5rem;
                font-weight: 800;
                margin-bottom: 15px;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            }
            .services-header p {
                font-size: 1rem;
                margin-bottom: 10px;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            }
            .services-header p span {
                color: #ffd700;
                font-weight: 600;
            }
            .btn-header {
                background: #36c6d3;
                color: white;
                border: none;
                padding: 12px 20px;
                margin-bottom: 15px;
                text-align: left;
                width: 100%;
                border-radius: 4px;
                transition: all 0.3s;
            }
            .btn-header:hover {
                background: #2ba1ac;
                color: white;
            }
            .btn-header.active {
                background: #2ba1ac;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
            .form-group label {
                font-weight: 600;
                color: #555;
                margin-bottom: 8px;
            }
        </style>
    </head>
    <body class="page-header-fixed page-sidebar-closed-hide-logo page-content-white">
        <div class="page-wrapper">
            <!-- BEGIN HEADER -->
            <?php echo $top_header;?>
            <!-- END HEADER -->
            <!-- BEGIN HEADER & CONTENT DIVIDER -->
            <div class="clearfix"> </div>
            <!-- END HEADER & CONTENT DIVIDER -->
            <!-- BEGIN CONTAINER -->
            <div class="page-container">
                <!-- BEGIN SIDEBAR -->
                <?php echo $sidebar;?>
                <!-- END SIDEBAR -->
                <!-- BEGIN CONTENT -->
                <div class="page-content-wrapper">
                    <!-- BEGIN CONTENT BODY -->
                    <div class="page-content">
                        <!-- BEGIN PAGE HEADER-->
                        <!-- BEGIN PAGE BAR -->
                        <div class="page-bar">
                            <ul class="page-breadcrumb">
                                <li>
                                    <a href="index.php">Home</a>
                                    <i class="fa fa-circle"></i>
                                </li>
                                <li>
                                    <span>SITE</span>
                                    <i class="fa fa-circle"></i>
                                </li>
                                <li>
                                    <span>OFFER DETAIL</span>
                                </li>
                            </ul>
                        </div>
                        <!-- END PAGE BAR -->
                        <!-- BEGIN PAGE TITLE-->
                        <h1 class="page-title"> OFFER DETAIL PAGE EDIT
                            <small></small>
                        </h1>
                        <!-- END PAGE TITLE-->
                        <!-- END PAGE HEADER-->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="portlet light bordered">
                                    <div class="portlet-title">
                                        <div class="caption font-dark">
                                            <i class="icon-settings font-dark"></i>
                                            <span class="caption-subject bold uppercase"> Sections</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <button type="button" class="btn btn-block btn-header active" onclick="open_header(1)">
                                            <i class="fa fa-image"></i> HEADER
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="portlet light bordered">
                                    <div class="portlet-title">
                                        <div class="caption">
                                            <i class="icon-equalizer font-dark"></i>
                                            <span class="caption-subject font-dark bold uppercase">Content Editor</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <div class="page-content-col">
                                            <!-- Dynamic content loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END CONTENT BODY -->
                </div>
                <!-- END CONTENT -->
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
