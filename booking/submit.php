<?php
session_start();
include(__DIR__ . '/../inc/include.php');
require_once __DIR__ . '/../admin/include/roles.php';
require_once __DIR__ . '/../admin/include/password_utils.php';
require_once __DIR__ . '/../admin/include/email_config.php';
require_once __DIR__ . '/../admin/include/booking_notification_recipients.php';
require_once __DIR__ . '/../admin/include/provider_medical_staff_helpers.php';
require_once __DIR__ . '/../admin/include/booking_patient_notifications.php';
require_once __DIR__ . '/../inc/email_template.php';
require_once __DIR__ . '/../inc/interaction_email.php';
require_once __DIR__ . '/../inc/calendar_utils.php';

function table_exists_local($conexion, $table)
{
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    return ($res && mysqli_num_rows($res) > 0);
}

function table_has_column_local($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function booking_client_ip_local()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    }
    if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    }
    if (!empty($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function booking_wants_json_response_local()
{
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string)$_SERVER['HTTP_ACCEPT']) : '';
    if ($accept !== '' && strpos($accept, 'application/json') !== false) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    return false;
}

function table_columns_meta_local($conexion, $table)
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

function enum_options_local($type)
{
    $type = (string)$type;
    if (!preg_match("/^enum\\((.*)\\)$/i", $type, $m)) {
        return [];
    }
    $raw = $m[1];
    $parts = str_getcsv($raw, ',', "'");
    $out = [];
    foreach ($parts as $p) {
        $v = trim((string)$p);
        if ($v !== '') {
            $out[] = $v;
        }
    }
    return $out;
}

function fill_required_usuario_defaults_local($conexion, &$data, $email, $userName, $baseToken)
{
    $meta = table_columns_meta_local($conexion, 'usuarios');
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
            $opts = enum_options_local($type);
            if (!empty($opts)) {
                if (in_array('3', $opts, true)) {
                    $picked = '3';
                } elseif (in_array('cliente', $opts, true)) {
                    $picked = 'cliente';
                } elseif (in_array('client', $opts, true)) {
                    $picked = 'client';
                } else {
                    $picked = $opts[0];
                }
                $data[$col] = $picked;
            } else {
                // Legacy login has historically used numeric role strings.
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

        $opts = enum_options_local($type);
        if (!empty($opts)) {
            $data[$col] = $opts[0];
            continue;
        }

        $data[$col] = '';
    }
}

function value_type_local($value)
{
    if (is_int($value)) {
        return 'i';
    }
    if (is_float($value)) {
        return 'd';
    }
    return 's';
}

function bind_stmt_params_local($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [];
    $bind[] = $stmt;
    $bind[] = &$types;
    foreach ($params as $k => $val) {
        $bind[] = &$params[$k];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function stmt_fetch_all_assoc_local($stmt)
{
    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if (!$res) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) {
        return [];
    }

    $bind = [$stmt];
    $rowRef = [];
    while ($field = mysqli_fetch_field($meta)) {
        $rowRef[$field->name] = null;
        $bind[] = &$rowRef[$field->name];
    }
    call_user_func_array('mysqli_stmt_bind_result', $bind);

    $rows = [];
    while (mysqli_stmt_fetch($stmt)) {
        $copy = [];
        foreach ($rowRef as $k => $v) {
            $copy[$k] = $v;
        }
        $rows[] = $copy;
    }
    return $rows;
}

function stmt_fetch_assoc_local($stmt)
{
    $rows = stmt_fetch_all_assoc_local($stmt);
    return !empty($rows) ? $rows[0] : null;
}

function random_hex_local($bytes = 16)
{
    $bytes = max(8, (int)$bytes);
    if (function_exists('random_bytes')) {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Exception $e) {
            // fallback below
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $raw = openssl_random_pseudo_bytes($bytes);
        if ($raw !== false && $raw !== null) {
            return bin2hex($raw);
        }
    }
    return md5(uniqid((string)mt_rand(), true)) . md5((string)microtime(true));
}

function post_int_array_local($key)
{
    if (!isset($_POST[$key])) {
        return [];
    }
    $raw = $_POST[$key];
    if (!is_array($raw)) {
        $raw = [$raw];
    }
    return array_values(array_filter(array_map('intval', $raw)));
}

function run_dynamic_insert_local($conexion, $table, $data, $requiredCols = [], $label = 'insert')
{
    $result = [
        'ok' => false,
        'id' => 0,
        'error' => '',
        'columns' => [],
    ];

    if (!table_exists_local($conexion, $table)) {
        $result['error'] = 'table_not_found';
        return $result;
    }

    $insertColumns = [];
    $insertValues = [];
    foreach ($data as $col => $val) {
        if (table_has_column_local($conexion, $table, $col)) {
            $insertColumns[] = $col;
            $insertValues[] = $val;
        }
    }
    $result['columns'] = $insertColumns;

    foreach ($requiredCols as $required) {
        if (!in_array($required, $insertColumns, true)) {
            $result['error'] = 'missing_required_' . $required;
            return $result;
        }
    }

    if (empty($insertColumns)) {
        $result['error'] = 'no_columns';
        return $result;
    }

    $placeholders = implode(',', array_fill(0, count($insertValues), '?'));
    $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $insertColumns) . "`) VALUES ({$placeholders})";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        $result['error'] = 'prepare_failed: ' . mysqli_error($conexion);
        return $result;
    }

    $types = str_repeat('s', count($insertValues));
    if (!bind_stmt_params_local($stmt, $types, $insertValues)) {
        $result['error'] = 'bind_failed';
        mysqli_stmt_close($stmt);
        return $result;
    }

    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        $result['error'] = 'execute_failed: ' . mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    $result['ok'] = true;
    $result['id'] = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $result;
}

function booking_runtime_log_local($message)
{
    $dir = __DIR__ . '/../admin/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL;
    @file_put_contents($dir . '/booking_submit_runtime.log', $line, FILE_APPEND | LOCK_EX);
}

function booking_meta_env_local($key, $default = '')
{
    $value = getenv($key);
    if ($value === false || trim((string)$value) === '') {
        return $default;
    }
    return trim((string)$value);
}

function booking_meta_generate_event_id_local()
{
    try {
        return 'mt_lead_' . bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        try {
            return 'mt_lead_' . hash('sha256', uniqid('', true) . '|' . random_int(PHP_INT_MIN, PHP_INT_MAX));
        } catch (Throwable $fallback) {
            return 'mt_lead_' . hash('sha256', uniqid('', true));
        }
    }
}

function booking_meta_client_ip_local()
{
    $raw = booking_client_ip_local();
    $first = trim(explode(',', (string)$raw)[0] ?? '');
    if ($first === '') {
        return '';
    }
    return filter_var($first, FILTER_VALIDATE_IP) ? $first : '';
}

function booking_meta_cookie_value_local($key)
{
    $value = trim((string)($_COOKIE[$key] ?? ''));
    if ($value === '') {
        return '';
    }
    return preg_match('/^[A-Za-z0-9._-]{1,255}$/', $value) ? $value : '';
}

function booking_meta_send_capi_lead_local($eventId)
{
    $eventId = trim((string)$eventId);
    if ($eventId === '') {
        booking_runtime_log_local('meta_capi_lead_error status=missing_event_id');
        return false;
    }

    $pixelId = booking_meta_env_local('META_PIXEL_ID', '2206493506836761');
    $accessToken = booking_meta_env_local('META_CAPI_ACCESS_TOKEN', '');
    if ($accessToken === '') {
        booking_runtime_log_local('meta_capi_skipped_missing_token');
        return false;
    }
    if ($pixelId === '') {
        booking_runtime_log_local('meta_capi_lead_error status=missing_pixel_id');
        return false;
    }

    $userData = [];
    $clientIp = booking_meta_client_ip_local();
    if ($clientIp !== '') {
        $userData['client_ip_address'] = $clientIp;
    }
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent !== '') {
        $userData['client_user_agent'] = substr($userAgent, 0, 1000);
    }
    $fbp = booking_meta_cookie_value_local('_fbp');
    if ($fbp !== '') {
        $userData['fbp'] = $fbp;
    }
    $fbc = booking_meta_cookie_value_local('_fbc');
    if ($fbc !== '') {
        $userData['fbc'] = $fbc;
    }

    $payload = [
        'data' => [[
            'event_name' => 'Lead',
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => 'https://medtravel.com.co/booking/wizard.php',
            'user_data' => $userData,
        ]],
    ];

    booking_runtime_log_local('meta_capi_lead_send_start event_id=' . $eventId);

    if (!function_exists('curl_init')) {
        booking_runtime_log_local('meta_capi_lead_error status=curl_unavailable');
        return false;
    }

    $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($encodedPayload === false) {
        booking_runtime_log_local('meta_capi_lead_error status=json_encode_failed');
        return false;
    }

    $url = 'https://graph.facebook.com/' . rawurlencode($pixelId) . '/events?access_token=' . rawurlencode($accessToken);
    $ch = curl_init($url);
    if (!$ch) {
        booking_runtime_log_local('meta_capi_lead_error status=curl_init_failed');
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $encodedPayload,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
    ]);

    $response = curl_exec($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpStatus < 200 || $httpStatus >= 300) {
        $status = $httpStatus > 0 ? (string)$httpStatus : 'curl_error';
        booking_runtime_log_local('meta_capi_lead_error status=' . $status);
        if ($curlError !== '') {
            error_log('[MedTravel Pixel] CAPI Lead error status=' . $status . ' msg=' . $curlError);
        }
        return false;
    }

    booking_runtime_log_local('meta_capi_lead_sent status=' . $httpStatus);
    return true;
}

function booking_user_is_privileged_local($row)
{
    if (!is_array($row)) {
        return false;
    }
    if (isset($row['ppal']) && intval($row['ppal']) === 1) {
        return true;
    }

    $roleValue = null;
    if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '') {
        $roleValue = intval($row['role_id']);
    } elseif (isset($row['rol'])) {
        if (function_exists('normalize_role_value')) {
            $roleValue = normalize_role_value($row['rol']);
        } elseif (is_numeric($row['rol'])) {
            $roleValue = intval($row['rol']);
        }
    }

    return ($roleValue === ROLE_ADMIN || $roleValue === ROLE_ADMINISTRATIVE);
}

function booking_pick_reusable_user_local($rows)
{
    if (!is_array($rows)) {
        return null;
    }
    foreach ($rows as $row) {
        if (!booking_user_is_privileged_local($row) && !empty($row['id'])) {
            return $row;
        }
    }
    return null;
}

function booking_build_user_lookup_local($conexion, $email)
{
    $email = trim((string)$email);
    $where = [];
    $types = '';
    $params = [];

    if (table_has_column_local($conexion, 'usuarios', 'email')) {
        $where[] = 'email = ?';
        $types .= 's';
        $params[] = $email;
    }
    if (table_has_column_local($conexion, 'usuarios', 'usuario')) {
        $where[] = 'usuario = ?';
        $types .= 's';
        $params[] = $email;
    }

    if (empty($where)) {
        return null;
    }

    $sql = "SELECT * FROM usuarios WHERE (" . implode(' OR ', $where) . ")";
    if (table_has_column_local($conexion, 'usuarios', 'is_deleted')) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " ORDER BY id ASC";

    return [
        'sql' => $sql,
        'types' => $types,
        'params' => $params,
    ];
}

function role_client_exists_local($conexion)
{
    static $checked = null;
    if ($checked !== null) {
        return $checked;
    }
    $checked = false;
    if (!table_exists_local($conexion, 'roles')) {
        return $checked;
    }
    $clientRoleId = (int)(defined('ROLE_CLIENT') ? ROLE_CLIENT : 3);
    $stmt = mysqli_prepare($conexion, 'SELECT id FROM roles WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return $checked;
    }
    mysqli_stmt_bind_param($stmt, 'i', $clientRoleId);
    if (mysqli_stmt_execute($stmt)) {
        $row = stmt_fetch_assoc_local($stmt);
        if ($row && !empty($row['id'])) {
            $checked = true;
        }
    }
    mysqli_stmt_close($stmt);
    return $checked;
}

function run_static_booking_insert_local($conexion, $data)
{
    $result = [
        'ok' => false,
        'id' => 0,
        'error' => '',
        'columns' => [],
    ];

    $requiredColumns = [
        'name',
        'email',
        'phone',
        'origin',
        'booking_datetime',
        'destination',
        'persons',
        'category',
        'special_request',
        'selected_offers',
        'budget',
        'timeline',
        'additional_notes',
    ];

    foreach ($requiredColumns as $col) {
        if (!table_has_column_local($conexion, 'booking_requests', $col)) {
            $result['error'] = 'missing_static_column_' . $col;
            return $result;
        }
    }

    $hasClientUserId = table_has_column_local($conexion, 'booking_requests', 'client_user_id');
    $hasTermsAccepted = table_has_column_local($conexion, 'booking_requests', 'terms_accepted');
    $hasTermsAcceptedAt = table_has_column_local($conexion, 'booking_requests', 'terms_accepted_at');
    $hasTermsVersion = table_has_column_local($conexion, 'booking_requests', 'terms_version');
    $hasTermsIp = table_has_column_local($conexion, 'booking_requests', 'terms_ip');
    $hasTermsUserAgent = table_has_column_local($conexion, 'booking_requests', 'terms_user_agent');
    $hasUtmSource   = table_has_column_local($conexion, 'booking_requests', 'utm_source');
    $hasUtmMedium   = table_has_column_local($conexion, 'booking_requests', 'utm_medium');
    $hasUtmCampaign = table_has_column_local($conexion, 'booking_requests', 'utm_campaign');
    $hasUtmContent  = table_has_column_local($conexion, 'booking_requests', 'utm_content');
    $hasUtmTerm     = table_has_column_local($conexion, 'booking_requests', 'utm_term');

    $columns = [
        'name', 'email', 'phone', 'origin', 'booking_datetime', 'destination', 'persons', 'category',
        'special_request', 'selected_offers', 'budget', 'timeline', 'additional_notes'
    ];
    if ($hasClientUserId) {
        $columns[] = 'client_user_id';
    }
    if ($hasTermsAccepted) {
        $columns[] = 'terms_accepted';
    }
    if ($hasTermsAcceptedAt) {
        $columns[] = 'terms_accepted_at';
    }
    if ($hasTermsVersion) {
        $columns[] = 'terms_version';
    }
    if ($hasTermsIp) {
        $columns[] = 'terms_ip';
    }
    if ($hasTermsUserAgent) {
        $columns[] = 'terms_user_agent';
    }
    if ($hasUtmSource)   { $columns[] = 'utm_source'; }
    if ($hasUtmMedium)   { $columns[] = 'utm_medium'; }
    if ($hasUtmCampaign) { $columns[] = 'utm_campaign'; }
    if ($hasUtmContent)  { $columns[] = 'utm_content'; }
    if ($hasUtmTerm)     { $columns[] = 'utm_term'; }
    $result['columns'] = $columns;

    $sql = "INSERT INTO booking_requests (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        $result['error'] = 'prepare_failed: ' . mysqli_error($conexion);
        return $result;
    }

    $values = [];
    $types = '';
    foreach ($columns as $col) {
        $val = $data[$col] ?? null;
        if ($col === 'client_user_id' && ($val === '' || $val === 0)) {
            $val = null;
        }
        $values[] = $val;
        $types .= value_type_local($val);
    }

    if (!bind_stmt_params_local($stmt, $types, $values)) {
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

function booking_fetch_eligible_staff_ids_local($conexion, $providerId, $providerCatalogServiceId = 0, $serviceId = 0)
{
    $providerId = (int)$providerId;
    $providerCatalogServiceId = (int)$providerCatalogServiceId;
    $serviceId = (int)$serviceId;
    if ($providerId <= 0 || $serviceId <= 0) {
        return [];
    }
    if (!table_exists_local($conexion, 'provider_medical_staff') || !table_exists_local($conexion, 'provider_medical_staff_services')) {
        return [];
    }

    $hasRelActive = table_has_column_local($conexion, 'provider_medical_staff_services', 'active');
    $hasRelProviderCatalogServiceId = table_has_column_local($conexion, 'provider_medical_staff_services', 'provider_catalog_service_id');
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
    if (!bind_stmt_params_local($stmt, $types, $params)) {
        mysqli_stmt_close($stmt);
        return [];
    }
    if (!mysqli_stmt_execute($stmt)) {
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

function booking_resolve_unique_staff_id_local($conexion, $providerId, $providerCatalogServiceId = 0, $serviceId = 0)
{
    $eligibleIds = booking_fetch_eligible_staff_ids_local($conexion, $providerId, $providerCatalogServiceId, $serviceId);
    return count($eligibleIds) === 1 ? (int)$eligibleIds[0] : 0;
}

function booking_assign_initial_staff_to_item_local($conexion, $itemId, $providerId, $providerCatalogServiceId = 0, $serviceId = 0)
{
    $itemId = (int)$itemId;
    $providerId = (int)$providerId;
    if ($itemId <= 0 || $providerId <= 0 || !table_has_column_local($conexion, 'booking_request_items', 'assigned_staff_id')) {
        return 0;
    }

    $staffId = booking_resolve_unique_staff_id_local($conexion, $providerId, $providerCatalogServiceId, $serviceId);
    if ($staffId <= 0) {
        return 0;
    }

    $setParts = ['assigned_staff_id = ?'];
    $types = 'ii';
    $params = [$staffId, $itemId];
    if (table_has_column_local($conexion, 'booking_request_items', 'assigned_at')) {
        $setParts[] = 'assigned_at = NOW()';
    }

    $sql = 'UPDATE booking_request_items SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 0;
    }
    if (!bind_stmt_params_local($stmt, $types, $params)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    mysqli_stmt_close($stmt);
    return $staffId;
}

function booking_build_provider_case_url_local($requestId, $itemId)
{
    return 'https://medtravel.com.co/admin/my_booking_requests.php?item_id=' . (int)$itemId . '&request_id=' . (int)$requestId;
}

function booking_build_complementary_case_url_local($requestId, $itemId)
{
    return 'https://medtravel.com.co/admin/my_booking_requests.php?item_id=' . (int)$itemId . '&request_id=' . (int)$requestId;
}

function booking_notify_provider_new_request_local($conexion, $bookingRequestId, $item)
{
    $bookingRequestId = (int)$bookingRequestId;
    $itemId = (int)($item['item_id'] ?? 0);
    if ($bookingRequestId <= 0 || $itemId <= 0) {
        return ['success' => false, 'error' => 'invalid_item'];
    }

    $recipient = booking_notification_resolve_medical_offer_recipient($conexion, $itemId, (array)$item);
    $providerEmail = trim((string)($recipient['email'] ?? ''));
    if (!filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => (string)($recipient['skip_reason'] ?? 'provider_email_not_found'),
            'recipient' => $recipient,
        ];
    }

    $timelineExpr = table_has_column_local($conexion, 'booking_requests', 'timeline') ? 'br.timeline' : "''";
    $detailSql = "SELECT
                    bri.provider_id,
                    br.name AS client_name,
                    br.email AS client_email,
                    " . (table_has_column_local($conexion, 'booking_requests', 'phone') ? 'br.phone' : "''") . " AS client_phone,
                    br.destination,
                    {$timelineExpr} AS timeline,
                    COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                    " . (table_has_column_local($conexion, 'booking_request_items', 'assigned_staff_id') ? 'bri.assigned_staff_id' : 'NULL') . " AS assigned_staff_id
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                LEFT JOIN service_catalog sc ON sc.id = o.service_id
                LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
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

    $requestMeta = interaction_email_request_meta($conexion, 'ITEM', $bookingRequestId, $itemId);
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
    $assignedStaff = $assignedStaffId > 0 ? provider_staff_fetch_basic_row($conexion, $assignedStaffId, (int)($item['provider_id'] ?? 0)) : null;
    $assignedStaffName = trim((string)($assignedStaff['full_name'] ?? ''));
    $caseUrl = booking_build_provider_case_url_local($bookingRequestId, $itemId);
    $subject = 'New booking request received - case #' . $bookingRequestId;

    $contentHtml = '<p>A new booking request has been created and is waiting for provider review.</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Case:</strong> ' . escape_html_local($itemTitle) . '</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Patient:</strong> ' . escape_html_local($patientName) . '</p>'
        . ($patientEmail !== '' ? '<p style="margin:0 0 6px 0;"><strong>Email:</strong> ' . escape_html_local($patientEmail) . '</p>' : '')
        . ($patientPhone !== '' ? '<p style="margin:0 0 6px 0;"><strong>Phone:</strong> ' . escape_html_local($patientPhone) . '</p>' : '')
        . ($destination !== '' ? '<p style="margin:0 0 6px 0;"><strong>Destination:</strong> ' . escape_html_local($destination) . '</p>' : '')
        . ($timeline !== '' ? '<p style="margin:0 0 6px 0;"><strong>Timeline:</strong> ' . escape_html_local($timeline) . '</p>' : '')
        . ($assignedStaffName !== '' ? '<p style="margin:0 0 16px 0;"><strong>Assigned staff:</strong> ' . escape_html_local($assignedStaffName) . '</p>' : '<p style="margin:0 0 16px 0;"><strong>Assigned staff:</strong> Pending assignment</p>')
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
        $subject,
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

function booking_notify_complementary_new_request_local($conexion, $bookingRequestId, $item)
{
    $bookingRequestId = (int)$bookingRequestId;
    $itemId = (int)($item['item_id'] ?? 0);
    if ($bookingRequestId <= 0 || $itemId <= 0) {
        return ['success' => false, 'error' => 'invalid_item'];
    }

    $recipient = booking_notification_resolve_complementary_service_recipient($conexion, $itemId, (array)$item);
    $providerEmail = trim((string)($recipient['email'] ?? ''));
    if (!filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => (string)($recipient['skip_reason'] ?? 'complementary_service_recipient_not_found'),
            'recipient' => $recipient,
        ];
    }

    $timelineExpr = table_has_column_local($conexion, 'booking_requests', 'timeline') ? 'br.timeline' : "''";
    $detailSql = "SELECT
                    bri.service_provider_id,
                    br.name AS client_name,
                    br.email AS client_email,
                    " . (table_has_column_local($conexion, 'booking_requests', 'phone') ? 'br.phone' : "''") . " AS client_phone,
                    br.destination,
                    {$timelineExpr} AS timeline,
                    COALESCE(NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
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

    $requestMeta = interaction_email_request_meta($conexion, 'ITEM', $bookingRequestId, $itemId);
    $itemTitle = trim((string)($requestMeta['title'] ?? ''));
    if ($itemTitle === '') {
        $itemTitle = trim((string)($item['item_name'] ?? 'Item #' . $itemId));
    }
    $patientName = trim((string)($item['client_name'] ?? 'Paciente'));
    $patientEmail = trim((string)($item['client_email'] ?? ''));
    $patientPhone = trim((string)($item['client_phone'] ?? ''));
    $destination = trim((string)($item['destination'] ?? ''));
    $timeline = trim((string)($item['timeline'] ?? ''));
    $caseUrl = booking_build_complementary_case_url_local($bookingRequestId, $itemId);
    $subject = 'New complementary booking request received - case #' . $bookingRequestId;

    $contentHtml = '<p>A new complementary booking request has been created and is waiting for provider review.</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Case:</strong> ' . escape_html_local($itemTitle) . '</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Patient:</strong> ' . escape_html_local($patientName) . '</p>'
        . ($patientEmail !== '' ? '<p style="margin:0 0 6px 0;"><strong>Email:</strong> ' . escape_html_local($patientEmail) . '</p>' : '')
        . ($patientPhone !== '' ? '<p style="margin:0 0 6px 0;"><strong>Phone:</strong> ' . escape_html_local($patientPhone) . '</p>' : '')
        . ($destination !== '' ? '<p style="margin:0 0 6px 0;"><strong>Destination:</strong> ' . escape_html_local($destination) . '</p>' : '')
        . ($timeline !== '' ? '<p style="margin:0 0 16px 0;"><strong>Timeline:</strong> ' . escape_html_local($timeline) . '</p>' : '')
        . '<p>Please open the provider portal to review the service details and continue the workflow.</p>';

    $textBody = "A new complementary booking request has been created and is waiting for provider review.\n\n"
        . 'Case: ' . $itemTitle . "\n"
        . 'Patient: ' . $patientName . "\n"
        . ($patientEmail !== '' ? 'Email: ' . $patientEmail . "\n" : '')
        . ($patientPhone !== '' ? 'Phone: ' . $patientPhone . "\n" : '')
        . ($destination !== '' ? 'Destination: ' . $destination . "\n" : '')
        . ($timeline !== '' ? 'Timeline: ' . $timeline . "\n" : '')
        . "\nOpen case: " . $caseUrl;

    return send_interaction_email(
        $providerEmail,
        $subject,
        $contentHtml,
        $textBody,
        [
            'preheader' => 'A new complementary case is waiting for provider review in MedTravel.',
            'cta' => ['text' => 'Open case in MedTravel', 'url' => $caseUrl],
            'footer_note' => 'Please handle the case through your MedTravel portal.',
            'sender_label' => 'MedTravel Coordination Team',
            'event' => 'booking_complementary_service_created',
            'recipient_source' => (string)($recipient['source'] ?? ''),
        ],
        $conexion
    );
}

function insert_booking_items_mvp($conexion, $booking_request_id, $selected_offers, $medtravel_services)
{
    $createdItems = [];
    if (!table_exists_local($conexion, 'booking_request_items')) {
        error_log('booking_submit: booking_request_items table does not exist; skipping item insert for booking_request_id=' . intval($booking_request_id));
        return $createdItems;
    }
    $itemsHasProviderId = table_has_column_local($conexion, 'booking_request_items', 'provider_id');
    $itemsHasServiceProviderId = table_has_column_local($conexion, 'booking_request_items', 'service_provider_id');
    $itemsHasProposedPrice = table_has_column_local($conexion, 'booking_request_items', 'proposed_price');
    $itemsHasCurrency = table_has_column_local($conexion, 'booking_request_items', 'currency');
    if (!$itemsHasProviderId && !$itemsHasServiceProviderId) {
        error_log('booking_submit: booking_request_items missing provider_id/service_provider_id; skipping booking_request_id=' . intval($booking_request_id));
        return $createdItems;
    }
    $canInsertMedical = $itemsHasProviderId;
    $canInsertComplementary = $itemsHasServiceProviderId;
    if (!$canInsertMedical && !empty($selected_offers)) {
        error_log('booking_submit: booking_request_items.provider_id not available; skipping medical items booking_request_id=' . intval($booking_request_id));
    }
    if (!$canInsertComplementary && !empty($medtravel_services)) {
        error_log('booking_submit: booking_request_items.service_provider_id not available; skipping complementary items booking_request_id=' . intval($booking_request_id));
    }

    $hasOfferActive = table_has_column_local($conexion, 'provider_service_offers', 'is_active');
    $hasOfferDeleted = table_has_column_local($conexion, 'provider_service_offers', 'is_deleted');
    $hasProviderActive = table_has_column_local($conexion, 'providers', 'is_active');
    $hasProviderDeleted = table_has_column_local($conexion, 'providers', 'is_deleted');
    $offerPriceExpr = table_has_column_local($conexion, 'provider_service_offers', 'price_from') ? 'o.price_from' : 'NULL';
    $offerCurrencyExpr = table_has_column_local($conexion, 'provider_service_offers', 'currency') ? 'o.currency' : "''";
    $offerProviderCatalogServiceExpr = table_has_column_local($conexion, 'provider_service_offers', 'provider_catalog_service_id') ? 'o.provider_catalog_service_id' : 'NULL';
    $offerSql = "SELECT o.provider_id,
                        o.service_id,
                        {$offerProviderCatalogServiceExpr} AS provider_catalog_service_id,
                        {$offerPriceExpr} AS offer_price,
                        {$offerCurrencyExpr} AS offer_currency
                 FROM provider_service_offers o
                 INNER JOIN providers p ON p.id = o.provider_id
                 WHERE o.id = ?";
    if ($hasOfferActive) {
        $offerSql .= " AND o.is_active = 1";
    }
    if ($hasOfferDeleted) {
        $offerSql .= " AND o.is_deleted = 0";
    }
    if ($hasProviderActive) {
        $offerSql .= " AND p.is_active = 1";
    }
    if ($hasProviderDeleted) {
        $offerSql .= " AND p.is_deleted = 0";
    }
    $offerSql .= " LIMIT 1";

    $offerStmt = $canInsertMedical ? mysqli_prepare($conexion, $offerSql) : null;
    $medicalInsertSql = "INSERT INTO booking_request_items (booking_request_id, item_type, offer_id";
    if ($itemsHasServiceProviderId) {
        $medicalInsertSql .= ", service_provider_id";
    }
    if ($itemsHasProposedPrice) {
        $medicalInsertSql .= ", proposed_price";
    }
    if ($itemsHasCurrency) {
        $medicalInsertSql .= ", currency";
    }
    $medicalInsertSql .= ", provider_id, item_status, created_at) VALUES (?, 'medical_offer', ?";
    if ($itemsHasServiceProviderId) {
        $medicalInsertSql .= ", NULL";
    }
    if ($itemsHasProposedPrice) {
        $medicalInsertSql .= ", ?";
    }
    if ($itemsHasCurrency) {
        $medicalInsertSql .= ", ?";
    }
    $medicalInsertSql .= ", ?, 'pending_provider', NOW())";
    $insertMedicalStmt = $canInsertMedical ? mysqli_prepare($conexion, $medicalInsertSql) : null;

    if ($canInsertMedical && $offerStmt && $insertMedicalStmt) {
        foreach ($selected_offers as $offerId) {
            $offerId = intval($offerId);
            if ($offerId <= 0) {
                continue;
            }

            mysqli_stmt_bind_param($offerStmt, 'i', $offerId);
            if (!mysqli_stmt_execute($offerStmt)) {
                error_log('booking_submit: offer lookup failed for offer_id=' . $offerId . ' error=' . mysqli_stmt_error($offerStmt));
                continue;
            }
            $offerRow = stmt_fetch_assoc_local($offerStmt);
            if (!$offerRow || empty($offerRow['provider_id'])) {
                error_log('booking_submit: offer skipped (missing provider_id) offer_id=' . $offerId);
                continue;
            }

            $offerPrice = null;
            if (isset($offerRow['offer_price']) && is_numeric($offerRow['offer_price'])) {
                $offerPrice = round((float)$offerRow['offer_price'], 2);
                if ($offerPrice <= 0) {
                    $offerPrice = null;
                }
            }
            $offerCurrency = strtoupper(trim((string)($offerRow['offer_currency'] ?? '')));
            if ($offerCurrency === '' && $offerPrice !== null) {
                $offerCurrency = 'USD';
            }
            if ($offerCurrency === '') {
                $offerCurrency = null;
            }

            $providerId = intval($offerRow['provider_id']);
            $params = [$booking_request_id, $offerId];
            if ($itemsHasProposedPrice) {
                $params[] = $offerPrice;
            }
            if ($itemsHasCurrency) {
                $params[] = $offerCurrency;
            }
            $params[] = $providerId;
            $types = '';
            foreach ($params as $param) {
                $types .= value_type_local($param);
            }
            if (!bind_stmt_params_local($insertMedicalStmt, $types, $params)) {
                error_log('booking_submit: medical item bind failed booking_request_id=' . intval($booking_request_id) . ' offer_id=' . $offerId);
                continue;
            }
            if (!mysqli_stmt_execute($insertMedicalStmt)) {
                error_log('booking_submit: medical item insert failed booking_request_id=' . intval($booking_request_id) . ' offer_id=' . $offerId . ' provider_id=' . $providerId . ' error=' . mysqli_stmt_error($insertMedicalStmt));
                continue;
            }

            $itemId = (int)mysqli_insert_id($conexion);
            $serviceId = (int)($offerRow['service_id'] ?? 0);
            $providerCatalogServiceId = (int)($offerRow['provider_catalog_service_id'] ?? 0);
            $assignedStaffId = booking_assign_initial_staff_to_item_local(
                $conexion,
                $itemId,
                $providerId,
                $providerCatalogServiceId,
                $serviceId
            );
            $createdItems[] = [
                'item_id' => $itemId,
                'item_type' => 'medical_offer',
                'offer_id' => $offerId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'provider_catalog_service_id' => $providerCatalogServiceId,
                'assigned_staff_id' => $assignedStaffId,
            ];
        }
    } elseif ($canInsertMedical) {
        error_log('booking_submit: prepare failed for medical items. offerStmt=' . ($offerStmt ? 'ok' : 'null') . ', insertMedicalStmt=' . ($insertMedicalStmt ? 'ok' : 'null'));
    }
    if ($offerStmt) {
        mysqli_stmt_close($offerStmt);
    }
    if ($insertMedicalStmt) {
        mysqli_stmt_close($insertMedicalStmt);
    }

    $serviceProviderColumn = null;
    if (table_has_column_local($conexion, 'medtravel_services_catalog', 'provider_id')) {
        $serviceProviderColumn = 'provider_id';
    } elseif (table_has_column_local($conexion, 'medtravel_services_catalog', 'service_provider_id')) {
        $serviceProviderColumn = 'service_provider_id';
    }

    if ($serviceProviderColumn === null || !$canInsertComplementary) {
        if (!empty($medtravel_services)) {
            error_log('booking_submit: medtravel_services_catalog has no provider_id/service_provider_id; skipping complementary item insert for booking_request_id=' . intval($booking_request_id));
        }
        return $createdItems;
    }

    $hasSvcActive = table_has_column_local($conexion, 'medtravel_services_catalog', 'is_active');
    $hasSvcDeleted = table_has_column_local($conexion, 'medtravel_services_catalog', 'is_deleted');
    $hasSvcProviderTable = table_exists_local($conexion, 'service_providers');
    $hasSvcProviderActive = $hasSvcProviderTable && table_has_column_local($conexion, 'service_providers', 'is_active');
    $hasSvcProviderDeleted = $hasSvcProviderTable && table_has_column_local($conexion, 'service_providers', 'is_deleted');

    $servicePriceExpr = table_has_column_local($conexion, 'medtravel_services_catalog', 'sale_price') ? 'm.sale_price' : 'NULL';
    $serviceCurrencyExpr = table_has_column_local($conexion, 'medtravel_services_catalog', 'currency') ? 'm.currency' : "''";
    $serviceSql = "SELECT m.`{$serviceProviderColumn}` AS service_provider_id,
                          {$servicePriceExpr} AS service_price,
                          {$serviceCurrencyExpr} AS service_currency
                   FROM medtravel_services_catalog m";
    if ($hasSvcProviderTable) {
        $serviceSql .= " LEFT JOIN service_providers sp ON sp.id = m.`{$serviceProviderColumn}`";
    }
    $serviceSql .= " WHERE m.id = ?";
    if ($hasSvcActive) {
        $serviceSql .= " AND m.is_active = 1";
    }
    if ($hasSvcDeleted) {
        $serviceSql .= " AND m.is_deleted = 0";
    }
    if ($hasSvcProviderActive) {
        $serviceSql .= " AND sp.is_active = 1";
    }
    if ($hasSvcProviderDeleted) {
        $serviceSql .= " AND sp.is_deleted = 0";
    }
    $serviceSql .= " LIMIT 1";

    $serviceStmt = mysqli_prepare($conexion, $serviceSql);
    $complementaryInsertSql = "INSERT INTO booking_request_items (booking_request_id, item_type, medtravel_service_id, service_provider_id";
    if ($itemsHasProviderId) {
        $complementaryInsertSql .= ", provider_id";
    }
    if ($itemsHasProposedPrice) {
        $complementaryInsertSql .= ", proposed_price";
    }
    if ($itemsHasCurrency) {
        $complementaryInsertSql .= ", currency";
    }
    $complementaryInsertSql .= ", item_status, created_at) VALUES (?, 'complementary_service', ?, ?";
    if ($itemsHasProviderId) {
        $complementaryInsertSql .= ", NULL";
    }
    if ($itemsHasProposedPrice) {
        $complementaryInsertSql .= ", ?";
    }
    if ($itemsHasCurrency) {
        $complementaryInsertSql .= ", ?";
    }
    $complementaryInsertSql .= ", 'pending_provider', NOW())";
    $insertComplementaryStmt = mysqli_prepare($conexion, $complementaryInsertSql);

    if ($serviceStmt && $insertComplementaryStmt) {
        foreach ($medtravel_services as $serviceId) {
            $serviceId = intval($serviceId);
            if ($serviceId <= 0) {
                continue;
            }

            mysqli_stmt_bind_param($serviceStmt, 'i', $serviceId);
            if (!mysqli_stmt_execute($serviceStmt)) {
                error_log('booking_submit: service lookup failed for medtravel_service_id=' . $serviceId . ' error=' . mysqli_stmt_error($serviceStmt));
                continue;
            }
            $serviceRow = stmt_fetch_assoc_local($serviceStmt);
            if (!$serviceRow || empty($serviceRow['service_provider_id'])) {
                error_log('booking_submit: complementary service skipped (not found/invalid/inactive) medtravel_service_id=' . $serviceId);
                continue;
            }

            $servicePrice = null;
            if (isset($serviceRow['service_price']) && is_numeric($serviceRow['service_price'])) {
                $servicePrice = round((float)$serviceRow['service_price'], 2);
                if ($servicePrice <= 0) {
                    $servicePrice = null;
                }
            }
            $serviceCurrency = strtoupper(trim((string)($serviceRow['service_currency'] ?? '')));
            if ($serviceCurrency === '' && $servicePrice !== null) {
                $serviceCurrency = 'USD';
            }
            if ($serviceCurrency === '') {
                $serviceCurrency = null;
            }

            $serviceProviderId = intval($serviceRow['service_provider_id']);
            $params = [$booking_request_id, $serviceId, $serviceProviderId];
            if ($itemsHasProposedPrice) {
                $params[] = $servicePrice;
            }
            if ($itemsHasCurrency) {
                $params[] = $serviceCurrency;
            }
            $types = '';
            foreach ($params as $param) {
                $types .= value_type_local($param);
            }
            if (!bind_stmt_params_local($insertComplementaryStmt, $types, $params)) {
                error_log('booking_submit: complementary item bind failed booking_request_id=' . intval($booking_request_id) . ' medtravel_service_id=' . $serviceId);
                continue;
            }
            if (!mysqli_stmt_execute($insertComplementaryStmt)) {
                error_log('booking_submit: complementary item insert failed booking_request_id=' . intval($booking_request_id) . ' medtravel_service_id=' . $serviceId . ' error=' . mysqli_stmt_error($insertComplementaryStmt));
                continue;
            }

            $createdItems[] = [
                'item_id' => (int)mysqli_insert_id($conexion),
                'item_type' => 'complementary_service',
                'medtravel_service_id' => $serviceId,
                'service_provider_id' => $serviceProviderId,
                'assigned_staff_id' => 0,
            ];
        }
    } else {
        error_log('booking_submit: prepare failed for complementary items. serviceStmt=' . ($serviceStmt ? 'ok' : 'null') . ', insertComplementaryStmt=' . ($insertComplementaryStmt ? 'ok' : 'null'));
    }

    if ($serviceStmt) {
        mysqli_stmt_close($serviceStmt);
    }
    if ($insertComplementaryStmt) {
        mysqli_stmt_close($insertComplementaryStmt);
    }

    return $createdItems;
}

function find_or_create_client_user_for_booking($conexion, $booking, &$isNewUser = null)
{
    $isNewUser = false;
    $email = trim((string)($booking['email'] ?? ''));
    if ($email === '') {
        return 0;
    }

    $lookup = booking_build_user_lookup_local($conexion, $email);
    if (!$lookup) {
        error_log('booking_submit: usuarios lookup columns missing (email/usuario)');
        booking_runtime_log_local('create_client_user lookup_missing_columns email=' . $email);
        return 0;
    }
    $findSql = $lookup['sql'];

    $stmtFind = mysqli_prepare($conexion, $findSql);
    if (!$stmtFind) {
        error_log('booking_submit: find_or_create_client_user prepare failed: ' . mysqli_error($conexion));
        return 0;
    }
    $findTypes = $lookup['types'];
    $findParams = $lookup['params'];
    if (!bind_stmt_params_local($stmtFind, $findTypes, $findParams)) {
        error_log('booking_submit: find_or_create_client_user bind failed');
        mysqli_stmt_close($stmtFind);
        return 0;
    }
    if (!mysqli_stmt_execute($stmtFind)) {
        error_log('booking_submit: find_or_create_client_user execute failed: ' . mysqli_stmt_error($stmtFind));
        mysqli_stmt_close($stmtFind);
        return 0;
    }
    $foundUsers = stmt_fetch_all_assoc_local($stmtFind);
    $userRow = booking_pick_reusable_user_local($foundUsers);
    $blockedPrivilegedUser = null;
    foreach ($foundUsers as $row) {
        if (booking_user_is_privileged_local($row) && empty($blockedPrivilegedUser['id'])) {
            $blockedPrivilegedUser = $row;
        }
    }
    mysqli_stmt_close($stmtFind);

    if ($userRow && !empty($userRow['id'])) {
        return intval($userRow['id']);
    }
    if ($blockedPrivilegedUser && !empty($blockedPrivilegedUser['id'])) {
        error_log('booking_submit: existing privileged account matches booking email; skipping reuse to avoid password reset impact. user_id=' . intval($blockedPrivilegedUser['id']) . ' email=' . $email);
        booking_runtime_log_local('create_client_user skipped_privileged_conflict email=' . $email . ' privileged_user_id=' . intval($blockedPrivilegedUser['id']));
        return 0;
    }

    $userName = trim((string)($booking['name'] ?? 'Client'));
    if ($userName === '') {
        $userName = 'Client';
    }

    $baseToken = function_exists('generate_user_token') ? generate_user_token() : md5(uniqid(rand(), true));
    $randomPassword = random_hex_local(16);
    $passwordHash = function_exists('hash_password')
        ? hash_password($randomPassword, $baseToken)
        : password_hash($randomPassword, PASSWORD_DEFAULT);

    $data = [];
    if (table_has_column_local($conexion, 'usuarios', 'usuario')) {
        $data['usuario'] = $email;
    }
    if (table_has_column_local($conexion, 'usuarios', 'password')) {
        $data['password'] = $passwordHash;
    }
    if (table_has_column_local($conexion, 'usuarios', 'avatar')) {
        $data['avatar'] = 'img/perfil/default.png';
    }
    if (table_has_column_local($conexion, 'usuarios', 'nombre')) {
        $data['nombre'] = $userName;
    }
    if (table_has_column_local($conexion, 'usuarios', 'activo')) {
        $data['activo'] = 1;
    }
    if (table_has_column_local($conexion, 'usuarios', 'token')) {
        $data['token'] = $baseToken;
    }
    if (table_has_column_local($conexion, 'usuarios', 'empresa')) {
        // Keep empty so legacy login can use virtual-company fallback and avoid empresa_invalid.
        $data['empresa'] = '';
    }
    if (table_has_column_local($conexion, 'usuarios', 'ppal')) {
        $data['ppal'] = 0;
    }
    if (table_has_column_local($conexion, 'usuarios', 'usrlogin')) {
        $data['usrlogin'] = $email;
    }
    if (table_has_column_local($conexion, 'usuarios', 'rol')) {
        $rolMeta = table_columns_meta_local($conexion, 'usuarios');
        $rolType = isset($rolMeta['rol']['Type']) ? strtolower((string)$rolMeta['rol']['Type']) : '';
        $rolOpts = enum_options_local($rolType);
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
    if (table_has_column_local($conexion, 'usuarios', 'role_id') && role_client_exists_local($conexion)) {
        $data['role_id'] = (int)(defined('ROLE_CLIENT') ? ROLE_CLIENT : 3);
    }
    if (table_has_column_local($conexion, 'usuarios', 'cargo')) {
        $data['cargo'] = 'Cliente';
    }
    if (table_has_column_local($conexion, 'usuarios', 'email')) {
        $data['email'] = $email;
    }
    if (table_has_column_local($conexion, 'usuarios', 'telefono')) {
        $data['telefono'] = trim((string)($booking['phone'] ?? ''));
    }
    if (table_has_column_local($conexion, 'usuarios', 'cambio_password')) {
        $data['cambio_password'] = 0;
    }

    if (empty($data['usuario']) || empty($data['password'])) {
        error_log('booking_submit: cannot create client user due missing required columns (usuario/password)');
        booking_runtime_log_local('create_client_user missing_required_columns email=' . $email . ' has_usuario=' . (empty($data['usuario']) ? '0' : '1') . ' has_password=' . (empty($data['password']) ? '0' : '1'));
        return 0;
    }

    fill_required_usuario_defaults_local($conexion, $data, $email, $userName, $baseToken);

    $columns = array_keys($data);
    $values = array_values($data);
    $types = '';
    foreach ($values as $value) {
        $types .= value_type_local($value);
    }
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $insertSql = "INSERT INTO usuarios (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})";

    $stmtInsert = mysqli_prepare($conexion, $insertSql);
    if (!$stmtInsert) {
        error_log('booking_submit: create client user prepare failed: ' . mysqli_error($conexion));
        booking_runtime_log_local('create_client_user prepare_failed email=' . $email . ' err=' . mysqli_error($conexion));
        return 0;
    }

    if (!bind_stmt_params_local($stmtInsert, $types, $values)) {
        error_log('booking_submit: create client user bind failed');
        booking_runtime_log_local('create_client_user bind_failed email=' . $email);
        mysqli_stmt_close($stmtInsert);
        return 0;
    }

    if (!mysqli_stmt_execute($stmtInsert)) {
        $executeErr = mysqli_stmt_error($stmtInsert);
        $executeErrNo = function_exists('mysqli_stmt_errno') ? mysqli_stmt_errno($stmtInsert) : 0;
        error_log('booking_submit: create client user execute failed: ' . $executeErr);
        booking_runtime_log_local('create_client_user execute_failed email=' . $email . ' errno=' . intval($executeErrNo) . ' err=' . $executeErr . ' columns=' . implode(',', $columns));
        mysqli_stmt_close($stmtInsert);
        // Rescue path: duplicate entry likely means the user already exists.
        if ((int)$executeErrNo === 1062) {
            $stmtRescue = mysqli_prepare($conexion, $findSql);
            if ($stmtRescue) {
                $rescueTypes = $lookup['types'];
                $rescueParams = $lookup['params'];
                if (bind_stmt_params_local($stmtRescue, $rescueTypes, $rescueParams) && mysqli_stmt_execute($stmtRescue)) {
                    $rescueRows = stmt_fetch_all_assoc_local($stmtRescue);
                    $reusable = booking_pick_reusable_user_local($rescueRows);
                    if ($reusable && !empty($reusable['id'])) {
                        booking_runtime_log_local('create_client_user rescued_duplicate email=' . $email . ' user_id=' . intval($reusable['id']));
                        mysqli_stmt_close($stmtRescue);
                        return intval($reusable['id']);
                    }
                }
                mysqli_stmt_close($stmtRescue);
            }
        }
        return 0;
    }

    $newId = mysqli_insert_id($conexion);
    booking_runtime_log_local('create_client_user ok email=' . $email . ' user_id=' . intval($newId));
    mysqli_stmt_close($stmtInsert);
    $isNewUser = ($newId > 0);
    return intval($newId);
}

function update_booking_client_user($conexion, $bookingRequestId, $clientUserId)
{
    if ($bookingRequestId <= 0 || $clientUserId <= 0) {
        return;
    }
    if (!table_has_column_local($conexion, 'booking_requests', 'client_user_id')) {
        return;
    }

    $stmt = mysqli_prepare($conexion, "UPDATE booking_requests SET client_user_id = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        error_log('booking_submit: update booking client_user_id prepare failed: ' . mysqli_error($conexion));
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $clientUserId, $bookingRequestId);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('booking_submit: update booking client_user_id execute failed: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
}

function parse_booking_datetime_bogota_local($rawValue)
{
    $rawValue = trim((string)$rawValue);
    if ($rawValue === '') {
        return '';
    }

    try {
        $tz = new DateTimeZone('America/Bogota');
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat('!' . $fmt, $rawValue, $tz);
            if ($dt instanceof DateTime) {
                if ($fmt === 'Y-m-d') {
                    $dt->setTime(0, 0, 0);
                }
                return $dt->format('Y-m-d H:i:s');
            }
        }

        $ts = strtotime($rawValue);
        if ($ts !== false) {
            $dt = new DateTime('@' . $ts);
            $dt->setTimezone($tz);
            return $dt->format('Y-m-d H:i:s');
        }
    } catch (Throwable $e) {
        error_log('booking_submit: parse_booking_datetime_bogota_local error: ' . $e->getMessage());
    }

    return '';
}

function parse_timeline_date_range_local($timelineText)
{
    $timelineText = trim((string)$timelineText);
    if ($timelineText === '') {
        return null;
    }

    if (!preg_match('/(\d{4}-\d{2}-\d{2})\s*(?:to|a|al|hasta|-|–)\s*(\d{4}-\d{2}-\d{2})/i', $timelineText, $m)) {
        return null;
    }

    $start = trim((string)$m[1]);
    $end = trim((string)$m[2]);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        return null;
    }
    $startParts = array_map('intval', explode('-', $start));
    $endParts = array_map('intval', explode('-', $end));
    if (count($startParts) !== 3 || count($endParts) !== 3) {
        return null;
    }
    if (!checkdate($startParts[1], $startParts[2], $startParts[0]) || !checkdate($endParts[1], $endParts[2], $endParts[0])) {
        return null;
    }
    if (strtotime($end) < strtotime($start)) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }

    return [
        'start_date' => $start,
        'end_date' => $end,
    ];
}

function upsert_calendar_care_event_local($conexion, $bookingRequestId, $clientUserId, $title, $startAt, $endAt, $allDay, $description)
{
    $bookingRequestId = (int)$bookingRequestId;
    $clientUserId = (int)$clientUserId;
    $title = trim((string)$title);
    $startAt = trim((string)$startAt);
    $description = trim((string)$description);
    $allDay = ((int)$allDay === 1) ? 1 : 0;
    $endAt = ($endAt === null || trim((string)$endAt) === '') ? null : trim((string)$endAt);

    if ($bookingRequestId <= 0 || $clientUserId <= 0 || $title === '' || $startAt === '') {
        return false;
    }
    if (!table_exists_local($conexion, 'calendar_events')) {
        return false;
    }

    $eventData = [
        'title' => $title,
        'description' => $description,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'all_day' => $allDay,
        'event_type' => 'CARE',
        'request_id' => $bookingRequestId,
        'item_id' => null,
        'created_by_role' => 'CLIENT',
        'created_by_user_id' => $clientUserId,
        'client_user_id' => $clientUserId,
        'status' => 'proposed',
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (table_has_column_local($conexion, 'calendar_events', 'thread_id')) {
        $eventData['thread_id'] = 'CARE:' . $bookingRequestId;
    }

    $existingId = 0;
    if (table_has_column_local($conexion, 'calendar_events', 'title')
        && table_has_column_local($conexion, 'calendar_events', 'request_id')
        && table_has_column_local($conexion, 'calendar_events', 'event_type')
    ) {
        $stmtExists = mysqli_prepare(
            $conexion,
            "SELECT id FROM calendar_events WHERE request_id = ? AND event_type = 'CARE' AND title = ? LIMIT 1"
        );
        if ($stmtExists) {
            mysqli_stmt_bind_param($stmtExists, 'is', $bookingRequestId, $title);
            if (mysqli_stmt_execute($stmtExists)) {
                $existsRow = stmt_fetch_assoc_local($stmtExists);
                $existingId = (int)($existsRow['id'] ?? 0);
            }
            mysqli_stmt_close($stmtExists);
        }
    }

    if ($existingId > 0) {
        $setParts = [];
        $values = [];
        foreach ($eventData as $col => $val) {
            if (!table_has_column_local($conexion, 'calendar_events', $col) || $col === 'created_by_role' || $col === 'created_by_user_id') {
                continue;
            }
            $setParts[] = "`{$col}` = ?";
            $values[] = $val;
        }
        if (empty($setParts)) {
            return true;
        }
        $sql = "UPDATE calendar_events SET " . implode(', ', $setParts) . " WHERE id = ? LIMIT 1";
        $stmtUpdate = mysqli_prepare($conexion, $sql);
        if (!$stmtUpdate) {
            return false;
        }
        $types = str_repeat('s', count($values)) . 'i';
        $values[] = $existingId;
        if (!bind_stmt_params_local($stmtUpdate, $types, $values)) {
            mysqli_stmt_close($stmtUpdate);
            return false;
        }
        $ok = mysqli_stmt_execute($stmtUpdate);
        if (!$ok) {
            error_log('booking_submit: calendar care upsert update failed event_id=' . $existingId . ' error=' . mysqli_stmt_error($stmtUpdate));
        }
        mysqli_stmt_close($stmtUpdate);
        return $ok;
    }

    $insert = run_dynamic_insert_local(
        $conexion,
        'calendar_events',
        $eventData,
        ['title', 'start_at', 'event_type', 'request_id', 'created_by_role', 'status'],
        'calendar_seed'
    );
    if (!$insert['ok']) {
        error_log('booking_submit: calendar care upsert insert failed request_id=' . $bookingRequestId . ' title=' . $title . ' error=' . (string)$insert['error']);
        return false;
    }
    return true;
}

function seed_initial_calendar_event_for_booking($conexion, $bookingRequestId, $clientUserId, $bookingDatetime, $timelineText)
{
    $bookingRequestId = (int)$bookingRequestId;
    $clientUserId = (int)$clientUserId;
    $timelineText = trim((string)$timelineText);

    if ($bookingRequestId <= 0 || $clientUserId <= 0) {
        return false;
    }
    if (!table_exists_local($conexion, 'calendar_events') || !table_exists_local($conexion, 'booking_requests')) {
        return false;
    }

    $hasRequestsSoftDelete = table_has_column_local($conexion, 'booking_requests', 'is_deleted');
    $requestSql = "SELECT id, client_user_id, created_at FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $requestSql .= " AND is_deleted = 0";
    }
    $requestSql .= " LIMIT 1";
    $stmtRequest = mysqli_prepare($conexion, $requestSql);
    if (!$stmtRequest) {
        return false;
    }
    mysqli_stmt_bind_param($stmtRequest, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmtRequest)) {
        mysqli_stmt_close($stmtRequest);
        return false;
    }
    $requestRow = stmt_fetch_assoc_local($stmtRequest);
    mysqli_stmt_close($stmtRequest);
    if (!$requestRow) {
        return false;
    }
    if ((int)($requestRow['client_user_id'] ?? 0) > 0) {
        $clientUserId = (int)$requestRow['client_user_id'];
    }
    if ($clientUserId <= 0) {
        return false;
    }

    $parsedStart = parse_booking_datetime_bogota_local($bookingDatetime);
    $parsedRange = parse_timeline_date_range_local($timelineText);
    $requestCreatedAt = parse_booking_datetime_bogota_local((string)($requestRow['created_at'] ?? ''));
    if ($requestCreatedAt === '') {
        $requestCreatedAt = parse_booking_datetime_bogota_local(date('Y-m-d H:i:s'));
    }

    $seeded = false;

    if ($parsedStart !== '') {
        $seeded = upsert_calendar_care_event_local(
            $conexion,
            $bookingRequestId,
            $clientUserId,
            'Preferred date (patient)',
            $parsedStart,
            date('Y-m-d H:i:s', strtotime($parsedStart . ' +30 minutes')),
            0,
            'Patient submitted preferred date during booking.'
        ) || $seeded;
    } elseif ($timelineText !== '' && $parsedRange === null) {
        $baseDate = $requestCreatedAt !== '' ? substr($requestCreatedAt, 0, 10) : date('Y-m-d');
        $seeded = upsert_calendar_care_event_local(
            $conexion,
            $bookingRequestId,
            $clientUserId,
            'Preferred date (patient)',
            $baseDate . ' 00:00:00',
            null,
            1,
            'Preferred timeline: ' . $timelineText
        ) || $seeded;
    }

    if ($parsedRange !== null) {
        $rangeStart = $parsedRange['start_date'] . ' 00:00:00';
        $rangeEndExclusive = date('Y-m-d 00:00:00', strtotime($parsedRange['end_date'] . ' +1 day'));
        $seeded = upsert_calendar_care_event_local(
            $conexion,
            $bookingRequestId,
            $clientUserId,
            'Travel window (patient)',
            $rangeStart,
            $rangeEndExclusive,
            1,
            'Patient travel window from ' . $parsedRange['start_date'] . ' to ' . $parsedRange['end_date'] . '.'
        ) || $seeded;
    }

    return $seeded;
}

function set_password_reset_token_for_user($conexion, $userId)
{
    $result = [
        'enabled' => false,
        'saved' => false,
        'token' => '',
        'expires_at' => '',
    ];

    if ($userId <= 0) {
        return $result;
    }

    $hasResetToken = table_has_column_local($conexion, 'usuarios', 'password_reset_token');
    $hasResetExpiry = table_has_column_local($conexion, 'usuarios', 'password_reset_expires_at');
    $hasLegacyToken = table_has_column_local($conexion, 'usuarios', 'token');

    // Preferred secure reset flow (24h expiry).
    if ($hasResetToken && $hasResetExpiry) {
        $result['enabled'] = true;
        $token = random_hex_local(32);
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);

        $stmt = mysqli_prepare(
            $conexion,
            "UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ? LIMIT 1"
        );
        if (!$stmt) {
            error_log('booking_submit: set reset token prepare failed: ' . mysqli_error($conexion));
            // continue to legacy fallback if available
        } else {
            mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiresAt, $userId);
            if (!mysqli_stmt_execute($stmt)) {
                error_log('booking_submit: set reset token execute failed: ' . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                // continue to legacy fallback if available
            } else {
                mysqli_stmt_close($stmt);
                $result['saved'] = true;
                $result['token'] = $token;
                $result['expires_at'] = $expiresAt;
                return $result;
            }
        }
    }

    // Legacy compatibility: use usuarios.token as reset link token.
    if ($hasLegacyToken) {
        $result['enabled'] = true;
        $legacyToken = random_hex_local(16);
        $updateLegacySql = "UPDATE usuarios SET token = ? WHERE id = ? LIMIT 1";
        $stmtWrite = mysqli_prepare($conexion, $updateLegacySql);
        if (!$stmtWrite) {
            error_log('booking_submit: set legacy token prepare failed: ' . mysqli_error($conexion));
            return $result;
        }
        mysqli_stmt_bind_param($stmtWrite, 'si', $legacyToken, $userId);
        if (!mysqli_stmt_execute($stmtWrite)) {
            error_log('booking_submit: set legacy token execute failed: ' . mysqli_stmt_error($stmtWrite));
            mysqli_stmt_close($stmtWrite);
            return $result;
        }
        mysqli_stmt_close($stmtWrite);

        $result['saved'] = true;
        $result['token'] = $legacyToken;
        $result['expires_at'] = '';
    }

    return $result;
}

function fetch_booking_summary_items($conexion, $selected_offers, $medtravel_services)
{
    $items = [];

    if (!empty($selected_offers)) {
        $offerIds = array_values(array_filter(array_map('intval', $selected_offers)));
        if (!empty($offerIds)) {
            $offerCsv = implode(',', $offerIds);
            $hasOfferDeleted = table_has_column_local($conexion, 'provider_service_offers', 'is_deleted');
            $hasProviderDeleted = table_has_column_local($conexion, 'providers', 'is_deleted');
            $offerSql = "SELECT o.id,
                                COALESCE(NULLIF(o.title, ''), sc.name, CONCAT('Offer #', o.id)) AS item_name,
                                COALESCE(p.name, 'MedTravel') AS provider_name,
                                COALESCE(cat.name, 'General Medical') AS category_name,
                                o.price_from AS price,
                                COALESCE(NULLIF(o.currency, ''), 'USD') AS currency
                         FROM provider_service_offers o
                         LEFT JOIN providers p ON p.id = o.provider_id
                         LEFT JOIN service_catalog sc ON sc.id = o.service_id
                         LEFT JOIN service_categories cat ON cat.id = sc.category_id
                         WHERE o.id IN ({$offerCsv})";
            if ($hasOfferDeleted) {
                $offerSql .= " AND o.is_deleted = 0";
            }
            if ($hasProviderDeleted) {
                $offerSql .= " AND (p.id IS NULL OR p.is_deleted = 0)";
            }
            $offerSql .= " ORDER BY o.id ASC";

            $offerRes = mysqli_query($conexion, $offerSql);
            if ($offerRes) {
                while ($row = mysqli_fetch_assoc($offerRes)) {
                    $currency = strtoupper(trim((string)($row['currency'] ?? 'USD')));
                    if ($currency === '') {
                        $currency = 'USD';
                    }
                    $price = is_numeric($row['price']) ? (float)$row['price'] : 0.0;
                    $items[] = [
                        'item_type' => 'medical_offer',
                        'item_type_label' => 'Medical',
                        'name' => (string)$row['item_name'],
                        'provider' => (string)$row['provider_name'],
                        'category' => (string)$row['category_name'],
                        'price' => $price,
                        'currency' => $currency,
                    ];
                }
            }
        }
    }

    if (!empty($medtravel_services)) {
        $serviceIds = array_values(array_filter(array_map('intval', $medtravel_services)));
        if (!empty($serviceIds)) {
            $serviceCsv = implode(',', $serviceIds);
            $providerCol = null;
            if (table_has_column_local($conexion, 'medtravel_services_catalog', 'provider_id')) {
                $providerCol = 'provider_id';
            } elseif (table_has_column_local($conexion, 'medtravel_services_catalog', 'service_provider_id')) {
                $providerCol = 'service_provider_id';
            }

            $hasSvcDeleted = table_has_column_local($conexion, 'medtravel_services_catalog', 'is_deleted');
            $hasSvcProviderDeleted = table_has_column_local($conexion, 'service_providers', 'is_deleted');

            $serviceSql = "SELECT m.id,
                                  COALESCE(NULLIF(m.service_name, ''), CONCAT('Service #', m.id)) AS item_name,
                                  COALESCE(sp.provider_name, 'MedTravel') AS provider_name,
                                  COALESCE(NULLIF(m.service_type, ''), 'other') AS category_name,
                                  m.sale_price AS price,
                                  COALESCE(NULLIF(m.currency, ''), 'USD') AS currency
                           FROM medtravel_services_catalog m";
            if ($providerCol !== null && table_exists_local($conexion, 'service_providers')) {
                $serviceSql .= " LEFT JOIN service_providers sp ON sp.id = m.`{$providerCol}`";
            } else {
                $serviceSql .= " LEFT JOIN service_providers sp ON 1=0";
            }
            $serviceSql .= " WHERE m.id IN ({$serviceCsv})";
            if ($hasSvcDeleted) {
                $serviceSql .= " AND m.is_deleted = 0";
            }
            if ($hasSvcProviderDeleted) {
                $serviceSql .= " AND (sp.id IS NULL OR sp.is_deleted = 0)";
            }
            $serviceSql .= " ORDER BY m.id ASC";

            $serviceRes = mysqli_query($conexion, $serviceSql);
            if ($serviceRes) {
                while ($row = mysqli_fetch_assoc($serviceRes)) {
                    $currency = strtoupper(trim((string)($row['currency'] ?? 'USD')));
                    if ($currency === '') {
                        $currency = 'USD';
                    }
                    $price = is_numeric($row['price']) ? (float)$row['price'] : 0.0;
                    $items[] = [
                        'item_type' => 'complementary_service',
                        'item_type_label' => 'Complementary',
                        'name' => (string)$row['item_name'],
                        'provider' => (string)$row['provider_name'],
                        'category' => ucfirst((string)$row['category_name']),
                        'price' => $price,
                        'currency' => $currency,
                    ];
                }
            }
        }
    }

    return $items;
}

function build_totals_by_currency($items)
{
    $totals = [];
    foreach ($items as $item) {
        $currency = strtoupper(trim((string)($item['currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }
        $price = isset($item['price']) ? (float)$item['price'] : 0.0;
        if ($price <= 0) {
            continue;
        }
        if (!isset($totals[$currency])) {
            $totals[$currency] = 0.0;
        }
        $totals[$currency] += $price;
    }
    return $totals;
}

function format_totals_by_currency($totals)
{
    if (empty($totals)) {
        return 'Price on request';
    }
    $parts = [];
    foreach ($totals as $currency => $amount) {
        $parts[] = $currency . ' $' . number_format((float)$amount, 2);
    }
    return implode(' / ', $parts);
}

function format_item_price_display($item)
{
    $price = isset($item['price']) ? (float)$item['price'] : 0.0;
    $currency = strtoupper(trim((string)($item['currency'] ?? 'USD')));
    if ($currency === '') {
        $currency = 'USD';
    }
    if ($price <= 0) {
        return 'On request';
    }
    return $currency . ' $' . number_format($price, 2);
}

function escape_html_local($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function booking_mask_email_local($email)
{
    $email = trim((string)$email);
    if ($email === '' || strpos($email, '@') === false) {
        return '';
    }
    [$local, $domain] = explode('@', $email, 2);
    $localPrefix = substr($local, 0, 2);
    $domainParts = explode('.', $domain);
    $domainPrefix = substr((string)($domainParts[0] ?? ''), 0, 2);
    $domainSuffix = count($domainParts) > 1 ? '.' . end($domainParts) : '';
    return $localPrefix . '***@' . $domainPrefix . '***' . $domainSuffix;
}

function booking_fetch_patient_email_for_request_local($conexion, $bookingRequestId)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0 || !table_has_column_local($conexion, 'booking_requests', 'email')) {
        return '';
    }

    $stmt = mysqli_prepare($conexion, 'SELECT email FROM booking_requests WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingRequestId);
    $email = '';
    if (mysqli_stmt_execute($stmt)) {
        $row = stmt_fetch_assoc_local($stmt);
        if ($row && isset($row['email'])) {
            $email = trim((string)$row['email']);
        }
    }
    mysqli_stmt_close($stmt);
    return $email;
}

function build_submission_summary_payload($bookingRequestId, $booking, $timeline, $items)
{
    $totals = build_totals_by_currency($items);
    $normalizedItems = [];
    foreach ($items as $item) {
        $item['price_display'] = format_item_price_display($item);
        $normalizedItems[] = $item;
    }

    return [
        'booking_id' => (int)$bookingRequestId,
        'patient_name' => (string)($booking['name'] ?? ''),
        'patient_email' => (string)($booking['email'] ?? ''),
        'patient_phone' => (string)($booking['phone'] ?? ''),
        'destination' => (string)($booking['destination'] ?? ''),
        'timeline' => (string)$timeline,
        'items' => $normalizedItems,
        'totals_by_currency' => $totals,
        'total_display' => format_totals_by_currency($totals),
    ];
}

function build_booking_confirmation_content_html($summaryPayload, $passwordResetUrl, $loginLink)
{
    $bookingId = (int)($summaryPayload['booking_id'] ?? 0);
    $patientName = trim((string)($summaryPayload['patient_name'] ?? ''));
    $patientEmail = trim((string)($summaryPayload['patient_email'] ?? ''));
    if ($patientName === '') {
        $patientName = 'Patient';
    }

    $timeline = trim((string)($summaryPayload['timeline'] ?? ''));
    $destination = trim((string)($summaryPayload['destination'] ?? ''));
    $totalDisplay = trim((string)($summaryPayload['total_display'] ?? 'Price on request'));
    $items = (isset($summaryPayload['items']) && is_array($summaryPayload['items'])) ? $summaryPayload['items'] : [];

    $itemRowsHtml = '';
    foreach ($items as $item) {
        $typeLabel = trim((string)($item['item_type_label'] ?? ''));
        if ($typeLabel === '') {
            $typeLabel = ((string)($item['item_type'] ?? '') === 'complementary_service') ? 'Complementary' : 'Medical';
        }
        $itemRowsHtml .= '<tr>'
            . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#0f172a;">' . escape_html_local($item['name'] ?? '-') . '</td>'
            . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . escape_html_local($typeLabel) . '</td>'
            . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . escape_html_local($item['provider'] ?? 'MedTravel') . '</td>'
            . '<td style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#334155;">' . escape_html_local($item['price_display'] ?? format_item_price_display($item)) . '</td>'
            . '</tr>';
    }
    if ($itemRowsHtml === '') {
        $itemRowsHtml = '<tr><td colspan="4" style="padding:10px; border:1px solid #dbe4f0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#64748b;">No services were itemized yet. A coordinator will complete details with you.</td></tr>';
    }

    $portalAccessBlock = ''
        . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Access your MedTravel Patient Portal</h3>'
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">We have created a secure patient account for you.</p>'
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Username:</strong> ' . escape_html_local($patientEmail !== '' ? $patientEmail : 'Not available') . '</p>'
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">To activate your access, please create your password using the secure link below.</p>';

    $accessUrl = $passwordResetUrl !== '' ? $passwordResetUrl : 'https://medtravel.com.co/set_password.php';
    $portalAccessBlock .= '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Create your password:</strong> <a href="' . escape_html_local($accessUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . escape_html_local($accessUrl) . '</a></p>'
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">If the button does not work, copy and paste this link:<br><a href="' . escape_html_local($accessUrl) . '" style="color:#0b4ea2; text-decoration:none;">' . escape_html_local($accessUrl) . '</a></p>';

    $portalAccessBlock .= ''
        . '<p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">For security reasons, this link expires in 24 hours. If it expires, you can request a new one on the same page.</p>'
        . '<p style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">In your portal you can:</p>'
        . '<ul style="margin:0 0 16px 18px; padding:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">'
        . '<li style="margin:0 0 6px 0;">Track the status of each requested service</li>'
        . '<li style="margin:0 0 6px 0;">See provider confirmations or proposed changes</li>'
        . '<li style="margin:0 0 6px 0;">Review updates from MedTravel</li>'
        . '<li style="margin:0;">Follow your case progress in real time</li>'
        . '</ul>';

    $content = ''
        . '<p style="margin:0 0 14px 0;">Hello ' . escape_html_local($patientName) . ',</p>'
        . '<p style="margin:0 0 14px 0;">We received your request and opened your case with MedTravel.</p>'
        . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 16px 0;">'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Booking ID:</strong> #' . $bookingId . '</td></tr>'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Patient:</strong> ' . escape_html_local($patientName) . '</td></tr>'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Email:</strong> ' . escape_html_local($patientEmail) . '</td></tr>'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Destination:</strong> ' . escape_html_local($destination !== '' ? $destination : 'Not specified') . '</td></tr>'
        . '<tr><td style="padding:6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;"><strong>Timeline:</strong> ' . escape_html_local($timeline !== '' ? $timeline : 'To be defined') . '</td></tr>'
        . '</table>'
        . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Selected services</h3>'
        . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 12px 0;">'
        . '<tr>'
        . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Service</th>'
        . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Type</th>'
        . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Provider</th>'
        . '<th align="left" style="padding:10px; border:1px solid #dbe4f0; background:#eff5ff; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#13357b;">Price</th>'
        . '</tr>'
        . $itemRowsHtml
        . '</table>'
        . '<p style="margin:0 0 16px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#0f172a;"><strong>Total estimated:</strong> ' . escape_html_local($totalDisplay) . '</p>'
        . $portalAccessBlock
        . '<h3 style="margin:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:18px; color:#13357b;">Next steps</h3>'
        . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:0 0 16px 0;">'
        . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">1. MedTravel reviews your request.</td></tr>'
        . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">2. We coordinate providers.</td></tr>'
        . '<tr><td style="padding:4px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">3. You will receive confirmation updates.</td></tr>'
        . '</table>'
        . '<p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">After creating your password, you can sign in here: <a href="' . escape_html_local($loginLink) . '" style="color:#0b4ea2; text-decoration:none;">' . escape_html_local($loginLink) . '</a></p>';

    return $content;
}

function send_booking_confirmation_email($conexion, $email, $summaryPayload, $resetToken, $isNewUser = false, $hasLinkedPatientAccount = true)
{
    $email = trim((string)$email);
    $bookingId = (int)($summaryPayload['booking_id'] ?? 0);
    error_log('[MedTravel Email] patient email start booking_id=' . $bookingId);
    error_log('[MedTravel Email] patient email recipient present=' . ($email !== '' ? 'yes' : 'no'));
    booking_runtime_log_local('patient_email_start booking_id=' . $bookingId . ' recipient_present=' . ($email !== '' ? '1' : '0') . ' recipient=' . booking_mask_email_local($email));
    if ($email === '') {
        error_log('[MedTravel Email] patient email sent=no');
        error_log('[MedTravel Email] patient email error: missing recipient');
        booking_runtime_log_local('patient_email_sent booking_id=' . $bookingId . ' sent=0 error=missing_recipient');
        return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('[MedTravel Email] patient email sent=no');
        error_log('[MedTravel Email] patient email error: invalid recipient ' . booking_mask_email_local($email));
        booking_runtime_log_local('patient_email_sent booking_id=' . $bookingId . ' sent=0 error=invalid_recipient');
        return false;
    }

    if ($isNewUser && trim((string)$resetToken) === '') {
        $fallbackBooking = [
            'email' => $email,
            'name' => (string)($summaryPayload['patient_name'] ?? ''),
            'phone' => (string)($summaryPayload['patient_phone'] ?? ''),
        ];
        $fallbackIsNewUser = false;
        $fallbackUserId = find_or_create_client_user_for_booking($conexion, $fallbackBooking, $fallbackIsNewUser);
        if ($fallbackUserId > 0) {
            $fallbackReset = set_password_reset_token_for_user($conexion, $fallbackUserId);
            if (!empty($fallbackReset['saved']) && !empty($fallbackReset['token'])) {
                $resetToken = (string)$fallbackReset['token'];
            }
        }
    }

    if ($isNewUser && trim((string)$resetToken) === '') {
        error_log('booking_submit: password reset token unavailable for booking_id=' . $bookingId . ' recipient=' . booking_mask_email_local($email) . ' using generic access page');
    }

    $patientName = trim((string)($summaryPayload['patient_name'] ?? ''));
    if ($patientName === '') {
        $patientName = 'Patient';
    }

    try {
        $emailPayload = booking_patient_build_email_payload([
            'flow' => 'public',
            'booking_id' => $bookingId,
            'patient_name' => $patientName,
            'patient_email' => (string)($summaryPayload['patient_email'] ?? $email),
            'destination' => (string)($summaryPayload['destination'] ?? ''),
            'timeline' => (string)($summaryPayload['timeline'] ?? ''),
            'total_display' => (string)($summaryPayload['total_display'] ?? 'Price on request'),
            'items' => (isset($summaryPayload['items']) && is_array($summaryPayload['items'])) ? $summaryPayload['items'] : [],
            'is_new_user' => !empty($isNewUser),
            'has_linked_patient_account' => !empty($hasLinkedPatientAccount),
            'reset_token' => (string)$resetToken,
        ]);
    } catch (Throwable $e) {
        error_log('[MedTravel Email] patient payload error booking_id=' . $bookingId . ': ' . $e->getMessage());
        booking_runtime_log_local('patient_email_payload_error booking_id=' . $bookingId . ' msg=' . $e->getMessage());
        $contentHtml = '<p>Hello ' . escape_html_local($patientName) . ',</p>'
            . '<p>We received your request and opened your case with MedTravel.</p>'
            . '<p><strong>Booking ID:</strong> #' . $bookingId . '</p>'
            . '<p>Our coordination team will review your request and connect you with the appropriate professional.</p>'
            . '<p>This message confirms receipt of your request; it does not confirm a medical procedure.</p>';
        $emailPayload = [
            'subject' => "MedTravel - Request received (ID #{$bookingId})",
            'body_html' => function_exists('renderMedTravelEmail')
                ? renderMedTravelEmail('Request received', 'We received your request and opened your MedTravel case.', $contentHtml, 'This is an automated message from MedTravel.')
                : $contentHtml,
            'alt_body' => "Hello {$patientName},\n\nWe received your request and opened your case with MedTravel.\n\nBooking ID: #{$bookingId}\n\nOur coordination team will review your request and connect you with the appropriate professional.\n\nThis message confirms receipt of your request; it does not confirm a medical procedure.\n",
            'access_url' => '',
        ];
    }

    try {
        $emailResult = sendEmail(
            $email,
            (string)($emailPayload['subject'] ?? "MedTravel – Request received (ID #{$bookingId})"),
            (string)($emailPayload['body_html'] ?? ''),
            'patientcare',
            [
                'alt_body' => (string)($emailPayload['alt_body'] ?? ''),
                'password_reset_url' => (string)($emailPayload['access_url'] ?? ''),
            ],
            $conexion
        );
        if ($emailResult !== true) {
            $errorMessage = is_array($emailResult) ? (string)($emailResult['error'] ?? 'unknown_error') : 'unknown_error';
            error_log('[MedTravel Email] patient email sent=no');
            error_log('[MedTravel Email] patient email error: ' . $errorMessage);
            booking_runtime_log_local('patient_email_sent booking_id=' . $bookingId . ' sent=0 error=' . $errorMessage);
            return false;
        }
        error_log('[MedTravel Email] patient email sent=yes');
        booking_runtime_log_local('patient_email_sent booking_id=' . $bookingId . ' sent=1');
        return true;
    } catch (Throwable $e) {
        error_log('[MedTravel Email] patient email sent=no');
        error_log('[MedTravel Email] patient confirmation email error: ' . $e->getMessage());
        error_log('[MedTravel Email] patient email error: ' . $e->getMessage());
        booking_runtime_log_local('patient_email_sent booking_id=' . $bookingId . ' sent=0 error=' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: wizard.php');
    exit;
}

try {
    error_log('[MedTravel Booking] submit reached');
    $booking = (isset($_SESSION['booking_request']) && is_array($_SESSION['booking_request'])) ? $_SESSION['booking_request'] : [];

// Fallback for cases where wizard is opened without step-1 session but hidden fields exist.
$fallbackBooking = [
    'name' => isset($_POST['name']) ? trim((string)$_POST['name']) : '',
    'email' => isset($_POST['email']) ? trim((string)$_POST['email']) : '',
    'phone' => isset($_POST['phone']) ? trim((string)$_POST['phone']) : '',
    'origin' => isset($_POST['origin']) ? trim((string)$_POST['origin']) : '',
    'destination' => isset($_POST['destination']) ? trim((string)$_POST['destination']) : '',
    'persons' => isset($_POST['persons']) ? trim((string)$_POST['persons']) : '',
    'category' => isset($_POST['category']) ? trim((string)$_POST['category']) : '',
    'special_request' => isset($_POST['special_request']) ? trim((string)$_POST['special_request']) : '',
    'preselected_offer' => isset($_POST['preselected_offer']) ? trim((string)$_POST['preselected_offer']) : '',
    'consent_terms' => isset($_POST['consent_terms']) ? trim((string)$_POST['consent_terms']) : '',
    'consent_privacy' => isset($_POST['consent_privacy']) ? trim((string)$_POST['consent_privacy']) : '',
    'consent_insurance' => isset($_POST['consent_insurance']) ? trim((string)$_POST['consent_insurance']) : '',
    'terms_accepted' => isset($_POST['terms_accepted']) ? trim((string)$_POST['terms_accepted']) : '',
    'utm_source'   => isset($_POST['utm_source'])   ? substr(trim((string)$_POST['utm_source']),   0, 255) : '',
    'utm_medium'   => isset($_POST['utm_medium'])   ? substr(trim((string)$_POST['utm_medium']),   0, 255) : '',
    'utm_campaign' => isset($_POST['utm_campaign']) ? substr(trim((string)$_POST['utm_campaign']), 0, 255) : '',
    'utm_content'  => isset($_POST['utm_content'])  ? substr(trim((string)$_POST['utm_content']),  0, 255) : '',
    'utm_term'     => isset($_POST['utm_term'])      ? substr(trim((string)$_POST['utm_term']),      0, 255) : '',
];

foreach ($fallbackBooking as $k => $v) {
    if ((empty($booking[$k]) || trim((string)$booking[$k]) === '') && $v !== '') {
        $booking[$k] = $v;
    }
}

foreach (['name', 'email', 'phone'] as $contactField) {
    if (isset($fallbackBooking[$contactField]) && $fallbackBooking[$contactField] !== '') {
        $booking[$contactField] = $fallbackBooking[$contactField];
    }
}

foreach (['consent_terms', 'consent_privacy', 'consent_insurance'] as $consentField) {
    if (isset($fallbackBooking[$consentField])) {
        $booking[$consentField] = ($fallbackBooking[$consentField] === '1') ? '1' : '';
    }
}

if (
    (string)($booking['consent_terms'] ?? '') === '1'
    && (string)($booking['consent_privacy'] ?? '') === '1'
    && (string)($booking['consent_insurance'] ?? '') === '1'
) {
    $booking['terms_accepted'] = '1';
} elseif (isset($fallbackBooking['terms_accepted'])) {
    $booking['terms_accepted'] = ($fallbackBooking['terms_accepted'] === '1') ? '1' : '';
}

if (empty($booking['origin'])) {
    $booking['origin'] = 'wizard';
}
$_SESSION['booking_request'] = $booking;
booking_runtime_log_local(
    'submit_start'
    . ' email_present=' . (!empty($booking['email']) ? '1' : '0')
    . ' email=' . booking_mask_email_local((string)($booking['email'] ?? ''))
    . ' name_present=' . (!empty($booking['name']) ? '1' : '0')
    . ' has_selected_offers=' . (isset($_POST['selected_offers']) ? '1' : '0')
    . ' has_medtravel_services=' . (isset($_POST['medtravel_services']) ? '1' : '0')
);

if (empty($booking['name']) || empty($booking['email'])) {
    $_SESSION['booking_request_status'] = 'error';
    $_SESSION['booking_request_message'] = 'Please complete your contact data (name and email) before submitting.';
    booking_runtime_log_local('submit_abort_missing_contact');
    header('Location: wizard.php');
    exit;
}

$termsAccepted = 0;
if (isset($booking['terms_accepted'])) {
    $termsAccepted = (int)$booking['terms_accepted'];
} elseif (isset($_POST['terms_accepted'])) {
    $termsAccepted = (int)$_POST['terms_accepted'];
}

if ($termsAccepted !== 1) {
    booking_runtime_log_local('submit_abort_terms_not_accepted');
    if (booking_wants_json_response_local()) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'code' => 'TERMS_NOT_ACCEPTED',
            'message' => 'You must accept the Terms to continue.'
        ]);
        exit;
    }
    $_SESSION['booking_request_status'] = 'error';
    $_SESSION['booking_request_message'] = 'You must accept the Terms to continue.';
    header('Location: wizard.php');
    exit;
}

$termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.1';
$termsAcceptedAt = !empty($booking['terms_accepted_at']) ? (string)$booking['terms_accepted_at'] : date('Y-m-d H:i:s');
$termsIp = !empty($booking['terms_ip']) ? (string)$booking['terms_ip'] : booking_client_ip_local();
$termsUserAgent = !empty($booking['terms_user_agent']) ? (string)$booking['terms_user_agent'] : (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$termsUserAgent = substr($termsUserAgent, 0, 255);

$booking['terms_accepted'] = 1;
$booking['terms_version'] = $termsVersion;
$booking['terms_accepted_at'] = $termsAcceptedAt;
$booking['terms_ip'] = $termsIp;
$booking['terms_user_agent'] = $termsUserAgent;
$_SESSION['booking_request'] = $booking;

$selected_offers = post_int_array_local('selected_offers');
$medtravel_services = post_int_array_local('medtravel_services');

$budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? number_format((float) $_POST['budget'], 2, '.', '') : null;

$timeline_from = isset($_POST['timeline_from']) ? trim($_POST['timeline_from']) : '';
$timeline_to = isset($_POST['timeline_to']) ? trim($_POST['timeline_to']) : '';
$timeline = '';
if ($timeline_from && $timeline_to) {
    $timeline = $timeline_from . ' to ' . $timeline_to;
} elseif ($timeline_from) {
    $timeline = 'From ' . $timeline_from;
} elseif ($timeline_to) {
    $timeline = 'Until ' . $timeline_to;
}

$additional_notes = isset($_POST['additional_notes']) ? trim($_POST['additional_notes']) : '';

if (!empty($medtravel_services)) {
    $ids = implode(',', array_map('intval', $medtravel_services));
    $med_query = mysqli_query($conexion, "SELECT service_name FROM medtravel_services_catalog WHERE id IN ($ids)");
    $med_names = [];
    if ($med_query) {
        while ($row = mysqli_fetch_assoc($med_query)) {
            $med_names[] = $row['service_name'];
        }
    }
    if (!empty($med_names)) {
        $additional_notes .= "\n\nMedTravel Services Requested:\n- " . implode("\n- ", $med_names);
    }
}

$origin = isset($booking['origin']) ? $booking['origin'] : 'wizard';
$phone = isset($booking['phone']) ? $booking['phone'] : '';
$selected_offers_json = json_encode($selected_offers);
$booking_datetime = $timeline_from ?: (isset($booking['datetime']) ? $booking['datetime'] : '');

$booking['datetime'] = $booking_datetime;
$booking['timeline_from'] = $timeline_from;
$booking['timeline_to'] = $timeline_to;
$_SESSION['booking_request'] = $booking;

$destination = isset($booking['destination']) ? $booking['destination'] : '';
$persons = isset($booking['persons']) ? $booking['persons'] : '';
$category = isset($booking['category']) ? $booking['category'] : '';
$special_request = isset($booking['special_request']) ? $booking['special_request'] : '';

$saved = false;
$booking_request_id = 0;
$client_user_id = 0;
$client_user_is_new = false;
$summaryPayload = [];

try {
    $client_user_id = find_or_create_client_user_for_booking($conexion, $booking, $client_user_is_new);
} catch (Throwable $e) {
    error_log('booking_submit: pre-insert client lookup error: ' . $e->getMessage());
}
$clientUserForBooking = ($client_user_id > 0) ? $client_user_id : null;

$staticPayload = [
    'name' => (string)($booking['name'] ?? ''),
    'email' => (string)($booking['email'] ?? ''),
    'phone' => ($phone !== '' ? (string)$phone : null),
    'origin' => ($origin !== '' ? (string)$origin : null),
    'booking_datetime' => ($booking_datetime !== '' ? (string)$booking_datetime : null),
    'destination' => ($destination !== '' ? (string)$destination : null),
    'persons' => ($persons !== '' ? (string)$persons : null),
    'category' => ($category !== '' ? (string)$category : null),
    'special_request' => ($special_request !== '' ? (string)$special_request : null),
    'selected_offers' => (string)$selected_offers_json,
    'budget' => $budget,
    'timeline' => ($timeline !== '' ? (string)$timeline : null),
    'additional_notes' => ($additional_notes !== '' ? (string)$additional_notes : null),
    'client_user_id' => $clientUserForBooking,
    'terms_accepted' => 1,
    'terms_accepted_at' => $termsAcceptedAt,
    'terms_version' => $termsVersion,
    'terms_ip' => $termsIp,
    'terms_user_agent' => $termsUserAgent,
    'utm_source'   => ($booking['utm_source']   ?? '') !== '' ? (string)$booking['utm_source']   : null,
    'utm_medium'   => ($booking['utm_medium']   ?? '') !== '' ? (string)$booking['utm_medium']   : null,
    'utm_campaign' => ($booking['utm_campaign'] ?? '') !== '' ? (string)$booking['utm_campaign'] : null,
    'utm_content'  => ($booking['utm_content']  ?? '') !== '' ? (string)$booking['utm_content']  : null,
    'utm_term'     => ($booking['utm_term']      ?? '') !== '' ? (string)$booking['utm_term']      : null,
];

$attemptStatic = run_static_booking_insert_local($conexion, $staticPayload);
$saved = $attemptStatic['ok'];
$booking_request_id = (int)$attemptStatic['id'];
if (!$saved) {
    error_log('booking_submit: booking_requests static insert failed: ' . $attemptStatic['error'] . ' columns=' . implode(',', $attemptStatic['columns']));
    booking_runtime_log_local('static_insert_failed error=' . $attemptStatic['error']);
}

if (!$saved) {
    // Dynamic fallback for schema drift in production environments.
    $bookingRequestData = [
        'name' => (string)($booking['name'] ?? ''),
        'email' => (string)($booking['email'] ?? ''),
        'phone' => (string)$phone,
        'origin' => (string)$origin,
        'booking_datetime' => (string)$booking_datetime,
        'destination' => (string)$destination,
        'persons' => (string)$persons,
        'category' => (string)$category,
        'special_request' => (string)$special_request,
        'service_categories' => null,
        'medical_services' => null,
        'selected_offers' => (string)$selected_offers_json,
        'budget' => $budget,
        'timeline' => (string)$timeline,
        'additional_notes' => (string)$additional_notes,
        'client_user_id' => $clientUserForBooking,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'terms_accepted' => 1,
        'terms_accepted_at' => $termsAcceptedAt,
        'terms_version' => $termsVersion,
        'terms_ip' => $termsIp,
        'terms_user_agent' => $termsUserAgent,
        'utm_source'   => ($booking['utm_source']   ?? '') !== '' ? (string)$booking['utm_source']   : null,
        'utm_medium'   => ($booking['utm_medium']   ?? '') !== '' ? (string)$booking['utm_medium']   : null,
        'utm_campaign' => ($booking['utm_campaign'] ?? '') !== '' ? (string)$booking['utm_campaign'] : null,
        'utm_content'  => ($booking['utm_content']  ?? '') !== '' ? (string)$booking['utm_content']  : null,
        'utm_term'     => ($booking['utm_term']      ?? '') !== '' ? (string)$booking['utm_term']      : null,
    ];

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$timeline_from)) {
        $bookingRequestData['timeline_from'] = (string)$timeline_from;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$timeline_to)) {
        $bookingRequestData['timeline_to'] = (string)$timeline_to;
    }

    foreach ($bookingRequestData as $col => $val) {
        if (($col !== 'name' && $col !== 'email' && $col !== 'status' && $col !== 'created_at') && is_string($val) && trim($val) === '') {
            $bookingRequestData[$col] = null;
        }
    }

    if (isset($bookingRequestData['booking_datetime']) && is_string($bookingRequestData['booking_datetime'])) {
        $bd = trim($bookingRequestData['booking_datetime']);
        if ($bd !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $bd)) {
            $bookingRequestData['booking_datetime'] = null;
        }
    }

    $attemptPrimary = run_dynamic_insert_local($conexion, 'booking_requests', $bookingRequestData, ['name', 'email'], 'primary');
    $saved = $attemptPrimary['ok'];
    $booking_request_id = (int)$attemptPrimary['id'];

    if (!$saved) {
        error_log('booking_submit: booking_requests dynamic primary insert failed: ' . $attemptPrimary['error'] . ' columns=' . implode(',', $attemptPrimary['columns']));
        booking_runtime_log_local('dynamic_primary_failed error=' . $attemptPrimary['error']);

        $fallbackData = [
            'name' => $bookingRequestData['name'],
            'email' => $bookingRequestData['email'],
            'origin' => $bookingRequestData['origin'] ?? null,
            'destination' => $bookingRequestData['destination'] ?? null,
            'special_request' => $bookingRequestData['special_request'] ?? null,
            'additional_notes' => $bookingRequestData['additional_notes'] ?? null,
            'client_user_id' => $clientUserForBooking,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'terms_accepted' => 1,
            'terms_accepted_at' => $termsAcceptedAt,
            'terms_version' => $termsVersion,
            'terms_ip' => $termsIp,
            'terms_user_agent' => $termsUserAgent,
        ];
        $attemptFallback = run_dynamic_insert_local($conexion, 'booking_requests', $fallbackData, ['name', 'email'], 'fallback');
        $saved = $attemptFallback['ok'];
        $booking_request_id = (int)$attemptFallback['id'];

        if (!$saved) {
            error_log('booking_submit: booking_requests dynamic fallback insert failed: ' . $attemptFallback['error'] . ' columns=' . implode(',', $attemptFallback['columns']));
            booking_runtime_log_local('dynamic_fallback_failed error=' . $attemptFallback['error']);

            $minimalData = [
                'name' => $bookingRequestData['name'],
                'email' => $bookingRequestData['email'],
                'client_user_id' => $clientUserForBooking,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'terms_accepted' => 1,
                'terms_accepted_at' => $termsAcceptedAt,
                'terms_version' => $termsVersion,
                'terms_ip' => $termsIp,
                'terms_user_agent' => $termsUserAgent,
            ];
            $attemptMinimal = run_dynamic_insert_local($conexion, 'booking_requests', $minimalData, ['name', 'email'], 'minimal');
            $saved = $attemptMinimal['ok'];
            $booking_request_id = (int)$attemptMinimal['id'];
            if (!$saved) {
                error_log('booking_submit: booking_requests dynamic minimal insert failed: ' . $attemptMinimal['error'] . ' columns=' . implode(',', $attemptMinimal['columns']));
                booking_runtime_log_local('dynamic_minimal_failed error=' . $attemptMinimal['error']);
            }
        }
    }
}

error_log('[MedTravel Booking] saved flag=' . ($saved ? '1' : '0'));
error_log('[MedTravel Booking] booking_request_id=' . intval($booking_request_id));

if ($saved && $booking_request_id > 0) {
    error_log('[MedTravel Booking] booking saved id=' . intval($booking_request_id));
    $createdItems = [];
    try {
        $createdItems = insert_booking_items_mvp($conexion, $booking_request_id, $selected_offers, $medtravel_services);
    } catch (Throwable $e) {
        error_log('booking_submit: post-save items error booking_id=' . intval($booking_request_id) . ' msg=' . $e->getMessage());
    }

    $mtLeadEventId = booking_meta_generate_event_id_local();
    booking_runtime_log_local('pixel_lead_event_id_created event_id=' . $mtLeadEventId);
    $_SESSION['mt_meta_pixel_lead_pending'] = '1';
    $_SESSION['mt_meta_pixel_lead_event_id'] = $mtLeadEventId;
    unset($_SESSION['mt_meta_pixel_lead_payload']);
    error_log('[MedTravel Pixel] Lead pending set booking_id=' . intval($booking_request_id));
    booking_runtime_log_local('pixel_lead_pending_set booking_id=' . intval($booking_request_id));
    try {
        booking_meta_send_capi_lead_local($mtLeadEventId);
    } catch (Throwable $e) {
        booking_runtime_log_local('meta_capi_lead_error status=exception');
        error_log('[MedTravel Pixel] CAPI Lead exception: ' . $e->getMessage());
    }

    $summaryPayload = build_submission_summary_payload($booking_request_id, $booking, $timeline, []);

    $resetInfo = ['enabled' => false, 'saved' => false, 'token' => ''];
    try {
        if ($client_user_id <= 0) {
            $retryClientUserIsNew = false;
            $client_user_id = find_or_create_client_user_for_booking($conexion, $booking, $retryClientUserIsNew);
            if ($client_user_id > 0) {
                $client_user_is_new = $retryClientUserIsNew;
            }
        }
        if ($client_user_id > 0) {
            update_booking_client_user($conexion, $booking_request_id, $client_user_id);
            $resetInfo = set_password_reset_token_for_user($conexion, $client_user_id);
            seed_initial_calendar_event_for_booking($conexion, $booking_request_id, $client_user_id, $booking_datetime, $timeline);
        }
        booking_runtime_log_local(
            'client_access'
            . ' booking_id=' . intval($booking_request_id)
            . ' client_user_id=' . intval($client_user_id)
            . ' reset_saved=' . (!empty($resetInfo['saved']) ? '1' : '0')
            . ' token_len=' . strlen((string)($resetInfo['token'] ?? ''))
        );
    } catch (Throwable $e) {
        error_log('booking_submit: post-save client/token error booking_id=' . intval($booking_request_id) . ' msg=' . $e->getMessage());
        booking_runtime_log_local('client_access_error booking_id=' . intval($booking_request_id) . ' msg=' . $e->getMessage());
    }

    try {
        $items = fetch_booking_summary_items($conexion, $selected_offers, $medtravel_services);
        $summaryPayload = build_submission_summary_payload($booking_request_id, $booking, $timeline, $items);
        $_SESSION['booking_submission_summary'] = $summaryPayload;

        foreach ($createdItems as $createdItem) {
            $itemType = (string)($createdItem['item_type'] ?? '');
            if ($itemType === 'medical_offer') {
                $notifyResult = booking_notify_provider_new_request_local($conexion, $booking_request_id, $createdItem);
            } elseif ($itemType === 'complementary_service') {
                $notifyResult = booking_notify_complementary_new_request_local($conexion, $booking_request_id, $createdItem);
            } else {
                continue;
            }
            if (is_array($notifyResult) && empty($notifyResult['success']) && empty($notifyResult['skipped'])) {
                error_log('booking_submit: provider notification failed booking_id=' . intval($booking_request_id) . ' item_id=' . intval($createdItem['item_id'] ?? 0) . ' payload=' . json_encode($notifyResult));
            }
        }

        $medicalItemsCount = 0;
        $complementaryItemsCount = 0;
        foreach ($createdItems as $createdItem) {
            $itemType = (string)($createdItem['item_type'] ?? '');
            if ($itemType === 'medical_offer') {
                $medicalItemsCount++;
            } elseif ($itemType === 'complementary_service') {
                $complementaryItemsCount++;
            }
        }
        $patientcareAlert = interaction_email_send_patientcare_booking_alert($conexion, $booking_request_id, [
            'patient_name' => (string)($summaryPayload['patient_name'] ?? ($booking['name'] ?? '')),
            'patient_email' => (string)($summaryPayload['patient_email'] ?? ($booking['email'] ?? '')),
            'patient_phone' => (string)($summaryPayload['patient_phone'] ?? ($booking['phone'] ?? '')),
            'destination' => (string)($summaryPayload['destination'] ?? ($booking['destination'] ?? '')),
            'timeline' => (string)($summaryPayload['timeline'] ?? $timeline),
            'creation_source' => (string)($booking['creation_source'] ?? 'public_booking'),
            'agent_channel' => (string)($booking['agent_channel'] ?? ''),
            'items_count' => count($createdItems),
            'medical_items_count' => $medicalItemsCount,
            'complementary_items_count' => $complementaryItemsCount,
        ]);
        if (is_array($patientcareAlert) && empty($patientcareAlert['success'])) {
            error_log('booking_submit: patientcare alert failed booking_id=' . intval($booking_request_id) . ' payload=' . json_encode($patientcareAlert));
        }

    } catch (Throwable $e) {
        error_log('booking_submit: post-save summary/notification error booking_id=' . intval($booking_request_id) . ' msg=' . $e->getMessage());
    }

    try {
        error_log('[MedTravel Booking] patient email attempted');
        $patientEmailForConfirmation = booking_fetch_patient_email_for_request_local($conexion, $booking_request_id);
        if ($patientEmailForConfirmation === '') {
            $patientEmailForConfirmation = (string)($booking['email'] ?? '');
        }
        if ($patientEmailForConfirmation !== '') {
            $summaryPayload['patient_email'] = $patientEmailForConfirmation;
        }
        $patientEmailSessionKey = 'mt_patient_confirmation_sent_' . intval($booking_request_id);
        if (empty($_SESSION[$patientEmailSessionKey])) {
            $patientEmailSent = send_booking_confirmation_email(
                $conexion,
                $patientEmailForConfirmation,
                $summaryPayload,
                ($resetInfo['saved'] ? (string)$resetInfo['token'] : ''),
                !empty($client_user_is_new),
                ($client_user_id > 0)
            );
            if ($patientEmailSent) {
                $_SESSION[$patientEmailSessionKey] = '1';
            }
        }
    } catch (Throwable $e) {
        error_log('booking_submit: patient confirmation email error booking_id=' . intval($booking_request_id) . ' msg=' . $e->getMessage());
        error_log('[MedTravel Email] patient email sent=no');
        error_log('[MedTravel Email] patient email error: ' . $e->getMessage());
    }
}

if ($saved && $booking_request_id > 0) {
    booking_runtime_log_local('submit_saved booking_id=' . intval($booking_request_id));
    $_SESSION['booking_request_status'] = 'submitted';
    $offers_count = count($selected_offers) + count($medtravel_services);
    $_SESSION['booking_request_message'] = "Your request (ID #{$booking_request_id}) with {$offers_count} selected item(s) was saved. One of our coordinators will reach back soon.";
} else {
    booking_runtime_log_local('submit_not_saved');
    $_SESSION['booking_request_status'] = 'error';
    $_SESSION['booking_request_message'] = 'We could not save your request. Please try again.';
}

header('Location: wizard.php');
exit;
} catch (Throwable $e) {
    error_log('booking_submit: fatal-safe catch: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    booking_runtime_log_local('fatal_catch ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!empty($booking_request_id)) {
        $_SESSION['booking_request_status'] = 'submitted';
        $_SESSION['booking_request_message'] = "Your request (ID #{$booking_request_id}) was saved. A coordinator will contact you shortly.";
    } else {
        $_SESSION['booking_request_status'] = 'error';
        $_SESSION['booking_request_message'] = 'We could not save your request. Please try again.';
    }
    header('Location: wizard.php');
    exit;
}
