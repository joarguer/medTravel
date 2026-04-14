<?php
require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth();

include_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/client_notifications.php';

$title = 'MedTravel Client Portal';

function client_current_script()
{
    static $current = null;
    if ($current !== null) {
        return $current;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $current = basename((string)$path);

    if ($current === '' || $current === '.' || $current === '/') {
        $fallback = parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH);
        $current = basename((string)$fallback);
    }

    if ($current === '' || $current === '.' || $current === '/') {
        $current = 'index.php';
    }

    return $current;
}

$currentScript = client_current_script();
$clientUserId = get_client_user_id();

// ── Terms gate: redirect to acceptance page if client has not yet accepted ────
// Skips: terms_gate.php itself (avoids infinite loop), AJAX endpoints.
if ($currentScript !== 'terms_gate.php') {
    if (!isset($_SESSION['client_terms_accepted'])) {
        // Lazy DB check — done once per session and then cached in session.
        $_SESSION['client_terms_accepted'] = 1; // safe default if column missing
        if (isset($conexion) && $conexion) {
            $_tcCheck = mysqli_query($conexion, "SHOW COLUMNS FROM `usuarios` LIKE 'terms_accepted'");
            if ($_tcCheck && mysqli_num_rows($_tcCheck) > 0 && $clientUserId > 0) {
                $_tcStmt = mysqli_prepare($conexion, "SELECT terms_accepted FROM usuarios WHERE id = ? LIMIT 1");
                if ($_tcStmt) {
                    mysqli_stmt_bind_param($_tcStmt, 'i', $clientUserId);
                    if (mysqli_stmt_execute($_tcStmt)) {
                        $_tcRes = mysqli_stmt_get_result($_tcStmt);
                        $_tcRow = $_tcRes ? mysqli_fetch_assoc($_tcRes) : null;
                        $_SESSION['client_terms_accepted'] = (int)($_tcRow['terms_accepted'] ?? 1);
                    }
                    mysqli_stmt_close($_tcStmt);
                }
            }
            unset($_tcCheck, $_tcStmt, $_tcRes, $_tcRow);
        }
    }
    if ((int)($_SESSION['client_terms_accepted'] ?? 1) !== 1) {
        header('Location: /client/terms_gate.php');
        exit;
    }
}
$clientName = htmlspecialchars((string)($_SESSION['nombre_usuario'] ?? 'Client'), ENT_QUOTES, 'UTF-8');
$avatarRaw = trim((string)($_SESSION['avatar'] ?? ''));
$avatarUrl = '/assets/layouts/layout/img/avatar3.jpg';
if ($avatarRaw !== '') {
    $avatarCandidate = '/admin/' . ltrim($avatarRaw, '/');
    $avatarPath = __DIR__ . '/../../admin/' . ltrim($avatarRaw, '/');
    if (is_file($avatarPath) && !preg_match('#/default\.(png|jpe?g|gif|webp)$#i', $avatarCandidate)) {
        $avatarUrl = $avatarCandidate;
    }
}

$notifPayload = ['count' => 0, 'items' => []];
if (isset($conexion) && $conexion) {
    $notifPayload = client_fetch_notifications($conexion, $clientUserId, 8);
}
$notifCount = (int)($notifPayload['count'] ?? 0);
$notifBadge = (string)$notifCount;
$notifSummary = ($notifCount > 0)
    ? '<span class="bold">' . $notifCount . '</span> unread message(s)'
    : 'No unread messages';

$notifListHtml = '';
if (!empty($notifPayload['items'])) {
    foreach ($notifPayload['items'] as $item) {
        $titleText = htmlspecialchars((string)($item['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8');
        $subtitleText = htmlspecialchars((string)($item['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8');
        $timeText = htmlspecialchars((string)($item['time'] ?? ''), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars((string)($item['url'] ?? '/client/my_requests.php'), ENT_QUOTES, 'UTF-8');
        $details = $titleText;
        if ($subtitleText !== '') {
            $details .= '<br><small>' . $subtitleText . '</small>';
        }
        $notifListHtml .= '<li><a href="' . $url . '"><span class="details"><span class="label label-sm label-icon label-info md-skip"><i class="fa fa-bell"></i></span> ' . $details . '</span>'
            . ($timeText !== '' ? '<span class="time">' . $timeText . '</span>' : '')
            . '</a></li>';
    }
} else {
    $notifListHtml = '<li><a href="/client/app_inbox.php"><span class="details"><span class="label label-sm label-icon label-default md-skip"><i class="fa fa-info"></i></span>No unread messages</span></a></li>';
}

function client_menu_li_class($script, $extraClass = '')
{
    $scripts = is_array($script) ? $script : [$script];
    $current = client_current_script();
    $isActive = in_array($current, $scripts, true);
    $classes = [];
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    if ($isActive) {
        $classes[] = 'active';
        $classes[] = 'selected';
        $classes[] = 'open';
    }
    return empty($classes) ? '' : ' class="' . implode(' ', $classes) . '"';
}

$global_first_style = '<meta content="width=device-width, initial-scale=1" name="viewport" />
                        <link href="//fonts.googleapis.com/css?family=Oswald:400,300,700" rel="stylesheet" type="text/css" />
                        <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
                        <link href="/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
                        <link href="/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
                        <link href="/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
                        <link href="/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />';

$theme_global_style = '<link href="/assets/global/css/components-md.min.css" rel="stylesheet" id="style_components" type="text/css" />
                       <link href="/assets/global/css/plugins-md.min.css" rel="stylesheet" type="text/css" />';

$theme_layout_style = '<link href="/assets/layouts/layout5/css/layout.min.css" rel="stylesheet" type="text/css" />
                       <link href="/assets/layouts/layout5/css/custom.min.css" rel="stylesheet" type="text/css" />
                       <link href="/assets/layouts/layout5/css/medtravel_ui_unified.css" rel="stylesheet" type="text/css" />
                       <link href="/assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />
                       <link rel="shortcut icon" href="/favicon.ico" />';

$top_header = '<div class="clearfix navbar-fixed-top">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-responsive-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="toggle-icon">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </span>
                </button>
                <a id="index" class="page-logo" href="/client/index.php">
                    <img src="/img/site/logo-navbar.png" srcset="/img/site/logo-navbar-small.png 239w, /img/site/logo-navbar.png 500w" sizes="(max-width: 991.98px) 180px, 220px" alt="MedTravel" style="max-width:220px;width:100%;height:auto;">
                </a>
                <div class="topbar-actions">
                    <div class="btn-group-notification btn-group" id="header_notification_bar_client" style="margin-right:10px;">
                        <button type="button" class="btn btn-sm md-skip dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <i class="fa fa-bell"></i>
                            <span class="badge client-notif-badge">' . $notifBadge . '</span>
                        </button>
                        <ul class="dropdown-menu-v2">
                            <li class="external">
                                <h3 id="client-notification-summary">' . $notifSummary . '</h3>
                                <a href="/client/app_inbox.php">view all</a>
                            </li>
                            <li>
                                <ul class="dropdown-menu-list scroller" id="client-notification-list" style="height: 250px; padding: 0;" data-handle-color="#637283">
                                    ' . $notifListHtml . '
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="btn-group-img btn-group" style="margin-left:8px;">
                        <button type="button" class="btn btn-sm md-skip dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <span>Hi, ' . $clientName . ' <span class="badge badge-success">CLIENT</span></span>
                            <img src="' . htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') . '" alt="" id="avatar_header">
                        </button>
                        <ul class="dropdown-menu-v2" role="menu">
                            <li>
                                <a href="/client/index.php"><i class="icon-home"></i> Dashboard</a>
                            </li>
                            <li>
                                <a href="/client/my_requests.php"><i class="icon-calendar"></i> My Requests</a>
                            </li>
                            <li>
                                <a href="/client/app_calendar.php"><i class="icon-clock"></i> My Calendar</a>
                            </li>
                            <li>
                                <a href="/client/mis_datos.php"><i class="icon-user"></i> My Profile</a>
                            </li>
                            <li>
                                <a href="/client/app_inbox.php"><i class="icon-envelope-open"></i> My Inbox</a>
                            </li>
                            <li>
                                <a href="/client/testimonial.php"><i class="icon-speech"></i> My Testimonial</a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="/admin/include/salir.php"><i class="icon-key"></i> Log Out</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>';

$top_header_2 = '<div class="nav-collapse collapse navbar-collapse navbar-responsive-collapse">
                    <ul class="nav navbar-nav">
                        <li' . client_menu_li_class(['index.php'], 'dropdown dropdown-fw dropdown-fw-disabled') . '>
                            <a href="/client/index.php" class="text-uppercase"><i class="icon-home"></i> Dashboard</a>
                        </li>
                        <li' . client_menu_li_class(['my_requests.php', 'request_detail.php'], 'dropdown dropdown-fw dropdown-fw-disabled') . '>
                            <a href="/client/my_requests.php" class="text-uppercase"><i class="icon-list"></i> My Requests</a>
                        </li>
                        <li' . client_menu_li_class(['app_inbox.php'], 'dropdown dropdown-fw dropdown-fw-disabled') . '>
                            <a href="/client/app_inbox.php" class="text-uppercase"><i class="icon-envelope-open"></i> Inbox</a>
                        </li>
                        <li' . client_menu_li_class(['app_calendar.php'], 'dropdown dropdown-fw dropdown-fw-disabled') . '>
                            <a href="/client/app_calendar.php" class="text-uppercase"><i class="icon-clock"></i> Calendar</a>
                        </li>
                        <li' . client_menu_li_class(['mis_datos.php'], 'dropdown dropdown-fw dropdown-fw-disabled') . '>
                            <a href="/client/mis_datos.php" class="text-uppercase"><i class="icon-user"></i> My Profile</a>
                        </li>
                        <li' . client_menu_li_class(['testimonial.php'], 'dropdown dropdown-fw dropdown-fw-disabled') . '>
                            <a href="/client/testimonial.php" class="text-uppercase"><i class="icon-speech"></i> Testimonial</a>
                        </li>
                    </ul>
                 </div>';

$footer = '<p class="copyright"> ' . date('Y') . ' &copy; MedTravel
            <a target="_blank" href="https://medtravel.com.co/">Patient Portal</a>
            </p>
            <a href="#index" class="go2top"><i class="icon-arrow-up"></i></a>';

$theme_layout_script = '<script src="/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
                        <script src="/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
                        <script src="/assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
                        <script src="/assets/global/scripts/app.min.js" type="text/javascript"></script>
                        <script src="/assets/layouts/layout5/scripts/layout.min.js" type="text/javascript"></script>
                        <script src="/assets/layouts/global/scripts/quick-sidebar.min.js" type="text/javascript"></script>';
