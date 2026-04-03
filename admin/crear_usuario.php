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
    if ($rid === ROLE_ADMIN || strpos($slug, 'principal') !== false) {
        return 'admin';
    }
    if ($rid === ROLE_ADMINISTRATIVE || strpos($slug, 'administrative') !== false) {
        return 'none';
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

$current_scope_title = 'Administración central';
$current_scope_text = 'Puedes crear cuentas nuevas manuales de distintos tipos dentro del sistema. El rol seleccionado define el alcance final de la cuenta y los permisos vigentes del entorno siguen aplicando.';
$sidebar_scope_text = 'Operas como administración central. Este flujo sirve para altas manuales adicionales y no reemplaza onboarding, staff, mantenimiento ni perfil propio.';

if (!$is_admin && $service_provider_session_id) {
    $current_scope_title = 'Scope actual: proveedor complementario';
    $current_scope_text = 'Las cuentas creadas aquí quedarán dentro de tu proveedor complementario actual. El rol y el scope quedan acotados a este dominio y no sustituyen el alta del proveedor ni la gestión de cuentas existentes.';
    $sidebar_scope_text = 'Operas dentro de tu proveedor complementario. Este flujo crea cuentas adicionales subordinadas a ese scope.';
} elseif (!$is_admin && $provider_session_id) {
    $current_scope_title = 'Scope actual: prestador médico';
    $current_scope_text = 'Las cuentas creadas aquí quedarán dentro de tu prestador médico actual. Este flujo crea cuentas adicionales del dominio médico y no reemplaza el onboarding del prestador ni la gestión transversal de accesos.';
    $sidebar_scope_text = 'Operas dentro de tu prestador médico. Este flujo crea cuentas adicionales subordinadas a ese scope.';
}
?>
<!DOCTYPE html>
<html lang="es">
    <!-- BEGIN HEAD -->
    <head>
        <meta charset="utf-8" />
        <title><?php echo $title;?> - Crear Usuarios</title>
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
                roleComplementary: <?php echo ROLE_COMPLEMENTARY_ADMIN; ?>,
                scopeTitle: <?php echo json_encode($current_scope_title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                scopeText: <?php echo json_encode($current_scope_text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                sidebarScopeText: <?php echo json_encode($sidebar_scope_text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
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
                        <h1>Alta manual de cuentas
                        <small>Crea cuentas nuevas adicionales dentro del scope permitido por tu sesión</small></h1>
                        <ol class="breadcrumb">
                            <li>
                                <a href="index.php">Inicio</a>
                            </li>
                            <li>
                                <a href="#">Usuarios y Accesos</a>
                            </li>
                            <li class="active">Alta manual de cuentas</li>
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
                                        <div class="profile-usertitle-name"> Cuenta manual pendiente </div>
                                        <div class="profile-usertitle-job" id="manual-account-sidebar-job">Alta adicional dentro del sistema</div>
                                    </div>
                                    <!-- END SIDEBAR USER TITLE -->
                                    <!-- SIDEBAR BUTTONS -->
                                    <div class="alert alert-info" style="margin:0 15px 15px;">
                                        <strong>Alcance actual</strong>
                                        <div id="manual-account-sidebar-scope" class="small"><?php echo htmlspecialchars($sidebar_scope_text, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="form-group" id="div-group-role">
                                        <div class="col-md-12">
                                            <label class="control-label">Tipo de cuenta / rol</label>
                                            <select id="user_role" name="role" class="form-control" <?php echo $is_admin ? '' : 'disabled'; ?>>
                                                <?php foreach (get_available_roles() as $rid => $rlabel): ?>
                                                    <option value="<?php echo $rid; ?>" <?php echo ((int)$rid === (int)$default_role_id ? 'selected' : ''); ?>><?php echo htmlspecialchars($rlabel); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="help-block" id="role-scope-help"></span>
                                            <span class="help-block" id="role-actor-help"></span>
                                            <?php if(!$is_admin): ?><span class="help-block"><?php echo $service_provider_session_id ? 'Tu scope de creación está fijado como Proveedor Complementario.' : 'Tu scope de creación está fijado como Prestador médico.'; ?></span><?php endif; ?>
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
                                        <div class="alert alert-info">
                                            <strong>Qué crea este flujo:</strong> cuentas <strong>nuevas</strong>, <strong>manuales</strong> y <strong>adicionales</strong> dentro del sistema. Pueden ser cuentas globales o cuentas asociadas a un dominio médico o complementario, según tu scope actual y el rol seleccionado.
                                        </div>
                                        <div class="alert alert-warning">
                                            <strong>Qué no crea este flujo:</strong> no reemplaza el onboarding médico canónico de <strong>Prestadores Médicos</strong>, no da de alta <strong>staff médico</strong>, no sirve para administrar <strong>cuentas ya existentes</strong> y no modifica <strong>tu perfil propio</strong>.
                                        </div>
                                        <div class="alert alert-success" id="current-scope-alert">
                                            <strong id="current-scope-title"><?php echo htmlspecialchars($current_scope_title, ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <div id="current-scope-text" class="small"><?php echo htmlspecialchars($current_scope_text, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
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
                                                <strong>Nota:</strong> Si asignas rol médico, debes seleccionar Prestador médico. Si asignas <code>complementary_admin</code>, debes seleccionar Proveedor Complementario activo. <code>client</code> es acceso mínimo. Esta tabla orienta el alta manual, pero no sustituye los módulos especializados del sistema.
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                        <div class="portlet light ">
                                            <div class="portlet-title tabbable-line">
                                                <div class="caption caption-md">
                                                    <i class="icon-globe theme-font hide"></i>
                                                    <span class="caption-subject font-blue-madison bold uppercase">Alta manual de cuentas adicionales</span>
                                                </div>
                                                <ul class="nav nav-tabs">
                                                    <li class="active">
                                                        <a href="#tab_1_1" id="tab_href_1_1" data-toggle="tab">1. Datos de cuenta</a>
                                                    </li>
                                                    <li>
                                                        <a href="#tab_1_2" id="tab_href_1_2" data-toggle="tab">2. Avatar opcional</a>
                                                    </li>
                                                    <li>
                                                        <a href="#tab_1_3" id="tab_href_1_3" data-toggle="tab">3. Contraseña inicial</a>
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
                                                <div class="alert alert-info" id="wizard-intro-alert">
                                                    <strong>Resultado esperado:</strong> este flujo crea una cuenta nueva de acceso. Después podrás cargar un avatar opcional y definir la contraseña inicial para entregar el acceso.
                                                </div>
                                                <div class="alert alert-warning" id="wizard-role-summary">
                                                    El rol elegido y tu scope actual determinan qué tipo de cuenta se crea y a qué empresa o dominio quedará vinculada.
                                                </div>
                                                <div class="tab-content">
                                                    <!-- PERSONAL INFO TAB -->
                                                    <div class="tab-pane active" id="tab_1_1">
                                                        <form role="form" action="#" id="form-crear-usuario" name="form-crear-usuario">
                                                            <p class="text-muted" style="margin-bottom:20px; max-width:900px;">
                                                                Completa los datos base de la nueva cuenta. Si tu sesión está limitada a un prestador médico o a un proveedor complementario, el alta quedará automáticamente subordinada a ese scope.
                                                            </p>
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
                                                                <label class="control-label">Empresa interna / cliente interno</label>
                                                                <select id="empresa" name="empresa" placeholder="Razón Social Empresa" class="form-control"></select></div>
                                                            <div class="form-group" id="div-provider" style="display:none;">
                                                                <label class="control-label">Prestador médico asociado <span class="required">*</span></label>
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
                                                                <span class="help-block">La cuenta quedará vinculada a este prestador médico. Usa este campo para cuentas adicionales del dominio médico, no para crear el owner canónico del prestador.</span>
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
                                                                <span class="help-block">La cuenta quedará vinculada a este proveedor complementario dentro del dominio actual.</span>
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
                                                                <input type="text" placeholder="Email de acceso" class="form-control" id="email" name="email" /> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Descripción breve</label>
                                                                <textarea class="form-control" rows="3" placeholder="Descripción breve o contexto operativo de la cuenta" id="about" name="about"></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Cargo / referencia interna</label>
                                                                <input type="text" placeholder="Cargo o referencia interna" class="form-control" id="cargo" name="cargo" /> </div>
                                                            <div class="margiv-top-10">
                                                                <button href="javascript:;" class="btn green" id="btn-crea-usuario"> Guardar datos de la cuenta y continuar </button>
                                                                <a href="javascript:;" class="btn default"> Cancelar </a>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <!-- END PERSONAL INFO TAB -->
                                                    <!-- CHANGE AVATAR TAB -->
                                                    <div class="tab-pane" id="tab_1_2">
                                                        <p> Paso opcional: carga un avatar para identificar visualmente esta cuenta dentro del sistema. </p>
                                                        <form action="#" role="form" id="form-avatar-usuario" name="form-avatar-usuario">
                                                            <div class="form-group">
                                                                <div class="fileinput fileinput-new" data-provides="fileinput">
                                                                    <div class="fileinput-new thumbnail" style="width: 200px; height: 150px;">
                                                                        <img src="img/no-image.jpg" alt="" /> </div>
                                                                    <div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 200px; max-height: 150px;"> </div>
                                                                    <div>
                                                                        <span class="btn default btn-file">
                                                                            <span class="fileinput-new"> Seleccionar imagen </span>
                                                                            <span class="fileinput-exists"> Cambiar </span>
                                                                            <input type="file" name="img-avatar" id="img-avatar"> </span>
                                                                        <a href="javascript:;" class="btn default fileinput-exists" data-dismiss="fileinput"> Quitar </a>
                                                                    </div>
                                                                </div>
                                                                <!--<div class="clearfix margin-top-10">
                                                                    <span class="label label-danger">NOTE! </span>
                                                                    <span>Attached image thumbnail is supported in Latest Firefox, Chrome, Opera, Safari and Internet Explorer 10 only </span>
                                                                </div>-->
                                                            </div>
                                                            <div class="margin-top-10">
                                                                <a href="javascript:;" class="btn green" onclick="crearAvatar();"> Guardar avatar y continuar </a>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <!-- END CHANGE AVATAR TAB -->
                                                    <!-- CHANGE PASSWORD TAB -->
                                                    <div class="tab-pane" id="tab_1_3">
                                                        <form action="#" id="form-password-usuario" name="form-password-usuario">
                                                            <p class="text-muted">Define la contraseña inicial que recibirá esta cuenta nueva para su primer acceso.</p>
                                                            <div class="form-group">
                                                                <label class="control-label">Usuario de acceso</label>
                                                                <input type="text" class="form-control" id="username" readonly/> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Contraseña inicial</label>
                                                                <input type="password" class="form-control" id="password_1"/> </div>
                                                            <div class="form-group">
                                                                <label class="control-label">Confirmar contraseña inicial</label>
                                                                <input type="password" class="form-control" id="password_2"/> 
                                                                <span id="comparaTexto"></span>
                                                            </div>
                                                            <div class="margin-top-10">
                                                                <a href="javascript:;" class="btn green" id="btnSubmitPass" disabled> Guardar contraseña inicial y finalizar </a>
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
