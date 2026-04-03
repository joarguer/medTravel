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
define('PERM_BOOKING_ASSISTED_CREATE', 'booking.assisted.create');
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
        if ($nr === ROLE_ADMIN) return true;
    }
    if (isset($_SESSION['role_id']) && is_numeric($_SESSION['role_id'])) {
        $rid = intval($_SESSION['role_id']);
        if ($rid === ROLE_ADMIN) return true;
    }
    return false;
}

function is_administrative_session() {
    if (isset($_SESSION['ppal']) && intval($_SESSION['ppal']) === 1) return false;
    if (isset($_SESSION['role_id']) && is_numeric($_SESSION['role_id'])) {
        return intval($_SESSION['role_id']) === ROLE_ADMINISTRATIVE;
    }
    if (isset($_SESSION['rol'])) {
        return normalize_role_value($_SESSION['rol']) === ROLE_ADMINISTRATIVE;
    }
    return false;
}

function is_coordination_admin_session() {
    return is_role_admin_session() || is_administrative_session();
}

function has_minimum_role_2() {
    if (is_role_admin_session()) return true;
    if (is_administrative_session()) return true;
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
        PERM_BOOKING_ASSISTED_CREATE => 'Crear booking asistido',
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
        PERM_BOOKING_ASSISTED_CREATE => [],
        PERM_USERS_MANAGE => ['users.edit', 'users.create'],
        PERM_REPORTS_VIEW => ['reports.view'],
        PERM_SETTINGS_MANAGE => ['roles.manage'],
        PERM_CONTENT_MANAGE => ['roles.manage'],
    ];
}

function get_role_fallback_permissions($role_id) {
    switch (intval($role_id)) {
        case ROLE_ADMINISTRATIVE:
            return [
                PERM_BOOKING_VIEW,
                PERM_BOOKING_ASSISTED_CREATE,
            ];
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

function current_admin_user_id() {
    $keys = ['id_usuario', 'id', 'user_id', 'usuario_id'];
    foreach ($keys as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
            $id = intval($_SESSION[$key]);
            if ($id > 0) {
                return $id;
            }
        }
    }
    return 0;
}

function is_protected_global_superuser_id($userId) {
    return (int)$userId === 1;
}

function is_medical_provider_role_id($roleId) {
    return in_array((int)$roleId, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true);
}

function provider_user_membership_role($conexion, $providerId, $userId) {
    if (
        !$conexion ||
        $providerId <= 0 ||
        $userId <= 0 ||
        is_protected_global_superuser_id($userId) ||
        !roles_table_exists($conexion, 'provider_users') ||
        !roles_table_has_column($conexion, 'provider_users', 'provider_id') ||
        !roles_table_has_column($conexion, 'provider_users', 'user_id')
    ) {
        return '';
    }

    $selectRole = roles_table_has_column($conexion, 'provider_users', 'role_in_provider')
        ? 'pu.role_in_provider'
        : "'owner' AS role_in_provider";

    $stmt = mysqli_prepare(
        $conexion,
        'SELECT ' . $selectRole . '
           FROM provider_users pu
          WHERE pu.provider_id = ? AND pu.user_id = ?
          LIMIT 1'
    );
    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 'ii', $providerId, $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return '';
    }

    return strtolower(trim((string)($row['role_in_provider'] ?? 'owner')));
}

function is_canonical_provider_owner_session($conexion, $providerId, $sessionUserId) {
    if (
        !$conexion ||
        $providerId <= 0 ||
        $sessionUserId <= 0 ||
        is_protected_global_superuser_id($sessionUserId) ||
        !roles_table_exists($conexion, 'usuarios') ||
        !roles_table_has_column($conexion, 'usuarios', 'provider_id')
    ) {
        return false;
    }

    $select = ['u.id'];
    $select[] = roles_table_has_column($conexion, 'usuarios', 'role_id') ? 'u.role_id' : 'NULL AS role_id';
    $select[] = roles_table_has_column($conexion, 'usuarios', 'rol') ? 'u.rol' : 'NULL AS rol';

    $sql = 'SELECT ' . implode(', ', $select) . '
              FROM usuarios u
             WHERE u.provider_id = ?
               AND u.id <> 1';
    if (roles_table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $sql .= ' AND COALESCE(u.service_provider_id, 0) = 0';
    }
    if (roles_table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= ' AND COALESCE(u.is_deleted, 0) = 0';
    }
    $sql .= ' ORDER BY u.id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $ownerUserId = 0;
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $resolvedRoleId = null;
        if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '') {
            $resolvedRoleId = (int)$row['role_id'];
        } elseif (isset($row['rol'])) {
            $resolvedRoleId = normalize_role_value($row['rol']);
        }

        if (is_medical_provider_role_id($resolvedRoleId)) {
            $ownerUserId = (int)($row['id'] ?? 0);
            break;
        }
    }

    mysqli_stmt_close($stmt);
    return $ownerUserId > 0 && $ownerUserId === $sessionUserId;
}

function is_provider_owner_or_admin_session($conexion = null) {
    if (is_role_admin_session()) {
        return true;
    }

    $providerId = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
    if ($providerId <= 0) {
        return false;
    }

    $sessionRoleId = current_role_id();
    if ($sessionRoleId === ROLE_PROVIDER_ADMIN) {
        return true;
    }

    if (isset($_SESSION['ppal'])) {
        $ppalValue = strtolower(trim((string)$_SESSION['ppal']));
        if ($ppalValue === '1' || $ppalValue === 'ppal') {
            return true;
        }
    }

    if ($conexion === null) {
        global $conexion;
    }
    if (!$conexion || !roles_table_exists($conexion, 'usuarios')) {
        return false;
    }

    $sessionUserId = current_admin_user_id();
    if ($sessionUserId <= 0) {
        return false;
    }
    if (is_protected_global_superuser_id($sessionUserId)) {
        return true;
    }

    $hasRoleId = roles_table_has_column($conexion, 'usuarios', 'role_id');
    $hasRol = roles_table_has_column($conexion, 'usuarios', 'rol');
    $hasPpal = roles_table_has_column($conexion, 'usuarios', 'ppal');
    $hasProviderId = roles_table_has_column($conexion, 'usuarios', 'provider_id');
    if (!$hasRoleId && !$hasRol && !$hasPpal && !$hasProviderId) {
        return false;
    }

    $select = ['u.id'];
    $select[] = $hasRoleId ? 'u.role_id' : 'NULL AS role_id';
    $select[] = $hasRol ? 'u.rol' : 'NULL AS rol';
    $select[] = $hasPpal ? 'u.ppal' : '0 AS ppal';
    $select[] = $hasProviderId ? 'u.provider_id' : 'NULL AS provider_id';

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM usuarios u WHERE u.id = ? LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $sessionUserId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return false;
    }

    if (isset($row['ppal']) && (int)$row['ppal'] === 1) {
        return true;
    }

    $resolvedRoleId = null;
    if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '') {
        $resolvedRoleId = (int)$row['role_id'];
    } elseif (isset($row['rol'])) {
        $resolvedRoleId = normalize_role_value($row['rol']);
    }

    if ((int)$resolvedRoleId === ROLE_PROVIDER_ADMIN) {
        return true;
    }

    $membershipRole = provider_user_membership_role($conexion, $providerId, $sessionUserId);
    if ($membershipRole !== '' && in_array($membershipRole, ['owner', 'admin', 'administrator', 'principal', 'primary'], true)) {
        return true;
    }

    if (
        (int)$resolvedRoleId === ROLE_PROVIDER &&
        isset($row['provider_id']) &&
        (int)$row['provider_id'] === $providerId &&
        $membershipRole === ''
    ) {
        return is_canonical_provider_owner_session($conexion, $providerId, $sessionUserId);
    }

    return false;
}

function roles_table_exists($conexion, $table) {
    static $cache = [];
    if (!$conexion) return false;
    if (array_key_exists($table, $cache)) return $cache[$table];
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$table];
}

function roles_table_has_column($conexion, $table, $column) {
    static $cache = [];
    if (!$conexion) return false;
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function current_provider_medical_staff_context($conexion = null) {
    static $cacheLoaded = false;
    static $cacheValue = null;

    if ($cacheLoaded) {
        return $cacheValue;
    }
    $cacheLoaded = true;

    if ($conexion === null) {
        global $conexion;
    }

    if (!$conexion || is_role_admin_session()) {
        return null;
    }

    $sessionUserId = current_admin_user_id();
    $providerId = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
    if ($sessionUserId <= 0 || $providerId <= 0) {
        return null;
    }

    if (is_provider_owner_or_admin_session($conexion)) {
        return null;
    }

    if (!roles_table_exists($conexion, 'provider_medical_staff')) {
        return null;
    }
    if (!roles_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id') ||
        !roles_table_has_column($conexion, 'provider_medical_staff', 'can_access_admin')) {
        return null;
    }

    $hasUserActivo = roles_table_exists($conexion, 'usuarios') && roles_table_has_column($conexion, 'usuarios', 'activo');
    $hasUserDeleted = roles_table_exists($conexion, 'usuarios') && roles_table_has_column($conexion, 'usuarios', 'is_deleted');

    $sql = "SELECT pms.id, pms.provider_id, pms.full_name, pms.linked_user_id, pms.can_access_admin
            FROM provider_medical_staff pms";
    if (roles_table_exists($conexion, 'usuarios')) {
        $sql .= " LEFT JOIN usuarios u ON u.id = pms.linked_user_id";
    }
    $statusColumn = '';
    if (roles_table_has_column($conexion, 'provider_medical_staff', 'is_active')) {
        $statusColumn = 'is_active';
    } elseif (roles_table_has_column($conexion, 'provider_medical_staff', 'active')) {
        $statusColumn = 'active';
    }

    $sql .= " WHERE pms.provider_id = ?
              AND pms.linked_user_id = ?";
    if ($statusColumn !== '') {
        $sql .= " AND pms.`{$statusColumn}` = 1";
    }
    $sql .= " AND pms.can_access_admin = 1";
    if ($hasUserActivo) {
        $sql .= " AND COALESCE(u.activo, 0) = 1";
    }
    if ($hasUserDeleted) {
        $sql .= " AND COALESCE(u.is_deleted, 0) = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $providerId, $sessionUserId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    $cacheValue = $row ?: null;
    return $cacheValue;
}

function current_provider_medical_staff_id($conexion = null) {
    $ctx = current_provider_medical_staff_context($conexion);
    return $ctx ? intval($ctx['id'] ?? 0) : 0;
}

function is_provider_linked_medical_staff_session($conexion = null) {
    return current_provider_medical_staff_id($conexion) > 0;
}

function get_administrative_allowed_permissions() {
    return [
        PERM_BOOKING_VIEW,
        PERM_BOOKING_ASSISTED_CREATE,
    ];
}

function user_can($permission_slug){
    if(is_role_admin_session()) return true; // admin principal tiene todo
    $rid = current_role_id();
    if($rid === null) return false;
    if ((int)$rid === ROLE_ADMINISTRATIVE) {
        return in_array((string)$permission_slug, get_administrative_allowed_permissions(), true);
    }

    if (is_provider_linked_medical_staff_session()) {
        $blockedForLinkedStaff = [
            PERM_PROVIDERS_MEDICAL_MANAGE,
            PERM_SERVICES_MEDICAL_MANAGE,
            PERM_USERS_MANAGE,
            'providers.view',
            'providers.medical.view',
            'providers.edit',
            'providers.medical.edit',
            'offers.manage',
            'users.view',
            'users.create',
            'users.edit',
            'users.manage',
        ];
        if (in_array($permission_slug, $blockedForLinkedStaff, true)) {
            return false;
        }
    }

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
