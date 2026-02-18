<?php
// Central roles helpers and constants
require_once __DIR__ . '/session_security.php';
medtravel_session_start();

// Role constants
define('ROLE_ADMIN', 1);
define('ROLE_ADMINISTRATIVE', 2);
define('ROLE_CLIENT', 3);
define('ROLE_PROVIDER', 4);
define('ROLE_ACCOUNTING', 11);
define('ROLE_PROVIDER_ADMIN', 12);
define('ROLE_COMPLEMENTARY_ADMIN', 13);

// Canonical granular permissions (RBAC hardening)
define('PERM_SERVICES_MEDICAL_MANAGE', 'services.medical.manage');
define('PERM_SERVICES_COMPLEMENTARY_MANAGE', 'services.complementary.manage');
define('PERM_PROVIDERS_MEDICAL_MANAGE', 'providers.medical.manage');
define('PERM_PROVIDERS_COMPLEMENTARY_MANAGE', 'providers.complementary.manage');
define('PERM_BOOKING_VIEW', 'booking.view');
define('PERM_BOOKING_MANAGE', 'booking.manage');
define('PERM_PACKAGES_MANAGE', 'packages.manage');
define('PERM_USERS_MANAGE', 'users.manage');
define('PERM_REPORTS_VIEW', 'reports.view');
define('PERM_SETTINGS_MANAGE', 'settings.manage');
define('PERM_CONTENT_MANAGE', 'content.manage');

// Return a normalized integer role or null
function normalize_role_value($rol) {
    if ($rol === null || $rol === '') return null;
    if (is_numeric($rol)) return intval($rol);
    // try to map common text values
    $r = strtolower((string)$rol);
    if (strpos($r, 'complementary_admin') !== false) return ROLE_COMPLEMENTARY_ADMIN;
    if (strpos($r, 'provider_admin') !== false || strpos($r, 'prestador_admin') !== false) return ROLE_PROVIDER_ADMIN;
    if (strpos($r, 'administrative') !== false) return ROLE_ADMINISTRATIVE;
    if (strpos($r, 'admin') !== false) return ROLE_ADMIN;
    if (strpos($r, 'partner') !== false || strpos($r, 'complement') !== false) return ROLE_COMPLEMENTARY_ADMIN;
    if (strpos($r, 'provider') !== false || strpos($r, 'prestador') !== false) return ROLE_PROVIDER;
    if (strpos($r, 'cliente') !== false || strpos($r, 'client') !== false) return ROLE_CLIENT;
    return null;
}

function is_role_admin_session() {
    if (isset($_SESSION['ppal']) && intval($_SESSION['ppal']) === 1) return true;
    if (isset($_SESSION['rol'])) {
        $nr = normalize_role_value($_SESSION['rol']);
        if ($nr === ROLE_ADMIN || $nr === ROLE_ADMINISTRATIVE) return true;
    }
    if (isset($_SESSION['role_id']) && is_numeric($_SESSION['role_id'])) {
        $rid = intval($_SESSION['role_id']);
        if ($rid === ROLE_ADMIN || $rid === ROLE_ADMINISTRATIVE) return true;
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
        ROLE_ADMINISTRATIVE => 'Administrativo',
        ROLE_PROVIDER_ADMIN => 'Admin Prestador',
        ROLE_COMPLEMENTARY_ADMIN => 'Admin Proveedor Complementario',
        ROLE_ACCOUNTING => 'Contable',
        ROLE_CLIENT => 'Cliente',
        ROLE_PROVIDER => 'Proveedor'
    ];
}

function get_granular_permissions_catalog() {
    return [
        PERM_SERVICES_MEDICAL_MANAGE => 'Gestionar servicios médicos',
        PERM_SERVICES_COMPLEMENTARY_MANAGE => 'Gestionar servicios complementarios',
        PERM_PROVIDERS_MEDICAL_MANAGE => 'Gestionar prestadores médicos',
        PERM_PROVIDERS_COMPLEMENTARY_MANAGE => 'Gestionar proveedores complementarios',
        PERM_BOOKING_VIEW => 'Ver bookings',
        PERM_BOOKING_MANAGE => 'Gestionar bookings',
        PERM_PACKAGES_MANAGE => 'Gestionar paquetes',
        PERM_USERS_MANAGE => 'Gestionar usuarios',
        PERM_REPORTS_VIEW => 'Ver reportes',
        PERM_SETTINGS_MANAGE => 'Gestionar configuración',
        PERM_CONTENT_MANAGE => 'Gestionar contenido web',
    ];
}

function get_permission_alias_map() {
    // Bridge entre permisos canónicos nuevos y slugs legacy existentes en DB.
    return [
        PERM_SERVICES_MEDICAL_MANAGE => ['offers.manage', 'providers.medical.edit', 'providers.edit'],
        // Complementarios: usar permiso canónico, sin alias legacy inseguros.
        PERM_SERVICES_COMPLEMENTARY_MANAGE => [],
        PERM_PROVIDERS_MEDICAL_MANAGE => ['providers.medical.edit', 'providers.edit'],
        // Complementarios: usar permiso canónico, sin alias legacy inseguros.
        PERM_PROVIDERS_COMPLEMENTARY_MANAGE => [],
        PERM_BOOKING_VIEW => ['reports.view'],
        PERM_BOOKING_MANAGE => ['reports.view'],
        PERM_USERS_MANAGE => ['users.edit', 'users.create'],
        PERM_REPORTS_VIEW => ['reports.view'],
        PERM_SETTINGS_MANAGE => ['roles.manage'],
        PERM_CONTENT_MANAGE => ['roles.manage'],
    ];
}

function get_role_fallback_permissions($role_id) {
    switch (intval($role_id)) {
        case ROLE_COMPLEMENTARY_ADMIN:
            return [
                PERM_SERVICES_COMPLEMENTARY_MANAGE,
                PERM_BOOKING_VIEW,
                'users.view',
                'users.create',
                'users.edit',
            ];
        case ROLE_PROVIDER_ADMIN:
            return [
                PERM_SERVICES_MEDICAL_MANAGE,
                PERM_PROVIDERS_MEDICAL_MANAGE,
                'offers.manage',
                'providers.medical.view',
                'providers.medical.edit',
                PERM_BOOKING_VIEW,
                'users.view',
                'users.create',
                'users.edit',
            ];
        case ROLE_PROVIDER:
            return [
                PERM_SERVICES_MEDICAL_MANAGE,
                PERM_PROVIDERS_MEDICAL_MANAGE,
                'offers.manage',
                'providers.medical.view',
                'providers.medical.edit',
                PERM_BOOKING_VIEW,
                'users.view',
            ];
        case ROLE_ACCOUNTING:
            return [PERM_REPORTS_VIEW, PERM_BOOKING_VIEW, 'reports.view'];
        default:
            return [];
    }
}

function is_granular_permission_slug($permission_slug) {
    $catalog = get_granular_permissions_catalog();
    return isset($catalog[$permission_slug]);
}

function permission_match_in_list($permission_slug, $perms) {
    if (in_array($permission_slug, $perms, true)) return true;
    $aliases = get_permission_alias_map();
    if (!empty($aliases[$permission_slug])) {
        foreach ($aliases[$permission_slug] as $alias) {
            if (in_array($alias, $perms, true)) return true;
        }
    }
    return false;
}

// Permission helpers
function current_role_id(){
    if (isset($_SESSION['role_id']) && is_numeric($_SESSION['role_id'])) return intval($_SESSION['role_id']);
    if (isset($_SESSION['rol'])) return normalize_role_value($_SESSION['rol']);
    return null;
}

function role_requires_service_provider_scope($role_id = null, $role_text = null){
    if ($role_id === null) {
        $role_id = current_role_id();
    }
    if ($role_text === null && isset($_SESSION['rol'])) {
        $role_text = (string)$_SESSION['rol'];
    }

    if ($role_id !== null) {
        if (intval($role_id) === ROLE_COMPLEMENTARY_ADMIN) {
            return true;
        }
        $perms = get_role_permissions(intval($role_id));
        if (in_array('providers.partner.view', $perms, true) || in_array('providers.partner.edit', $perms, true)) {
            return true;
        }
    }

    $txt = strtolower(trim((string)$role_text));
    if ($txt !== '' && (strpos($txt, 'partner') !== false || strpos($txt, 'complement') !== false)) {
        return true;
    }
    return false;
}

function current_service_provider_id(){
    if (!empty($_SESSION['service_provider_id'])) {
        return intval($_SESSION['service_provider_id']);
    }
    return 0;
}

function is_complementary_user_session(){
    if (is_role_admin_session()) return false;
    if (current_service_provider_id() > 0) return true;
    return role_requires_service_provider_scope();
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

    // Hardening: complementary_admin no administra catálogo de proveedores complementarios.
    if (intval($rid) === ROLE_COMPLEMENTARY_ADMIN && $permission_slug === PERM_PROVIDERS_COMPLEMENTARY_MANAGE) {
        return false;
    }

    $perms = get_role_permissions($rid);
    if (permission_match_in_list($permission_slug, $perms)) return true;

    // Fallback no destructivo para ambientes sin migración completa de permisos granulares.
    if (is_granular_permission_slug($permission_slug)) {
        $fallback = get_role_fallback_permissions($rid);
        if (permission_match_in_list($permission_slug, $fallback)) return true;
    }
    return false;
}

?>
