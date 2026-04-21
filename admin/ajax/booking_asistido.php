<?php
/**
 * AJAX handler — Agent-assisted booking
 *
 * Actions:
 *   lookup  — check if email already exists as a client user
 *   submit  — create booking_request + items + client user + send credentials
 *
 * Security: requires valid session with PERM_BOOKING_ASSISTED_CREATE.
 * Terms acceptance: deliberately NOT set on behalf of client (terms_accepted = 0).
 * The client must personally accept on first login via the terms gate.
 */

@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);

require_once __DIR__ . '/../include/conexion.php';
require_once __DIR__ . '/../include/roles.php';
require_once __DIR__ . '/../include/password_utils.php';
require_once __DIR__ . '/../include/email_config.php';
require_once __DIR__ . '/../include/booking_notification_recipients.php';
require_once __DIR__ . '/../include/provider_medical_staff_helpers.php';
require_once __DIR__ . '/../../inc/email_template.php';
require_once __DIR__ . '/../../inc/interaction_email.php';

function ab_json_response(array $payload, $status = 200)
{
    http_response_code((int)$status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function ab_json_error($message, $status = 400, array $extra = [])
{
    ab_json_response(array_merge(['success' => false, 'message' => (string)$message], $extra), $status);
}

set_exception_handler(function ($e) {
    error_log('booking_asistido ajax exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ab_json_error('Internal server error.', 500);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    error_log('booking_asistido ajax fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
});

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!user_can(PERM_BOOKING_ASSISTED_CREATE)) {
    ab_json_error('Access denied', 403);
}

$action = trim((string)($_POST['action'] ?? ''));

// ── Helpers ───────────────────────────────────────────────────────────────────

function ab_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $t = mysqli_real_escape_string($conexion, $table);
    $c = mysqli_real_escape_string($conexion, $column);
    $r = mysqli_query($conexion, "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    $cache[$key] = ($r && mysqli_num_rows($r) > 0);
    return $cache[$key];
}

function ab_table_exists($conexion, $table)
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    $t = mysqli_real_escape_string($conexion, $table);
    $r = mysqli_query($conexion, "SHOW TABLES LIKE '{$t}'");
    $cache[$table] = ($r && mysqli_num_rows($r) > 0);
    return $cache[$table];
}

function ab_random_hex($bytes = 16)
{
    if (function_exists('random_bytes')) {
        try { return bin2hex(random_bytes($bytes)); } catch (Exception $e) {}
    }
    return md5(uniqid((string)mt_rand(), true));
}

function ab_value_type($v)
{
    if (is_int($v)) return 'i';
    if (is_float($v)) return 'd';
    return 's';
}

function ab_bind_params($stmt, $types, &$params)
{
    if (!$types || empty($params)) return true;
    $bind = [$stmt, &$types];
    foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function ab_status_conditions($conexion, $table, $alias)
{
    $conditions = [];
    if (ab_has_column($conexion, $table, 'is_active')) {
        $conditions[] = $alias . '.is_active = 1';
    }
    if (ab_has_column($conexion, $table, 'is_deleted')) {
        $conditions[] = $alias . '.is_deleted = 0';
    }
    return $conditions;
}

function ab_fetch_eligible_staff_ids($conexion, $providerId, $providerCatalogServiceId = 0, $serviceId = 0)
{
    $providerId = (int)$providerId;
    $providerCatalogServiceId = (int)$providerCatalogServiceId;
    $serviceId = (int)$serviceId;
    if ($providerId <= 0 || $serviceId <= 0) {
        return [];
    }
    if (!ab_table_exists($conexion, 'provider_medical_staff') || !ab_table_exists($conexion, 'provider_medical_staff_services')) {
        return [];
    }

    $hasRelActive = ab_has_column($conexion, 'provider_medical_staff_services', 'active');
    $hasRelProviderCatalogServiceId = ab_has_column($conexion, 'provider_medical_staff_services', 'provider_catalog_service_id');
    $staffStatusColumn = provider_staff_status_column_name($conexion);

    $sql = "SELECT DISTINCT pms.id
            FROM provider_medical_staff_services rel
            INNER JOIN provider_medical_staff pms ON pms.id = rel.provider_medical_staff_id
            WHERE pms.provider_id = ?";
    $types = 'i';
    $params = [$providerId];

    if ($hasRelActive) {
        $sql .= ' AND rel.active = 1';
    }
    if ($staffStatusColumn !== '') {
        $sql .= ' AND pms.`' . $staffStatusColumn . '` = 1';
    }

    if ($providerCatalogServiceId > 0 && $hasRelProviderCatalogServiceId) {
        $sql .= ' AND (rel.provider_catalog_service_id = ?';
        $types .= 'i';
        $params[] = $providerCatalogServiceId;
        $sql .= ' OR (COALESCE(rel.provider_catalog_service_id, 0) = 0 AND rel.service_id = ?))';
        $types .= 'i';
        $params[] = $serviceId;
    } else {
        $sql .= ' AND rel.service_id = ?';
        $types .= 'i';
        $params[] = $serviceId;
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    if (!ab_bind_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [];
    }

    $res = mysqli_stmt_get_result($stmt);
    $ids = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $staffId = (int)($row['id'] ?? 0);
        if ($staffId > 0) {
            $ids[] = $staffId;
        }
    }
    mysqli_stmt_close($stmt);
    return array_values(array_unique($ids));
}

function ab_resolve_unique_staff_id($conexion, $providerId, $providerCatalogServiceId = 0, $serviceId = 0)
{
    $eligibleIds = ab_fetch_eligible_staff_ids($conexion, $providerId, $providerCatalogServiceId, $serviceId);
    return count($eligibleIds) === 1 ? (int)$eligibleIds[0] : 0;
}

function ab_assign_initial_staff_to_item($conexion, $itemId, $providerId, $providerCatalogServiceId = 0, $serviceId = 0)
{
    $itemId = (int)$itemId;
    $providerId = (int)$providerId;
    if ($itemId <= 0 || $providerId <= 0 || !ab_has_column($conexion, 'booking_request_items', 'assigned_staff_id')) {
        return 0;
    }

    $staffId = ab_resolve_unique_staff_id($conexion, $providerId, $providerCatalogServiceId, $serviceId);
    if ($staffId <= 0) {
        return 0;
    }

    $setParts = ['assigned_staff_id = ?'];
    $types = 'ii';
    $params = [$staffId, $itemId];
    if (ab_has_column($conexion, 'booking_request_items', 'assigned_at')) {
        $setParts[] = 'assigned_at = NOW()';
    }

    $sql = 'UPDATE booking_request_items SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 0;
    }
    if (!ab_bind_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    mysqli_stmt_close($stmt);
    return $staffId;
}

function ab_load_category_row($conexion, $categoryId)
{
    $categoryId = (int)$categoryId;
    if ($categoryId <= 0) {
        return null;
    }

    $sql = "SELECT cat.id, cat.name
            FROM service_categories cat
            WHERE cat.id = ?";
    $conditions = ab_status_conditions($conexion, 'service_categories', 'cat');
    if (!empty($conditions)) {
        $sql .= ' AND ' . implode(' AND ', $conditions);
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $categoryId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function ab_load_service_row($conexion, $serviceId, $categoryId = 0)
{
    $serviceId = (int)$serviceId;
    $categoryId = (int)$categoryId;
    if ($serviceId <= 0) {
        return null;
    }

    $sql = "SELECT
                sc.id,
                sc.name,
                sc.category_id,
                COALESCE(cat.name, '') AS category_name
            FROM service_catalog sc
            LEFT JOIN service_categories cat ON cat.id = sc.category_id
            WHERE sc.id = ?";
    $types = 'i';
    $params = [$serviceId];

    if ($categoryId > 0) {
        $sql .= " AND sc.category_id = ?";
        $types .= 'i';
        $params[] = $categoryId;
    }

    $serviceConditions = ab_status_conditions($conexion, 'service_catalog', 'sc');
    if (!empty($serviceConditions)) {
        $sql .= ' AND ' . implode(' AND ', $serviceConditions);
    }

    $categoryConditions = ab_status_conditions($conexion, 'service_categories', 'cat');
    if (!empty($categoryConditions)) {
        $sql .= ' AND ' . implode(' AND ', $categoryConditions);
    }

    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    if (!ab_bind_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function ab_fetch_services_for_category($conexion, $categoryId)
{
    $categoryId = (int)$categoryId;
    if ($categoryId <= 0) {
        return [];
    }

    $serviceConditions = ab_status_conditions($conexion, 'service_catalog', 'sc');
    $offerConditions = ab_status_conditions($conexion, 'provider_service_offers', 'o');
    $providerConditions = ab_status_conditions($conexion, 'providers', 'p');
    $orderExpr = ab_has_column($conexion, 'service_catalog', 'sort_order') ? 'COALESCE(sc.sort_order, 9999)' : 'sc.id';

    $sql = "SELECT
                sc.id,
                sc.name,
                COUNT(DISTINCT CASE WHEN p.id IS NOT NULL THEN o.id END) AS offer_count
            FROM service_catalog sc
            LEFT JOIN provider_service_offers o
                ON o.service_id = sc.id";
    if (!empty($offerConditions)) {
        $sql .= ' AND ' . implode(' AND ', $offerConditions);
    }
    $sql .= "
            LEFT JOIN providers p
                ON p.id = o.provider_id";
    if (!empty($providerConditions)) {
        $sql .= ' AND ' . implode(' AND ', $providerConditions);
    }
    $sql .= "
            WHERE sc.category_id = ?";
    if (!empty($serviceConditions)) {
        $sql .= ' AND ' . implode(' AND ', $serviceConditions);
    }
    $sql .= "
            GROUP BY sc.id, sc.name, {$orderExpr}
            ORDER BY {$orderExpr} ASC, sc.name ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $categoryId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $services = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $services[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'offer_count' => (int)($row['offer_count'] ?? 0),
        ];
    }
    mysqli_stmt_close($stmt);

    return $services;
}

function ab_selected_offers_are_valid($conexion, $serviceId, array $selectedOffers)
{
    $selectedOffers = array_values(array_unique(array_filter(array_map('intval', $selectedOffers))));
    if (empty($selectedOffers)) {
        return true;
    }

    $idsCsv = implode(',', $selectedOffers);
    $offerConditions = ab_status_conditions($conexion, 'provider_service_offers', 'o');
    $providerConditions = ab_status_conditions($conexion, 'providers', 'p');

    $sql = "SELECT COUNT(DISTINCT o.id) AS valid_count
            FROM provider_service_offers o
            INNER JOIN providers p ON p.id = o.provider_id
            WHERE o.id IN ({$idsCsv})
              AND o.service_id = ?";
    if (!empty($offerConditions)) {
        $sql .= ' AND ' . implode(' AND ', $offerConditions);
    }
    if (!empty($providerConditions)) {
        $sql .= ' AND ' . implode(' AND ', $providerConditions);
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $serviceId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['valid_count'] ?? 0) === count($selectedOffers);
}

function ab_submit_log($message)
{
    $line = date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL;
    @file_put_contents(__DIR__ . '/../logs/booking_asistido_submit.log', $line, FILE_APPEND | LOCK_EX);
}

function ab_table_columns_meta($conexion, $table)
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $meta = [];
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}`");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            if (!empty($row['Field'])) {
                $meta[$row['Field']] = $row;
            }
        }
    }
    $cache[$table] = $meta;
    return $meta;
}

function ab_enum_options($type)
{
    $type = (string)$type;
    if (!preg_match("/^enum\\((.*)\\)$/i", $type, $m)) {
        return [];
    }
    $parts = str_getcsv($m[1], ',', "'");
    $out = [];
    foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value !== '') {
            $out[] = $value;
        }
    }
    return $out;
}

function ab_role_client_exists($conexion)
{
    static $checked = null;
    if ($checked !== null) {
        return $checked;
    }
    $checked = false;
    if (!ab_table_exists($conexion, 'roles')) {
        return $checked;
    }
    $clientRoleId = (int)(defined('ROLE_CLIENT') ? ROLE_CLIENT : 3);
    $stmt = mysqli_prepare($conexion, 'SELECT id FROM roles WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return $checked;
    }
    mysqli_stmt_bind_param($stmt, 'i', $clientRoleId);
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($row && !empty($row['id'])) {
            $checked = true;
        }
    }
    mysqli_stmt_close($stmt);
    return $checked;
}

function ab_fill_required_usuario_defaults($conexion, &$data, $email, $userName, $baseToken)
{
    $meta = ab_table_columns_meta($conexion, 'usuarios');
    if (empty($meta)) {
        return;
    }

    foreach ($meta as $col => $info) {
        if (array_key_exists($col, $data)) {
            continue;
        }

        $nullAllowed = strtoupper((string)($info['Null'] ?? 'YES')) === 'YES';
        $hasDefault = array_key_exists('Default', $info) && $info['Default'] !== null;
        $extra = strtolower((string)($info['Extra'] ?? ''));
        if ($nullAllowed || $hasDefault || strpos($extra, 'auto_increment') !== false) {
            continue;
        }

        $type = strtolower((string)($info['Type'] ?? ''));
        if ($col === 'email') {
            $data[$col] = (string)$email;
            continue;
        }
        if ($col === 'usuario' || $col === 'usrlogin') {
            $data[$col] = (string)$email;
            continue;
        }
        if ($col === 'nombre') {
            $data[$col] = (string)$userName;
            continue;
        }
        if ($col === 'token') {
            $data[$col] = (string)$baseToken;
            continue;
        }
        if ($col === 'rol') {
            $opts = ab_enum_options($type);
            if (!empty($opts)) {
                if (in_array('3', $opts, true)) {
                    $data[$col] = '3';
                } elseif (in_array('cliente', $opts, true)) {
                    $data[$col] = 'cliente';
                } elseif (in_array('client', $opts, true)) {
                    $data[$col] = 'client';
                } else {
                    $data[$col] = $opts[0];
                }
            } else {
                $data[$col] = '3';
            }
            continue;
        }
        if ($col === 'role_id') {
            $data[$col] = (int)(defined('ROLE_CLIENT') ? ROLE_CLIENT : 3);
            continue;
        }
        if (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
            $data[$col] = 0;
            continue;
        }
        if (strpos($type, 'date') !== false && strpos($type, 'datetime') === false && strpos($type, 'timestamp') === false) {
            $data[$col] = date('Y-m-d');
            continue;
        }
        if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
            $data[$col] = date('Y-m-d H:i:s');
            continue;
        }
        $opts = ab_enum_options($type);
        if (!empty($opts)) {
            $data[$col] = $opts[0];
            continue;
        }
        $data[$col] = '';
    }
}

function ab_user_is_privileged($row)
{
    if (!is_array($row)) {
        return false;
    }
    if (isset($row['ppal']) && (int)$row['ppal'] === 1) {
        return true;
    }

    $roleValue = null;
    if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '') {
        $roleValue = (int)$row['role_id'];
    } elseif (isset($row['rol'])) {
        if (function_exists('normalize_role_value')) {
            $roleValue = normalize_role_value($row['rol']);
        } elseif (is_numeric($row['rol'])) {
            $roleValue = (int)$row['rol'];
        }
    }

    return ($roleValue === ROLE_ADMIN);
}

function ab_pick_reusable_user(array $rows)
{
    foreach ($rows as $row) {
        if (!ab_user_is_privileged($row) && !empty($row['id'])) {
            return $row;
        }
    }
    return null;
}

function ab_build_user_lookup($conexion, $email)
{
    $email = trim((string)$email);
    $where = [];
    $types = '';
    $params = [];

    if (ab_has_column($conexion, 'usuarios', 'email')) {
        $where[] = 'email = ?';
        $types .= 's';
        $params[] = $email;
    }
    if (ab_has_column($conexion, 'usuarios', 'usuario')) {
        $where[] = 'usuario = ?';
        $types .= 's';
        $params[] = $email;
    }

    if (empty($where)) {
        return null;
    }

    $sql = "SELECT * FROM usuarios WHERE (" . implode(' OR ', $where) . ")";
    if (ab_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " ORDER BY id ASC";

    return [
        'sql' => $sql,
        'types' => $types,
        'params' => $params,
    ];
}

function ab_find_or_create_client_user($conexion, $email, $name, $phone, &$isNewUser, &$resetToken)
{
    $lookup = ab_build_user_lookup($conexion, $email);
    if (!$lookup) {
        ab_submit_log('client_user lookup_missing_columns email=' . $email);
        return [0, 'lookup_missing_columns'];
    }

    $stmtFind = mysqli_prepare($conexion, $lookup['sql']);
    if (!$stmtFind) {
        return [0, 'lookup_prepare_failed: ' . mysqli_error($conexion)];
    }
    $types = $lookup['types'];
    $params = $lookup['params'];
    if (!ab_bind_params($stmtFind, $types, $params) || !mysqli_stmt_execute($stmtFind)) {
        $err = $stmtFind ? mysqli_stmt_error($stmtFind) : mysqli_error($conexion);
        if ($stmtFind) {
            mysqli_stmt_close($stmtFind);
        }
        return [0, 'lookup_execute_failed: ' . $err];
    }
    $resFind = mysqli_stmt_get_result($stmtFind);
    $rows = [];
    while ($resFind && ($row = mysqli_fetch_assoc($resFind))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmtFind);

    $reusable = ab_pick_reusable_user($rows);
    if ($reusable && !empty($reusable['id'])) {
        return [(int)$reusable['id'], null];
    }
    foreach ($rows as $row) {
        if (ab_user_is_privileged($row) && !empty($row['id'])) {
            return [0, 'privileged_email_conflict'];
        }
    }

    $isNewUser = true;
    $baseToken = function_exists('generate_user_token') ? generate_user_token() : ab_random_hex(16);
    $randomPassword = ab_random_hex(16);
    $passwordHash = function_exists('hash_password')
        ? hash_password($randomPassword, $baseToken)
        : password_hash($randomPassword, PASSWORD_DEFAULT);

    $data = [];
    if (ab_has_column($conexion, 'usuarios', 'usuario'))   { $data['usuario'] = $email; }
    if (ab_has_column($conexion, 'usuarios', 'password'))  { $data['password'] = $passwordHash; }
    if (ab_has_column($conexion, 'usuarios', 'avatar'))    { $data['avatar'] = 'img/perfil/default.png'; }
    if (ab_has_column($conexion, 'usuarios', 'nombre'))    { $data['nombre'] = $name !== '' ? $name : 'Client'; }
    if (ab_has_column($conexion, 'usuarios', 'activo'))    { $data['activo'] = 1; }
    if (ab_has_column($conexion, 'usuarios', 'token'))     { $data['token'] = $baseToken; }
    if (ab_has_column($conexion, 'usuarios', 'empresa'))   { $data['empresa'] = ''; }
    if (ab_has_column($conexion, 'usuarios', 'ppal'))      { $data['ppal'] = 0; }
    if (ab_has_column($conexion, 'usuarios', 'usrlogin'))  { $data['usrlogin'] = $email; }
    if (ab_has_column($conexion, 'usuarios', 'cargo'))     { $data['cargo'] = 'Cliente'; }
    if (ab_has_column($conexion, 'usuarios', 'email'))     { $data['email'] = $email; }
    if (ab_has_column($conexion, 'usuarios', 'telefono'))  { $data['telefono'] = $phone; }
    if (ab_has_column($conexion, 'usuarios', 'cambio_password')) { $data['cambio_password'] = 0; }
    if (ab_has_column($conexion, 'usuarios', 'rol')) {
        $rolMeta = ab_table_columns_meta($conexion, 'usuarios');
        $rolType = isset($rolMeta['rol']['Type']) ? strtolower((string)$rolMeta['rol']['Type']) : '';
        $rolOpts = ab_enum_options($rolType);
        if (!empty($rolOpts)) {
            if (in_array('3', $rolOpts, true)) {
                $data['rol'] = '3';
            } elseif (in_array('cliente', $rolOpts, true)) {
                $data['rol'] = 'cliente';
            } elseif (in_array('client', $rolOpts, true)) {
                $data['rol'] = 'client';
            } else {
                $data['rol'] = $rolOpts[0];
            }
        } else {
            $data['rol'] = '3';
        }
    }
    if (ab_has_column($conexion, 'usuarios', 'role_id') && ab_role_client_exists($conexion)) {
        $data['role_id'] = (int)(defined('ROLE_CLIENT') ? ROLE_CLIENT : 3);
    }

    if (empty($data['usuario']) || empty($data['password'])) {
        return [0, 'missing_required_user_columns'];
    }

    ab_fill_required_usuario_defaults($conexion, $data, $email, $name !== '' ? $name : 'Client', $baseToken);

    $columns = array_keys($data);
    $values = array_values($data);
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $types = '';
    foreach ($values as $value) {
        $types .= ab_value_type($value);
    }
    $sql = "INSERT INTO usuarios (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})";
    $stmtInsert = mysqli_prepare($conexion, $sql);
    if (!$stmtInsert) {
        return [0, 'create_prepare_failed: ' . mysqli_error($conexion)];
    }
    if (!ab_bind_params($stmtInsert, $types, $values) || !mysqli_stmt_execute($stmtInsert)) {
        $err = $stmtInsert ? mysqli_stmt_error($stmtInsert) : mysqli_error($conexion);
        $errno = $stmtInsert && function_exists('mysqli_stmt_errno') ? mysqli_stmt_errno($stmtInsert) : mysqli_errno($conexion);
        if ($stmtInsert) {
            mysqli_stmt_close($stmtInsert);
        }
        if ((int)$errno === 1062) {
            $stmtRescue = mysqli_prepare($conexion, $lookup['sql']);
            if ($stmtRescue) {
                $rescueTypes = $lookup['types'];
                $rescueParams = $lookup['params'];
                if (ab_bind_params($stmtRescue, $rescueTypes, $rescueParams) && mysqli_stmt_execute($stmtRescue)) {
                    $rescueRes = mysqli_stmt_get_result($stmtRescue);
                    $rescueRows = [];
                    while ($rescueRes && ($row = mysqli_fetch_assoc($rescueRes))) {
                        $rescueRows[] = $row;
                    }
                    $rescued = ab_pick_reusable_user($rescueRows);
                    mysqli_stmt_close($stmtRescue);
                    if ($rescued && !empty($rescued['id'])) {
                        $isNewUser = false;
                        return [(int)$rescued['id'], null];
                    }
                } else {
                    mysqli_stmt_close($stmtRescue);
                }
            }
        }
        return [0, 'create_execute_failed: ' . $err];
    }

    $userId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtInsert);
    $resetToken = $baseToken;
    return [$userId, null];
}

function ab_dynamic_insert($conexion, $table, $data, array $requiredCols = [])
{
    $result = ['ok' => false, 'id' => 0, 'error' => '', 'columns' => []];
    if (!ab_table_exists($conexion, $table)) {
        $result['error'] = 'table_not_found';
        return $result;
    }

    $columns = [];
    $values = [];
    foreach ($data as $col => $value) {
        if (ab_has_column($conexion, $table, $col)) {
            $columns[] = $col;
            $values[] = $value;
        }
    }
    $result['columns'] = $columns;

    foreach ($requiredCols as $required) {
        if (!in_array($required, $columns, true)) {
            $result['error'] = 'missing_required_' . $required;
            return $result;
        }
    }

    if (empty($columns)) {
        $result['error'] = 'no_columns';
        return $result;
    }

    $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        $result['error'] = 'prepare_failed: ' . mysqli_error($conexion);
        return $result;
    }

    $types = '';
    foreach ($values as $value) {
        $types .= ab_value_type($value);
    }
    if (!ab_bind_params($stmt, $types, $values)) {
        $result['error'] = 'bind_failed: ' . mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    if (!mysqli_stmt_execute($stmt)) {
        $result['error'] = 'execute_failed: ' . mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    $result['ok'] = true;
    $result['id'] = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $result;
}

function ab_insert_booking_request_safe($conexion, array $baseData)
{
    $primary = [
        'name' => $baseData['name'] ?? '',
        'email' => $baseData['email'] ?? '',
        'phone' => $baseData['phone'] ?? null,
        'origin' => $baseData['origin'] ?? 'agent_assisted',
        'booking_datetime' => $baseData['booking_datetime'] ?? date('Y-m-d H:i:s'),
        'destination' => $baseData['destination'] ?? null,
        'persons' => $baseData['persons'] ?? 1,
        'category' => $baseData['category'] ?? null,
        'special_request' => $baseData['special_request'] ?? null,
        'selected_offers' => $baseData['selected_offers'] ?? '[]',
        'budget' => $baseData['budget'] ?? null,
        'timeline' => $baseData['timeline'] ?? null,
        'additional_notes' => $baseData['additional_notes'] ?? null,
        'client_user_id' => $baseData['client_user_id'] ?? null,
        'creation_source' => $baseData['creation_source'] ?? 'agent_assisted',
        'created_by_agent' => $baseData['created_by_agent'] ?? null,
        'agent_channel' => $baseData['agent_channel'] ?? null,
        'terms_accepted' => array_key_exists('terms_accepted', $baseData) ? $baseData['terms_accepted'] : 0,
        'terms_accepted_at' => $baseData['terms_accepted_at'] ?? null,
        'terms_version' => $baseData['terms_version'] ?? null,
        'terms_ip' => $baseData['terms_ip'] ?? null,
        'terms_user_agent' => $baseData['terms_user_agent'] ?? null,
        'utm_source' => $baseData['utm_source'] ?? null,
        'utm_medium' => $baseData['utm_medium'] ?? null,
        'utm_campaign' => $baseData['utm_campaign'] ?? null,
        'utm_content' => $baseData['utm_content'] ?? null,
        'utm_term' => $baseData['utm_term'] ?? null,
        'cw_conversation_id' => $baseData['cw_conversation_id'] ?? null,
        'status' => $baseData['status'] ?? 'pending',
        'created_at' => $baseData['created_at'] ?? date('Y-m-d H:i:s'),
    ];

    $attemptPrimary = ab_dynamic_insert($conexion, 'booking_requests', $primary, ['name', 'email']);
    if ($attemptPrimary['ok']) {
        return $attemptPrimary;
    }

    $fallback = [
        'name' => $primary['name'],
        'email' => $primary['email'],
        'phone' => $primary['phone'],
        'origin' => $primary['origin'],
        'booking_datetime' => $primary['booking_datetime'],
        'destination' => $primary['destination'],
        'persons' => $primary['persons'],
        'category' => $primary['category'],
        'special_request' => $primary['special_request'],
        'selected_offers' => $primary['selected_offers'],
        'budget' => $primary['budget'],
        'timeline' => $primary['timeline'],
        'additional_notes' => $primary['additional_notes'],
        'client_user_id' => $primary['client_user_id'],
        'terms_accepted' => $primary['terms_accepted'],
        'status' => $primary['status'],
        'created_at' => $primary['created_at'],
    ];
    $attemptFallback = ab_dynamic_insert($conexion, 'booking_requests', $fallback, ['name', 'email']);
    if ($attemptFallback['ok']) {
        return $attemptFallback;
    }

    $minimal = [
        'name' => $primary['name'],
        'email' => $primary['email'],
        'client_user_id' => $primary['client_user_id'],
        'terms_accepted' => $primary['terms_accepted'],
        'status' => $primary['status'],
        'created_at' => $primary['created_at'],
    ];
    $attemptMinimal = ab_dynamic_insert($conexion, 'booking_requests', $minimal, ['name', 'email']);
    if ($attemptMinimal['ok']) {
        return $attemptMinimal;
    }

    return [
        'ok' => false,
        'id' => 0,
        'error' => 'primary=' . $attemptPrimary['error'] . '; fallback=' . $attemptFallback['error'] . '; minimal=' . $attemptMinimal['error'],
        'columns' => array_values(array_unique(array_merge($attemptPrimary['columns'], $attemptFallback['columns'], $attemptMinimal['columns']))),
    ];
}

function ab_escape_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ab_build_provider_case_url($requestId, $itemId)
{
    return 'https://medtravel.com.co/admin/my_booking_requests.php?item_id=' . (int)$itemId . '&request_id=' . (int)$requestId;
}

function ab_notify_provider_new_request($conexion, $bookingRequestId, array $item)
{
    $bookingRequestId = (int)$bookingRequestId;
    $itemId = (int)($item['item_id'] ?? 0);
    if ($bookingRequestId <= 0 || $itemId <= 0 || !function_exists('booking_notification_resolve_medical_offer_recipient') || !function_exists('send_interaction_email')) {
        return ['success' => false, 'error' => 'notification_dependencies_unavailable'];
    }

    $recipient = booking_notification_resolve_medical_offer_recipient($conexion, $itemId, $item);
    $providerEmail = trim((string)($recipient['email'] ?? ''));
    if (!filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => (string)($recipient['skip_reason'] ?? 'provider_email_not_found'),
            'recipient' => $recipient,
        ];
    }

    $timelineExpr = ab_has_column($conexion, 'booking_requests', 'timeline') ? 'br.timeline' : "''";
    $assignedStaffExpr = ab_has_column($conexion, 'booking_request_items', 'assigned_staff_id') ? 'bri.assigned_staff_id' : 'NULL';
    $detailSql = "SELECT
                    bri.provider_id,
                    br.name AS client_name,
                    br.email AS client_email,
                    " . (ab_has_column($conexion, 'booking_requests', 'phone') ? 'br.phone' : "''") . " AS client_phone,
                    br.destination,
                    {$timelineExpr} AS timeline,
                    COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), CONCAT('Item #', bri.id)) AS item_name,
                    {$assignedStaffExpr} AS assigned_staff_id
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                LEFT JOIN service_catalog sc ON sc.id = o.service_id
                WHERE bri.id = ? AND bri.booking_request_id = ?
                LIMIT 1";
    $stmtDetail = mysqli_prepare($conexion, $detailSql);
    if ($stmtDetail) {
        mysqli_stmt_bind_param($stmtDetail, 'ii', $itemId, $bookingRequestId);
        if (mysqli_stmt_execute($stmtDetail)) {
            $resDetail = mysqli_stmt_get_result($stmtDetail);
            $detailRow = $resDetail ? mysqli_fetch_assoc($resDetail) : null;
            if (is_array($detailRow)) {
                $item = array_merge($detailRow, $item);
            }
        }
        mysqli_stmt_close($stmtDetail);
    }

    $requestMeta = function_exists('interaction_email_request_meta')
        ? interaction_email_request_meta($conexion, 'ITEM', $bookingRequestId, $itemId)
        : [];
    $itemTitle = trim((string)($requestMeta['title'] ?? ''));
    if ($itemTitle === '') {
        $itemTitle = trim((string)($item['item_name'] ?? 'Item #' . $itemId));
    }
    $patientName = trim((string)($item['client_name'] ?? 'Paciente'));
    $patientEmail = trim((string)($item['client_email'] ?? ''));
    $patientPhone = trim((string)($item['client_phone'] ?? ''));
    $destination = trim((string)($item['destination'] ?? ''));
    $timeline = trim((string)($item['timeline'] ?? ''));
    $assignedStaffId = (int)($item['assigned_staff_id'] ?? 0);
    $assignedStaff = $assignedStaffId > 0 && function_exists('provider_staff_fetch_basic_row')
        ? provider_staff_fetch_basic_row($conexion, $assignedStaffId, (int)($item['provider_id'] ?? 0))
        : null;
    $assignedStaffName = trim((string)($assignedStaff['full_name'] ?? ''));
    $caseUrl = ab_build_provider_case_url($bookingRequestId, $itemId);

    $contentHtml = '<p>A new booking request has been created and is waiting for provider review.</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Case:</strong> ' . ab_escape_html($itemTitle) . '</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Patient:</strong> ' . ab_escape_html($patientName) . '</p>'
        . ($patientEmail !== '' ? '<p style="margin:0 0 6px 0;"><strong>Email:</strong> ' . ab_escape_html($patientEmail) . '</p>' : '')
        . ($patientPhone !== '' ? '<p style="margin:0 0 6px 0;"><strong>Phone:</strong> ' . ab_escape_html($patientPhone) . '</p>' : '')
        . ($destination !== '' ? '<p style="margin:0 0 6px 0;"><strong>Destination:</strong> ' . ab_escape_html($destination) . '</p>' : '')
        . ($timeline !== '' ? '<p style="margin:0 0 6px 0;"><strong>Timeline:</strong> ' . ab_escape_html($timeline) . '</p>' : '')
        . ($assignedStaffName !== '' ? '<p style="margin:0 0 16px 0;"><strong>Assigned staff:</strong> ' . ab_escape_html($assignedStaffName) . '</p>' : '<p style="margin:0 0 16px 0;"><strong>Assigned staff:</strong> Pending assignment</p>')
        . '<p>Please open the provider portal to review the case details and continue the workflow.</p>';

    $textBody = "A new booking request has been created and is waiting for provider review.\n\n"
        . 'Case: ' . $itemTitle . "\n"
        . 'Patient: ' . $patientName . "\n"
        . ($patientEmail !== '' ? 'Email: ' . $patientEmail . "\n" : '')
        . ($patientPhone !== '' ? 'Phone: ' . $patientPhone . "\n" : '')
        . ($destination !== '' ? 'Destination: ' . $destination . "\n" : '')
        . ($timeline !== '' ? 'Timeline: ' . $timeline . "\n" : '')
        . 'Assigned staff: ' . ($assignedStaffName !== '' ? $assignedStaffName : 'Pending assignment') . "\n\n"
        . 'Open case: ' . $caseUrl;

    return send_interaction_email(
        $providerEmail,
        'New booking request received - case #' . $bookingRequestId,
        $contentHtml,
        $textBody,
        [
            'preheader' => 'A new case is waiting for provider review in MedTravel.',
            'cta' => ['text' => 'Open case in MedTravel', 'url' => $caseUrl],
            'footer_note' => 'Please handle the case through your MedTravel portal.',
            'sender_label' => 'MedTravel Coordination Team',
            'event' => 'booking_medical_offer_created',
            'recipient_source' => (string)($recipient['source'] ?? ''),
        ],
        $conexion
    );
}

// ── action: lookup ────────────────────────────────────────────────────────────

if ($action === 'lookup') {
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ab_json_response(['found' => false]);
    }

    $lookup = ab_build_user_lookup($conexion, $email);
    if (!$lookup) {
        ab_json_response(['found' => false]);
    }

    $stmt = mysqli_prepare($conexion, $lookup['sql']);
    if ($stmt && ab_bind_params($stmt, $lookup['types'], $lookup['params']) && mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        $reusable = ab_pick_reusable_user($rows);
        if ($reusable) {
            ab_json_response([
                'found'    => true,
                'id'       => (int)$reusable['id'],
                'nombre'   => (string)($reusable['nombre'] ?? ''),
                'telefono' => (string)($reusable['telefono'] ?? ''),
            ]);
        }

        foreach ($rows as $row) {
            if (ab_user_is_privileged($row)) {
                ab_json_response([
                    'found' => false,
                    'conflict' => true,
                    'code' => 'PATIENT_EMAIL_CONFLICT',
                    'message' => 'This email belongs to an internal MedTravel user. The booking can be created, but no patient portal account will be linked until a patient email is provided.',
                ]);
            }
        }
    } elseif ($stmt) {
        mysqli_stmt_close($stmt);
    }
    ab_json_response(['found' => false]);
}

// ── action: get_services ─────────────────────────────────────────────────────

if ($action === 'get_services') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    if ($categoryId <= 0) {
        ab_json_error('category_id required');
    }

    $categoryRow = ab_load_category_row($conexion, $categoryId);
    if (!$categoryRow) {
        ab_json_error('Category not found or inactive', 404);
    }

    $services = ab_fetch_services_for_category($conexion, $categoryId);
    ab_json_response([
        'success' => true,
        'category_id' => $categoryId,
        'category_name' => (string)$categoryRow['name'],
        'services' => $services,
        'message' => empty($services) ? 'No active services are available for this category.' : '',
    ]);
}

// ── action: get_offers ───────────────────────────────────────────────────────
// Returns active offers for a given category + service pair.

if ($action === 'get_offers') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $serviceId = (int)($_POST['service_id'] ?? 0);
    if ($categoryId <= 0) {
        ab_json_error('category_id required');
    }
    if ($serviceId <= 0) {
        ab_json_error('service_id required');
    }

    $categoryRow = ab_load_category_row($conexion, $categoryId);
    if (!$categoryRow) {
        ab_json_error('Category not found or inactive', 404);
    }

    $svcRow = ab_load_service_row($conexion, $serviceId, $categoryId);
    if (!$svcRow) {
        ab_json_error('Selected service does not belong to the chosen category or is inactive', 422);
    }

    $offerConditions = ab_status_conditions($conexion, 'provider_service_offers', 'o');
    $providerConditions = ab_status_conditions($conexion, 'providers', 'p');

    $offersSql = "SELECT o.id,
                         COALESCE(NULLIF(o.title,''), sc.name, CONCAT('Offer #',o.id)) AS offer_title,
                         p.name AS provider_name,
                         COALESCE(o.price_from, 0) AS price_from,
                         COALESCE(NULLIF(o.currency,''),'USD') AS currency
                  FROM provider_service_offers o
                  INNER JOIN providers p ON p.id = o.provider_id
                  INNER JOIN service_catalog sc ON sc.id = o.service_id
                  WHERE o.service_id = ?
                    AND sc.category_id = ?";
    if (!empty($offerConditions)) {
        $offersSql .= ' AND ' . implode(' AND ', $offerConditions);
    }
    if (!empty($providerConditions)) {
        $offersSql .= ' AND ' . implode(' AND ', $providerConditions);
    }
    $offersSql .= "
                  ORDER BY p.name ASC, offer_title ASC";
    $stmtOffers = mysqli_prepare($conexion, $offersSql);
    if (!$stmtOffers) {
        ab_json_error('DB prepare error', 500);
    }
    mysqli_stmt_bind_param($stmtOffers, 'ii', $serviceId, $categoryId);
    if (!mysqli_stmt_execute($stmtOffers)) {
        $err = mysqli_stmt_error($stmtOffers);
        mysqli_stmt_close($stmtOffers);
        ab_json_error('Failed to load offers: ' . $err, 500);
    }
    $resOffers = mysqli_stmt_get_result($stmtOffers);
    $offers = [];
    while ($resOffers && ($row = mysqli_fetch_assoc($resOffers))) {
        $offers[] = [
            'id'       => (int)$row['id'],
            'title'    => (string)$row['offer_title'],
            'provider' => (string)$row['provider_name'],
            'price'    => is_numeric($row['price_from']) ? round((float)$row['price_from'], 2) : 0,
            'currency' => strtoupper(trim((string)$row['currency'])),
        ];
    }
    mysqli_stmt_close($stmtOffers);

    ab_json_response([
        'success' => true,
        'category_id' => $categoryId,
        'service_id' => $serviceId,
        'service_name' => (string)($svcRow['name'] ?? ''),
        'offers' => $offers,
        'message' => empty($offers) ? 'No active offers are available for this service.' : '',
    ]);
}

// ── action: submit ────────────────────────────────────────────────────────────

if ($action === 'submit') {
    // ── Collect and sanitize input ────────────────────────────────────────────
    $email          = trim((string)($_POST['email'] ?? ''));
    $name           = trim((string)($_POST['name'] ?? ''));
    $phone          = trim((string)($_POST['phone'] ?? ''));
    $origin         = trim((string)($_POST['origin'] ?? 'agent_assisted'));
    $destination    = trim((string)($_POST['destination'] ?? 'Armenia, Quindío'));
    $persons        = max(1, (int)($_POST['persons'] ?? 1));
    $categoryId     = (int)($_POST['category_id'] ?? 0);
    $serviceId      = (int)($_POST['service_id'] ?? 0);
    $specialRequest = trim((string)($_POST['special_request'] ?? ''));
    $timelineFrom   = trim((string)($_POST['timeline_from'] ?? ''));
    $timelineTo     = trim((string)($_POST['timeline_to'] ?? ''));
    $agentChannel      = trim((string)($_POST['agent_channel'] ?? 'other'));
    $agentUserId       = (int)($_SESSION['id_usuario'] ?? 0);
    $cwConversationId  = trim((string)($_POST['cw_conversation_id'] ?? ''));
    $cwContactId       = trim((string)($_POST['cw_contact_id'] ?? ''));

    // Offer IDs
    $rawOffers = $_POST['selected_offers'] ?? [];
    if (!is_array($rawOffers)) $rawOffers = [$rawOffers];
    $selectedOffers = array_values(array_unique(array_filter(array_map('intval', $rawOffers))));

    // ── Validate required fields ──────────────────────────────────────────────
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ab_json_error('Valid patient email is required.');
    }
    if ($name === '') {
        ab_json_error('Patient full name is required.');
    }
    if ($categoryId <= 0) {
        ab_json_error('A category must be selected.');
    }
    if ($serviceId <= 0) {
        ab_json_error('A medical service must be selected.');
    }
    if (empty($selectedOffers)) {
        ab_json_error('At least one active offer must be selected.', 422, ['code' => 'OFFER_REQUIRED']);
    }

    $categoryRow = ab_load_category_row($conexion, $categoryId);
    if (!$categoryRow) {
        ab_json_error('Selected category not found or inactive.', 404);
    }

    // ── Validate service exists, is active, and belongs to category ──────────
    $svcCheckRow = ab_load_service_row($conexion, $serviceId, $categoryId);
    if (!$svcCheckRow) {
        ab_json_error('Selected service does not belong to the chosen category or is inactive.', 422);
    }
    $category = (string)$svcCheckRow['category_name']; // derived from service catalog — not free text

    // ── Backend validation: each selected offer must belong to the chosen service ─
    if (!ab_selected_offers_are_valid($conexion, $serviceId, $selectedOffers)) {
        ab_json_error('One or more selected offers do not belong to the chosen service or are no longer active.', 422);
    }

    // ── Build timeline string ─────────────────────────────────────────────────
    $timeline = '';
    if ($timelineFrom !== '' && $timelineTo !== '') {
        $timeline = $timelineFrom . ' to ' . $timelineTo;
    } elseif ($timelineFrom !== '') {
        $timeline = 'From ' . $timelineFrom;
    } elseif ($timelineTo !== '') {
        $timeline = 'Until ' . $timelineTo;
    }

    $bookingDatetime = $timelineFrom !== '' ? $timelineFrom . ' 00:00:00' : date('Y-m-d H:i:s');

    // ── Find or create client user ────────────────────────────────────────────
    $clientUserId = 0;
    $isNewUser = false;
    $resetToken = '';
    $accountWarningCode = '';
    $accountWarningMessage = '';
    list($clientUserId, $clientUserError) = ab_find_or_create_client_user($conexion, $email, $name, $phone, $isNewUser, $resetToken);
    if ($clientUserId <= 0) {
        ab_submit_log('client_user_failed email=' . $email . ' error=' . (string)$clientUserError);
        if ($clientUserError === 'privileged_email_conflict') {
            $accountWarningCode = 'PATIENT_EMAIL_CONFLICT';
            $accountWarningMessage = 'Booking will be created without linking a patient portal account because this email belongs to an internal MedTravel user.';
            $clientUserId = 0;
            $isNewUser = false;
            $resetToken = '';
        }
        if ($clientUserId <= 0 && ($clientUserError === 'lookup_missing_columns' || $clientUserError === 'missing_required_user_columns')) {
            ab_json_error('Patient account creation is not available in this environment. Check the usuarios schema.', 500, [
                'code' => 'PATIENT_ACCOUNT_SCHEMA_ERROR',
                'detail' => (string)$clientUserError,
            ]);
        }
        if ($clientUserId <= 0 && $accountWarningCode === '') {
            ab_json_error('Failed to create or reuse patient account.', 500, [
                'code' => 'PATIENT_ACCOUNT_CREATE_FAILED',
                'detail' => (string)$clientUserError,
            ]);
        }
    }

    // ── Set password reset token (if new user or to trigger set-password flow) ─
    if ($isNewUser && $clientUserId > 0 && $resetToken === '') {
        if (ab_has_column($conexion, 'usuarios', 'password_reset_token') && ab_has_column($conexion, 'usuarios', 'password_reset_expires_at')) {
            $resetToken  = ab_random_hex(32);
            $resetExpiry = date('Y-m-d H:i:s', time() + 86400);
            $stmtRst = mysqli_prepare($conexion, "UPDATE usuarios SET password_reset_token=?, password_reset_expires_at=? WHERE id=? LIMIT 1");
            if ($stmtRst) {
                mysqli_stmt_bind_param($stmtRst, 'ssi', $resetToken, $resetExpiry, $clientUserId);
                mysqli_stmt_execute($stmtRst);
                mysqli_stmt_close($stmtRst);
            }
        } elseif (ab_has_column($conexion, 'usuarios', 'token')) {
            $resetToken = ab_random_hex(16);
            $stmtRst = mysqli_prepare($conexion, "UPDATE usuarios SET token=? WHERE id=? LIMIT 1");
            if ($stmtRst) {
                mysqli_stmt_bind_param($stmtRst, 'si', $resetToken, $clientUserId);
                mysqli_stmt_execute($stmtRst);
                mysqli_stmt_close($stmtRst);
            }
        }
    }

    // ── Insert booking_request ────────────────────────────────────────────────
    $selectedOffersJson = json_encode($selectedOffers);

    $brData = [
        'name'             => $name,
        'email'            => $email,
        'phone'            => $phone,
        'origin'           => $origin !== '' ? $origin : 'agent_assisted',
        'booking_datetime' => $bookingDatetime,
        'destination'      => $destination,
        'persons'          => $persons,
        'category'         => $category,
        'special_request'  => $specialRequest,
        'selected_offers'  => $selectedOffersJson,
        'budget'           => null,
        'timeline'         => $timeline,
        'additional_notes' => '',
        // Agent traceability
        'creation_source'  => 'agent_assisted',
        'created_by_agent' => $agentUserId > 0 ? $agentUserId : null,
        'agent_channel'    => $agentChannel,
        // terms: intentionally 0 — client must accept personally
        'terms_accepted'   => 0,
        'terms_accepted_at'=> null,
        'terms_version'    => null,
        'terms_ip'         => null,
        'terms_user_agent' => null,
        // link to client user
        'client_user_id'   => $clientUserId > 0 ? $clientUserId : null,
        // UTM: record agent channel as utm_medium for analytics
        'utm_source'   => 'agent',
        'utm_medium'   => $agentChannel,
        'utm_campaign' => '',
        'utm_content'  => '',
        'utm_term'     => '',
        // ConectarBot/Chatwoot traceability
        'cw_conversation_id' => $cwConversationId !== '' ? $cwConversationId : null,
    ];

    $bookingInsert = ab_insert_booking_request_safe($conexion, $brData);
    if (empty($bookingInsert['ok']) || (int)($bookingInsert['id'] ?? 0) <= 0) {
        $bookingInsertError = (string)($bookingInsert['error'] ?? 'unknown_error');
        ab_submit_log('booking_request_insert_failed email=' . $email . ' error=' . $bookingInsertError);
        ab_json_error('Failed to create booking request.', 500, [
            'code' => 'BOOKING_REQUEST_CREATE_FAILED',
            'detail' => $bookingInsertError,
        ]);
    }
    $bookingRequestId = (int)$bookingInsert['id'];

    // ── Insert booking_request_items ──────────────────────────────────────────
    $createdItems = [];
    if (!empty($selectedOffers) && ab_table_exists($conexion, 'booking_request_items')) {
        $offerMetaConditions = ab_status_conditions($conexion, 'provider_service_offers', 'o');
        $providerMetaConditions = ab_status_conditions($conexion, 'providers', 'p');
        $offerMetaSql = "SELECT
                            o.provider_id,
                            o.service_id,
                            " . (ab_has_column($conexion, 'provider_service_offers', 'provider_catalog_service_id') ? 'o.provider_catalog_service_id' : 'NULL AS provider_catalog_service_id') . ",
                            o.price_from,
                            COALESCE(NULLIF(o.currency,''), 'USD') AS currency
                         FROM provider_service_offers o
                         INNER JOIN providers p ON p.id = o.provider_id
                         WHERE o.id = ?";
        if (!empty($offerMetaConditions)) {
            $offerMetaSql .= ' AND ' . implode(' AND ', $offerMetaConditions);
        }
        if (!empty($providerMetaConditions)) {
            $offerMetaSql .= ' AND ' . implode(' AND ', $providerMetaConditions);
        }
        $offerMetaSql .= ' LIMIT 1';

        foreach ($selectedOffers as $offerId) {
            $offerId = (int)$offerId;
            if ($offerId <= 0) continue;

            // Fetch offer metadata
            $stmtOff = mysqli_prepare($conexion, $offerMetaSql);
            if (!$stmtOff) continue;
            mysqli_stmt_bind_param($stmtOff, 'i', $offerId);
            if (!mysqli_stmt_execute($stmtOff)) { mysqli_stmt_close($stmtOff); continue; }
            $resOff = mysqli_stmt_get_result($stmtOff);
            $offerRow = $resOff ? mysqli_fetch_assoc($resOff) : null;
            mysqli_stmt_close($stmtOff);
            if (!$offerRow || empty($offerRow['provider_id'])) continue;

            $providerId  = (int)$offerRow['provider_id'];
            $serviceId   = (int)($offerRow['service_id'] ?? 0);
            $providerCatalogServiceId = (int)($offerRow['provider_catalog_service_id'] ?? 0);
            $price       = is_numeric($offerRow['price_from']) ? round((float)$offerRow['price_from'], 2) : null;
            $currency    = strtoupper(trim((string)($offerRow['currency'] ?? 'USD')));

            $itemCols = ['booking_request_id', 'item_type', 'offer_id', 'provider_id', 'item_status', 'created_at'];
            $itemVals = [$bookingRequestId, 'medical_offer', $offerId, $providerId, 'pending_provider', date('Y-m-d H:i:s')];
            $itemTypes = 'isiiss';

            if (ab_has_column($conexion, 'booking_request_items', 'proposed_price')) {
                $itemCols[] = 'proposed_price'; $itemVals[] = $price; $itemTypes .= ab_value_type($price);
            }
            if (ab_has_column($conexion, 'booking_request_items', 'currency')) {
                $itemCols[] = 'currency'; $itemVals[] = $currency; $itemTypes .= 's';
            }
            if (ab_has_column($conexion, 'booking_request_items', 'service_provider_id')) {
                $itemCols[] = 'service_provider_id'; $itemVals[] = null; $itemTypes .= 's';
            }

            $iSql2 = "INSERT INTO booking_request_items (`" . implode('`,`', $itemCols) . "`) VALUES (" . implode(',', array_fill(0, count($itemVals), '?')) . ")";
            $stmtItem = mysqli_prepare($conexion, $iSql2);
            if (!$stmtItem || !ab_bind_params($stmtItem, $itemTypes, $itemVals) || !mysqli_stmt_execute($stmtItem)) {
                if ($stmtItem) mysqli_stmt_close($stmtItem);
                continue;
            }
            $itemId = (int)mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtItem);
            $assignedStaffId = ab_assign_initial_staff_to_item(
                $conexion,
                $itemId,
                $providerId,
                $providerCatalogServiceId,
                $serviceId
            );
            $createdItems[] = [
                'item_id' => $itemId,
                'offer_id' => $offerId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'provider_catalog_service_id' => $providerCatalogServiceId,
                'assigned_staff_id' => $assignedStaffId,
            ];
        }
    }

    // ── Send credentials email to patient ────────────────────────────────────
    if ($isNewUser && $clientUserId > 0) {
        $resetUrl = 'https://medtravel.com.co/set_password.php' . ($resetToken !== '' ? '?token=' . urlencode($resetToken) : '');
        $loginUrl = 'https://medtravel.com.co/login.php';

        $subjectEmail = "Your MedTravel booking (case #{$bookingRequestId}) — Activate your account";
        $bodyHtml = '';
        if (function_exists('renderMedTravelEmail')) {
            $contentHtml = '<p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>A MedTravel coordinator has created a booking on your behalf (Case #' . $bookingRequestId . ').</p>'
                . '<p>To track your case and manage your appointments, please activate your patient portal account by creating a password:</p>'
                . '<p><strong>Username:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Important:</strong> On your first login you will be asked to review and accept the MedTravel Terms of Service to complete the activation.</p>'
                . '<p style="margin:16px 0;"><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="background:#0b4ea2;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;font-weight:bold;">Create my password</a></p>'
                . '<p style="font-size:12px;color:#666;">If the button does not work, copy and paste this link: ' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '<br>This link expires in 24 hours.</p>'
                . '<p>After activation, sign in at: <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';

            $bodyHtml = renderMedTravelEmail(
                'Your booking has been created',
                'Activate your patient portal account',
                $contentHtml,
                'This is an automated message from MedTravel.',
                ['text' => 'Create my password', 'url' => $resetUrl]
            );
        }

        if ($bodyHtml === '') {
            $bodyHtml = '<h2>Your MedTravel booking</h2>'
                . '<p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>A MedTravel coordinator has opened case #' . $bookingRequestId . ' on your behalf.</p>'
                . '<p><strong>Activate your account:</strong> <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
                . '<p>You will need to accept the Terms of Service on first login.</p>';
        }

        $altBody = "Hello {$name},\n\n"
            . "A MedTravel coordinator has created booking #{$bookingRequestId} on your behalf.\n\n"
            . "Activate your account:\n{$resetUrl}\n\n"
            . "Note: You will be asked to accept the Terms of Service on first login.\n\n"
            . "Sign in at: {$loginUrl}\n";

        try {
            sendEmail($email, $subjectEmail, $bodyHtml, 'patientcare', ['alt_body' => $altBody, 'password_reset_url' => $resetUrl], $conexion);
        } catch (Exception $ex) {
            error_log('booking_asistido: credentials email failed for user_id=' . $clientUserId . ' email=' . $email . ': ' . $ex->getMessage());
        }
    }

    // ── Provider notifications ────────────────────────────────────────────────
    if (!empty($createdItems)) {
        foreach ($createdItems as $item) {
            $notifyResult = ab_notify_provider_new_request($conexion, $bookingRequestId, $item);
            if (empty($notifyResult['success'])) {
                ab_submit_log(
                    'provider_notify_failed request_id=' . $bookingRequestId
                    . ' item_id=' . (int)($item['item_id'] ?? 0)
                    . ' error=' . (string)($notifyResult['error'] ?? 'unknown_error')
                );
            }
        }
    }

    $patientcareAlert = interaction_email_send_patientcare_booking_alert($conexion, $bookingRequestId, [
        'patient_name' => $name,
        'patient_email' => $email,
        'patient_phone' => $phone,
        'destination' => $destination,
        'timeline' => $timeline,
        'creation_source' => 'agent_assisted',
        'agent_channel' => $agentChannel,
        'items_count' => count($createdItems),
        'medical_items_count' => count($createdItems),
        'complementary_items_count' => 0,
    ]);
    if (is_array($patientcareAlert) && empty($patientcareAlert['success'])) {
        ab_submit_log(
            'patientcare_alert_failed request_id=' . $bookingRequestId
            . ' error=' . (string)($patientcareAlert['error'] ?? 'unknown_error')
        );
    }

    // ── Success response ──────────────────────────────────────────────────────
    ab_json_response([
        'success'          => true,
        'booking_id'       => $bookingRequestId,
        'client_user_id'   => $clientUserId,
        'is_new_user'      => $isNewUser,
        'items_created'    => count($createdItems),
        'warning_code'     => $accountWarningCode,
        'warning_message'  => $accountWarningMessage,
        'credentials_sent' => ($isNewUser && $clientUserId > 0),
        'message'          => $isNewUser
            ? 'Booking created. Credentials sent to patient.'
            : ($accountWarningCode !== ''
                ? 'Booking created. No patient portal account was linked because the email belongs to an internal MedTravel user.'
                : ($clientUserId > 0
                    ? 'Booking created. Existing patient account reused.'
                    : 'Booking created.'))
        ,
    ]);
}

// ── Unknown action ────────────────────────────────────────────────────────────
ab_json_error('Unknown action', 400);
