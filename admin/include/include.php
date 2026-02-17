<?php  
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include("valida_session.php");
include_once("conexion.php");
$nombre_usuario = isset($_SESSION["nombre_usuario"]) ? $_SESSION["nombre_usuario"] : '';
//separator
$nombre_usuario_parts = explode(" ", $nombre_usuario ?: '');
$nombre_usuario = isset($nombre_usuario_parts[0]) ? $nombre_usuario_parts[0] : '';
$title = 'MedTravel';

// ROLE FLAGS: determinar permisos de menú
// Determinación robusta de roles: acepta 'ppal' numérico, rol numérico o texto que contenga 'admin'
$es_admin = false;
$es_prestador = false;
$es_complementario = false;
// Load roles helpers
require_once __DIR__ . '/roles.php';
$es_admin = is_role_admin_session();
if (isset($_SESSION['provider_id']) && !empty($_SESSION['provider_id'])) {
    $es_prestador = true;
}
$es_complementario = is_complementary_user_session();

// Contadores y notificaciones de booking pending
$booking_pending_count = 0;
$booking_notifications = [];
$booking_badge = '0';
$booking_summary_text = 'No pending bookings';
$booking_list_html = '';
$deletion_count = 0;
$deletion_list_html = '';
if (isset($conexion)) {
    $notif_sql = "SELECT id, name, destination, created_at FROM booking_requests WHERE status = 'pending' ORDER BY id DESC LIMIT 5";
    $notif_res = mysqli_query($conexion, $notif_sql);
    if ($notif_res) {
        while ($row = mysqli_fetch_assoc($notif_res)) {
            $booking_notifications[] = $row;
        }
        $booking_pending_count = mysqli_num_rows($notif_res);
        $booking_badge = (string) $booking_pending_count;
        $booking_summary_text = $booking_pending_count > 0
            ? '<span class="bold">' . $booking_pending_count . '</span> pending bookings'
            : 'No pending bookings';

        if ($booking_pending_count > 0) {
            foreach ($booking_notifications as $notif) {
                $id = (int) $notif['id'];
                $name = htmlspecialchars($notif['name'] ?? 'Client', ENT_QUOTES, 'UTF-8');
                $dest = htmlspecialchars($notif['destination'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                $created = htmlspecialchars($notif['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
                $booking_list_html .= '<li>'
                    . '<a href="booking_requests.php">'
                    . '<span class="details">'
                    . '<span class="label label-sm label-icon label-success md-skip">'
                    . '<i class="fa fa-calendar"></i>'
                    . '</span> New booking from ' . $name . ' (' . $dest . ')</span>';
                if ($created !== '') {
                    $booking_list_html .= '<span class="time">' . $created . '</span>';
                }
                $booking_list_html .= '</a></li>';
            }
        } else {
            $booking_list_html = '<li><a href="booking_requests.php"><span class="details"><span class="label label-sm label-icon label-default md-skip"><i class="fa fa-info"></i></span>No pending bookings</span></a></li>';
        }
    }

    // Solicitudes de eliminación de datos (solo admin)
    if ($es_admin) {
        $deletion_log = __DIR__ . '/../logs/data_deletion.log';
        if (file_exists($deletion_log)) {
            $lines = file($deletion_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $deletion_count = count($lines);
            $last = array_slice($lines, -5);
            foreach (array_reverse($last) as $line) {
                $entry = json_decode($line, true);
                if (!$entry) continue;
                $req = htmlspecialchars($entry['request_id'] ?? '', ENT_QUOTES, 'UTF-8');
                $phone = htmlspecialchars($entry['phone'] ?? '', ENT_QUOTES, 'UTF-8');
                $email = htmlspecialchars($entry['email'] ?? '', ENT_QUOTES, 'UTF-8');
                $time = htmlspecialchars($entry['timestamp'] ?? '', ENT_QUOTES, 'UTF-8');
                $deletion_list_html .= '<li>'
                    . '<a href="data_deletion_requests.php">'
                    . '<span class="details">'
                    . '<span class="label label-sm label-icon label-warning md-skip"><i class="fa fa-trash"></i></span>'
                    . $req . ' • ' . $phone . ' • ' . $email . '<br><small>' . $time . '</small>'
                    . '</span></a></li>';
            }
        }
        if ($deletion_list_html === '') {
            $deletion_list_html = '<li><a href="data_deletion_requests.php"><span class="details"><span class="label label-sm label-icon label-default md-skip"><i class="fa fa-info"></i></span>No deletion requests</span></a></li>';
        }
    }
}
// Sanitizar nombre de usuario para salida segura
$nombre_usuario = htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8');
$global_first_style =  '<meta content="width=device-width, initial-scale=1" name="viewport" />
                        <!-- BEGIN LAYOUT FIRST STYLES -->
                        <link href="//fonts.googleapis.com/css?family=Oswald:400,300,700" rel="stylesheet" type="text/css" />
                        <!-- END LAYOUT FIRST STYLES -->
                        <!-- BEGIN GLOBAL MANDATORY STYLES -->
                        <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
                        <link href="../../assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
                        <link href="../../assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
                        <link href="../../assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
                        <link href="../../assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
                        <!-- END GLOBAL MANDATORY STYLES -->';

$theme_global_style =  '<!-- BEGIN THEME GLOBAL STYLES -->
                        <link href="../../assets/global/css/components-md.min.css" rel="stylesheet" id="style_components" type="text/css" />
                        <link href="../../assets/global/css/plugins-md.min.css" rel="stylesheet" type="text/css" />
                        <!-- END THEME GLOBAL STYLES -->';

$theme_layout_style =  '<!-- BEGIN THEME LAYOUT STYLES -->
                        <link href="../../assets/layouts/layout5/css/layout.min.css" rel="stylesheet" type="text/css" />
                        <link href="../../assets/layouts/layout5/css/custom.min.css" rel="stylesheet" type="text/css" />
                        <!-- END THEME LAYOUT STYLES -->
                        <link rel="shortcut icon" href="favicon.ico" />
                        <link href="../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />';   

$theme_layout_script =  '<!-- BEGIN THEME LAYOUT SCRIPTS -->
                        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
                        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
                        <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
                        <script src="../../assets/global/scripts/app.min.js" type="text/javascript"></script>
                        <script src="../../assets/layouts/layout5/scripts/layout.min.js" type="text/javascript"></script>
                        <script src="../../assets/layouts/global/scripts/quick-sidebar.min.js" type="text/javascript"></script>
                        <script src="../assets/pages/scripts/ui-toastr.min.js" type="text/javascript"></script>
                        <!-- END THEME LAYOUT SCRIPTS -->
                        <script src="js/global_scripts.js" type="text/javascript"></script>';

// Scripts base para las vistas (se usan en la mayoría de páginas admin)
$theme_global_js = '<!-- BEGIN CORE PLUGINS -->
                    <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
                    <script src="../../assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>
                    <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
                    <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
                    <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
                    <!-- END CORE PLUGINS -->';

$theme_layout_js = '<!-- BEGIN THEME GLOBAL SCRIPTS -->
                    <script src="../../assets/global/scripts/app.min.js" type="text/javascript"></script>
                    <!-- END THEME GLOBAL SCRIPTS -->
                    <!-- BEGIN THEME LAYOUT SCRIPTS -->
                    <script src="../../assets/layouts/layout5/scripts/layout.min.js" type="text/javascript"></script>
                    <script src="../../assets/layouts/global/scripts/quick-sidebar.min.js" type="text/javascript"></script>
                    <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
                    <script src="../assets/pages/scripts/ui-toastr.min.js" type="text/javascript"></script>
                    <script src="js/global_scripts.js" type="text/javascript"></script>
                    <!-- END THEME LAYOUT SCRIPTS -->';

$avatar = $_SESSION['avatar'];
$avatar = '../'.$avatar;

$top_header =  '<div class="clearfix navbar-fixed-top">
                <!-- Brand and toggle get grouped for better mobile display -->
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-responsive-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="toggle-icon">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </span>
                </button>
                <!-- End Toggle Button -->
                <!-- BEGIN LOGO -->
                <a id="index" class="page-logo" href="index.php">
                    <img src="img/logoWhite.png" alt="Logo" width="150px"> </a>
                <!-- END LOGO -->
                <!-- BEGIN SEARCH
                <form class="search" action="extra_search.html" method="GET">
                    <input type="name" class="form-control" name="query" placeholder="Search...">
                    <a href="javascript:;" class="btn submit md-skip">
                        <i class="fa fa-search"></i>
                    </a>
                </form>
                <!-- END SEARCH -->
                <!-- BEGIN TOPBAR ACTIONS -->
                <div class="topbar-actions">
                    <!-- BEGIN GROUP NOTIFICATION -->
                    <div class="btn-group-notification btn-group" id="header_notification_bar">
                        <button type="button" class="btn btn-sm md-skip dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <i class="icon-bell"></i>
                            <span class="badge">'.$booking_badge.'</span>
                        </button>
                        <ul class="dropdown-menu-v2">
                            <li class="external">
                                <h3>'.$booking_summary_text.'</h3>
                                <a href="booking_requests.php">view all</a>
                            </li>
                            <li>
                                <ul class="dropdown-menu-list scroller" style="height: 250px; padding: 0;" data-handle-color="#637283">
                                    '.$booking_list_html.'
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <!-- END GROUP NOTIFICATION -->'
                    ;

// Data deletion dropdown (admin only)
if ($es_admin) {
    $top_header .= '
                    <!-- BEGIN DATA DELETION ALERTS -->
                    <div class="btn-group-notification btn-group" style="margin-left:12px; margin-right:4px;">
                        <button type="button" class="btn btn-sm md-skip dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <i class="fa fa-trash"></i>
                            <span class="badge">' . $deletion_count . '</span>
                        </button>
                        <ul class="dropdown-menu-v2">
                            <li class="external">
                                <h3>' . $deletion_count . ' data deletion request(s)</h3>
                                <a href="data_deletion_requests.php">view all</a>
                            </li>
                            <li>
                                <ul class="dropdown-menu-list scroller" style="height: 250px; padding: 0;" data-handle-color="#637283">
                                    ' . $deletion_list_html . '
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <!-- END DATA DELETION ALERTS -->';
}

$top_header .= '
                    <!-- BEGIN USER PROFILE -->
                    <div class="btn-group-img btn-group">
                        <button type="button" class="btn btn-sm md-skip dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <span>Hi, '.$nombre_usuario.' ';
if ($es_admin) {
    $top_header .= '<span class="badge badge-danger">ADMIN</span>';
} elseif ($es_prestador) {
    $top_header .= '<span class="badge badge-info">PRESTADOR</span>';
}
$top_header .= '</span>
                            <img src="admin/'.$avatar.'" alt="" id="avatar_header"> </button>
                        <ul class="dropdown-menu-v2" role="menu">
                            <li>
                                <a href="mis_datos.php">
                                    <i class="icon-user"></i> My Profile
                                    <span class="badge badge-danger">1</span>
                                </a>
                            </li>
                            <li>
                                <a href="app_calendar.html">
                                    <i class="icon-calendar"></i> My Calendar </a>
                            </li>
                            <li>
                                <a href="app_inbox.html">
                                    <i class="icon-envelope-open"></i> My Inbox
                                    <span class="badge badge-danger"> 3 </span>
                                </a>
                            </li>
                            <li>
                                <a href="app_todo_2.html">
                                    <i class="icon-rocket"></i> My Tasks
                                    <span class="badge badge-success"> 7 </span>
                                </a>
                            </li>
                            <li class="divider"> </li>
                            <li>
                                <a href="page_user_lock_1.html">
                                    <i class="icon-lock"></i> Lock Screen </a>
                            </li>
                            <li>
                                <a href="include/salir.php">
                                    <i class="icon-key"></i> Log Out </a>
                            </li>
                        </ul>
                    </div>
                    <!-- END USER PROFILE -->
                    <!-- BEGIN QUICK SIDEBAR TOGGLER -->
                    <button type="button" class="quick-sidebar-toggler md-skip" data-toggle="collapse">
                        <span class="sr-only">Toggle Quick Sidebar</span>
                        <i class="icon-logout"></i>
                    </button>
                    <!-- END QUICK SIDEBAR TOGGLER -->
                </div>
                <!-- END TOPBAR ACTIONS -->
                </div>';

require_once __DIR__ . '/menu_helpers.php';
$current = menu_current_script();

$dashboard_pages = array('index.php');
$management_pages = array('service_categories.php','service_catalog.php','providers.php','providers_complementary.php','provider_offers.php','mi_empresa.php','clientes.php','provider_verification.php','paquetes.php','booking_requests.php','medtravel_services.php');
$medical_group_pages = array('service_categories.php','service_catalog.php','providers.php','provider_verification.php','provider_offers.php');
$complementary_group_pages = array('providers_complementary.php','medtravel_services.php','paquetes.php');
$complementary_scope_pages = array('providers_complementary.php','medtravel_services.php');
$clients_booking_pages = array('clientes.php','booking_requests.php');
$admin_section_pages = array('mis_datos.php','crear_usuario.php','usuarios.php','roles.php','informes.php','email_settings.php','data_deletion_requests.php');
$admin_users_pages = array('mis_datos.php','usuarios.php','crear_usuario.php','roles.php');
$site_pages = array('home_edit.php','about_edit.php','services_edit.php','offers_header_edit.php','offer_detail_edit.php','blog_edit.php','wizard_header_edit.php');
$profile_pages = array('mis_datos.php');

$top_header_2 = '<div class="nav-collapse collapse navbar-collapse navbar-responsive-collapse">
                    <ul class="nav navbar-nav">
                        <!-- DASHBOARD -->
                        <li'.menu_li_class($dashboard_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="index.php" class="text-uppercase">
                                <i class="icon-home"></i> Dashboard </a>
                        </li>
                        
                        <!-- GESTIÓN DE SERVICIOS -->
                        <li'.menu_li_class($management_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                <i class="icon-layers"></i> Gestión </a>
                            <ul class="dropdown-menu dropdown-menu-fw">';

// Submenú: Servicios Médicos
if ($es_admin) {
    $top_header_2 .=               '<li'.menu_li_class($medical_group_pages, 'dropdown more-dropdown-sub').'>
                                        <a href="javascript:;">
                                            <i class="icon-heart"></i> Servicios Médicos </a>
                                        <ul class="dropdown-menu">
                                            <li'.menu_li_class('service_categories.php').'>
                                                <a href="./service_categories.php">Categorías</a>
                                            </li>
                                            <li'.menu_li_class('service_catalog.php').'>
                                                <a href="./service_catalog.php">Catálogo de Servicios</a>
                                            </li>
                                            <li'.menu_li_class('providers.php').'>
                                                <a href="./providers.php">Prestadores Médicos</a>
                                            </li>
                                            <li'.menu_li_class('provider_verification.php').'>
                                                <a href="./provider_verification.php">Verificación Prestadores</a>
                                            </li>
                                            <li'.menu_li_class('provider_offers.php').'>
                                                <a href="./provider_offers.php">Mis Ofertas</a>
                                            </li>
                                        </ul>
                                    </li>';

    // Submenú: Servicios Complementarios
    $top_header_2 .=               '<li'.menu_li_class($complementary_group_pages, 'dropdown more-dropdown-sub').'>
                                        <a href="javascript:;">
                                            <i class="icon-plane"></i> Servicios Complementarios </a>
                                        <ul class="dropdown-menu">
                                            <li'.menu_li_class('providers_complementary.php').'>
                                                <a href="./providers_complementary.php">Proveedores Complementarios</a>
                                            </li>
                                            <li'.menu_li_class('medtravel_services.php').'>
                                                <a href="./medtravel_services.php">MedTravel Services</a>
                                            </li>
                                            <li'.menu_li_class('paquetes.php').'>
                                                <a href="./paquetes.php">Paquetes de Viaje</a>
                                            </li>
                                        </ul>
                                    </li>';

    // Submenú: Clientes y Bookings
    $top_header_2 .=               '<li'.menu_li_class($clients_booking_pages, 'dropdown more-dropdown-sub').'>
                                        <a href="javascript:;">
                                            <i class="icon-users"></i> Clientes y Bookings </a>
                                        <ul class="dropdown-menu">
                                            <li'.menu_li_class('clientes.php').'>
                                                <a href="./clientes.php">Gestión de Clientes</a>
                                            </li>
                                            <li'.menu_li_class('booking_requests.php').'>
                                                <a href="./booking_requests.php">Solicitudes de Booking</a>
                                            </li>
                                        </ul>
                                    </li>';
} elseif ($es_prestador) {
    // Prestador médico: dejar entrada directa para evitar menús vacíos en temas legacy.
    $top_header_2 .=               '<li'.menu_li_class('provider_offers.php').'>
                                        <a href="./provider_offers.php">
                                            <i class="icon-tag"></i> Mis Ofertas </a>
                                    </li>';
} elseif ($es_complementario) {
    $top_header_2 .=               '<li'.menu_li_class($complementary_scope_pages, 'dropdown more-dropdown-sub').'>
                                        <a href="javascript:;">
                                            <i class="icon-plane"></i> Servicios Complementarios </a>
                                        <ul class="dropdown-menu">
                                            <li'.menu_li_class('providers_complementary.php').'>
                                                <a href="./providers_complementary.php">Proveedores Complementarios</a>
                                            </li>
                                            <li'.menu_li_class('medtravel_services.php').'>
                                                <a href="./medtravel_services.php">MedTravel Services</a>
                                            </li>
                                        </ul>
                                    </li>';
}

// Mi Empresa (solo prestadores)
if ($es_prestador && !$es_admin) {
    $top_header_2 .=               '<li'.menu_li_class('mi_empresa.php').'>
                                        <a href="./mi_empresa.php">
                                            <i class="icon-briefcase"></i> Mi Empresa </a>
                                    </li>';
}

$top_header_2 .=           '</ul>
                        </li>';

// ADMINISTRACIÓN (solo admins)
if ($es_admin) {
    $top_header_2 .=           '<li'.menu_li_class($admin_section_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                                <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                    <i class="icon-settings"></i> Administración </a>
                                <ul class="dropdown-menu dropdown-menu-fw">
                                    <li'.menu_li_class($admin_users_pages, 'dropdown more-dropdown-sub').'>
                                        <a href="javascript:;">
                                            <i class="icon-user"></i> Usuarios y Accesos </a>
                                        <ul class="dropdown-menu">
                                            <li'.menu_li_class('mis_datos.php').'>
                                                <a href="./mis_datos.php">Mi Perfil</a>
                                            </li>
                                            <li'.menu_li_class('usuarios.php').'>
                                                <a href="./usuarios.php">Usuarios</a>
                                            </li>
                                            <li'.menu_li_class('crear_usuario.php').'>
                                                <a href="./crear_usuario.php">Crear Usuarios</a>
                                            </li>
                                            <li'.menu_li_class('roles.php').'>
                                                <a href="./roles.php">Roles</a>
                                            </li>
                                        </ul>
                                    </li>
    <li'.menu_li_class('informes.php').'>
        <a href="./informes.php">
            <i class="icon-bar-chart"></i> Informes </a>
    </li>
    <li'.menu_li_class('data_deletion_requests.php').'>
        <a href="./data_deletion_requests.php">
            <i class="icon-trash"></i> Solicitudes de eliminación </a>
    </li>
    <li'.menu_li_class('email_settings.php').'>
        <a href="./email_settings.php">
            <i class="icon-envelope"></i> Configuración Email </a>
    </li>
</ul>
                            </li>';

    // CONTENIDO DEL SITIO
    $top_header_2 .=           '<li'.menu_li_class($site_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                                <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                    <i class="icon-globe"></i> Contenido Web </a>
                                <ul class="dropdown-menu dropdown-menu-fw">
                                    <li'.menu_li_class('home_edit.php').'>
                                        <a href="home_edit.php">
                                            <i class="icon-home"></i> Home </a>
                                    </li>
                                    <li'.menu_li_class('about_edit.php').'>
                                        <a href="about_edit.php">
                                            <i class="icon-info"></i> About </a>
                                    </li>
                                    <li'.menu_li_class('services_edit.php').'>
                                        <a href="services_edit.php">
                                            <i class="icon-grid"></i> Services </a>
                                    </li>
                                    <li'.menu_li_class('offers_header_edit.php').'>
                                        <a href="offers_header_edit.php">
                                            <i class="icon-heart"></i> Medical Services </a>
                                    </li>
                                    <li'.menu_li_class('offer_detail_edit.php').'>
                                        <a href="offer_detail_edit.php">
                                            <i class="icon-docs"></i> Offer Detail </a>
                                    </li>
                                    <li'.menu_li_class('wizard_header_edit.php').'>
                                        <a href="wizard_header_edit.php">
                                            <i class="icon-magic-wand"></i> Wizard Header </a>
                                    </li>
                                    <li'.menu_li_class('blog_edit.php').'>
                                        <a href="blog_edit.php">
                                            <i class="icon-speech"></i> Blog </a>
                                    </li>
                                </ul>
                            </li>';
} else {
    // Para usuarios NO admin, mostrar solo "Mi Perfil"
    $top_header_2 .=               '<li'.menu_li_class($profile_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                                        <a href="./mis_datos.php" class="text-uppercase">
                                            <i class="icon-user"></i> Mi Perfil </a>
                                    </li>';
}

$top_header_2 .=           '</ul>
                 </div>';

$footer =  '<p class="copyright"> '.date('Y').' &copy; GRODEV Dev By
            <a target="_blank" href="http://citofono_app.com/gro/">GRO</a> &nbsp;|&nbsp;
            <a href="#" title="Purchase Metronic just for 27$ and get lifetime updates for free" target="_blank">MedTravel!</a>
            </p>
            <a href="#index" class="go2top">
            <i class="icon-arrow-up"></i>
            </a>';

$sider_bar = '<a href="javascript:;" class="page-quick-sidebar-toggler">
                <i class="icon-login"></i>
             </a>
             <div class="page-quick-sidebar-wrapper" data-close-on-body-click="false">
                <div class="page-quick-sidebar">
                    <ul class="nav nav-tabs">
                        <li class="active">
                            <a href="javascript:;" data-target="#quick_sidebar_tab_1" data-toggle="tab"> Users
                                <span class="badge badge-danger">2</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:;" data-target="#quick_sidebar_tab_2" data-toggle="tab"> Alerts
                                <span class="badge badge-success">7</span>
                            </a>
                        </li>
                        <li class="dropdown">
                            <a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown"> More
                                <i class="fa fa-angle-down"></i>
                            </a>
                            <ul class="dropdown-menu pull-right">
                                <li>
                                    <a href="javascript:;" data-target="#quick_sidebar_tab_3" data-toggle="tab">
                                        <i class="icon-bell"></i> Alerts </a>
                                </li>
                                <li>
                                    <a href="javascript:;" data-target="#quick_sidebar_tab_3" data-toggle="tab">
                                        <i class="icon-info"></i> Notifications </a>
                                </li>
                                <li>
                                    <a href="javascript:;" data-target="#quick_sidebar_tab_3" data-toggle="tab">
                                        <i class="icon-speech"></i> Activities </a>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <a href="javascript:;" data-target="#quick_sidebar_tab_3" data-toggle="tab">
                                        <i class="icon-settings"></i> Settings </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active page-quick-sidebar-chat" id="quick_sidebar_tab_1">
                            <div class="page-quick-sidebar-chat-users" data-rail-color="#ddd" data-wrapper-class="page-quick-sidebar-list">
                                <h3 class="list-heading">Staff</h3>
                                <ul class="media-list list-items">
                                    <li class="media">
                                        <div class="media-status">
                                            <span class="badge badge-success">8</span>
                                        </div>
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar3.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Bob Nilson</h4>
                                            <div class="media-heading-sub"> Project Manager </div>
                                        </div>
                                    </li>
                                    <li class="media">
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar1.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Nick Larson</h4>
                                            <div class="media-heading-sub"> Art Director </div>
                                        </div>
                                    </li>
                                    <li class="media">
                                        <div class="media-status">
                                            <span class="badge badge-danger">3</span>
                                        </div>
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar4.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Deon Hubert</h4>
                                            <div class="media-heading-sub"> CTO </div>
                                        </div>
                                    </li>
                                    <li class="media">
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar2.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Ella Wong</h4>
                                            <div class="media-heading-sub"> CEO </div>
                                        </div>
                                    </li>
                                </ul>
                                <h3 class="list-heading">Customers</h3>
                                <ul class="media-list list-items">
                                    <li class="media">
                                        <div class="media-status">
                                            <span class="badge badge-warning">2</span>
                                        </div>
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar6.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Lara Kunis</h4>
                                            <div class="media-heading-sub"> CEO, Loop Inc </div>
                                            <div class="media-heading-small"> Last seen 03:10 AM </div>
                                        </div>
                                    </li>
                                    <li class="media">
                                        <div class="media-status">
                                            <span class="label label-sm label-success">new</span>
                                        </div>
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar7.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Ernie Kyllonen</h4>
                                            <div class="media-heading-sub"> Project Manager,
                                                <br> SmartBizz PTL </div>
                                        </div>
                                    </li>
                                    <li class="media">
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar8.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Lisa Stone</h4>
                                            <div class="media-heading-sub"> CTO, Keort Inc </div>
                                            <div class="media-heading-small"> Last seen 13:10 PM </div>
                                        </div>
                                    </li>
                                    <li class="media">
                                        <div class="media-status">
                                            <span class="badge badge-success">7</span>
                                        </div>
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar9.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Deon Portalatin</h4>
                                            <div class="media-heading-sub"> CFO, H&D LTD </div>
                                        </div>
                                    </li>
                                    <li class="media">
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar10.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Irina Savikova</h4>
                                            <div class="media-heading-sub"> CEO, Tizda Motors Inc </div>
                                        </div>
                                    </li>
                                    <li class="media">
                                        <div class="media-status">
                                            <span class="badge badge-danger">4</span>
                                        </div>
                                        <img class="media-object" src="../../assets/layouts/layout/img/avatar11.jpg" alt="...">
                                        <div class="media-body">
                                            <h4 class="media-heading">Maria Gomez</h4>
                                            <div class="media-heading-sub"> Manager, Infomatic Inc </div>
                                            <div class="media-heading-small"> Last seen 03:10 AM </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div class="page-quick-sidebar-item">
                                <div class="page-quick-sidebar-chat-user">
                                    <div class="page-quick-sidebar-nav">
                                        <a href="javascript:;" class="page-quick-sidebar-back-to-list">
                                            <i class="icon-arrow-left"></i>Back</a>
                                    </div>
                                    <div class="page-quick-sidebar-chat-user-messages">
                                        <div class="post out">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar3.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Bob Nilson</a>
                                                <span class="datetime">20:15</span>
                                                <span class="body"> When could you send me the report ? </span>
                                            </div>
                                        </div>
                                        <div class="post in">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar2.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Ella Wong</a>
                                                <span class="datetime">20:15</span>
                                                <span class="body"> Its almost done. I will be sending it shortly </span>
                                            </div>
                                        </div>
                                        <div class="post out">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar3.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Bob Nilson</a>
                                                <span class="datetime">20:15</span>
                                                <span class="body"> Alright. Thanks! :) </span>
                                            </div>
                                        </div>
                                        <div class="post in">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar2.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Ella Wong</a>
                                                <span class="datetime">20:16</span>
                                                <span class="body"> You are most welcome. Sorry for the delay. </span>
                                            </div>
                                        </div>
                                        <div class="post out">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar3.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Bob Nilson</a>
                                                <span class="datetime">20:17</span>
                                                <span class="body"> No probs. Just take your time :) </span>
                                            </div>
                                        </div>
                                        <div class="post in">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar2.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Ella Wong</a>
                                                <span class="datetime">20:40</span>
                                                <span class="body"> Alright. I just emailed it to you. </span>
                                            </div>
                                        </div>
                                        <div class="post out">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar3.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Bob Nilson</a>
                                                <span class="datetime">20:17</span>
                                                <span class="body"> Great! Thanks. Will check it right away. </span>
                                            </div>
                                        </div>
                                        <div class="post in">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar2.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Ella Wong</a>
                                                <span class="datetime">20:40</span>
                                                <span class="body"> Please let me know if you have any comment. </span>
                                            </div>
                                        </div>
                                        <div class="post out">
                                            <img class="avatar" alt="" src="../../assets/layouts/layout/img/avatar3.jpg" />
                                            <div class="message">
                                                <span class="arrow"></span>
                                                <a href="javascript:;" class="name">Bob Nilson</a>
                                                <span class="datetime">20:17</span>
                                                <span class="body"> Sure. I will check and buzz you if anything needs to be corrected. </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="page-quick-sidebar-chat-user-form">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Type a message here...">
                                            <div class="input-group-btn">
                                                <button type="button" class="btn green">
                                                    <i class="icon-paper-clip"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane page-quick-sidebar-alerts" id="quick_sidebar_tab_2">
                            <div class="page-quick-sidebar-alerts-list">
                                <h3 class="list-heading">General</h3>
                                <ul class="feeds list-items">
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-info">
                                                        <i class="fa fa-check"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> You have 4 pending tasks.
                                                        <span class="label label-sm label-warning "> Take action
                                                            <i class="fa fa-share"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> Just now </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a href="javascript:;">
                                            <div class="col1">
                                                <div class="cont">
                                                    <div class="cont-col1">
                                                        <div class="label label-sm label-success">
                                                            <i class="fa fa-bar-chart-o"></i>
                                                        </div>
                                                    </div>
                                                    <div class="cont-col2">
                                                        <div class="desc"> Finance Report for year 2013 has been released. </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col2">
                                                <div class="date"> 20 mins </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-danger">
                                                        <i class="fa fa-user"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> You have 5 pending membership that requires a quick review. </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> 24 mins </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-info">
                                                        <i class="fa fa-shopping-cart"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> New order received with
                                                        <span class="label label-sm label-success"> Reference Number: DR23923 </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> 30 mins </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-success">
                                                        <i class="fa fa-user"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> You have 5 pending membership that requires a quick review. </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> 24 mins </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-info">
                                                        <i class="fa fa-bell-o"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> Web server hardware needs to be upgraded.
                                                        <span class="label label-sm label-warning"> Overdue </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> 2 hours </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a href="javascript:;">
                                            <div class="col1">
                                                <div class="cont">
                                                    <div class="cont-col1">
                                                        <div class="label label-sm label-default">
                                                            <i class="fa fa-briefcase"></i>
                                                        </div>
                                                    </div>
                                                    <div class="cont-col2">
                                                        <div class="desc"> IPO Report for year 2013 has been released. </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col2">
                                                <div class="date"> 20 mins </div>
                                            </div>
                                        </a>
                                    </li>
                                </ul>
                                <h3 class="list-heading">System</h3>
                                <ul class="feeds list-items">
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-info">
                                                        <i class="fa fa-check"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> You have 4 pending tasks.
                                                        <span class="label label-sm label-warning "> Take action
                                                            <i class="fa fa-share"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> Just now </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a href="javascript:;">
                                            <div class="col1">
                                                <div class="cont">
                                                    <div class="cont-col1">
                                                        <div class="label label-sm label-danger">
                                                            <i class="fa fa-bar-chart-o"></i>
                                                        </div>
                                                    </div>
                                                    <div class="cont-col2">
                                                        <div class="desc"> Finance Report for year 2013 has been released. </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col2">
                                                <div class="date"> 20 mins </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-default">
                                                        <i class="fa fa-user"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> You have 5 pending membership that requires a quick review. </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> 24 mins </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-info">
                                                        <i class="fa fa-shopping-cart"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> New order received with
                                                        <span class="label label-sm label-success"> Reference Number: DR23923 </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> 30 mins </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-success">
                                                        <i class="fa fa-user"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> You have 5 pending membership that requires a quick review. </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> 24 mins </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="col1">
                                            <div class="cont">
                                                <div class="cont-col1">
                                                    <div class="label label-sm label-warning">
                                                        <i class="fa fa-bell-o"></i>
                                                    </div>
                                                </div>
                                                <div class="cont-col2">
                                                    <div class="desc"> Web server hardware needs to be upgraded.
                                                        <span class="label label-sm label-default "> Overdue </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col2">
                                            <div class="date"> 2 hours </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a href="javascript:;">
                                            <div class="col1">
                                                <div class="cont">
                                                    <div class="cont-col1">
                                                        <div class="label label-sm label-info">
                                                            <i class="fa fa-briefcase"></i>
                                                        </div>
                                                    </div>
                                                    <div class="cont-col2">
                                                        <div class="desc"> IPO Report for year 2013 has been released. </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col2">
                                                <div class="date"> 20 mins </div>
                                            </div>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="tab-pane page-quick-sidebar-settings" id="quick_sidebar_tab_3">
                            <div class="page-quick-sidebar-settings-list">
                                <h3 class="list-heading">General Settings</h3>
                                <ul class="list-items borderless">
                                    <li> Enable Notifications
                                        <input type="checkbox" class="make-switch" checked data-size="small" data-on-color="success" data-on-text="ON" data-off-color="default" data-off-text="OFF"> </li>
                                    <li> Allow Tracking
                                        <input type="checkbox" class="make-switch" data-size="small" data-on-color="info" data-on-text="ON" data-off-color="default" data-off-text="OFF"> </li>
                                    <li> Log Errors
                                        <input type="checkbox" class="make-switch" checked data-size="small" data-on-color="danger" data-on-text="ON" data-off-color="default" data-off-text="OFF"> </li>
                                    <li> Auto Sumbit Issues
                                        <input type="checkbox" class="make-switch" data-size="small" data-on-color="warning" data-on-text="ON" data-off-color="default" data-off-text="OFF"> </li>
                                    <li> Enable SMS Alerts
                                        <input type="checkbox" class="make-switch" checked data-size="small" data-on-color="success" data-on-text="ON" data-off-color="default" data-off-text="OFF"> </li>
                                </ul>
                                <h3 class="list-heading">System Settings</h3>
                                <ul class="list-items borderless">
                                    <li> Security Level
                                        <select class="form-control input-inline input-sm input-small">
                                            <option value="1">Normal</option>
                                            <option value="2" selected>Medium</option>
                                            <option value="e">High</option>
                                        </select>
                                    </li>
                                    <li> Failed Email Attempts
                                        <input class="form-control input-inline input-sm input-small" value="5" /> </li>
                                    <li> Secondary SMTP Port
                                        <input class="form-control input-inline input-sm input-small" value="3560" /> </li>
                                    <li> Notify On System Error
                                        <input type="checkbox" class="make-switch" checked data-size="small" data-on-color="danger" data-on-text="ON" data-off-color="default" data-off-text="OFF"> </li>
                                    <li> Notify On SMTP Error
                                        <input type="checkbox" class="make-switch" checked data-size="small" data-on-color="warning" data-on-text="ON" data-off-color="default" data-off-text="OFF"> </li>
                                </ul>
                                <div class="inner-content">
                                    <button class="btn btn-success">
                                        <i class="icon-settings"></i> Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
             </div>';

/*
 * BLOQUE DINÁMICO DE SERVICIOS (COMENTADO)
 * --------------------------------------------------
 * Código listo para activar: genera una sección "Servicios"
 * en la barra lateral consultando `service_catalog`.
 * Reglas:
 *  - Admins ven todos los servicios activos.
 *  - Prestadores ven todos los servicios activos por defecto;
 *    si existe una tabla `provider_services` puede filtrarse por provider_id.
 * Seguridad: usa prepared statements, verifica $conn y escapa con htmlspecialchars().
 */
/*
if (isset($conn) && $conn) {
    $sql = "SELECT id, name FROM service_catalog WHERE is_active = 1 ORDER BY name";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $has = false;
            $services_html = '<div class="sidebar-section"><h4>Servicios</h4><ul class="nav nav-pills nav-stacked">';
            while ($row = $res->fetch_assoc()) {
                $has = true;
                $sid = intval($row['id']);
                $sname = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                $services_html .= '<li><a href="service_catalog.php?service_id=' . $sid . '">' . $sname . '</a></li>';
            }
            $services_html .= '</ul></div>';
            if ($has) {
                $sider_bar .= $services_html;
            }
        }
        $stmt->close();
    }
}

// Si tiene sentido filtrar por prestador y existe la tabla provider_services,
// reemplazar la consulta por algo como:
// SELECT sc.id, sc.name FROM service_catalog sc
// JOIN provider_services ps ON ps.service_id = sc.id
// WHERE sc.is_active = 1 AND ps.provider_id = ? ORDER BY sc.name
*/

/*
 * RECOMENDACIÓN DE ESQUEMA (opcional)
 * - Crear tabla relacional para filtrar servicios por prestador:
 *
 *   CREATE TABLE provider_services (
 *     provider_id INT NOT NULL,
 *     service_id INT NOT NULL,
 *     PRIMARY KEY (provider_id, service_id),
 *     INDEX idx_service_id (service_id),
 *     INDEX idx_provider_id (provider_id)
 *   );
 *
 * - Índices recomendados:
 *   - service_catalog(is_active)
 *   - provider_services(provider_id, service_id)
 *
 * Estos índices aceleran la generación del menú dinámico y las consultas de catálogo.
 */
?>
