<?php
session_start();
include('../include/include.php');
require_once '../include/email_config.php';
require_once '../../inc/email_template.php';
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('is_role_admin_session') || !is_role_admin_session()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden_admin_only']);
    exit;
}

$tipo = isset($_REQUEST['tipo']) ? $_REQUEST['tipo'] : '';

function slugify($text){
    $text = preg_replace('~[^\pL0-9]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) return 'n-a';
    return $text;
}

function table_has_column($conexion, $table, $column){
    static $cache = array();
    $key = $table.'.'.$column;
    if(array_key_exists($key, $cache)) return $cache[$key];
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

function table_exists($conexion, $table){
    static $cache = array();
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $q = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$table];
}

function bind_dynamic_stmt_params($stmt, $values){
    if (empty($values)) {
        return;
    }
    $types = '';
    $bind = array();
    foreach ($values as $idx => $value) {
        $types .= is_int($value) ? 'i' : 's';
        $bindName = 'b' . $idx;
        $$bindName = $value;
        $bind[] = &$$bindName;
    }
    array_unshift($bind, $types);
    call_user_func_array(array($stmt, 'bind_param'), $bind);
}

function provider_table_columns($conexion){
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = array(
        'type' => table_has_column($conexion, 'providers', 'type'),
        'kind' => table_has_column($conexion, 'providers', 'kind'),
        'name' => table_has_column($conexion, 'providers', 'name'),
        'legal_name' => table_has_column($conexion, 'providers', 'legal_name'),
        'slug' => table_has_column($conexion, 'providers', 'slug'),
        'description' => table_has_column($conexion, 'providers', 'description'),
        'city' => table_has_column($conexion, 'providers', 'city'),
        'address' => table_has_column($conexion, 'providers', 'address'),
        'phone' => table_has_column($conexion, 'providers', 'phone'),
        'email' => table_has_column($conexion, 'providers', 'email'),
        'website' => table_has_column($conexion, 'providers', 'website'),
        'is_verified' => table_has_column($conexion, 'providers', 'is_verified'),
        'is_active' => table_has_column($conexion, 'providers', 'is_active')
    );
    return $cache;
}

function provider_users_schema_ready($conexion){
    return table_exists($conexion, 'provider_users')
        && table_has_column($conexion, 'provider_users', 'provider_id')
        && table_has_column($conexion, 'provider_users', 'user_id')
        && table_has_column($conexion, 'provider_users', 'role_in_provider');
}

function resolve_provider_owner_role_priority_sql(){
    return "CASE LOWER(COALESCE(NULLIF(TRIM(pu.role_in_provider), ''), 'owner'))
                WHEN 'owner' THEN 0
                WHEN 'primary' THEN 1
                WHEN 'principal' THEN 2
                WHEN 'admin' THEN 3
                WHEN 'administrator' THEN 4
                ELSE 10
            END";
}

function fetch_provider_owner_user_from_mapping($conexion, $provider_id){
    if ($provider_id <= 0 || !provider_users_schema_ready($conexion) || !table_exists($conexion, 'usuarios')) {
        return null;
    }

    $select = array(
        'u.id',
        table_has_column($conexion, 'usuarios', 'usuario') ? 'u.usuario' : "'' AS usuario",
        table_has_column($conexion, 'usuarios', 'nombre') ? 'u.nombre' : "'' AS nombre",
        table_has_column($conexion, 'usuarios', 'email') ? 'u.email' : "'' AS email",
        table_has_column($conexion, 'usuarios', 'provider_id') ? 'u.provider_id' : 'NULL AS provider_id',
        table_has_column($conexion, 'usuarios', 'service_provider_id') ? 'u.service_provider_id' : 'NULL AS service_provider_id',
        table_has_column($conexion, 'usuarios', 'role_id') ? 'u.role_id' : 'NULL AS role_id',
        table_has_column($conexion, 'usuarios', 'rol') ? 'u.rol' : 'NULL AS rol',
        'pu.role_in_provider'
    );

    $sql = "SELECT " . implode(', ', $select) . "
              FROM provider_users pu
              INNER JOIN usuarios u ON u.id = pu.user_id
             WHERE pu.provider_id = ?
               AND u.id <> 1";
    if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $sql .= " AND COALESCE(u.service_provider_id, 0) = 0";
    }
    if (table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= " AND COALESCE(u.is_deleted, 0) = 0";
    }
    $sql .= " ORDER BY " . resolve_provider_owner_role_priority_sql() . ", u.id ASC LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    $row['owner_source'] = 'provider_users';
    return $row;
}

function fetch_provider_owner_user_legacy($conexion, $provider_id){
    if ($provider_id <= 0 || !table_exists($conexion, 'usuarios') || !table_has_column($conexion, 'usuarios', 'provider_id')) {
        return null;
    }

    $select = array(
        'u.id',
        table_has_column($conexion, 'usuarios', 'usuario') ? 'u.usuario' : "'' AS usuario",
        table_has_column($conexion, 'usuarios', 'nombre') ? 'u.nombre' : "'' AS nombre",
        table_has_column($conexion, 'usuarios', 'email') ? 'u.email' : "'' AS email",
        table_has_column($conexion, 'usuarios', 'provider_id') ? 'u.provider_id' : 'NULL AS provider_id',
        table_has_column($conexion, 'usuarios', 'service_provider_id') ? 'u.service_provider_id' : 'NULL AS service_provider_id',
        table_has_column($conexion, 'usuarios', 'role_id') ? 'u.role_id' : 'NULL AS role_id',
        table_has_column($conexion, 'usuarios', 'rol') ? 'u.rol' : 'NULL AS rol',
        table_has_column($conexion, 'usuarios', 'ppal') ? 'u.ppal' : '0 AS ppal'
    );

    $sql = "SELECT " . implode(', ', $select) . "
              FROM usuarios u
             WHERE u.provider_id = ?
               AND u.id <> 1";
    if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $sql .= " AND COALESCE(u.service_provider_id, 0) = 0";
    }
    if (table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= " AND COALESCE(u.is_deleted, 0) = 0";
    }

    $rolePriority = '5';
    if (table_has_column($conexion, 'usuarios', 'role_id')) {
        $rolePriority = "CASE
            WHEN u.role_id = " . (int)ROLE_PROVIDER_ADMIN . " THEN 0
            WHEN u.role_id = " . (int)ROLE_PROVIDER . " THEN 1
            ELSE 5
        END";
    } elseif (table_has_column($conexion, 'usuarios', 'rol')) {
        $rolePriority = "CASE LOWER(TRIM(COALESCE(u.rol, '')))
            WHEN '" . mysqli_real_escape_string($conexion, (string)ROLE_PROVIDER_ADMIN) . "' THEN 0
            WHEN 'provider_admin' THEN 0
            WHEN 'prestador_admin' THEN 0
            WHEN 'admin prestador' THEN 0
            WHEN '" . mysqli_real_escape_string($conexion, (string)ROLE_PROVIDER) . "' THEN 1
            WHEN 'provider' THEN 1
            WHEN 'prestador' THEN 1
            WHEN 'proveedor' THEN 1
            ELSE 5
        END";
    }

    $ppalPriority = table_has_column($conexion, 'usuarios', 'ppal')
        ? 'CASE WHEN COALESCE(u.ppal, 0) = 1 THEN 0 ELSE 1 END'
        : '1';

    $sql .= " ORDER BY {$ppalPriority}, {$rolePriority}, u.id ASC LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    $row['owner_source'] = 'legacy_fallback';
    return $row;
}

function fetch_provider_owner_user($conexion, $provider_id, $allowLegacyFallback = true){
    $owner = fetch_provider_owner_user_from_mapping($conexion, $provider_id);
    if ($owner) {
        return $owner;
    }
    if (!$allowLegacyFallback) {
        return null;
    }
    return fetch_provider_owner_user_legacy($conexion, $provider_id);
}

function build_provider_owner_ux($user_data){
    $owner_source = ($user_data && isset($user_data['owner_source'])) ? (string)$user_data['owner_source'] : '';

    if (!$user_data) {
        return [
            'owner_state' => 'missing',
            'requires_owner_password' => true,
            'owner_source' => '',
            'owner_notice' => 'No existe una cuenta owner/admin inicial visible para este prestador medico.'
        ];
    }

    if ($owner_source === 'legacy_fallback') {
        return [
            'owner_state' => 'legacy_fallback',
            'requires_owner_password' => false,
            'owner_source' => $owner_source,
            'owner_notice' => 'Se detecto una cuenta owner/admin por compatibilidad legacy. Al guardar se formalizara el ownership explicito.'
        ];
    }

    return [
        'owner_state' => 'explicit',
        'requires_owner_password' => false,
        'owner_source' => $owner_source ?: 'provider_users',
        'owner_notice' => 'La cuenta owner/admin inicial ya esta vinculada de forma explicita al prestador medico.'
    ];
}

function normalize_owner_admin_email($value){
    $email = trim((string)$value);
    if ($email === '') {
        return '';
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

function provider_owner_admin_login_from_email($email){
    return strtolower(trim((string)$email));
}

function generate_provider_owner_temp_password(){
    if (function_exists('random_bytes')) {
        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            error_log('providers owner temp password random_bytes failed: ' . $e->getMessage());
        }
    }
    return hash('sha256', uniqid((string)mt_rand(), true) . microtime(true));
}

function owner_admin_login_exists($conexion, $login, $exclude_user_id = 0){
    $login = trim((string)$login);
    if ($login === '' || !table_exists($conexion, 'usuarios') || !table_has_column($conexion, 'usuarios', 'usuario')) {
        return false;
    }

    $sql = 'SELECT id FROM usuarios WHERE usuario = ?';
    if ($exclude_user_id > 0) {
        $sql .= ' AND id <> ?';
    }
    if (table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= ' AND COALESCE(is_deleted, 0) = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    if ($exclude_user_id > 0) {
        mysqli_stmt_bind_param($stmt, 'si', $login, $exclude_user_id);
    } else {
        mysqli_stmt_bind_param($stmt, 's', $login);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $exists = ($res && mysqli_num_rows($res) > 0);
    mysqli_stmt_close($stmt);
    return $exists;
}

function owner_admin_email_exists($conexion, $email, $exclude_user_id = 0){
    if ($email === '' || !table_exists($conexion, 'usuarios') || !table_has_column($conexion, 'usuarios', 'email')) {
        return false;
    }

    $sql = 'SELECT id FROM usuarios WHERE email = ?';
    if ($exclude_user_id > 0) {
        $sql .= ' AND id <> ?';
    }
    if (table_has_column($conexion, 'usuarios', 'is_deleted')) {
        $sql .= ' AND COALESCE(is_deleted, 0) = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    if ($exclude_user_id > 0) {
        mysqli_stmt_bind_param($stmt, 'si', $email, $exclude_user_id);
    } else {
        mysqli_stmt_bind_param($stmt, 's', $email);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $exists = ($res && mysqli_num_rows($res) > 0);
    mysqli_stmt_close($stmt);
    return $exists;
}

function generate_provider_owner_reset_token(){
    if (function_exists('random_bytes')) {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            error_log('providers owner reset token random_bytes failed: ' . $e->getMessage());
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $raw = openssl_random_pseudo_bytes(32);
        if ($raw !== false) {
            return bin2hex($raw);
        }
    }
    return hash('sha256', uniqid((string)mt_rand(), true) . microtime(true));
}

function issue_provider_owner_access_token($conexion, $user_id){
    $user_id = (int)$user_id;
    if ($user_id <= 0 || !table_exists($conexion, 'usuarios')) {
        return ['error' => 'invalid_user'];
    }

    $hasResetToken = table_has_column($conexion, 'usuarios', 'password_reset_token');
    $hasResetExpires = table_has_column($conexion, 'usuarios', 'password_reset_expires_at');
    $hasResetSentAt = table_has_column($conexion, 'usuarios', 'password_reset_sent_at');
    $hasLegacyToken = table_has_column($conexion, 'usuarios', 'token');

    if (!$hasResetToken && !$hasLegacyToken) {
        return ['error' => 'password_reset_columns_missing'];
    }

    $token = generate_provider_owner_reset_token();
    $expires_at = date('Y-m-d H:i:s', time() + 86400);

    if ($hasResetToken && $hasResetExpires && $hasResetSentAt) {
        $sql = 'UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ?, password_reset_sent_at = NOW() WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['error' => 'db_prepare_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expires_at, $user_id);
    } elseif ($hasResetToken && $hasResetExpires) {
        $sql = 'UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['error' => 'db_prepare_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expires_at, $user_id);
    } elseif ($hasLegacyToken && $hasResetSentAt) {
        $sql = 'UPDATE usuarios SET token = ?, password_reset_sent_at = NOW() WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['error' => 'db_prepare_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'si', $token, $user_id);
    } else {
        $sql = 'UPDATE usuarios SET token = ? WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['error' => 'db_prepare_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'si', $token, $user_id);
    }

    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        return ['error' => 'db_error', 'detail' => $err];
    }

    return [
        'ok' => true,
        'token' => $token,
        'expires_at' => $expires_at,
        'set_password_url' => 'https://medtravel.com.co/set_password.php?token=' . urlencode($token),
        'login_url' => 'https://medtravel.com.co/login.php',
    ];
}

function build_provider_owner_welcome_email_payload($owner_name, $provider_name, $login_email, $set_password_url, $login_url, $expires_at){
    $owner_name = trim((string)$owner_name) !== '' ? trim((string)$owner_name) : 'Provider owner';
    $provider_name = trim((string)$provider_name) !== '' ? trim((string)$provider_name) : 'your medical provider';
    $login_email = trim((string)$login_email);
    $expires_label = $expires_at !== '' ? date('M j, Y g:i A', strtotime($expires_at)) . ' UTC' : 'within 24 hours';

    $content_html = ''
        . '<p style="margin:0 0 14px 0;">Hello ' . htmlspecialchars($owner_name, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p style="margin:0 0 14px 0;">Your MedTravel owner/admin access for <strong>' . htmlspecialchars($provider_name, ENT_QUOTES, 'UTF-8') . '</strong> is ready.</p>'
        . '<p style="margin:0 0 14px 0;">Access email: <strong>' . htmlspecialchars($login_email, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<p style="margin:0 0 14px 0;">For security, create your password using the secure button below. This invitation expires on <strong>' . htmlspecialchars($expires_label, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
        . '<p style="margin:0 0 14px 0;">If the button does not work, copy this secure link into your browser:<br><a href="' . htmlspecialchars($set_password_url, ENT_QUOTES, 'UTF-8') . '" style="color:#0b4ea2; text-decoration:none;">' . htmlspecialchars($set_password_url, ENT_QUOTES, 'UTF-8') . '</a></p>'
        . '<p style="margin:0;">After setting your password, you can sign in here: <a href="' . htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') . '" style="color:#0b4ea2; text-decoration:none;">' . htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') . '</a></p>';

    $html = renderMedTravelEmail(
        'Your provider owner access is ready',
        'Create your password and access MedTravel as provider owner/admin.',
        $content_html,
        'This access email was generated automatically by MedTravel.',
        [
            'text' => 'Create your password',
            'url' => $set_password_url,
        ],
        'MedTravel Patient Care'
    );

    $alt = "Hello {$owner_name},\n\n"
        . "Your MedTravel owner/admin access for {$provider_name} is ready.\n"
        . "Access email: {$login_email}\n"
        . "Create your password: {$set_password_url}\n"
        . "Login after activation: {$login_url}\n"
        . "This invitation expires on {$expires_label}.\n";

    return [
        'subject' => 'MedTravel - Your provider owner access is ready',
        'html' => $html,
        'alt' => $alt,
    ];
}

function send_provider_owner_welcome_email($conexion, $to_email, $payload){
    if (!function_exists('sendEmail') || !function_exists('renderMedTravelEmail')) {
        return ['success' => false, 'error' => 'email_functions_unavailable'];
    }
    try {
        $result = sendEmail($to_email, $payload['subject'], $payload['html'], 'patientcare', ['alt_body' => $payload['alt']], $conexion);
        if ($result === true) {
            return ['success' => true];
        }
        if (is_array($result)) {
            return ['success' => false, 'error' => $result['error'] ?? 'send_failed'];
        }
        return ['success' => false, 'error' => 'send_failed'];
    } catch (Throwable $e) {
        error_log('providers owner welcome email exception: ' . $e->getMessage());
        return ['success' => false, 'error' => 'send_exception'];
    }
}

function create_provider_owner_user($conexion, $provider_id, $owner_email, $display_name){
    $login = provider_owner_admin_login_from_email($owner_email);
    $password_hash = password_hash(generate_provider_owner_temp_password(), PASSWORD_DEFAULT);
    $fields = array('usuario', 'password', 'nombre', 'rol', 'provider_id');
    $values = array($login, $password_hash, $display_name, (string)ROLE_PROVIDER_ADMIN, (int)$provider_id);

    if (table_has_column($conexion, 'usuarios', 'email')) {
        $fields[] = 'email';
        $values[] = $owner_email;
    }

    if (table_has_column($conexion, 'usuarios', 'role_id')) {
        $fields[] = 'role_id';
        $values[] = (int)ROLE_PROVIDER_ADMIN;
    }
    if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $fields[] = 'service_provider_id';
        $values[] = null;
    }
    if (table_has_column($conexion, 'usuarios', 'ppal')) {
        $fields[] = 'ppal';
        $values[] = 0;
    }

    $sql = "INSERT INTO usuarios (" . implode(', ', $fields) . ") VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception('Error preparando INSERT usuario owner/admin');
    }
    bind_dynamic_stmt_params($stmt, $values);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Error ejecutando INSERT usuario owner/admin: ' . $err);
    }
    $user_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $user_id;
}

function update_provider_owner_user($conexion, $user_id, $provider_id, $owner_email, $display_name){
    $login = provider_owner_admin_login_from_email($owner_email);
    $fields = array(
        'usuario = ?',
        'nombre = ?'
    );
    $values = array($login, $display_name);

    if ($owner_email !== null && table_has_column($conexion, 'usuarios', 'email')) {
        $fields[] = 'email = ?';
        $values[] = $owner_email;
    }
    if (table_has_column($conexion, 'usuarios', 'rol')) {
        $fields[] = 'rol = ?';
        $values[] = (string)ROLE_PROVIDER_ADMIN;
    }
    if (table_has_column($conexion, 'usuarios', 'role_id')) {
        $fields[] = 'role_id = ?';
        $values[] = (int)ROLE_PROVIDER_ADMIN;
    }
    if (table_has_column($conexion, 'usuarios', 'provider_id')) {
        $fields[] = 'provider_id = ?';
        $values[] = (int)$provider_id;
    }
    if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
        $fields[] = 'service_provider_id = ?';
        $values[] = null;
    }
    if (table_has_column($conexion, 'usuarios', 'ppal')) {
        $fields[] = 'ppal = ?';
        $values[] = 0;
    }

    $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ? LIMIT 1";
    $values[] = (int)$user_id;
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception('Error preparando UPDATE usuario owner/admin');
    }
    bind_dynamic_stmt_params($stmt, $values);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Error ejecutando UPDATE usuario owner/admin: ' . $err);
    }
    mysqli_stmt_close($stmt);
}

function ensure_provider_owner_mapping($conexion, $provider_id, $user_id){
    if ($provider_id <= 0 || $user_id <= 0) {
        throw new Exception('Provider owner mapping inválido');
    }
    if ((int)$user_id === 1) {
        throw new Exception('El superusuario global no puede mapearse como owner de provider');
    }
    if (!provider_users_schema_ready($conexion)) {
        throw new Exception('provider_users no está listo para ownership explícito');
    }

    $sql = "INSERT INTO provider_users (provider_id, user_id, role_in_provider)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE role_in_provider = VALUES(role_in_provider)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception('Error preparando INSERT provider_users');
    }
    $role = 'owner';
    mysqli_stmt_bind_param($stmt, 'iis', $provider_id, $user_id, $role);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Error ejecutando INSERT provider_users: ' . $err);
    }
    mysqli_stmt_close($stmt);
}

function provider_owner_staff_mirror_schema($conexion){
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = array(
        'table' => table_exists($conexion, 'provider_medical_staff'),
        'provider_id' => table_has_column($conexion, 'provider_medical_staff', 'provider_id'),
        'full_name' => table_has_column($conexion, 'provider_medical_staff', 'full_name'),
        'linked_user_id' => table_has_column($conexion, 'provider_medical_staff', 'linked_user_id'),
        'role_title' => table_has_column($conexion, 'provider_medical_staff', 'role_title'),
        'email' => table_has_column($conexion, 'provider_medical_staff', 'email'),
        'is_primary_doctor' => table_has_column($conexion, 'provider_medical_staff', 'is_primary_doctor'),
        'is_active' => table_has_column($conexion, 'provider_medical_staff', 'is_active'),
        'active' => table_has_column($conexion, 'provider_medical_staff', 'active'),
        'sort_order' => table_has_column($conexion, 'provider_medical_staff', 'sort_order'),
        'can_access_admin' => table_has_column($conexion, 'provider_medical_staff', 'can_access_admin')
    );

    return $cache;
}

function provider_owner_staff_mirror_full_name($display_name, $owner_email, $owner_user_id){
    $full_name = trim((string)$display_name);
    if ($full_name !== '') {
        return $full_name;
    }

    $owner_email = trim((string)$owner_email);
    if ($owner_email !== '') {
        return $owner_email;
    }

    return 'Owner #' . (int)$owner_user_id;
}

function find_provider_owner_staff_mirror($conexion, $provider_id, $owner_user_id){
    $schema = provider_owner_staff_mirror_schema($conexion);
    if (!$schema['table'] || !$schema['linked_user_id'] || $provider_id <= 0 || $owner_user_id <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($conexion, 'SELECT id FROM provider_medical_staff WHERE provider_id = ? AND linked_user_id = ? ORDER BY id ASC LIMIT 1');
    if (!$stmt) {
        throw new Exception('Error preparando búsqueda de espejo owner/staff');
    }
    mysqli_stmt_bind_param($stmt, 'ii', $provider_id, $owner_user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    return $row ? (int)$row['id'] : 0;
}

function next_provider_owner_staff_sort_order($conexion, $provider_id){
    $stmt = mysqli_prepare($conexion, 'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort_order FROM provider_medical_staff WHERE provider_id = ?');
    if (!$stmt) {
        throw new Exception('Error preparando cálculo de sort_order para espejo owner/staff');
    }
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    return $row ? (int)$row['next_sort_order'] : 1;
}

function ensure_provider_owner_staff_mirror($conexion, $provider_id, $owner_user_id, $provider_type, $display_name, $owner_email){
    $provider_type = strtolower(trim((string)$provider_type));
    if ($provider_type !== 'medico' || $provider_id <= 0 || $owner_user_id <= 0) {
        return 0;
    }

    $schema = provider_owner_staff_mirror_schema($conexion);
    if (!$schema['table']) {
        throw new Exception('provider_medical_staff no existe para materializar el espejo owner/staff');
    }
    if (!$schema['provider_id'] || !$schema['full_name'] || !$schema['linked_user_id']) {
        throw new Exception('provider_medical_staff no tiene las columnas mínimas para materializar el espejo owner/staff');
    }

    $full_name = provider_owner_staff_mirror_full_name($display_name, $owner_email, $owner_user_id);
    $owner_email = trim((string)$owner_email);
    $existing_id = find_provider_owner_staff_mirror($conexion, $provider_id, $owner_user_id);

    if ($existing_id > 0) {
        $fields = array('full_name = ?');
        $values = array($full_name);

        if ($schema['role_title']) {
            $fields[] = 'role_title = ?';
            $values[] = 'Owner / admin inicial';
        }
        if ($schema['email']) {
            $fields[] = 'email = ?';
            $values[] = ($owner_email !== '' ? $owner_email : null);
        }
        if ($schema['is_active']) {
            $fields[] = 'is_active = 1';
        }
        if ($schema['active']) {
            $fields[] = 'active = 1';
        }
        if ($schema['is_primary_doctor']) {
            $fields[] = 'is_primary_doctor = 1';
        }
        if ($schema['can_access_admin']) {
            $fields[] = 'can_access_admin = 1';
        }

        $sql = 'UPDATE provider_medical_staff SET ' . implode(', ', $fields) . ' WHERE id = ? AND provider_id = ? LIMIT 1';
        $values[] = $existing_id;
        $values[] = $provider_id;
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando UPDATE del espejo owner/staff');
        }
        bind_dynamic_stmt_params($stmt, $values);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('Error actualizando espejo owner/staff: ' . $err);
        }
        mysqli_stmt_close($stmt);
        return $existing_id;
    }

    $columns = array('provider_id', 'full_name', 'linked_user_id');
    $values = array($provider_id, $full_name, $owner_user_id);

    if ($schema['role_title']) {
        $columns[] = 'role_title';
        $values[] = 'Owner / admin inicial';
    }
    if ($schema['email']) {
        $columns[] = 'email';
        $values[] = ($owner_email !== '' ? $owner_email : null);
    }
    if ($schema['is_primary_doctor']) {
        $columns[] = 'is_primary_doctor';
        $values[] = 1;
    }
    if ($schema['is_active']) {
        $columns[] = 'is_active';
        $values[] = 1;
    }
    if ($schema['active']) {
        $columns[] = 'active';
        $values[] = 1;
    }
    if ($schema['sort_order']) {
        $columns[] = 'sort_order';
        $values[] = next_provider_owner_staff_sort_order($conexion, $provider_id);
    }
    if ($schema['can_access_admin']) {
        $columns[] = 'can_access_admin';
        $values[] = 1;
    }

    $sql = 'INSERT INTO provider_medical_staff (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception('Error preparando INSERT del espejo owner/staff');
    }
    bind_dynamic_stmt_params($stmt, $values);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Error creando espejo owner/staff: ' . $err);
    }
    $mirror_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $mirror_id;
}

try{
    if($tipo == 'list'){
        $kind_filter = isset($_REQUEST['kind']) ? $_REQUEST['kind'] : '';
        $kinds = array('medical','partner');
        if($kind_filter && !in_array($kind_filter, $kinds)) $kind_filter = '';
        if($kind_filter === ''){
            $kind_filter = 'medical';
        }
        // permiso: vista general si no hay filtro, o específica por tipo
        $can_view_any = user_can('providers.view');
        $can_view_med = user_can('providers.medical.view');
        $can_view_partner = user_can('providers.partner.view');
        if(!$can_view_any && !$can_view_med && !$can_view_partner){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        $rows = [];
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $sql = "SELECT 
                    p.id, p.type, p.kind, p.name, p.slug, p.city, p.is_verified, p.is_active, p.created_at,
                    COALESCE(pv.status,'pending') AS verification_status,
                    COALESCE(pv.verification_level,'basic') AS verification_level,
                    COALESCE(pv.trust_score,0) AS trust_score,
                    COALESCE(items.total_items,0) AS total_items,
                    COALESCE(items.checked_items,0) AS checked_items,
                    CASE WHEN COALESCE(items.total_items,0) > 0 
                        THEN ROUND((items.checked_items / items.total_items) * 100, 0)
                        ELSE 0 END AS completion_percent
                FROM providers p
                LEFT JOIN provider_verification pv ON pv.provider_id = p.id
                LEFT JOIN (
                    SELECT provider_id, COUNT(*) AS total_items,
                           SUM(CASE WHEN is_checked = 1 THEN 1 ELSE 0 END) AS checked_items
                    FROM provider_verification_items
                    GROUP BY provider_id
                ) items ON items.provider_id = p.id
                WHERE 1=1";
        if($hasSoftDelete){ $sql .= " AND p.is_deleted = 0"; }
        if($kind_filter){ $sql .= " AND p.kind = '".mysqli_real_escape_string($conexion,$kind_filter)."'"; }
        $sql .= " ORDER BY p.created_at DESC";
        $res = mysqli_query($conexion, $sql);
        if(mysqli_errno($conexion)){ error_log('providers list error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db']); exit; }
        while($r = mysqli_fetch_assoc($res)) {
            $owner = fetch_provider_owner_user($conexion, (int)$r['id'], true);
            $ownerUx = build_provider_owner_ux($owner);
            $r['owner_admin_username'] = $owner && isset($owner['usuario']) ? (string)$owner['usuario'] : '';
            $r['owner_admin_name'] = $owner && isset($owner['nombre']) ? (string)$owner['nombre'] : '';
            $r['owner_source'] = $ownerUx['owner_source'];
            $r['owner_state'] = $ownerUx['owner_state'];
            $rows[] = $r;
        }
        // filtrar según permisos específicos si no tiene permiso general
        if(!$can_view_any){
            $rows = array_filter($rows, function($r) use ($can_view_med, $can_view_partner){
                if($r['kind']==='partner') return $can_view_partner;
                return $can_view_med; // default medical
            });
            $rows = array_values($rows);
        }
        echo json_encode(['ok'=>true,'data'=>$rows]); exit;
    }

    if($tipo == 'get'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id <= 0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $sql = "SELECT * FROM providers WHERE id = ?";
        if($hasSoftDelete){ $sql .= " AND is_deleted = 0"; }
        $sql .= " LIMIT 1";
        if($st = mysqli_prepare($conexion, $sql)){
            mysqli_stmt_bind_param($st, 'i', $id);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($st);
            if(!$row){ echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
            if((isset($row['kind']) ? $row['kind'] : 'medical') !== 'medical'){
                echo json_encode([
                    'ok'=>false,
                    'error'=>'wrong_domain',
                    'message'=>'Este registro pertenece al dominio complementario y debe administrarse desde providers_complementary.php.'
                ]); exit;
            }
            // permiso según tipo
            $kind = isset($row['kind']) ? $row['kind'] : 'medical';
            if(!user_can('providers.view') && !user_can('providers.'.$kind.'.view')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
            
            // categories
            $cats = [];
            $s = mysqli_prepare($conexion, "SELECT category_id FROM provider_categories WHERE provider_id = ?");
            mysqli_stmt_bind_param($s, 'i', $id); mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s);
            while($cc = mysqli_fetch_assoc($r)) $cats[] = (int)$cc['category_id']; mysqli_stmt_close($s);
            
            // services
            $sv = [];
            $s2 = mysqli_prepare($conexion, "SELECT service_id FROM provider_catalog_services WHERE provider_id = ?");
            mysqli_stmt_bind_param($s2, 'i', $id); mysqli_stmt_execute($s2); $r2 = mysqli_stmt_get_result($s2);
            while($ss = mysqli_fetch_assoc($r2)) $sv[] = (int)$ss['service_id']; mysqli_stmt_close($s2);
            
            // owner/admin inicial canónico del provider
            $user_data = fetch_provider_owner_user($conexion, $id, true);

            $owner_ux = build_provider_owner_ux($user_data);

            echo json_encode([
                'ok'=>true,
                'data'=>[
                    'provider'=>$row,
                    'category_ids'=>$cats,
                    'service_ids'=>$sv,
                    'user'=>$user_data,
                    'ux'=>$owner_ux
                ]
            ]); exit;
        } else { error_log('providers get prepare error: '.mysqli_error($conexion)); echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
    }

    if($tipo == 'create'){
        $type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        $owner_email = normalize_owner_admin_email($_REQUEST['owner_email'] ?? '');
        $owner_login = provider_owner_admin_login_from_email($owner_email);
        $kind = 'medical';
        if(isset($_REQUEST['kind']) && trim((string)$_REQUEST['kind']) !== '' && trim((string)$_REQUEST['kind']) !== 'medical'){
            http_response_code(422);
            echo json_encode([
                'ok'=>false,
                'error'=>'wrong_domain',
                'message'=>'Los prestadores complementarios deben crearse desde providers_complementary.php.'
            ]);
            exit;
        }

        // permisos por tipo
        if(!user_can('providers.medical.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        
        if($type === '' || ($type != 'medico' && $type != 'clinica') || $name === ''){ 
            echo json_encode(['ok'=>false,'error'=>'invalid_input','message'=>'Datos incompletos']); exit; 
        }
        if($owner_email === '' || $owner_email === false){
            echo json_encode(['ok'=>false,'error'=>'invalid_owner_email','message'=>'El email del owner/admin inicial es requerido']); exit;
        }

        if(owner_admin_login_exists($conexion, $owner_login, 0)){
            echo json_encode(['ok'=>false,'error'=>'owner_login_exists','message'=>'El email del owner/admin inicial ya está en uso como acceso']); exit;
        }

        if(owner_admin_email_exists($conexion, $owner_email, 0)){
            echo json_encode(['ok'=>false,'error'=>'owner_email_exists','message'=>'El email del owner/admin inicial ya existe']); exit;
        }
        
        $legal_name = isset($_REQUEST['legal_name']) ? trim($_REQUEST['legal_name']) : null;
        $description = isset($_REQUEST['description']) ? trim($_REQUEST['description']) : null;
        $city = isset($_REQUEST['city']) ? trim($_REQUEST['city']) : null;
        $address = isset($_REQUEST['address']) ? trim($_REQUEST['address']) : null;
        $phone = isset($_REQUEST['phone']) ? trim($_REQUEST['phone']) : null;
        $email = isset($_REQUEST['email']) ? trim($_REQUEST['email']) : null;
        $website = isset($_REQUEST['website']) ? trim($_REQUEST['website']) : null;
        $is_verified = isset($_REQUEST['is_verified']) ? (int)$_REQUEST['is_verified'] : 0;
        $is_active = isset($_REQUEST['is_active']) ? (int)$_REQUEST['is_active'] : 0;

        $base_slug = slugify($name);
        $slug = $base_slug; $i = 1;
        while(true){ $s = mysqli_prepare($conexion, "SELECT id FROM providers WHERE slug = ? LIMIT 1"); mysqli_stmt_bind_param($s, 's', $slug); mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s); $exists = ($r && mysqli_num_rows($r)>0); mysqli_stmt_close($s); if(!$exists) break; $slug = $base_slug . '-' . $i; $i++; }

        // Iniciar transacción
        mysqli_begin_transaction($conexion);
        
        try {
            // 1. Insertar proveedor
            $providerColumns = provider_table_columns($conexion);
            $insert_fields = array();
            $insert_values = array();

            $provider_insert_map = array(
                'type' => $type,
                'kind' => $kind,
                'name' => $name,
                'legal_name' => $legal_name,
                'slug' => $slug,
                'description' => $description,
                'city' => $city,
                'address' => $address,
                'phone' => $phone,
                'email' => $email,
                'website' => $website,
                'is_verified' => $is_verified,
                'is_active' => $is_active
            );

            foreach($provider_insert_map as $column => $value){
                if(!empty($providerColumns[$column])){
                    $insert_fields[] = $column;
                    $insert_values[] = $value;
                }
            }

            $insert_placeholders = implode(',', array_fill(0, count($insert_fields), '?'));
            $ins = mysqli_prepare($conexion, "INSERT INTO providers (" . implode(',', $insert_fields) . ") VALUES (" . $insert_placeholders . ")");
            if(!$ins){ throw new Exception('Error preparando INSERT provider: ' . mysqli_error($conexion)); }
            bind_dynamic_stmt_params($ins, $insert_values);
            $exec = mysqli_stmt_execute($ins);
            if(!$exec){ throw new Exception('Error ejecutando INSERT provider: '.mysqli_stmt_error($ins)); }
            $provider_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($ins);
            
            // 1b. Crear registro de verificación base si no existe
            $ver_status = $is_verified ? 'verified' : 'pending';
            $vs = mysqli_prepare($conexion, "INSERT INTO provider_verification (provider_id, status, verification_level, trust_score) VALUES (?,?, 'basic', 0)");
            if($vs){ mysqli_stmt_bind_param($vs, 'is', $provider_id, $ver_status); mysqli_stmt_execute($vs); mysqli_stmt_close($vs); }
            
            // 2. Crear usuario owner/admin inicial
            $owner_user_id = create_provider_owner_user($conexion, $provider_id, $owner_email, $name);

            // 3. Persistir ownership explícito del provider
            ensure_provider_owner_mapping($conexion, $provider_id, $owner_user_id);
            ensure_provider_owner_staff_mirror($conexion, $provider_id, $owner_user_id, $type, $name, $owner_email);
            
            // 4. Relaciones con categorías y servicios
            $category_ids = isset($_REQUEST['category_ids']) && is_array($_REQUEST['category_ids']) ? $_REQUEST['category_ids'] : [];
            $service_ids = isset($_REQUEST['service_ids']) && is_array($_REQUEST['service_ids']) ? $_REQUEST['service_ids'] : [];
            
            if(!empty($category_ids)){
                $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_categories (provider_id, category_id) VALUES (?,?)");
                foreach($category_ids as $cid){ $cid = (int)$cid; mysqli_stmt_bind_param($stmt,'ii',$provider_id,$cid); mysqli_stmt_execute($stmt); }
                mysqli_stmt_close($stmt);
            }
            if(!empty($service_ids)){
                $stmt2 = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_catalog_services (provider_id, service_id) VALUES (?,?)");
                foreach($service_ids as $sid){ $sid = (int)$sid; mysqli_stmt_bind_param($stmt2,'ii',$provider_id,$sid); mysqli_stmt_execute($stmt2); }
                mysqli_stmt_close($stmt2);
            }
            
            // Commit
            mysqli_commit($conexion);

            $mail_sent = false;
            $mail_error = '';
            $token_result = issue_provider_owner_access_token($conexion, $owner_user_id);
            if(empty($token_result['ok'])){
                $mail_error = $token_result['error'] ?? 'token_issue_failed';
            } else {
                $welcome_payload = build_provider_owner_welcome_email_payload(
                    $owner_email,
                    $name,
                    $owner_email,
                    $token_result['set_password_url'],
                    $token_result['login_url'],
                    (string)$token_result['expires_at']
                );
                $mail_meta = send_provider_owner_welcome_email($conexion, $owner_email, $welcome_payload);
                $mail_sent = !empty($mail_meta['success']);
                if(!$mail_sent){
                    $mail_error = $mail_meta['error'] ?? 'email_send_failed';
                }
            }

            $message = 'Proveedor y owner/admin inicial creados exitosamente';
            if($mail_sent){
                $message .= '. Correo de acceso enviado al owner/admin inicial.';
            } else {
                $message .= '. El owner/admin fue creado, pero el correo de acceso no pudo enviarse.';
            }

            echo json_encode([
                'ok'=>true,
                'id'=>$provider_id,
                'message'=>$message,
                'mail_sent'=>$mail_sent,
                'mail_error'=>$mail_error
            ]); exit;
            
        } catch(Exception $e) {
            mysqli_rollback($conexion);
            error_log('providers create error: '.$e->getMessage());
            echo json_encode(['ok'=>false,'error'=>'db_transaction','message'=>$e->getMessage()]); exit;
        }
    }

    if($tipo == 'update'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id<=0){ echo json_encode(['ok'=>false,'error'=>'invalid_id','message'=>'ID inválido']); exit; }
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        
        $owner_email = normalize_owner_admin_email($_REQUEST['owner_email'] ?? '');
        // obtener kind actual si no viene en request
        $kind = isset($_REQUEST['kind']) ? trim($_REQUEST['kind']) : '';
        $kind_db = 'medical';
        $type_db = '';
        $providerFound = false;
        $kindSql = "SELECT kind, type FROM providers WHERE id = ?";
        if($hasSoftDelete){ $kindSql .= " AND is_deleted = 0"; }
        $kindSql .= " LIMIT 1";
        $kq = mysqli_prepare($conexion, $kindSql);
        mysqli_stmt_bind_param($kq,'i',$id);
        mysqli_stmt_execute($kq);
        $kr = mysqli_stmt_get_result($kq);
        if($kr && $rowk = mysqli_fetch_assoc($kr)){ $kind_db = $rowk['kind'] ?: 'medical'; $type_db = isset($rowk['type']) ? trim((string)$rowk['type']) : ''; $providerFound = true; }
        mysqli_stmt_close($kq);
        if($hasSoftDelete && !$providerFound){
            echo json_encode(['ok'=>false,'error'=>'record_deleted','message'=>'registro eliminado']); exit;
        }
        if($kind_db !== 'medical'){
            http_response_code(422);
            echo json_encode([
                'ok'=>false,
                'error'=>'wrong_domain',
                'message'=>'Este registro pertenece al dominio complementario y debe administrarse desde providers_complementary.php.'
            ]);
            exit;
        }
        $kind = 'medical';

        // permisos por tipo
        if(!user_can('providers.medical.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        
        if($owner_email === false){
            echo json_encode(['ok'=>false,'error'=>'invalid_owner_email','message'=>'El email del owner/admin inicial no es válido']); exit;
        }
        
        $owner_user = fetch_provider_owner_user($conexion, $id, true);
        $owner_user_id = $owner_user && !empty($owner_user['id']) ? (int)$owner_user['id'] : 0;

        $owner_email_to_persist = $owner_email !== ''
            ? $owner_email
            : (($owner_user && !empty($owner_user['email']))
                ? trim((string)$owner_user['email'])
                : (($owner_user && !empty($owner_user['usuario'])) ? trim((string)$owner_user['usuario']) : ''));

        if($owner_email_to_persist !== '' && owner_admin_login_exists($conexion, provider_owner_admin_login_from_email($owner_email_to_persist), $owner_user_id)){
            echo json_encode(['ok'=>false,'error'=>'owner_login_exists','message'=>'El email del owner/admin inicial ya está en uso como acceso']); exit;
        }

        if($owner_email !== '' && owner_admin_email_exists($conexion, $owner_email, $owner_user_id)){
            echo json_encode(['ok'=>false,'error'=>'owner_email_exists','message'=>'El email del owner/admin inicial ya está en uso']); exit;
        }
        
        $providerColumns = provider_table_columns($conexion);
        $provider_type_effective = isset($_REQUEST['type']) && trim((string)$_REQUEST['type']) !== ''
            ? trim((string)$_REQUEST['type'])
            : $type_db;
        $allowed = ['type','kind','name','legal_name','description','city','address','phone','email','website','is_verified','is_active'];
        $fields=[]; $values=[];
        foreach($allowed as $k){ 
            if(!empty($providerColumns[$k]) && isset($_REQUEST[$k])){ 
                if(in_array($k,['is_verified','is_active'])) $values[] = (int)$_REQUEST[$k]; 
                else $values[] = trim($_REQUEST[$k]); 
                $fields[] = "$k = ?"; 
            } 
        }
        if(empty($fields)){ echo json_encode(['ok'=>false,'error'=>'nothing_to_update','message'=>'No hay datos para actualizar']); exit; }
        
        $regenerate_slug = isset($_REQUEST['name']);
        if($regenerate_slug){ 
            $base_slug = slugify(trim($_REQUEST['name'])); 
            $slug = $base_slug; $i=1; 
            while(true){ 
                $s = mysqli_prepare($conexion, "SELECT id FROM providers WHERE slug = ? AND id != ? LIMIT 1"); 
                mysqli_stmt_bind_param($s,'si',$slug,$id); 
                mysqli_stmt_execute($s); 
                $r = mysqli_stmt_get_result($s); 
                $exists = ($r && mysqli_num_rows($r)>0); 
                mysqli_stmt_close($s); 
                if(!$exists) break; 
                $slug = $base_slug . '-' . $i; 
                $i++; 
            } 
            array_unshift($fields,'slug = ?'); 
            array_unshift($values,$slug); 
        }
        
        // Iniciar transacción
        mysqli_begin_transaction($conexion);
        
        try {
            // 1. Actualizar proveedor
            $sql = 'UPDATE providers SET '.implode(', ', $fields).' WHERE id = ?';
            if($hasSoftDelete){ $sql .= ' AND is_deleted = 0'; }
            $sql .= ' LIMIT 1';
            $values[] = $id; 
            $types=''; 
            foreach($values as $v){ $types .= is_int($v)?'i':'s'; }
            
            if($stmt = mysqli_prepare($conexion, $sql)){
                $bind_names = array(); 
                $bind_names[] = $types; 
                for($i=0;$i<count($values);$i++){ 
                    $bind_name = 'b'.$i; 
                    $$bind_name = $values[$i]; 
                    $bind_names[] = &$$bind_name; 
                }
	                call_user_func_array(array($stmt,'bind_param'), $bind_names);
	                $exec = mysqli_stmt_execute($stmt);
	                if(!$exec){ throw new Exception('Error actualizando provider: '.mysqli_stmt_error($stmt)); }
	                mysqli_stmt_close($stmt);
	            } else { 
	                throw new Exception('Error preparando UPDATE provider: '.mysqli_error($conexion)); 
            }
            
            // 2. Actualizar o crear owner/admin inicial
            $provider_name = isset($_REQUEST['name'])
                ? trim($_REQUEST['name'])
                : (($owner_user && isset($owner_user['nombre'])) ? trim((string)$owner_user['nombre']) : '');
            $owner_email_to_persist = $owner_email !== ''
                ? $owner_email
                : (($owner_user && !empty($owner_user['email']))
                    ? trim((string)$owner_user['email'])
                    : (($owner_user && !empty($owner_user['usuario'])) ? trim((string)$owner_user['usuario']) : null));
            
            if($owner_user_id > 0){
                if($owner_email_to_persist !== null && $owner_email_to_persist !== ''){
                    update_provider_owner_user($conexion, $owner_user_id, $id, $owner_email_to_persist, $provider_name);
                }
            } else {
                if($owner_email_to_persist === null || $owner_email_to_persist === ''){
                    throw new Exception('Se requiere email para crear el owner/admin inicial');
                }
                $owner_user_id = create_provider_owner_user($conexion, $id, $owner_email_to_persist, $provider_name);
            }

            ensure_provider_owner_mapping($conexion, $id, $owner_user_id);
            ensure_provider_owner_staff_mirror($conexion, $id, $owner_user_id, $provider_type_effective, $provider_name, (string)$owner_email_to_persist);
            
            // 3. Actualizar relaciones
            $category_ids = isset($_REQUEST['category_ids']) && is_array($_REQUEST['category_ids']) ? $_REQUEST['category_ids'] : [];
            $service_ids = isset($_REQUEST['service_ids']) && is_array($_REQUEST['service_ids']) ? $_REQUEST['service_ids'] : [];
            
            // Eliminar relaciones existentes
            $d1 = mysqli_prepare($conexion, "DELETE FROM provider_categories WHERE provider_id = ?"); 
            mysqli_stmt_bind_param($d1,'i',$id); 
            mysqli_stmt_execute($d1); 
            mysqli_stmt_close($d1);
            
            $d2 = mysqli_prepare($conexion, "DELETE FROM provider_catalog_services WHERE provider_id = ?"); 
            mysqli_stmt_bind_param($d2,'i',$id); 
            mysqli_stmt_execute($d2); 
            mysqli_stmt_close($d2);
            
            // Reinsertar
            if(!empty($category_ids)){
                $ins = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_categories (provider_id, category_id) VALUES (?,?)");
                foreach($category_ids as $cid){ 
                    $cid = (int)$cid; 
                    mysqli_stmt_bind_param($ins,'ii',$id,$cid); 
                    mysqli_stmt_execute($ins); 
                }
                mysqli_stmt_close($ins);
            }
            if(!empty($service_ids)){
                $ins2 = mysqli_prepare($conexion, "INSERT IGNORE INTO provider_catalog_services (provider_id, service_id) VALUES (?,?)");
                foreach($service_ids as $sid){ 
                    $sid = (int)$sid; 
                    mysqli_stmt_bind_param($ins2,'ii',$id,$sid); 
                    mysqli_stmt_execute($ins2); 
                }
                mysqli_stmt_close($ins2);
            }
            
            // Commit
            mysqli_commit($conexion);
            echo json_encode(['ok'=>true,'message'=>'Proveedor y owner/admin inicial actualizados exitosamente']); exit;
            
        } catch(Exception $e) {
            mysqli_rollback($conexion);
            error_log('providers update error: '.$e->getMessage());
            echo json_encode(['ok'=>false,'error'=>'db_transaction','message'=>$e->getMessage()]); exit;
        }
    }

    if($tipo == 'toggle'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0; $val = isset($_REQUEST['val']) ? (int)$_REQUEST['val'] : 0; if($id<=0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        if(!in_array($val, [0,1], true)){ echo json_encode(['ok'=>false,'error'=>'invalid_val']); exit; }
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $kind = 'medical';
        $kindSql = "SELECT kind FROM providers WHERE id = ?";
        if($hasSoftDelete){ $kindSql .= " AND is_deleted = 0"; }
        $kindSql .= " LIMIT 1";
        $kq = mysqli_prepare($conexion, $kindSql);
        mysqli_stmt_bind_param($kq,'i',$id);
        mysqli_stmt_execute($kq);
        $kr = mysqli_stmt_get_result($kq);
        if($kr && $rowk = mysqli_fetch_assoc($kr)) $kind = $rowk['kind'] ?: 'medical';
        mysqli_stmt_close($kq);
        if($hasSoftDelete && (!$kr || mysqli_num_rows($kr) === 0)){ echo json_encode(['ok'=>false,'error'=>'record_deleted','message'=>'registro eliminado']); exit; }
        if($kind === 'partner'){
            if(!user_can('providers.partner.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        } else {
            if(!user_can('providers.medical.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        }
        $toggleSql = "UPDATE providers SET is_active = ? WHERE id = ?";
        if($hasSoftDelete){ $toggleSql .= " AND is_deleted = 0"; }
        $toggleSql .= " LIMIT 1";
        $st = mysqli_prepare($conexion, $toggleSql); mysqli_stmt_bind_param($st,'ii',$val,$id); $exec = mysqli_stmt_execute($st); if(!$exec){ error_log('providers toggle error: '.mysqli_stmt_error($st)); echo json_encode(['ok'=>false,'error'=>'db_toggle']); mysqli_stmt_close($st); exit; } mysqli_stmt_close($st); echo json_encode(['ok'=>true]); exit;
    }

    if($tipo == 'soft_delete'){
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if($id<=0){ echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
        $hasSoftDelete = table_has_column($conexion, 'providers', 'is_deleted');
        $hasDeletedAt = table_has_column($conexion, 'providers', 'deleted_at');
        $hasDeletedBy = table_has_column($conexion, 'providers', 'deleted_by');
        if(!$hasSoftDelete || !$hasDeletedAt || !$hasDeletedBy){ echo json_encode(['ok'=>false,'error'=>'soft_delete_columns_missing']); exit; }

        $kind = 'medical';
        $kindSql = "SELECT kind FROM providers WHERE id = ? AND is_deleted = 0 LIMIT 1";
        $kq = mysqli_prepare($conexion, $kindSql);
        mysqli_stmt_bind_param($kq,'i',$id);
        mysqli_stmt_execute($kq);
        $kr = mysqli_stmt_get_result($kq);
        if($kr && $rowk = mysqli_fetch_assoc($kr)) $kind = $rowk['kind'] ?: 'medical';
        mysqli_stmt_close($kq);
        if(!$kr || mysqli_num_rows($kr) === 0){ echo json_encode(['ok'=>false,'error'=>'record_deleted','message'=>'registro eliminado']); exit; }

        if($kind === 'partner'){
            if(!user_can('providers.partner.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        } else {
            if(!user_can('providers.medical.edit') && !user_can('providers.edit')){ echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
        }

        $sessionUserId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
        $sql = "UPDATE providers SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, is_active = 0 WHERE id = ? AND is_deleted = 0 LIMIT 1";
        $st = mysqli_prepare($conexion, $sql);
        if(!$st){ echo json_encode(['ok'=>false,'error'=>'db_prepare']); exit; }
        mysqli_stmt_bind_param($st, 'ii', $sessionUserId, $id);
        $exec = mysqli_stmt_execute($st);
        if(!$exec){ error_log('providers soft delete error: '.mysqli_stmt_error($st)); echo json_encode(['ok'=>false,'error'=>'db_soft_delete']); mysqli_stmt_close($st); exit; }
        if(mysqli_stmt_affected_rows($st) < 1){ echo json_encode(['ok'=>false,'error'=>'record_deleted','message'=>'registro eliminado']); mysqli_stmt_close($st); exit; }
        mysqli_stmt_close($st);
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'unknown_tipo']); exit;
} catch(Exception $e){ error_log('providers exception: '.$e->getMessage()); echo json_encode(['ok'=>false,'error'=>'exception']); exit; }
