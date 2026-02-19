<?php

if (!function_exists('medtravel_session_start')) {
    $sessionSecurityPath = __DIR__ . '/../admin/include/session_security.php';
    if (file_exists($sessionSecurityPath)) {
        require_once $sessionSecurityPath;
    }
}

if (function_exists('medtravel_session_start')) {
    medtravel_session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('ROLE_CLIENT')) {
    $rolesPath = __DIR__ . '/../admin/include/roles.php';
    if (file_exists($rolesPath)) {
        require_once $rolesPath;
    } else {
        define('ROLE_CLIENT', 3);
        define('ROLE_ADMIN', 1);
        define('ROLE_ADMINISTRATIVE', 2);
    }
}

function client_current_role_id()
{
    if (isset($_SESSION['role_id']) && is_numeric($_SESSION['role_id'])) {
        return (int)$_SESSION['role_id'];
    }
    if (isset($_SESSION['rol'])) {
        $rol = $_SESSION['rol'];
        if (function_exists('normalize_role_value')) {
            return normalize_role_value($rol);
        }
        if (is_numeric($rol)) {
            return (int)$rol;
        }
        $txt = strtolower(trim((string)$rol));
        if ($txt === 'cliente' || $txt === 'client') {
            return ROLE_CLIENT;
        }
    }
    return null;
}

function is_client_session()
{
    if (empty($_SESSION['id_usuario']) || empty($_SESSION['usuario'])) {
        return false;
    }
    if (isset($_SESSION['ppal']) && (int)$_SESSION['ppal'] === 1) {
        return false;
    }
    if (!empty($_SESSION['provider_id']) || !empty($_SESSION['service_provider_id'])) {
        return false;
    }

    $roleId = client_current_role_id();
    if ($roleId === ROLE_ADMIN || $roleId === ROLE_ADMINISTRATIVE) {
        return false;
    }
    if ($roleId !== null) {
        return ((int)$roleId === (int)ROLE_CLIENT);
    }

    $rolText = strtolower(trim((string)($_SESSION['rol'] ?? '')));
    return ($rolText === 'cliente' || $rolText === 'client' || $rolText === '3');
}

function get_client_user_id()
{
    return isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
}

function get_client_display_name()
{
    return (string)($_SESSION['nombre_usuario'] ?? '');
}

function require_client_auth()
{
    if (empty($_SESSION['id_usuario']) || empty($_SESSION['usuario'])) {
        header('Location: /login.php?error=auth_required');
        exit;
    }

    if (function_exists('medtravel_session_enforce_limits')) {
        $state = medtravel_session_enforce_limits();
        if (empty($state['ok'])) {
            if (function_exists('medtravel_session_destroy')) {
                medtravel_session_destroy();
            } else {
                session_destroy();
            }
            header('Location: /login.php?error=session_expired');
            exit;
        }
    }

    if (!is_client_session()) {
        header('Location: /admin/index.php');
        exit;
    }
}

function require_client_auth_ajax()
{
    if (empty($_SESSION['id_usuario']) || empty($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'auth_required']);
        exit;
    }

    if (function_exists('medtravel_session_enforce_limits')) {
        $state = medtravel_session_enforce_limits();
        if (empty($state['ok'])) {
            if (function_exists('medtravel_session_destroy')) {
                medtravel_session_destroy();
            } else {
                session_destroy();
            }
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'session_expired']);
            exit;
        }
    }

    if (!is_client_session()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'forbidden']);
        exit;
    }
}

