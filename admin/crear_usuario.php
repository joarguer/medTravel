<?php
include("include/include.php");
$is_admin = is_role_admin_session();
$provider_session_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : null;
$service_provider_session_id = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : null;
$default_role_id = ROLE_PROVIDER;
if (!$is_admin) {
    if ($service_provider_session_id) {
        $default_role_id = ROLE_COMPLEMENTARY_ADMIN;
    } elseif ($provider_session_id) {
        $default_role_id = ROLE_PROVIDER;
    }
}
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);

$can_view_roles_help = $is_admin;
if (!$can_view_roles_help && function_exists('user_can')) {
    $can_view_roles_help = user_can(PERM_USERS_MANAGE) || user_can('users.create');
}

function crear_usuario_scope_type($role_id, $role_slug) {
    $rid = (int)$role_id;
    $slug = strtolower(trim((string)$role_slug));
    if ($rid === ROLE_ADMIN || $rid === ROLE_ADMINISTRATIVE || strpos($slug, 'principal') !== false || strpos($slug, 'administrative') !== false) {
        return 'admin';
    }
    if ($rid === ROLE_PROVIDER || $rid === ROLE_PROVIDER_ADMIN || (strpos($slug, 'provider') !== false && strpos($slug, 'complement') === false)) {
        return 'medical_provider';
    }
    if ($rid === ROLE_COMPLEMENTARY_ADMIN || strpos($slug, 'complementary') !== false) {
        return 'complementary_provider';
    }
    if ($rid === ROLE_CLIENT || strpos($slug, 'client') !== false || strpos($slug, 'cliente') !== false) {
        return 'none';
    }
    return 'none';
}

function crear_usuario_role_requirements($scope_type) {
    if ($scope_type === 'medical_provider') {
        return 'Requiere Prestador médico (provider_id). Debe quedar service_provider_id en NULL.';
    }
    if ($scope_type === 'complementary_provider') {
        return 'Requiere Proveedor Complementario activo (service_provider_id). Debe quedar provider_id en NULL.';
    }
    return 'No requiere scope de empresa.';
}

function crear_usuario_role_menu_summary($role_id, $scope_type) {
    if ($scope_type === 'admin') {
        return 'Gestión + Administración + Contenido Web (según permisos actuales).';
    }
    if ($scope_type === 'medical_provider') {
        return 'Módulos médicos: Categorías, Catálogo, Prestadores, Mis Ofertas (según RBAC actual).';
    }
    if ($scope_type === 'complementary_provider') {
        return 'Módulos complementarios: Proveedores Complementarios y MedTravel Services (según RBAC actual).';
    }
    if ((int)$role_id === ROLE_CLIENT) {
        return 'Acceso mínimo: Mi Perfil / opciones habilitadas.';
    }
    return 'Acceso según permisos del rol.';
}

$roles_help_rows = [];
$roles_help_map = [];
if ($can_view_roles_help) {
    $roles_sql = "SELECT id, slug, name, description FROM roles ORDER BY id ASC";
    $roles_result = mysqli_query($conexion, $roles_sql);
    if (!$roles_result) {
        $roles_sql = "SELECT id, slug, name, '' AS description FROM roles ORDER BY id ASC";
        $roles_result = mysqli_query($conexion, $roles_sql);
    }
    if ($roles_result) {
        while ($role = mysqli_fetch_assoc($roles_result)) {
            $rid = isset($role['id']) ? (int)$role['id'] : 0;
            if ($rid <= 0) {
                continue;
            }
            $scope_type = crear_usuario_scope_type($rid, $role['slug'] ?? '');
            $requirements = crear_usuario_role_requirements($scope_type);
            $menu_summary = crear_usuario_role_menu_summary($rid, $scope_type);

            $roles_help_rows[] = [
                'id' => $rid,
                'slug' => (string)($role['slug'] ?? ''),
                'name' => (string)($role['name'] ?? ('Rol #' . $rid)),
                'description' => (string)($role['description'] ?? ''),
                'requirements' => $requirements,
                'menu_summary' => $menu_summary,
            ];

            $roles_help_map[(string)$rid] = [
                'scope_type' => $scope_type,
                'required_fields' => $requirements,
                'menu_summary' => $menu_summary,
                'hint' => $requirements,
            ];
        }
    }
}
$roles_help_json = json_encode($roles_help_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($roles_help_json === false) {
    $roles_help_json = '{}';
}
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
        <script>
            window.CREAR_USUARIO_CTX = {
                isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
                providerId: <?php echo $provider_session_id ? $provider_session_id : 'null'; ?>,
                serviceProviderId: <?php echo $service_provider_session_id ? $service_provider_session_id : 'null'; ?>,
                roleProvider: <?php echo ROLE_PROVIDER; ?>,
                roleProviderAdmin: <?php echo ROLE_PROVIDER_ADMIN; ?>,
                roleComplementary: <?php echo ROLE_COMPLEMENTARY_ADMIN; ?>
            };
            window.ROLES_HELP = <?php echo $roles_help_json; ?>;
        </script>
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
                                    <div class="form-group" id="div-group-role">
                                        <div class="col-md-12">
                                            <label class="control-label">Rol</label>
                                            <select id="user_role" name="role" class="form-control" <?php echo $is_admin ? '' : 'disabled'; ?>>
                                                <?php foreach (get_available_roles() as $rid => $rlabel): ?>
                                                    <option value="<?php echo $rid; ?>" <?php echo ((int)$rid === (int)$default_role_id ? 'selected' : ''); ?>><?php echo htmlspecialchars($rlabel); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="help-block" id="role-scope-help"></span>
                                            <?php if(!$is_admin): ?><span class="help-block"><?php echo $service_provider_session_id ? 'Tu rol está fijado como Proveedor Complementario' : 'Tu rol está fijado como Proveedor'; ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                    <script>
                                        (function(){
                                            function toggleProviderField(){
                                                var sel = document.getElementById('user_role');
                                                var provDiv = document.getElementById('div-provider');
                                                var serviceProvDiv = document.getElementById('div-service-provider');
                                                var empDiv = document.getElementById('div-empresa');
                                                if(!sel || !provDiv || !serviceProvDiv || !empDiv) return;
                                                var roleProvider = '<?php echo ROLE_PROVIDER; ?>';
                                                var roleProviderAdmin = '<?php echo ROLE_PROVIDER_ADMIN; ?>';
                                                var roleComplementary = '<?php echo ROLE_COMPLEMENTARY_ADMIN; ?>';
                                                var isMedical = (sel.value == roleProvider || sel.value == roleProviderAdmin);
                                                var isComplementary = (sel.value == roleComplementary);
                                                provDiv.style.display = isMedical ? '' : 'none';
                                                serviceProvDiv.style.display = isComplementary ? '' : 'none';
                                                empDiv.style.display = (isMedical || isComplementary) ? 'none' : '';
                                            }
                                            document.addEventListener('DOMContentLoaded', function(){
                                                var sel = document.getElementById('user_role');
                                                if(sel){ sel.addEventListener('change', toggleProviderField); toggleProviderField(); }
                                            });
                                        })();
                                    </script>
                                    <!-- END SIDEBAR BUTTONS -->
                                </div>
                                <!-- END PORTLET MAIN -->
                            </div>
                            <!-- END BEGIN PROFILE SIDEBAR -->
                            <!-- BEGIN PROFILE CONTENT -->
                            <div class="profile-content">
                                <div class="row">
                                    <div class="col-md-12">
                                        <?php if ($can_view_roles_help && !empty($roles_help_rows)): ?>
                                        <div class="alert alert-info">
                                            <h4 class="block">Roles y accesos</h4>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th>Rol</th>
                                                            <th>Requiere</th>
                                                            <th>Acceso principal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($roles_help_rows as $role_help): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($role_help['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($role_help['slug'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($role_help['requirements'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars($role_help['menu_summary'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <p class="mb-0">
                                                <strong>Nota:</strong> Si asignas rol médico, debes seleccionar Prestador médico. Si asignas <code>complementary_admin</code>, debes seleccionar Proveedor Complementario activo. <code>client</code> es acceso mínimo.
                                            </p>
                                        </div>
                                        <?php endif; ?>
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
                                                                <label class="control-label">Empresa (cliente interno)</label>
                                                                <select id="empresa" name="empresa" placeholder="Razón Social Empresa" class="form-control"></select></div>
                                                            <div class="form-group" id="div-provider" style="display:none;">
                                                                <label class="control-label">Prestador / Empresa <span class="required">*</span></label>
                                                                <select id="provider_id" name="provider_id" class="form-control">
                                                                    <option value="">-- Seleccione un prestador --</option>
                                                                    <?php
                                                                    $providers_sql = "SELECT id, name, type FROM providers WHERE is_active = 1";
                                                                    $providers_has_deleted = mysqli_query($conexion, "SHOW COLUMNS FROM providers LIKE 'is_deleted'");
                                                                    if ($providers_has_deleted && mysqli_num_rows($providers_has_deleted) > 0) {
                                                                        $providers_sql .= " AND is_deleted = 0";
                                                                    }
                                                                    $providers_sql .= " ORDER BY name ASC";
                                                                    $providers = mysqli_query($conexion, $providers_sql);
                                                                    while($prov = mysqli_fetch_array($providers)) {
                                                                        echo '<option value="'.$prov['id'].'">'.htmlspecialchars($prov['name']).' ('.ucfirst($prov['type']).')</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span class="help-block">Selecciona el prestador al que se asocia este usuario (solo rol Proveedor)</span>
                                                            </div>
                                                            <div class="form-group" id="div-service-provider" style="display:none;">
                                                                <label class="control-label">Proveedor Complementario <span class="required">*</span></label>
                                                                <select id="service_provider_id" name="service_provider_id" class="form-control">
                                                                    <option value="">-- Seleccione un proveedor complementario --</option>
                                                                    <?php
                                                                    $service_providers_sql = "SELECT id, provider_name, provider_type FROM service_providers WHERE is_active = 1";
                                                                    $service_providers_has_deleted = mysqli_query($conexion, "SHOW COLUMNS FROM service_providers LIKE 'is_deleted'");
                                                                    if ($service_providers_has_deleted && mysqli_num_rows($service_providers_has_deleted) > 0) {
                                                                        $service_providers_sql .= " AND is_deleted = 0";
                                                                    }
                                                                    $service_providers_sql .= " ORDER BY provider_name ASC";
                                                                    $service_providers = mysqli_query($conexion, $service_providers_sql);
                                                                    if ($service_providers) {
                                                                        while($sp = mysqli_fetch_array($service_providers)) {
                                                                            $sp_type = !empty($sp['provider_type']) ? ' ('.ucfirst($sp['provider_type']).')' : '';
                                                                            echo '<option value="'.$sp['id'].'">'.htmlspecialchars($sp['provider_name']).$sp_type.'</option>';
                                                                        }
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span class="help-block">Selecciona el proveedor complementario dueño del catálogo para este usuario.</span>
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
        <?php echo $theme_layout_script;?>
        <!-- CORE / PAGE PLUGINS (after theme so jQuery is stable) -->
        <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.sparkline.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/select2/js/select2.full.min.js" type="text/javascript"></script>
        <!-- PAGE LEVEL SCRIPTS -->
        <script src="../../assets/pages/scripts/components-select2.min.js" type="text/javascript"></script>
        <script src="../../assets/pages/scripts/profile.min.js" type="text/javascript"></script>
        <script src="js/crear_usuario.js" type="text/javascript"></script>
    </body>
</html>
