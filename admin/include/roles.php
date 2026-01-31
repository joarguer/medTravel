<?php
// Central roles helpers and constants
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Role constants
define('ROLE_ADMIN', 1);
define('ROLE_CLIENT', 3);
define('ROLE_PROVIDER', 4);
define('ROLE_ACCOUNTING', 11);

// Return a normalized integer role or null
function normalize_role_value($rol) {
    if ($rol === null || $rol === '') return null;
    if (is_numeric($rol)) return intval($rol);
    // try to map common text values
    $r = strtolower((string)$rol);
    if (strpos($r, 'admin') !== false) return ROLE_ADMIN;
    if (strpos($r, 'provider') !== false || strpos($r, 'prestador') !== false) return ROLE_PROVIDER;
    if (strpos($r, 'cliente') !== false || strpos($r, 'client') !== false) return ROLE_CLIENT;
    return null;
}

function is_role_admin_session() {
    if (isset($_SESSION['ppal']) && intval($_SESSION['ppal']) === 1) return true;
    if (isset($_SESSION['rol'])) {
        $nr = normalize_role_value($_SESSION['rol']);
        if ($nr === ROLE_ADMIN) return true;
    }
    return false;
}

function has_minimum_role_2() {
    if (is_role_admin_session()) return true;
    if (isset($_SESSION['rol'])) {
        $nr = normalize_role_value($_SESSION['rol']);
        if ($nr !== null && $nr <= 2) return true; // lower number == higher privilege in current app
        if (intval($_SESSION['rol']) === 2) return true;
    }
    return false;
}

function get_available_roles() {
    return [
        ROLE_ADMIN => 'Principal / Admin',
        ROLE_ACCOUNTING => 'Contable',
        ROLE_CLIENT => 'Cliente',
        ROLE_PROVIDER => 'Proveedor'
    ];
}

?>
