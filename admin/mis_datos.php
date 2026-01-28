<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);
?>
<!DOCTYPE html>
<html lang="es">
    <!-- BEGIN HEAD -->
    <head>
        <meta charset="utf-8" />
        <title><?php echo $title;?> - Mis Datos</title>
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
        <link href="../../assets/pages/css/profile.min.css" rel="stylesheet" type="text/css" />
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
                        <h1>New User Profile | Account</h1>
                        <ol class="breadcrumb">
                            <li>
                                <a href="#">Home</a>
                            </li>
                            <li>
                                <a href="#">Pages</a>
                            </li>
                            <li class="active">User</li>
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
                            <div class="page-content-col">
                                <!-- BEGIN PAGE BASE CONTENT -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <!-- BEGIN PROFILE SIDEBAR -->
                                        <div class="profile-sidebar">
                                            <!-- PORTLET MAIN -->
                                            <div class="portlet light profile-sidebar-portlet ">
                                                <!-- SIDEBAR USERPIC -->
                                                <div class="profile-userpic">
                                                    <img src="<?php echo $rst["avatar"];?>" class="img-responsive" alt="">
                                                </div>
                                                <!-- END SIDEBAR USERPIC -->
                                                <!-- SIDEBAR USER TITLE -->
                                                <div class="profile-usertitle">
                                                    <div class="profile-usertitle-name"><?php echo $rst["nombre"];?></div>
                                                    <div class="profile-usertitle-job">  </div>
                                                </div>
                                                <!-- END SIDEBAR USER TITLE -->
                                            </div>
                                            <!-- END PORTLET MAIN -->
                                            <!-- PORTLET MAIN -->
                                            <div class="portlet light ">
                                                <!-- STAT -->
                                                <div class="row list-separated profile-stat">
                                                    <div class="col-md-4 col-sm-4 col-xs-6">
                                                        <div class="uppercase profile-stat-title"> 0 </div>
                                                        <div class="uppercase profile-stat-text"> Contratos </div>
                                                    </div>
                                                    <div class="col-md-4 col-sm-4 col-xs-6">
                                                        <div class="uppercase profile-stat-title"> 0 </div>
                                                        <div class="uppercase profile-stat-text"> Pendientes </div>
                                                    </div>
                                                    <div class="col-md-4 col-sm-4 col-xs-6">
                                                        <div class="uppercase profile-stat-title"> 0 </div>
                                                        <div class="uppercase profile-stat-text"> Cancelados </div>
                                                    </div>
                                                </div>
                                                <!-- END STAT -->
                                                <div>
                                                    <?php
                                                    if(isset($rst["about"]) && $rst["about"] != ''){
                                                    ?>
                                                    <h4 class="profile-desc-title">Sobre <span id="profile-desc-title-nombre"><?php echo $rst["nombre"];?></span></h4>
                                                        <span class="profile-desc-text"><?php echo $rst["about"];?></span>
                                                    <?php
                                                    }
                                                    if(isset($rst["url"]) && $rst["url"] != ''){
                                                    ?>
                                                    <div class="url margin-top-20 profile-desc-link">
                                                        <i class="fa fa-globe"></i>
                                                        <a id="profile-url" target="_blanc"><?php echo $rst["url"];?></a>
                                                    </div>
                                                    <?php
                                                    }

                                                    if($rst["celular"] != ''){
                                                    ?>
                                                    <div class="phone margin-top-20 profile-desc-link">
                                                        <i class="fa-solid fa-mobile-screen"></i>
                                                        <a id="profile-phone" target="_blanc"><?php echo $rst["celular"];?></a>
                                                    </div>
                                                    <?
                                                    }

                                                    if($rst["email"] != ''){
                                                    ?>
                                                    <div class="email margin-top-20 profile-desc-link">
                                                        <i class="fa-solid fa-at"></i>
                                                        <a id="profile-email" target="_blanc"><?php echo $rst["email"];?></a>
                                                    </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <!-- END PORTLET MAIN -->
                                        </div>
                                        <!-- END BEGIN PROFILE SIDEBAR -->
                                        <!-- BEGIN PROFILE CONTENT -->
                                        <div class="profile-content">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="portlet light ">
                                                        <div class="portlet-title tabbable-line">
                                                            <div class="caption caption-md">
                                                                <i class="icon-globe theme-font hide"></i>
                                                                <span class="caption-subject font-blue-madison bold uppercase">Profile Account</span>
                                                            </div>
                                                            <ul class="nav nav-tabs">
                                                                <li class="active">
                                                                    <a href="#tab_1_1" data-toggle="tab">Información Personal</a>
                                                                </li>
                                                                <li>
                                                                    <a href="#tab_1_2" data-toggle="tab">Actualizar Avatar</a>
                                                                </li>
                                                                <li>
                                                                    <a href="#tab_1_3" data-toggle="tab">Cambiar Password</a>
                                                                </li>
                                                                
                                                                <!--<li>
                                                                    <a href="#tab_1_4" data-toggle="tab">Settings</a>
                                                                </li>>-->
                                                            </ul>
                                                        </div>
                                                        <div class="portlet-body">
                                                            <div class="tab-content">
                                                                <!-- PERSONAL INFO TAB -->
                                                                <div class="tab-pane active" id="tab_1_1">
                                                                    <form action="#" id="form-usuario">
                                                                        <div class="form-body">
                                                                            <input type="hidden" id="id_usuario" name="id_usuario" value="<?php echo $rst["id"];?>">
                                                                            <div class="form-group form-md-line-input">
                                                                                <input type="text" class="form-control form-control-actualiza-data" id="usuario" name="usuario" placeholder="Ingrese el número de usuario" value="<?php echo $rst["usuario"];?>" disabled>
                                                                                <label for="form_control_1">Usuario
                                                                                    <span class="required">*</span>
                                                                                </label>
                                                                                <span class="usuario-help-block help-block">escriba la usuario...</span>
                                                                            </div>
                                                                            <div class="form-group form-md-line-input">
                                                                                <input type="text" class="form-control form-control-actualiza-data" name="name" id="nombre" placeholder="Ingrese su nombre" value="<?php echo $rst["nombre"];?>" onchange="actualizarDatos(this)">
                                                                                <label for="form_control_1">Nombre
                                                                                    <span class="required">*</span>
                                                                                </label>
                                                                                <span class="nombre-help-block help-block">Ingrese el nombre</span>
                                                                            </div>
                                                                            <div class="form-group form-md-line-input">
                                                                                <input type="text" class="form-control form-control-actualiza-data" id="email" name="email" placeholder="Enter your email" value="<?php echo $rst["email"];?>" onchange="actualizarDatos(this)">
                                                                                <span class="email-help-block help-block">Please enter your email...</span>
                                                                                <label for="form_control_1">Email
                                                                                    <span class="required">*</span>
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                                <!-- END PERSONAL INFO TAB -->
                                                                <!-- CHANGE AVATAR TAB -->
                                                                <div class="tab-pane" id="tab_1_2">
                                                                    <form action="#" role="form">
                                                                            <?php
                                                                            $id = $rst["id"];
                                                                            ?>
                                                                            <div class="form-group">
                                                                                <div class="fileinput fileinput-new" data-provides="fileinput">
                                                                                    <div class="fileinput-new thumbnail" style="width: 200px; height: 150px;">
                                                                                        <img src="<?php echo $rst["avatar"];?>" alt="" /> </div>
                                                                                    <div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 200px; max-height: 150px;"> </div>
                                                                                    <div>
                                                                                        <span class="btn default btn-file">
                                                                                            <span class="fileinput-new"> Cambiar imagen perfil </span>
                                                                                            <span class="fileinput-exists"> Cambiar </span>
                                                                                            <input type="file" name="..." id="img-avatar" onchange="subirImg(<?php echo $id;?>)"> </span>
                                                                                        <a href="javascript:;" class="btn default fileinput-exists" data-dismiss="fileinput"> Remover </a>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                    </form>
                                                                </div>
                                                                <!-- END CHANGE AVATAR TAB -->
                                                                <!-- CHANGE PASSWORD TAB -->
                                                                <div class="tab-pane" id="tab_1_3">
                                                                    <form action="#">
                                                                        <div class="form-group">
                                                                            <label class="control-label"> Password Actual</label>
                                                                            <input type="password" class="form-control" id="password_actual"> 
                                                                            <span class="password-a-validate-block help-block" id="passTexto"></span></div>
                                                                        <div class="form-group has-warning">
                                                                            <label class="control-label">Nuevo Nombre de Usuario</label>
                                                                            <input type="text" class="form-control" id="usuario_edit" disabled placeholder="Dejar en blanco si no quiere cambiar el nombre de usuario actual"/> </div>
                                                                        <hr>
                                                                        <div class="form-group">
                                                                            <label class="control-label">Nuevo Password</label>
                                                                            <input type="password" class="form-control" id="password" disabled/> </div>
                                                                        <div class="form-group">
                                                                            <label class="control-label">Repita el nuevo password</label>
                                                                            <input type="password" class="form-control" id="rpassword" disabled onchange="comparaPass()"/> </div>
                                                                            <span class="rpassword-validate-block help-block" id="comparaTexto"></span>
                                                                        <div class="margin-top-10">
                                                                            <a class="btn green" id="cambiar_password" disabled> Cambiar Password </a>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                                <!-- END CHANGE PASSWORD TAB -->
                                                                <!-- PRIVACY SETTINGS TAB -->
                                                                <div class="tab-pane" id="tab_1_4">
                                                                    <form action="#">
                                                                        <table class="table table-light table-hover">
                                                                            <tr>
                                                                                <td> Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus.. </td>
                                                                                <td>
                                                                                    <div class="mt-radio-inline">
                                                                                        <label class="mt-radio">
                                                                                            <input type="radio" name="optionsRadios1" value="option1" /> Yes
                                                                                            <span></span>
                                                                                        </label>
                                                                                        <label class="mt-radio">
                                                                                            <input type="radio" name="optionsRadios1" value="option2" checked/> No
                                                                                            <span></span>
                                                                                        </label>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td> Enim eiusmod high life accusamus terry richardson ad squid wolf moon </td>
                                                                                <td>
                                                                                    <div class="mt-radio-inline">
                                                                                        <label class="mt-radio">
                                                                                            <input type="radio" name="optionsRadios11" value="option1" /> Yes
                                                                                            <span></span>
                                                                                        </label>
                                                                                        <label class="mt-radio">
                                                                                            <input type="radio" name="optionsRadios11" value="option2" checked/> No
                                                                                            <span></span>
                                                                                        </label>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td> Enim eiusmod high life accusamus terry richardson ad squid wolf moon </td>
                                                                                <td>
                                                                                    <div class="mt-radio-inline">
                                                                                        <label class="mt-radio">
                                                                                            <input type="radio" name="optionsRadios21" value="option1" /> Yes
                                                                                            <span></span>
                                                                                        </label>
                                                                                        <label class="mt-radio">
                                                                                            <input type="radio" name="optionsRadios21" value="option2" checked/> No
                                                                                            <span></span>
                                                                                        </label>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td> Enim eiusmod high life accusamus terry richardson ad squid wolf moon </td>
                                                                                <td>
                                                                                    <div class="mt-radio-inline">
                                                                                        <label class="mt-radio">
                                                                                            <input type="radio" name="optionsRadios31" value="option1" /> Yes
                                                                                            <span></span>
                                                                                        </label>
                                                                                        <label class="mt-radio">
                                                                                            <input type="radio" name="optionsRadios31" value="option2" checked/> No
                                                                                            <span></span>
                                                                                        </label>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        </table>
                                                                        <!--end profile-settings-->
                                                                        <div class="margin-top-10">
                                                                            <a href="javascript:;" class="btn red"> Save Changes </a>
                                                                            <a href="javascript:;" class="btn default"> Cancel </a>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                                <!-- END PRIVACY SETTINGS TAB -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- END PROFILE CONTENT -->
                                    </div>
                                </div>
                                <!-- END PAGE BASE CONTENT -->
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
        <!-- BEGIN CORE PLUGINS -->
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
        <!-- END CORE PLUGINS -->
        <!-- BEGIN PAGE LEVEL PLUGINS -->
        <script src="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.sparkline.min.js" type="text/javascript"></script>
        <!-- BEGIN THEME GLOBAL SCRIPTS -->
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        <!-- BEGIN PAGE LEVEL SCRIPTS -->
        <script src="../../assets/pages/scripts/profile.min.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL SCRIPTS -->
        <script src="js/mis_datos.js" type="text/javascript"></script>
    </body>
</html>