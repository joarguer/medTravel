<?php  
require_once __DIR__ . '/session_security.php';
medtravel_session_start();
include("valida_session.php");
include_once("conexion.php");
require_once __DIR__ . '/../../inc/realtime.php';
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
require_once __DIR__ . '/data_deletion_service.php';
$es_admin = is_role_admin_session();
if (isset($_SESSION['provider_id']) && !empty($_SESSION['provider_id'])) {
    $es_prestador = true;
}
$es_complementario = is_complementary_user_session();
$session_role_id = current_role_id();
$es_cliente = (
    !$es_admin
    && !$es_prestador
    && !$es_complementario
    && $session_role_id !== null
    && intval($session_role_id) === ROLE_CLIENT
);

$can_view_my_bookings = (
    !$es_admin
    && user_can(PERM_BOOKING_VIEW)
    && (
        ($es_prestador && !empty($_SESSION['provider_id']))
        || ($es_complementario && current_service_provider_id() > 0)
    )
);

// Contadores y notificaciones de booking pending
$booking_pending_count = 0;
$booking_notifications = [];
$booking_badge = '0';
$booking_summary_text = 'No unread messages';
$booking_list_html = '';
$booking_notifications_href = 'app_inbox.php';
if ($es_cliente) {
    $booking_notifications_href = '../client/app_inbox.php';
} elseif (!$es_admin && (!empty($_SESSION['provider_id']) || !empty($_SESSION['service_provider_id']))) {
    $booking_notifications_href = 'app_inbox.php';
}
$deletion_count = 0;
$deletion_list_html = '';
if (isset($conexion)) {
    $provider_id = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
    $service_provider_id = isset($_SESSION['service_provider_id']) ? intval($_SESSION['service_provider_id']) : 0;
    $client_user_id = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;
    $is_provider_scope = (!$es_admin && ($provider_id > 0 || $service_provider_id > 0) && user_can(PERM_BOOKING_VIEW));

    $booking_has_soft_delete = false;
    $booking_soft_delete_check = mysqli_query($conexion, "SHOW COLUMNS FROM booking_requests LIKE 'is_deleted'");
    if ($booking_soft_delete_check && mysqli_num_rows($booking_soft_delete_check) > 0) {
        $booking_has_soft_delete = true;
    }

    if ($es_cliente && $client_user_id > 0) {
        $client_count_sql = "SELECT COUNT(*) AS total FROM booking_requests br WHERE br.client_user_id = ?";
        if ($booking_has_soft_delete) {
            $client_count_sql .= " AND br.is_deleted = 0";
        }
        $stmt_client_count = mysqli_prepare($conexion, $client_count_sql);
        if ($stmt_client_count) {
            mysqli_stmt_bind_param($stmt_client_count, 'i', $client_user_id);
            if (mysqli_stmt_execute($stmt_client_count)) {
                mysqli_stmt_bind_result($stmt_client_count, $client_total);
                if (mysqli_stmt_fetch($stmt_client_count)) {
                    $booking_pending_count = intval($client_total);
                }
            }
            mysqli_stmt_close($stmt_client_count);
        }

        $client_list_sql = "SELECT br.id, br.destination, br.status, br.created_at
                            FROM booking_requests br
                            WHERE br.client_user_id = ?";
        if ($booking_has_soft_delete) {
            $client_list_sql .= " AND br.is_deleted = 0";
        }
        $client_list_sql .= " ORDER BY br.created_at DESC LIMIT 5";
        $stmt_client_list = mysqli_prepare($conexion, $client_list_sql);
        if ($stmt_client_list) {
            mysqli_stmt_bind_param($stmt_client_list, 'i', $client_user_id);
            if (mysqli_stmt_execute($stmt_client_list)) {
                mysqli_stmt_bind_result($stmt_client_list, $booking_id_raw, $destination_raw, $status_raw, $created_at_raw);
                while (mysqli_stmt_fetch($stmt_client_list)) {
                    $booking_notifications[] = [
                        'name' => 'Request #' . intval($booking_id_raw),
                        'destination' => (string)$destination_raw,
                        'created_at' => (string)$created_at_raw,
                        'item_name' => 'Status: ' . ((string)$status_raw !== '' ? (string)$status_raw : 'pending'),
                        'item_status' => (string)$status_raw,
                    ];
                }
            }
            mysqli_stmt_close($stmt_client_list);
        }
    } elseif ($is_provider_scope) {
        $items_table_exists = false;
        $items_table_check = mysqli_query($conexion, "SHOW TABLES LIKE 'booking_request_items'");
        if ($items_table_check && mysqli_num_rows($items_table_check) > 0) {
            $items_table_exists = true;
        }

        if ($items_table_exists) {
            $items_has_soft_delete = false;
            $items_soft_delete_check = mysqli_query($conexion, "SHOW COLUMNS FROM booking_request_items LIKE 'is_deleted'");
            if ($items_soft_delete_check && mysqli_num_rows($items_soft_delete_check) > 0) {
                $items_has_soft_delete = true;
            }

            $items_has_provider_id = false;
            $items_provider_id_check = mysqli_query($conexion, "SHOW COLUMNS FROM booking_request_items LIKE 'provider_id'");
            if ($items_provider_id_check && mysqli_num_rows($items_provider_id_check) > 0) {
                $items_has_provider_id = true;
            }

            $items_has_service_provider_id = false;
            $items_service_provider_id_check = mysqli_query($conexion, "SHOW COLUMNS FROM booking_request_items LIKE 'service_provider_id'");
            if ($items_service_provider_id_check && mysqli_num_rows($items_service_provider_id_check) > 0) {
                $items_has_service_provider_id = true;
            }

            $items_has_status = false;
            $items_status_check = mysqli_query($conexion, "SHOW COLUMNS FROM booking_request_items LIKE 'item_status'");
            if ($items_status_check && mysqli_num_rows($items_status_check) > 0) {
                $items_has_status = true;
            }
            $item_status_expr = $items_has_status
                ? "CASE WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin','pending_review') THEN 'pending_provider' ELSE bri.item_status END"
                : "'pending_provider'";

            // Scope estricto por ownership (como en admin/ajax/my_booking_requests.php).
            $scope_where = '';
            $scope_types = '';
            $scope_values = [];
            $is_complementary_scope = $es_complementario && $service_provider_id > 0;
            if ($is_complementary_scope) {
                if ($items_has_service_provider_id) {
                    $scope_where = " AND bri.service_provider_id = ? AND bri.item_type = 'complementary_service'";
                    $scope_types = 'i';
                    $scope_values = [$service_provider_id];
                } else {
                    $scope_where = ' AND 1=0';
                }
            } elseif ($provider_id > 0) {
                if ($items_has_provider_id) {
                    $scope_where = " AND bri.provider_id = ? AND bri.item_type = 'medical_offer'";
                    $scope_types = 'i';
                    $scope_values = [$provider_id];
                } else {
                    $scope_where = ' AND 1=0';
                }
            }

            $count_sql = "SELECT COUNT(*) AS total
                          FROM booking_request_items bri
                          INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                          WHERE 1=1";
            if ($items_has_soft_delete) {
                $count_sql .= " AND bri.is_deleted = 0";
            }
            if ($booking_has_soft_delete) {
                $count_sql .= " AND br.is_deleted = 0";
            }
            $count_sql .= $scope_where;

            $stmt_count = mysqli_prepare($conexion, $count_sql);
            if ($stmt_count) {
                if ($scope_types !== '') {
                    $bind_types = $scope_types;
                    $bind_values = $scope_values;
                    $bind_params = [];
                    $bind_params[] = $stmt_count;
                    $bind_params[] = &$bind_types;
                    foreach ($bind_values as $k => $v) {
                        $bind_values[$k] = (int)$v;
                        $bind_params[] = &$bind_values[$k];
                    }
                    call_user_func_array('mysqli_stmt_bind_param', $bind_params);
                }
                if (mysqli_stmt_execute($stmt_count)) {
                    mysqli_stmt_bind_result($stmt_count, $total_count);
                    if (mysqli_stmt_fetch($stmt_count)) {
                        $booking_pending_count = intval($total_count);
                    }
                }
                mysqli_stmt_close($stmt_count);
            }

            $list_sql = "SELECT
                            bri.id AS item_id,
                            bri.item_type,
                            {$item_status_expr} AS item_status,
                            br.name,
                            br.destination,
                            br.created_at,
                            COALESCE(o.title, ms.service_name, CONCAT('Item #', bri.id)) AS item_name
                         FROM booking_request_items bri
                         INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                         LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                         LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                         WHERE 1=1";
            if ($items_has_soft_delete) {
                $list_sql .= " AND bri.is_deleted = 0";
            }
            if ($booking_has_soft_delete) {
                $list_sql .= " AND br.is_deleted = 0";
            }
            $list_sql .= $scope_where . " ORDER BY br.created_at DESC, bri.id DESC LIMIT 5";

            $stmt_list = mysqli_prepare($conexion, $list_sql);
            if ($stmt_list) {
                if ($scope_types !== '') {
                    $bind_types = $scope_types;
                    $bind_values = $scope_values;
                    $bind_params = [];
                    $bind_params[] = $stmt_list;
                    $bind_params[] = &$bind_types;
                    foreach ($bind_values as $k => $v) {
                        $bind_values[$k] = (int)$v;
                        $bind_params[] = &$bind_values[$k];
                    }
                    call_user_func_array('mysqli_stmt_bind_param', $bind_params);
                }
                if (mysqli_stmt_execute($stmt_list)) {
                    mysqli_stmt_bind_result(
                        $stmt_list,
                        $item_id,
                        $item_type,
                        $item_status,
                        $name_raw,
                        $destination_raw,
                        $created_at_raw,
                        $item_name_raw
                    );
                    while (mysqli_stmt_fetch($stmt_list)) {
                        $booking_notifications[] = [
                            'item_id' => $item_id,
                            'item_type' => $item_type,
                            'item_status' => $item_status,
                            'name' => $name_raw,
                            'destination' => $destination_raw,
                            'created_at' => $created_at_raw,
                            'item_name' => $item_name_raw,
                        ];
                    }
                }
                mysqli_stmt_close($stmt_list);
            }
        }
    } else {
        $notif_sql = "SELECT id, name, destination, created_at FROM booking_requests WHERE status = 'pending'";
        if ($booking_has_soft_delete) {
            $notif_sql .= " AND is_deleted = 0";
        }
        $notif_sql .= " ORDER BY id DESC LIMIT 5";
        $notif_res = mysqli_query($conexion, $notif_sql);
        if ($notif_res) {
            while ($row = mysqli_fetch_assoc($notif_res)) {
                $booking_notifications[] = $row;
            }
            $booking_pending_count = mysqli_num_rows($notif_res);
        }
    }

    $booking_badge = (string) $booking_pending_count;
    if ($es_cliente) {
        $booking_summary_text = $booking_pending_count > 0
            ? '<span class="bold">' . $booking_pending_count . '</span> unread message(s)'
            : 'No unread messages';
    } else {
        $booking_summary_text = $booking_pending_count > 0
            ? '<span class="bold">' . $booking_pending_count . '</span> unread message(s)'
            : 'No unread messages';
    }

    if ($booking_pending_count > 0) {
        foreach ($booking_notifications as $notif) {
            $name = htmlspecialchars($notif['name'] ?? 'Client', ENT_QUOTES, 'UTF-8');
            $dest = htmlspecialchars($notif['destination'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $created = htmlspecialchars($notif['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
            $item_name = htmlspecialchars($notif['item_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $item_status = htmlspecialchars($notif['item_status'] ?? '', ENT_QUOTES, 'UTF-8');
            $details_text = ($item_name !== '')
                ? ('Item: ' . $item_name . ' · ' . ($item_status !== '' ? $item_status : 'pending'))
                : ('New booking from ' . $name . ' (' . $dest . ')');

            $booking_list_html .= '<li>'
                . '<a href="' . $booking_notifications_href . '">'
                . '<span class="details">'
                . '<span class="label label-sm label-icon label-success md-skip">'
                . '<i class="fa fa-calendar"></i>'
                . '</span> ' . $details_text . '</span>';
            if ($created !== '') {
                $booking_list_html .= '<span class="time">' . $created . '</span>';
            }
            $booking_list_html .= '</a></li>';
        }
    } else {
        $booking_list_html = '<li><a href="' . $booking_notifications_href . '"><span class="details"><span class="label label-sm label-icon label-default md-skip"><i class="fa fa-info"></i></span>No unread messages</span></a></li>';
    }

    // Solicitudes de eliminación de datos (solo admin)
    if ($es_admin) {
        if (dd_table_exists($conexion, 'data_deletion_requests')) {
            $deletion_count = dd_count_open_requests($conexion);
            $recentDeletionRows = dd_fetch_recent_open_requests($conexion, 5);
            foreach ($recentDeletionRows as $entry) {
                $req = htmlspecialchars((string)($entry['request_id'] ?? ''), ENT_QUOTES, 'UTF-8');
                $status = htmlspecialchars((string)($entry['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8');
                $time = htmlspecialchars((string)($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                $deletion_list_html .= '<li>'
                    . '<a href="data_deletion_requests.php">'
                    . '<span class="details">'
                    . '<span class="label label-sm label-icon label-warning md-skip"><i class="fa fa-trash"></i></span>'
                    . $req . ' • ' . $status . '<br><small>' . $time . '</small>'
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
                        <link href="/assets/layouts/layout5/css/medtravel_ui_unified.css" rel="stylesheet" type="text/css" />
                        <!-- END THEME LAYOUT STYLES -->
                        <link rel="shortcut icon" href="favicon.ico" />
                        <link href="../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />';   

$realtime_base_url = defined('MT_REALTIME_BASE_URL') ? MT_REALTIME_BASE_URL : '';
$realtime_socket_path = defined('MT_REALTIME_SOCKET_PATH') ? MT_REALTIME_SOCKET_PATH : '';
$realtime_admin_token_url = '/admin/ajax/realtime_admin_token.php';

$theme_layout_script =  '<!-- BEGIN THEME LAYOUT SCRIPTS -->
                        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
                        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
                        <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
                        <script src="../../assets/global/scripts/app.min.js" type="text/javascript"></script>
                        <script src="../../assets/layouts/layout5/scripts/layout.min.js" type="text/javascript"></script>
                        <script src="../../assets/layouts/global/scripts/quick-sidebar.min.js" type="text/javascript"></script>
                        <script src="../assets/pages/scripts/ui-toastr.min.js" type="text/javascript"></script>
                        <!-- END THEME LAYOUT SCRIPTS -->
                        <script src="js/global_scripts.js" type="text/javascript"></script>
                        <script type="text/javascript">
                        window.MT_REALTIME = {
                            baseUrl: ' . json_encode($realtime_base_url) . ',
                            socketPath: ' . json_encode($realtime_socket_path) . ',
                            adminTokenUrl: ' . json_encode($realtime_admin_token_url) . ',
                            isAdmin: ' . json_encode($es_admin ? 1 : 0) . '
                        };
                        </script>
                        <script src="https://medtravel.com.co/realtime/socket.io/socket.io.js" type="text/javascript"></script>
                        <script src="js/header_notifications.js" type="text/javascript"></script>';

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
$notification_bar_id = $es_cliente ? 'header_notification_bar_client' : 'header_notification_bar';
$notification_icon = 'fa fa-bell';

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
                    <div class="btn-group-notification btn-group" id="'.$notification_bar_id.'" style="margin-right:10px;">
                        <button type="button" class="btn btn-sm md-skip dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <i class="'.$notification_icon.'"></i>
                            <span class="badge admin-notif-badge">'.$booking_badge.'</span>
                        </button>
                        <ul class="dropdown-menu-v2">
                            <li class="external">
                                <h3 id="admin-notification-summary">'.$booking_summary_text.'</h3>
                                <a id="admin-notification-view-all" href="'.$booking_notifications_href.'">view all</a>
                            </li>
                            <li>
                                <ul class="dropdown-menu-list scroller" id="admin-notification-list" style="height: 250px; padding: 0;" data-handle-color="#637283">
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
                    <div class="btn-group-img btn-group" style="margin-left:8px;">
                        <button type="button" class="btn btn-sm md-skip dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <span>Hi, '.$nombre_usuario.' ';
if ($es_admin) {
    $top_header .= '<span class="badge badge-danger">ADMIN</span>';
} elseif ($es_prestador || $es_complementario) {
    $top_header .= '<span class="badge badge-info">PRESTADOR</span>';
}
$top_header .= '</span>
                            <img src="admin/'.$avatar.'" alt="" id="avatar_header"> </button>
                        <ul class="dropdown-menu-v2" role="menu">';

if ($es_admin) {
    $top_header .= '      <li><a href="index.php"><i class="icon-home"></i> Dashboard</a></li>
                            <li><a href="booking_requests.php"><i class="icon-layers"></i> Gestión</a></li>
                            <li><a href="app_inbox.php"><i class="icon-envelope-open"></i> Inbox</a></li>
                            <li><a href="app_calendar.php"><i class="icon-clock"></i> Calendar</a></li>
                            <li><a href="usuarios.php"><i class="icon-users"></i> Users</a></li>
                            <li class="divider"> </li>
                            <li><a href="mis_datos.php"><i class="icon-user"></i> My Profile</a></li>';
} else {
    $top_header .= '      <li><a href="index.php"><i class="icon-home"></i> Dashboard</a></li>';
    if ($can_view_my_bookings) {
        $top_header .= '  <li><a href="my_booking_requests.php"><i class="icon-calendar"></i> My Requests</a></li>';
    }
    $top_header .= '      <li><a href="app_inbox.php"><i class="icon-envelope-open"></i> Inbox</a></li>
                            <li><a href="mis_datos.php"><i class="icon-user"></i> My Profile</a></li>';
}

$top_header .= '        <li class="divider"> </li>
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
$management_pages = array('service_categories.php','service_catalog.php','providers.php','providers_complementary.php','provider_offers.php','blog_edit.php','mi_empresa.php','clientes.php','provider_verification.php','paquetes.php','booking_requests.php','medtravel_services.php','my_booking_requests.php','app_inbox.php','app_calendar.php','testimonials.php');
$medical_group_pages = array('service_categories.php','service_catalog.php','providers.php','provider_verification.php','provider_offers.php','blog_edit.php','my_booking_requests.php','app_inbox.php','app_calendar.php');
$complementary_group_pages = array('providers_complementary.php','medtravel_services.php','paquetes.php','my_booking_requests.php','app_inbox.php','app_calendar.php');
$complementary_scope_pages = array('providers_complementary.php','medtravel_services.php','my_booking_requests.php','app_inbox.php','app_calendar.php');
$clients_booking_pages = array('clientes.php','booking_requests.php','app_inbox.php','app_calendar.php','testimonials.php');
$admin_section_pages = array('mis_datos.php','crear_usuario.php','usuarios.php','roles.php','email_settings.php','google_calendar_settings.php','data_deletion_requests.php','cleanup.php');
$admin_users_pages = array('mis_datos.php','usuarios.php','crear_usuario.php','roles.php');
$site_pages = array('home_edit.php','about_edit.php','services_edit.php','offers_header_edit.php','offer_detail_edit.php','booking_header_edit.php','contact_header_edit.php','blog_edit.php','wizard_header_edit.php');
$profile_pages = array('mis_datos.php');
$can_manage_complementary_providers = user_can(PERM_PROVIDERS_COMPLEMENTARY_MANAGE);
$can_manage_complementary_services = user_can(PERM_SERVICES_COMPLEMENTARY_MANAGE);
$can_manage_packages = user_can(PERM_PACKAGES_MANAGE);
$can_view_clients = (
    !$es_admin &&
    user_can(PERM_BOOKING_VIEW)
);

// ─── Grupos de páginas para la arquitectura de menú por dominios funcionales ──
$operacion_pages  = array('my_booking_requests.php', 'app_inbox.php', 'app_calendar.php', 'clientes.php');
$servicios_pages  = array('service_catalog.php', 'provider_offers.php');
$presencia_pages  = array('blog_edit.php');
$empresa_pages    = array('mi_empresa.php', 'staff_medico.php', 'staff_catalogs.php', 'mis_datos.php');

$top_header_2 = '<div class="nav-collapse collapse navbar-collapse navbar-responsive-collapse">
                    <ul class="nav navbar-nav">
                        <li'.menu_li_class($dashboard_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="index.php" class="text-uppercase">
                                <i class="icon-home"></i> Dashboard </a>
                        </li>';

if ($es_admin) {
    // ─── ADMIN: Gestión + Administración + Contenido Web ─────────────────────
    $top_header_2 .= '
                        <li'.menu_li_class($management_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                <i class="icon-layers"></i> Gestión </a>
                            <ul class="dropdown-menu dropdown-menu-fw">
                                <li'.menu_li_class($medical_group_pages, 'dropdown more-dropdown-sub').'>
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
                                </li>
                                <li'.menu_li_class($complementary_group_pages, 'dropdown more-dropdown-sub').'>
                                    <a href="javascript:;">
                                        <i class="icon-plane"></i> Servicios Complementarios </a>
                                    <ul class="dropdown-menu">
                                        '.($can_manage_complementary_providers ? '<li'.menu_li_class('providers_complementary.php').'>
                                            <a href="./providers_complementary.php">Proveedores Complementarios</a>
                                        </li>' : '').'
                                        '.($can_manage_complementary_services ? '<li'.menu_li_class('medtravel_services.php').'>
                                            <a href="./medtravel_services.php">MedTravel Services</a>
                                        </li>' : '').'
                                        '.($can_manage_packages ? '<li'.menu_li_class('paquetes.php').'>
                                            <a href="./paquetes.php">Paquetes de Viaje</a>
                                        </li>' : '').'
                                    </ul>
                                </li>
                                <li'.menu_li_class($clients_booking_pages, 'dropdown more-dropdown-sub').'>
                                    <a href="javascript:;">
                                        <i class="icon-users"></i> Clientes y Bookings </a>
                                    <ul class="dropdown-menu">
                                        <li'.menu_li_class('clientes.php').'>
                                            <a href="./clientes.php">Gestión de Clientes</a>
                                        </li>
                                        <li'.menu_li_class('booking_requests.php').'>
                                            <a href="./booking_requests.php">Solicitudes de Booking</a>
                                        </li>
                                        <li'.menu_li_class('testimonials.php').'>
                                            <a href="./testimonials.php">Testimonials</a>
                                        </li>
                                        <li'.menu_li_class('app_inbox.php').'>
                                            <a href="./app_inbox.php">Inbox Solicitudes</a>
                                        </li>
                                        <li'.menu_li_class('app_calendar.php').'>
                                            <a href="./app_calendar.php">Calendar Solicitudes</a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>';

    $top_header_2 .= '
                        <li'.menu_li_class($admin_section_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
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
                                <li'.menu_li_class('data_deletion_requests.php').'>
                                    <a href="./data_deletion_requests.php">
                                        <i class="icon-trash"></i> Solicitudes de eliminación </a>
                                </li>
                                <li'.menu_li_class('cleanup.php').'>
                                    <a href="./cleanup.php">
                                        <i class="fa fa-trash"></i> Limpieza (DEV) </a>
                                </li>
                                <li'.menu_li_class('email_settings.php').'>
                                    <a href="./email_settings.php">
                                        <i class="icon-envelope"></i> Configuración Email </a>
                                </li>
                                <li'.menu_li_class('google_calendar_settings.php').'>
                                    <a href="./google_calendar_settings.php">
                                        <i class="icon-social-google"></i> Google Calendar / Meet </a>
                                </li>
                            </ul>
                        </li>';

    $top_header_2 .= '
                        <li'.menu_li_class($site_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
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
                                <li'.menu_li_class('booking_header_edit.php').'>
                                    <a href="booking_header_edit.php">
                                        <i class="icon-calendar"></i> Booking </a>
                                </li>
                                <li'.menu_li_class('contact_header_edit.php').'>
                                    <a href="contact_header_edit.php">
                                        <i class="icon-envelope"></i> Contact </a>
                                </li>
                                <li'.menu_li_class('wizard_header_edit.php').'>
                                    <a href="wizard_header_edit.php">
                                        <i class="icon-magic-wand"></i> Booking Wizard </a>
                                </li>
                                <li'.menu_li_class('blog_edit.php').'>
                                    <a href="blog_edit.php">
                                        <i class="icon-speech"></i> Blog </a>
                                </li>
                            </ul>
                        </li>';

} elseif ($es_prestador) {
    // ═══════════════════════════════════════════════════════════════════════════
    // PRESTADOR/MÉDICO — menú por dominios funcionales
    // ═══════════════════════════════════════════════════════════════════════════

    // ── 1. OPERACIÓN: Solicitudes, Inbox, Agenda, Pacientes/Clientes ──────────
    if ($can_view_my_bookings || $can_view_clients) {
        $top_header_2 .= '
                        <li'.menu_li_class($operacion_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                <i class="icon-calendar"></i> Operación </a>
                            <ul class="dropdown-menu dropdown-menu-fw">';
        if ($can_view_my_bookings) {
            $top_header_2 .= '
                                <li'.menu_li_class('my_booking_requests.php').'>
                                    <a href="./my_booking_requests.php">
                                        <i class="icon-layers"></i> Mis Solicitudes </a>
                                </li>
                                <li'.menu_li_class('app_inbox.php').'>
                                    <a href="./app_inbox.php">
                                        <i class="icon-envelope-open"></i> Inbox </a>
                                </li>
                                <li'.menu_li_class('app_calendar.php').'>
                                    <a href="./app_calendar.php">
                                        <i class="icon-clock"></i> Agenda </a>
                                </li>';
        }
        if ($can_view_clients) {
            $top_header_2 .= '
                                <li'.menu_li_class('clientes.php').'>
                                    <a href="./clientes.php">
                                        <i class="icon-users"></i> Pacientes / Clientes </a>
                                </li>';
        }
        $top_header_2 .= '
                            </ul>
                        </li>';
    }

    // ── 2. SERVICIOS: Mis Servicios (catálogo habilitado) + Mis Ofertas ─────────
    $top_header_2 .= '
                        <li'.menu_li_class($servicios_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                <i class="fa fa-th-list"></i> Servicios </a>
                            <ul class="dropdown-menu dropdown-menu-fw">
                                <li'.menu_li_class('service_catalog.php').'>
                                    <a href="./service_catalog.php">
                                        <i class="fa fa-th-list"></i> Mis Servicios </a>
                                </li>
                                <li'.menu_li_class('provider_offers.php').'>
                                    <a href="./provider_offers.php">
                                        <i class="icon-docs"></i> Mis Ofertas </a>
                                </li>
                            </ul>
                        </li>';

    // ── 3. PRESENCIA: Mi Blog ─────────────────────────────────────────────────
    $top_header_2 .= '
                        <li'.menu_li_class($presencia_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                <i class="icon-speech"></i> Presencia </a>
                            <ul class="dropdown-menu dropdown-menu-fw">
                                <li'.menu_li_class('blog_edit.php').'>
                                    <a href="./blog_edit.php">
                                        <i class="icon-speech"></i> Mi Blog </a>
                                </li>
                            </ul>
                        </li>';

    // ── 4. MI EMPRESA / CONFIGURACIÓN: Datos empresa + Staff médico + Mi Perfil
    $top_header_2 .= '
                        <li'.menu_li_class($empresa_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                <i class="icon-briefcase"></i> Mi Empresa </a>
                            <ul class="dropdown-menu dropdown-menu-fw">
                                <li'.menu_li_class('mi_empresa.php').'>
                                    <a href="./mi_empresa.php">
                                        <i class="icon-briefcase"></i> Datos de Empresa </a>
                                </li>
                                <li'.menu_li_class('staff_medico.php').'>
                                    <a href="./staff_medico.php">
                                        <i class="fa fa-user-md"></i> Staff médico </a>
                                </li>
                                <li'.menu_li_class('staff_catalogs.php').'>
                                    <a href="./staff_catalogs.php">
                                        <i class="fa fa-tags"></i> Catálogos del staff </a>
                                </li>
                                <li'.menu_li_class('mis_datos.php').'>
                                    <a href="./mis_datos.php">
                                        <i class="icon-user"></i> Mi Perfil </a>
                                </li>
                            </ul>
                        </li>';

} else {
    // ─── COMPLEMENTARIO y otros no-admin ──────────────────────────────────────
    if ($can_manage_complementary_providers || $can_manage_complementary_services || $can_view_my_bookings) {
        $top_header_2 .= '
                        <li'.menu_li_class($complementary_scope_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                <i class="icon-plane"></i> Servicios Complementarios </a>
                            <ul class="dropdown-menu dropdown-menu-fw">
                                '.($can_manage_complementary_providers ? '<li'.menu_li_class('providers_complementary.php').'>
                                    <a href="./providers_complementary.php">Proveedores Complementarios</a>
                                </li>' : '').'
                                '.($can_manage_complementary_services ? '<li'.menu_li_class('medtravel_services.php').'>
                                    <a href="./medtravel_services.php">MedTravel Services</a>
                                </li>' : '').'
                                '.($can_view_my_bookings ? '<li'.menu_li_class('my_booking_requests.php').'>
                                    <a href="./my_booking_requests.php">Mis Solicitudes</a>
                                </li>
                                <li'.menu_li_class('app_inbox.php').'>
                                    <a href="./app_inbox.php">Inbox</a>
                                </li>
                                <li'.menu_li_class('app_calendar.php').'>
                                    <a href="./app_calendar.php">Calendar</a>
                                </li>' : '').'
                            </ul>
                        </li>';
    }
    if ($can_view_clients) {
        $top_header_2 .= '
                        <li'.menu_li_class('clientes.php').'>
                            <a href="./clientes.php">
                                <i class="icon-users"></i> Clientes </a>
                        </li>';
    }
    if ($es_complementario) {
        // Complementario: empresa + perfil agrupados
        $top_header_2 .= '
                        <li'.menu_li_class($empresa_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="javascript:;" class="text-uppercase dropdown-toggle" data-toggle="dropdown">
                                <i class="icon-briefcase"></i> Mi Empresa </a>
                            <ul class="dropdown-menu dropdown-menu-fw">
                                <li'.menu_li_class('mi_empresa.php').'>
                                    <a href="./mi_empresa.php">
                                        <i class="icon-briefcase"></i> Datos de Empresa </a>
                                </li>
                                <li'.menu_li_class('mis_datos.php').'>
                                    <a href="./mis_datos.php">
                                        <i class="icon-user"></i> Mi Perfil </a>
                                </li>
                            </ul>
                        </li>';
    } else {
        $top_header_2 .= '
                        <li'.menu_li_class($profile_pages, 'dropdown dropdown-fw dropdown-fw-disabled').'>
                            <a href="./mis_datos.php" class="text-uppercase">
                                <i class="icon-user"></i> Mi Perfil </a>
                        </li>';
    }
}

$top_header_2 .= '
                    </ul>
                 </div>';

$footer =  '<p class="copyright"> '.date('Y').' &copy; GRODEV Dev By
            <a target="_blank" href="http://citofono_app.com/gro/">GRO</a> &nbsp;|&nbsp;
            <a href="#" title="Purchase Metronic just for 27$ and get lifetime updates for free" target="_blank">MedTravel!</a>
            </p>
            <a href="#index" class="go2top">
            <i class="icon-arrow-up"></i>
            </a>';


// ── Quick sidebar: pre-cargar staff del prestador médico ─────────────────────
$_qs_staff_rows   = [];
$_qs_staff_active = 0;
$_qs_provider_id  = isset($provider_id) ? (int)$provider_id : 0;
if ($es_prestador && $_qs_provider_id > 0 && isset($conexion) && $conexion
    && function_exists('provider_staff_table_exists')
    && provider_staff_table_exists($conexion)
    && function_exists('provider_staff_select_columns')) {
    $_qs_cols  = implode(', ', provider_staff_select_columns($conexion, 'pms'));
    $_qs_sort  = function_exists('provider_staff_sort_column_name')
        ? provider_staff_sort_column_name($conexion) : '';
    $_qs_order = $_qs_sort !== '' ? "pms.`{$_qs_sort}` ASC, pms.full_name ASC" : 'pms.full_name ASC';
    $_qs_sql   = "SELECT {$_qs_cols} FROM provider_medical_staff pms
                  WHERE pms.provider_id = ? ORDER BY {$_qs_order} LIMIT 30";
    if ($_qs_stmt = mysqli_prepare($conexion, $_qs_sql)) {
        mysqli_stmt_bind_param($_qs_stmt, 'i', $_qs_provider_id);
        mysqli_stmt_execute($_qs_stmt);
        $_qs_res = mysqli_stmt_get_result($_qs_stmt);
        while ($_qs_row = mysqli_fetch_assoc($_qs_res)) {
            if (function_exists('provider_staff_normalize_row')) {
                $_qs_row = provider_staff_normalize_row($_qs_row);
            }
            $_qs_staff_rows[] = $_qs_row;
            if ((int)($_qs_row['is_active'] ?? 1) === 1) { $_qs_staff_active++; }
        }
        mysqli_stmt_close($_qs_stmt);
    }
}

// ── Generar HTML del tab Staff médico ────────────────────────────────────────
ob_start();
if ($es_prestador && $_qs_provider_id > 0) {
    if (empty($_qs_staff_rows)) {
        echo '<div style="padding:28px 18px; text-align:center; color:#888;">';
        echo '<i class="fa fa-user-md" style="font-size:32px; margin-bottom:12px; display:block; opacity:.4;"></i>';
        echo '<p style="font-size:13px; line-height:1.5; margin-bottom:16px;">';
        echo 'A&uacute;n no has registrado personal m&eacute;dico.<br>Agrega tu equipo cl&iacute;nico para organizar mejor la atenci&oacute;n.';
        echo '</p>';
        echo '<a href="staff_medico.php" class="btn btn-sm btn-primary btn-block">';
        echo '<i class="fa fa-plus"></i> Agregar personal</a>';
        echo '</div>';
    } else {
        foreach ($_qs_staff_rows as $_qs_m) {
            $__name    = htmlspecialchars((string)($_qs_m['full_name'] ?? ''), ENT_QUOTES);
            $__role    = htmlspecialchars((string)($_qs_m['role_title'] ?? ''), ENT_QUOTES);
            $__spec    = htmlspecialchars((string)($_qs_m['specialty'] ?? ''), ENT_QUOTES);
            $__active  = ((int)($_qs_m['is_active'] ?? 1) === 1);
            $__primary = ((int)($_qs_m['is_primary_doctor'] ?? 0) === 1);
            $__id      = (int)($_qs_m['id'] ?? 0);
            $__photo   = trim((string)($_qs_m['photo'] ?? ''));
            $__photo_src = '';
            if ($__photo !== '') {
                $_candidate = 'img/staff/' . $_qs_provider_id . '/' . $__photo;
                if (file_exists(dirname(__FILE__, 2) . '/' . $_candidate)) {
                    $__photo_src = '../' . $_candidate;
                }
            }
            echo '<li class="media" style="padding:10px 15px; border-bottom:1px solid #f0f0f0; margin:0;">';
            echo '<div style="display:flex; align-items:center; gap:10px;">';
            echo '<div style="flex-shrink:0; width:38px; height:38px; border-radius:50%; background:#e8e8e8; overflow:hidden; display:flex; align-items:center; justify-content:center;">';
            if ($__photo_src !== '') {
                echo '<img src="' . htmlspecialchars($__photo_src, ENT_QUOTES) . '" alt="' . $__name . '" style="width:38px; height:38px; object-fit:cover;" />';
            } else {
                echo '<i class="fa fa-user-md" style="color:#aaa; font-size:16px;"></i>';
            }
            echo '</div>';
            echo '<div style="flex:1; min-width:0;">';
            echo '<div style="font-size:13px; font-weight:600; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">';
            echo $__name;
            if ($__primary) {
                echo ' <span class="badge badge-primary" style="font-size:9px;">Principal</span>';
            }
            echo '</div>';
            if ($__role !== '') {
                echo '<div style="font-size:11px; color:#777; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . $__role . '</div>';
            }
            if ($__spec !== '') {
                echo '<div style="font-size:11px; color:#999; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . $__spec . '</div>';
            }
            echo '</div>';
            echo '<div style="flex-shrink:0; display:flex; flex-direction:column; align-items:flex-end; gap:3px;">';
            $__badge_class = $__active ? 'badge-success' : 'badge-default';
            $__badge_label = $__active ? 'Activo' : 'Inactivo';
            echo '<span class="badge ' . $__badge_class . '" style="font-size:9px;">' . $__badge_label . '</span>';
            echo '<a href="staff_medico.php?edit=' . $__id . '" title="Editar ' . $__name . '" style="font-size:10px; color:#888;">';
            echo '<i class="fa fa-pencil"></i></a>';
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }
        echo '<li style="padding:12px 15px; background:#f8f8f8; border-top:1px solid #e5e5e5;">';
        echo '<div style="display:flex; gap:6px;">';
        echo '<a href="staff_medico.php?action=new" class="btn btn-xs btn-primary" style="flex:1; text-align:center;"><i class="fa fa-plus"></i> Agregar</a>';
        echo '<a href="staff_medico.php" class="btn btn-xs btn-default" style="flex:1; text-align:center;"><i class="fa fa-th-list"></i> Ver todo</a>';
        echo '</div></li>';
    }
} else {
    echo '<div style="padding:20px 15px; color:#aaa; font-size:12px; text-align:center;">';
    echo '<i class="fa fa-user-md" style="font-size:28px; display:block; margin-bottom:8px; opacity:.3;"></i>';
    echo 'Este panel solo est&aacute; disponible para prestadores m&eacute;dicos.';
    echo '</div>';
}
$_qs_staff_html  = ob_get_clean();
$_qs_staff_badge = $_qs_staff_active > 0
    ? ' <span class="badge badge-success" style="font-size:9px;">' . $_qs_staff_active . '</span>'
    : '';

// ── Construir $sider_bar ──────────────────────────────────────────────────────
ob_start();
echo '<a href="javascript:;" class="page-quick-sidebar-toggler">';
echo '<i class="icon-login"></i>';
echo '</a>';
echo '<div class="page-quick-sidebar-wrapper" data-close-on-body-click="false">';
echo '<div class="page-quick-sidebar">';
echo '<ul class="nav nav-tabs">';
echo '<li class="active"><a href="javascript:;" data-target="#quick_sidebar_tab_1" data-toggle="tab">Staff' . $_qs_staff_badge . '</a></li>';
echo '<li><a href="javascript:;" data-target="#quick_sidebar_tab_2" data-toggle="tab">Alertas</a></li>';
echo '</ul>';
echo '<div class="tab-content">';
echo '<div class="tab-pane active page-quick-sidebar-chat" id="quick_sidebar_tab_1">';
echo '<div class="page-quick-sidebar-chat-users" data-rail-color="#ddd" data-wrapper-class="page-quick-sidebar-list">';
echo '<h3 class="list-heading">Staff m&eacute;dico</h3>';
echo '<ul class="media-list list-items" id="qs-staff-list">';
echo $_qs_staff_html;
echo '</ul>';
echo '</div>';
echo '</div>';
echo '<div class="tab-pane page-quick-sidebar-alerts" id="quick_sidebar_tab_2">';
echo '<div class="page-quick-sidebar-alerts-list" style="padding:20px 15px; color:#aaa; font-size:12px; text-align:center;">';
echo '<i class="fa fa-bell-o" style="font-size:28px; display:block; margin-bottom:8px; opacity:.3;"></i>';
echo 'Sin alertas activas.';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';
$sider_bar = ob_get_clean();


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
