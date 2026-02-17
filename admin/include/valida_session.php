<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["usuario"]) || $_SESSION["usuario"] == "") {
    header("Location: include/salir.php?error=1");
    exit();
}

// Permite renderizar la pantalla 403 usando el layout compartido sin re-evaluar permisos.
if (defined('RENDERING_FORBIDDEN_PAGE') && RENDERING_FORBIDDEN_PAGE === true) {
    return;
}

if (!function_exists('user_can')) {
    require_once __DIR__ . '/roles.php';
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
        'clientes.php' => PERM_BOOKING_VIEW,

        // Usuarios / accesos
        'crear_usuario.php' => 'users.create',
        'usuarios.php' => PERM_USERS_MANAGE,
        'roles.php' => PERM_USERS_MANAGE,

        // Reportes / configuración
        'informes.php' => PERM_REPORTS_VIEW,
        'email_settings.php' => PERM_SETTINGS_MANAGE,
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

function render_forbidden_page() {
    $errorPage = __DIR__ . '/../error_403.php';

    header_remove('Content-Type');
    header('Content-Type: text/html; charset=utf-8');

    if (!defined('RENDERING_FORBIDDEN_PAGE')) {
        define('RENDERING_FORBIDDEN_PAGE', true);
    }

    if (!isset($GLOBALS['top_header_2'])) {
        include __DIR__ . '/include.php';
    }

    if (is_file($errorPage)) {
        ob_start();
        try {
            require $errorPage;
        } catch (Throwable $e) {
            ob_end_clean();
            error_log('render_forbidden_page error: ' . $e->getMessage());
        }

        $html = ob_get_clean();
        if (is_string($html) && trim($html) !== '') {
            echo $html;
            return;
        }
    }

    // Fallback defensivo: nunca responder en blanco.
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>403</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"></head><body>'
        . '<h1>403 - Acceso Denegado</h1>'
        . '<p>No tienes permisos para acceder a este módulo.</p>'
        . '<p><a href="index.php">Ir al Dashboard</a></p>'
        . '</body></html>';
}

$current = basename($_SERVER['PHP_SELF']);
$required_permission = get_required_permission_for_script($current);

if ($required_permission !== null) {
    if (!function_exists('user_can') || !user_can($required_permission)) {
        http_response_code(403);
        render_forbidden_page();
        exit();
    }
}
?>
