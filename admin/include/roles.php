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
define('ROLE_PROVIDER_ADMIN', 12);

// Return a normalized integer role or null
function normalize_role_value($rol) {
    if ($rol === null || $rol === '') return null;
    if (is_numeric($rol)) return intval($rol);
    // try to map common text values
    $r = strtolower((string)$rol);
    if (strpos($r, 'admin') !== false) return ROLE_ADMIN;
    if (strpos($r, 'provider') !== false || strpos($r, 'prestador') !== false) return ROLE_PROVIDER;
    if (strpos($r, 'cliente') !== false || strpos($r, 'client') !== false) return ROLE_CLIENT;
    if (strpos($r, 'provider_admin') !== false || strpos($r, 'prestador_admin') !== false) return ROLE_PROVIDER_ADMIN;
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
        ROLE_PROVIDER_ADMIN => 'Admin Prestador',
        ROLE_ACCOUNTING => 'Contable',
        ROLE_CLIENT => 'Cliente',
        ROLE_PROVIDER => 'Proveedor'
    ];
}

// Permission helpers
function current_role_id(){
    if (isset($_SESSION['role_id']) && is_numeric($_SESSION['role_id'])) return intval($_SESSION['role_id']);
    if (isset($_SESSION['rol'])) return normalize_role_value($_SESSION['rol']);
    return null;
}

function get_role_permissions($role_id){
    static $cache = [];
    if($role_id === null) return [];
    if(isset($cache[$role_id])) return $cache[$role_id];
    global $conexion;
    $perms = [];
    if(!$conexion) return $perms;
    $stmt = mysqli_prepare($conexion, "SELECT p.slug FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($res)){
        $perms[] = $row['slug'];
    }
    $cache[$role_id] = $perms;
    return $perms;
}

function user_can($permission_slug){
    if(is_role_admin_session()) return true; // admin principal tiene todo
    $rid = current_role_id();
    if($rid === null) return false;
    $perms = get_role_permissions($rid);
    return in_array($permission_slug, $perms, true);
}

?>
