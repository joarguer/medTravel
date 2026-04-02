<?php
/**
 * AJAX handler — Agent-assisted booking
 *
 * Actions:
 *   lookup  — check if email already exists as a client user
 *   submit  — create booking_request + items + client user + send credentials
 *
 * Security: requires valid admin session with PERM_BOOKING_MANAGE.
 * Terms acceptance: deliberately NOT set on behalf of client (terms_accepted = 0).
 * The client must personally accept on first login via the terms gate.
 */

@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);

require_once __DIR__ . '/../include/conexion.php';
require_once __DIR__ . '/../include/roles.php';
require_once __DIR__ . '/../include/password_utils.php';
require_once __DIR__ . '/../include/email_config.php';
require_once __DIR__ . '/../../inc/email_template.php';

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

if (!user_can(PERM_BOOKING_MANAGE)) {
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

// ── action: lookup ────────────────────────────────────────────────────────────

if ($action === 'lookup') {
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ab_json_response(['found' => false]);
    }

    $whereParts = [];
    $types = '';
    $params = [];
    if (ab_has_column($conexion, 'usuarios', 'email')) {
        $whereParts[] = 'email = ?'; $types .= 's'; $params[] = $email;
    }
    if (ab_has_column($conexion, 'usuarios', 'usuario')) {
        $whereParts[] = 'usuario = ?'; $types .= 's'; $params[] = $email;
    }
    if (empty($whereParts)) {
        ab_json_response(['found' => false]);
    }

    $sql = "SELECT id, nombre, telefono FROM usuarios WHERE (" . implode(' OR ', $whereParts) . ")";
    if (ab_has_column($conexion, 'usuarios', 'is_deleted')) $sql .= " AND is_deleted = 0";
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if ($stmt && ab_bind_params($stmt, $types, $params) && mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row) {
            ab_json_response([
                'found'    => true,
                'id'       => (int)$row['id'],
                'nombre'   => (string)($row['nombre'] ?? ''),
                'telefono' => (string)($row['telefono'] ?? ''),
            ]);
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
    $agentChannel   = trim((string)($_POST['agent_channel'] ?? 'other'));
    $agentUserId    = (int)($_SESSION['id_usuario'] ?? 0);

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
    $isNewUser    = false;
    $resetToken   = '';

    // Look up by email
    $lookupWhere = [];
    $lookupTypes = '';
    $lookupParams = [];
    if (ab_has_column($conexion, 'usuarios', 'email')) {
        $lookupWhere[] = 'email = ?'; $lookupTypes .= 's'; $lookupParams[] = $email;
    }
    if (ab_has_column($conexion, 'usuarios', 'usuario')) {
        $lookupWhere[] = 'usuario = ?'; $lookupTypes .= 's'; $lookupParams[] = $email;
    }

    if (!empty($lookupWhere)) {
        $lookupSql = "SELECT id, rol, role_id, ppal FROM usuarios WHERE (" . implode(' OR ', $lookupWhere) . ")";
        if (ab_has_column($conexion, 'usuarios', 'is_deleted')) $lookupSql .= " AND is_deleted = 0";
        $lookupSql .= " ORDER BY id ASC LIMIT 5";

        $stmtLookup = mysqli_prepare($conexion, $lookupSql);
        if ($stmtLookup && ab_bind_params($stmtLookup, $lookupTypes, $lookupParams) && mysqli_stmt_execute($stmtLookup)) {
            $resLookup = mysqli_stmt_get_result($stmtLookup);
            while ($resLookup && ($lu = mysqli_fetch_assoc($resLookup))) {
                // Skip privileged users
                $roleId = (int)($lu['role_id'] ?? 0);
                if ((int)($lu['ppal'] ?? 0) === 1) continue;
                if ($roleId === ROLE_ADMIN || $roleId === ROLE_ADMINISTRATIVE) continue;
                $clientUserId = (int)$lu['id'];
                break;
            }
            mysqli_stmt_close($stmtLookup);
        } elseif ($stmtLookup) {
            mysqli_stmt_close($stmtLookup);
        }
    }

    if ($clientUserId === 0) {
        // Create new client user
        $isNewUser = true;
        $baseToken   = ab_random_hex(16);
        $randPassword = ab_random_hex(16);
        $passwordHash = function_exists('hash_password')
            ? hash_password($randPassword, $baseToken)
            : password_hash($randPassword, PASSWORD_DEFAULT);

        $userData = [];
        if (ab_has_column($conexion, 'usuarios', 'usuario'))   $userData['usuario']   = $email;
        if (ab_has_column($conexion, 'usuarios', 'email'))     $userData['email']     = $email;
        if (ab_has_column($conexion, 'usuarios', 'nombre'))    $userData['nombre']    = $name;
        if (ab_has_column($conexion, 'usuarios', 'password'))  $userData['password']  = $passwordHash;
        if (ab_has_column($conexion, 'usuarios', 'token'))     $userData['token']     = $baseToken;
        if (ab_has_column($conexion, 'usuarios', 'telefono'))  $userData['telefono']  = $phone;
        if (ab_has_column($conexion, 'usuarios', 'activo'))    $userData['activo']    = 1;
        if (ab_has_column($conexion, 'usuarios', 'ppal'))      $userData['ppal']      = 0;
        if (ab_has_column($conexion, 'usuarios', 'empresa'))   $userData['empresa']   = '';
        if (ab_has_column($conexion, 'usuarios', 'cargo'))     $userData['cargo']     = 'Cliente';
        if (ab_has_column($conexion, 'usuarios', 'avatar'))    $userData['avatar']    = 'img/perfil/default.png';
        if (ab_has_column($conexion, 'usuarios', 'cambio_password')) $userData['cambio_password'] = 0;
        if (ab_has_column($conexion, 'usuarios', 'usrlogin'))  $userData['usrlogin']  = $email;
        // terms_accepted intentionally left at DEFAULT 0 — client must accept personally
        if (ab_has_column($conexion, 'usuarios', 'role_id')) {
            $userData['role_id'] = defined('ROLE_CLIENT') ? ROLE_CLIENT : 3;
        }
        if (ab_has_column($conexion, 'usuarios', 'rol')) {
            $userData['rol'] = '3';
        }

        if (empty($userData['usuario']) || empty($userData['password'])) {
            ab_json_error('Cannot create user account (missing required columns).', 500);
        }

        $insertCols = array_keys($userData);
        $insertVals = array_values($userData);
        $iTypes = implode('', array_map('ab_value_type', $insertVals));
        $iPlaceholders = implode(',', array_fill(0, count($insertVals), '?'));
        $iSql = "INSERT INTO usuarios (`" . implode('`,`', $insertCols) . "`) VALUES ({$iPlaceholders})";
        $stmtIns = mysqli_prepare($conexion, $iSql);
        if (!$stmtIns || !ab_bind_params($stmtIns, $iTypes, $insertVals) || !mysqli_stmt_execute($stmtIns)) {
            $errMsg = $stmtIns ? mysqli_stmt_error($stmtIns) : mysqli_error($conexion);
            if ($stmtIns) mysqli_stmt_close($stmtIns);
            // Duplicate email — try rescue lookup
            if (strpos($errMsg, '1062') !== false || mysqli_errno($conexion) === 1062) {
                // Re-lookup
                $stmtRescue = mysqli_prepare($conexion, $lookupSql ?? "SELECT id FROM usuarios WHERE email = ? LIMIT 1");
                if ($stmtRescue && ab_bind_params($stmtRescue, $lookupTypes, $lookupParams) && mysqli_stmt_execute($stmtRescue)) {
                    $resR = mysqli_stmt_get_result($stmtRescue);
                    $rowR = $resR ? mysqli_fetch_assoc($resR) : null;
                    mysqli_stmt_close($stmtRescue);
                    if ($rowR) { $clientUserId = (int)$rowR['id']; $isNewUser = false; }
                } elseif ($stmtRescue) { mysqli_stmt_close($stmtRescue); }
            }
            if ($clientUserId === 0) {
                ab_json_error('Failed to create patient account: ' . $errMsg, 500);
            }
        } else {
            $clientUserId = (int)mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtIns);
        }
        $resetToken = $baseToken;
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
    ];

    // Build columns dynamically based on what exists in the table
    $brCols = [];
    $brVals = [];
    foreach ($brData as $col => $val) {
        if (ab_has_column($conexion, 'booking_requests', $col)) {
            $brCols[] = $col;
            $brVals[] = $val;
        }
    }

    // Verify required columns present
    $requiredBrCols = ['name', 'email', 'phone', 'origin', 'booking_datetime', 'destination', 'persons', 'category', 'special_request', 'selected_offers', 'budget', 'timeline', 'additional_notes'];
    foreach ($requiredBrCols as $req) {
        if (!in_array($req, $brCols, true)) {
            ab_json_error("Missing required column: {$req}. Run the migration SQL.", 500);
        }
    }

    $brTypes       = implode('', array_map('ab_value_type', $brVals));
    $brPlaceholders = implode(',', array_fill(0, count($brVals), '?'));
    $brSql = "INSERT INTO booking_requests (`" . implode('`,`', $brCols) . "`) VALUES ({$brPlaceholders})";

    $stmtBr = mysqli_prepare($conexion, $brSql);
    if (!$stmtBr || !ab_bind_params($stmtBr, $brTypes, $brVals) || !mysqli_stmt_execute($stmtBr)) {
        $errMsg = $stmtBr ? mysqli_stmt_error($stmtBr) : mysqli_error($conexion);
        if ($stmtBr) mysqli_stmt_close($stmtBr);
        ab_json_error('Failed to create booking request: ' . $errMsg, 500);
    }
    $bookingRequestId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtBr);

    // ── Insert booking_request_items ──────────────────────────────────────────
    $createdItems = [];
    if (!empty($selectedOffers) && ab_table_exists($conexion, 'booking_request_items')) {
        $offerMetaConditions = ab_status_conditions($conexion, 'provider_service_offers', 'o');
        $providerMetaConditions = ab_status_conditions($conexion, 'providers', 'p');
        $offerMetaSql = "SELECT
                            o.provider_id,
                            o.service_id,
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
            $createdItems[] = ['item_id' => $itemId, 'offer_id' => $offerId, 'provider_id' => $providerId];
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
        // Include provider notification helper from submit.php functions if available
        $submitPath = __DIR__ . '/../../booking/submit.php';
        if (!defined('BOOKING_SUBMIT_FNDEF') && file_exists($submitPath)) {
            define('BOOKING_SUBMIT_FNDEF', 1);
            // Only load function definitions — the execution block checks REQUEST_METHOD POST
            // and won't re-run since we're in an AJAX POST context. But to be safe we spoof:
            $savedMethod = $_SERVER['REQUEST_METHOD'] ?? 'POST';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            @include_once $submitPath;
            $_SERVER['REQUEST_METHOD'] = $savedMethod;
        }

        foreach ($createdItems as $item) {
            if (function_exists('booking_notify_provider_new_request_local')) {
                booking_notify_provider_new_request_local($conexion, $bookingRequestId, $item);
            }
        }
    }

    // ── Success response ──────────────────────────────────────────────────────
    ab_json_response([
        'success'          => true,
        'booking_id'       => $bookingRequestId,
        'client_user_id'   => $clientUserId,
        'is_new_user'      => $isNewUser,
        'items_created'    => count($createdItems),
        'message'          => 'Booking created. Credentials sent to patient.',
    ]);
}

// ── Unknown action ────────────────────────────────────────────────────────────
ab_json_error('Unknown action', 400);
