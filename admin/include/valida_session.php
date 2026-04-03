<?php
require_once __DIR__ . '/session_security.php';
medtravel_session_start();

if (defined('SKIP_AUTH')) {
    return;
}

if (!isset($_SESSION["usuario"]) || $_SESSION["usuario"] == "") {
    header("Location: include/salir.php?error=1");
    exit();
}

$sessionState = medtravel_session_enforce_limits();
if (empty($sessionState['ok'])) {
    medtravel_session_destroy();
    header("Location: ../../login.php?error=session_expired");
    exit();
}

if (!function_exists('user_can')) {
    require_once __DIR__ . '/roles.php';
}
if (!function_exists('is_client_session')) {
    require_once __DIR__ . '/../../inc/auth_client.php';
}
if (function_exists('is_client_session') && is_client_session()) {
    header("Location: ../../client/index.php");
    exit();
}
if (!isset($GLOBALS['conexion']) || !$GLOBALS['conexion']) {
    include_once __DIR__ . '/conexion.php';
}

function get_required_permission_for_script($script_name) {
    // RBAC hardening: mapa canónico script -> permiso requerido.
    static $map = [
        // Gestión médica
        'service_categories.php' => PERM_SERVICES_MEDICAL_MANAGE,
        'service_catalog.php' => PERM_SERVICES_MEDICAL_MANAGE,
        'providers.php' => PERM_PROVIDERS_MEDICAL_MANAGE,
        'provider_verification.php' => PERM_PROVIDERS_MEDICAL_MANAGE,
        'provider_offers.php' => PERM_SERVICES_MEDICAL_MANAGE,

        // Gestión complementaria
        'providers_complementary.php' => PERM_PROVIDERS_COMPLEMENTARY_MANAGE,
        'medtravel_services.php' => PERM_SERVICES_COMPLEMENTARY_MANAGE,
        'paquetes.php' => PERM_PACKAGES_MANAGE,

        // Booking / clientes
        'booking_requests.php' => PERM_BOOKING_MANAGE,
        'booking_asistido.php' => PERM_BOOKING_ASSISTED_CREATE,
        'my_booking_requests.php' => PERM_BOOKING_VIEW,
        'app_calendar.php' => PERM_BOOKING_VIEW,
        'clientes.php' => PERM_BOOKING_VIEW,

        // Usuarios / accesos
        'crear_usuario.php' => 'users.create',
        'usuarios.php' => PERM_USERS_MANAGE,
        'roles.php' => PERM_USERS_MANAGE,

        // Reportes / configuración
        'informes.php' => PERM_REPORTS_VIEW,
        'email_settings.php' => PERM_SETTINGS_MANAGE,
        'google_calendar_settings.php' => PERM_SETTINGS_MANAGE,
        'google_calendar_oauth.php' => PERM_SETTINGS_MANAGE,
        'data_deletion_requests.php' => PERM_SETTINGS_MANAGE,
        'create_bd.php' => PERM_SETTINGS_MANAGE,

        // Contenido web
        'home_edit.php' => PERM_CONTENT_MANAGE,
        'about_edit.php' => PERM_CONTENT_MANAGE,
        'services_edit.php' => PERM_CONTENT_MANAGE,
        'offers_header_edit.php' => PERM_CONTENT_MANAGE,
        'offer_detail_edit.php' => PERM_CONTENT_MANAGE,
        'wizard_header_edit.php' => PERM_CONTENT_MANAGE,
        'blog_edit.php' => PERM_CONTENT_MANAGE,
    ];

    return isset($map[$script_name]) ? $map[$script_name] : null;
}

function is_script_forbidden_for_current_session($script_name) {
    if ($script_name === 'clientes.php' && function_exists('is_administrative_session') && is_administrative_session()) {
        return true;
    }
    return false;
}

$current = basename($_SERVER['PHP_SELF']);
$required_permission = get_required_permission_for_script($current);

if (is_script_forbidden_for_current_session($current)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/../error_403.php';
    exit();
}

if ($required_permission !== null) {
    if ($current === 'blog_edit.php') {
        $canAccessBlog = (
            (function_exists('user_can') && user_can(PERM_CONTENT_MANAGE))
            || (function_exists('user_can') && user_can(PERM_SERVICES_MEDICAL_MANAGE))
        );
        if (!$canAccessBlog) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            require __DIR__ . '/../error_403.php';
            exit();
        }
        return;
    }
    if (!function_exists('user_can') || !user_can($required_permission)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../error_403.php';
        exit();
    }
}
?>
