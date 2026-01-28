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
        <!-- BEGIN PAGE LEVEL PLUGINS -->
        <link href="../../assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />
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
                        <h1>Registro nuevo usuario | Cuenta
                        <small>pagina cuenta de usuario</small></h1>
                        <ol class="breadcrumb">
                            <li>
                                <a href="#">Home</a>
                            </li>
                            <li>
                                <a href="#">Administrativo</a>
                            </li>
                            <li class="active">Crear Usuario</li>
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
                    <!-- END PAGE HEADER-->
                    <div class="row">
                        <div class="col-md-12">
                            <!-- BEGIN PROFILE SIDEBAR -->
                            <div class="profile-sidebar">
                                <!-- PORTLET MAIN -->
                                <div class="portlet light profile-sidebar-portlet ">
                                    <!-- SIDEBAR USERPIC -->
                                    <div class="profile-userpic">
                                        <img src="img/no-image.jpg" class="img-responsive" id="avatar" alt=""> </div>
                                    <!-- END SIDEBAR USERPIC -->
                                    <!-- SIDEBAR USER TITLE -->
                                    <div class="profile-usertitle">
                                        <div class="profile-usertitle-name"> Pepito Perez </div>
                                        <div class="profile-usertitle-job">  </div>
                                    </div>
                                    <!-- END SIDEBAR USER TITLE -->
                                    <!-- SIDEBAR BUTTONS -->
                                    <div class="form-group" id="div-group-radio">
                                        <div class="col-md-12">
                                            <div class="margin-bottom-10">
                                                <label for="option1" class="col-md-7">Principal</label>
                                                <input id="option1" type="radio" name="radio1" value="1" class="make-switch switch-radio1 col-md-5">
                                            </div>
                                            <div class="margin-bottom-10">
                                                <label for="option2" class="col-md-7">Administrativo</label>
                                                <input id="option2" type="radio" name="radio1" value="2" class="make-switch switch-radio1 col-md-5">
                                            </div>
                                            <div class="margin-bottom-10">
                                                <label for="option3" class="col-md-7">Contable</label>
                                                <input id="option3" type="radio" name="radio1" value="11" class="make-switch switch-radio1 col-md-5">
                                            </div>
                                            <hr>
                                            <div class="margin-bottom-10" id="cliente-ppal">
                                                <label for="option3" class="col-md-7">Cliente</label>
                                                <input id="option3" type="radio" name="radio1" value="3" class="make-switch switch-radio1 col-md-5">
                                            </div>
                                            <div class="margin-bottom-10">
                                                <label for="option4" class="col-md-7">Proveedor</label>
                                                <input id="option4" type="radio" name="radio1" value="4" class="make-switch switch-radio1 col-md-5" checked>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- END SIDEBAR BUTTONS -->
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
                                                        <a href="#tab_1_1" id="tab_href_1_1" data-toggle="tab">Información Personal</a>
                                                    </li>
                                                    <li>
                                                        <a href="#tab_1_2" id="tab_href_1_2" data-toggle="tab">Avatar</a>
                                                    </li>
                                                    <li>
                                                        <a href="#tab_1_3" id="tab_href_1_3" data-toggle="tab">Usuario y Password</a>
                                                    </li>
                                                    <!--
                                                    <li>
                                                        <a href="#tab_1_4" id="tab_href_1_4" data-toggle="tab">Permisos</a>
                                                    </li>
                                                    -->
                                                </ul>
                                            </div>
                                            <div class="portlet-body">
                                                <input type="hidden" id="id_usuario" name="id_usuario">
                                                <input type="hidden" id="usuario" name="usuario">
                                                <div class="tab-content">
                                                    <!-- PERSONAL INFO TAB -->
                                                    <div class="tab-pane active" id="tab_1_1">
                                                        <form role="form" action="#" id="form-crear-usuario" name="form-crear-usuario">
                                                            <div class="form-group">
                                                                <label class="control-label">Nombre</label>
                                                                <input type="text" placeholder="John" class="form-control" id="nombre" name="nombre" /> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Apellido</label>
                                                                <input type="text" placeholder="Doe" class="form-control" id="apellido" name="apellido" /> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Cedula</label>
                                                                <input type="text" placeholder="813912390128" class="form-control" id="cedula" name="cedula" /> </div>
                                                            <div class="form-group" id="div-empresa">
                                                                <label class="control-label">Empresa</label>
                                                                <select id="empresa" name="empresa" placeholder="Razón Social Empresa" class="form-control"></select></div>
                                                            <div class="form-group" id="div-provider" style="display:none;">
                                                                <label class="control-label">Prestador / Empresa <span class="required">*</span></label>
                                                                <select id="provider_id" name="provider_id" class="form-control">
                                                                    <option value="">-- Seleccione una empresa --</option>
                                                                    <?php
                                                                    $providers = mysqli_query($conexion, "SELECT id, name, type FROM providers WHERE is_active = 1 ORDER BY name ASC");
                                                                    while($prov = mysqli_fetch_array($providers)) {
                                                                        echo '<option value="'.$prov['id'].'">'.htmlspecialchars($prov['name']).' ('.ucfirst($prov['type']).')</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span class="help-block">Seleccione la empresa a la que pertenecerá este usuario</span>
                                                            </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Número Celular</label>
                                                                <input type="text" placeholder="3191234567" class="form-control" id="celular" name="celular" /> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Telefono</label>
                                                                <input type="text" placeholder="6011234567" class="form-control" id="telefono" name="telefono" /> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Ciudad</label>
                                                                <input type="text" placeholder="Ciudad" class="form-control" id="ciudad" name="ciudad" /> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Dirección</label>
                                                                <input type="text" placeholder="Dirección" class="form-control" id="direccion" name="direccion" /> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Email</label>
                                                                <input type="text" placeholder="Email" class="form-control" id="email" name="email" /> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Sobre ti</label>
                                                                <textarea class="form-control" rows="3" placeholder="Somos proveedores de servicios turisticos" id="about" name="about"></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Cargo</label>
                                                                <input type="text" placeholder="Cargo" class="form-control" id="cargo" name="cargo" /> </div>
                                                            <div class="margiv-top-10">
                                                                <button href="javascript:;" class="btn green" id="btn-crea-usuario"> Guardar y continuar </button>
                                                                <a href="javascript:;" class="btn default"> Cancel </a>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <!-- END PERSONAL INFO TAB -->
                                                    <!-- CHANGE AVATAR TAB -->
                                                    <div class="tab-pane" id="tab_1_2">
                                                        <p> Suba la imagen del nuevo usuario </p>
                                                        <form action="#" role="form" id="form-avatar-usuario" name="form-avatar-usuario">
                                                            <div class="form-group">
                                                                <div class="fileinput fileinput-new" data-provides="fileinput">
                                                                    <div class="fileinput-new thumbnail" style="width: 200px; height: 150px;">
                                                                        <img src="img/no-image.jpg" alt="" /> </div>
                                                                    <div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 200px; max-height: 150px;"> </div>
                                                                    <div>
                                                                        <span class="btn default btn-file">
                                                                            <span class="fileinput-new"> Seleccione la imagen </span>
                                                                            <span class="fileinput-exists"> Cambiar </span>
                                                                            <input type="file" name="img-avatar" id="img-avatar"> </span>
                                                                        <a href="javascript:;" class="btn default fileinput-exists" data-dismiss="fileinput"> Remove </a>
                                                                    </div>
                                                                </div>
                                                                <!--<div class="clearfix margin-top-10">
                                                                    <span class="label label-danger">NOTE! </span>
                                                                    <span>Attached image thumbnail is supported in Latest Firefox, Chrome, Opera, Safari and Internet Explorer 10 only </span>
                                                                </div>-->
                                                            </div>
                                                            <div class="margin-top-10">
                                                                <a href="javascript:;" class="btn green" onclick="crearAvatar();"> Continuar </a>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <!-- END CHANGE AVATAR TAB -->
                                                    <!-- CHANGE PASSWORD TAB -->
                                                    <div class="tab-pane" id="tab_1_3">
                                                        <form action="#" id="form-password-usuario" name="form-password-usuario">
                                                            <div class="form-group">
                                                                <label class="control-label">Usuario</label>
                                                                <input type="text" class="form-control" id="username" readonly/> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">New Password</label>
                                                                <input type="password" class="form-control" id="password_1"/> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Re-type New Password</label>
                                                                <input type="password" class="form-control" id="password_2"/> 
                                                                <span id="comparaTexto"></span>
                                                            </div>
                                                            <div class="margin-top-10">
                                                                <a href="javascript:;" class="btn green" id="btnSubmitPass" disabled> Crear Password y Continuar </a>
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
        <script src="../../assets/global/plugins/select2/js/select2.full.min.js" type="text/javascript"></script>
        <!-- BEGIN THEME GLOBAL SCRIPTS -->
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        <!-- BEGIN PAGE LEVEL SCRIPTS -->
        <script src="../../assets/pages/scripts/components-select2.min.js" type="text/javascript"></script>
        <script src="../../assets/pages/scripts/profile.min.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL SCRIPTS -->
        <script src="js/crear_usuario.js" type="text/javascript"></script>
    </body>
</html>