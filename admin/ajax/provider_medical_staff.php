<?php
/**
 * admin/ajax/provider_medical_staff.php
 * CRUD MVP para staff medico interno por prestador.
 *
 * Acciones:
 *   - list_staff
 *   - get_staff
 *   - save_staff
 *   - toggle_staff
 *   - reorder_staff
 *
 * Nota: expone active_only para futura asignacion provider -> medical staff
 * sin acoplar aun booking_request_items a esta tabla.
 */

require_once '../include/conexion.php';
require_once '../include/roles.php';
require_once '../include/provider_medical_staff_helpers.php';
require_once '../include/password_utils.php';
require_once '../include/email_config.php';
require_once '../../inc/email_template.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (
    !user_can(PERM_PROVIDERS_MEDICAL_MANAGE) &&
    !user_can('providers.medical.edit') &&
    !user_can('providers.edit')
) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'forbidden']);
    exit;
}

function pms_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function pms_err($message, $status = 400, $extra = [])
{
    http_response_code((int)$status);
    echo json_encode(array_merge(['ok' => false, 'message' => $message], $extra));
    exit;
}

function bind_stmt_params($stmt, $types, &$values)
{
    if ($types === '' || empty($values)) {
        return true;
    }
    $bind = [$types];
    foreach ($values as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function pms_table_ready($conexion)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $q = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_medical_staff'");
    $ready = ($q && mysqli_num_rows($q) > 0);
    return $ready;
}

function pms_staff_services_table_ready($conexion)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $q = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_medical_staff_services'");
    $ready = ($q && mysqli_num_rows($q) > 0);
    return $ready;
}

function pms_catalog_table_ready($conexion, $table)
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $escaped = mysqli_real_escape_string($conexion, $table);
    $q = mysqli_query($conexion, "SHOW TABLES LIKE '{$escaped}'");
    $cache[$table] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$table];
}

/**
 * Sube la foto de un miembro del staff.
 * Devuelve la ruta relativa a la raíz del proyecto (p.ej. "uploads/staff_photos/123_abc.jpg")
 * o un array con clave 'error' si falla.
 */
function pms_upload_staff_photo(array $file, int $providerId)
{
    $maxBytes  = 2 * 1024 * 1024; // 2 MB
    $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $extMap    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    if ($file['size'] > $maxBytes) {
        return ['error' => 'La foto no puede superar 2 MB'];
    }

    // Verificar tipo real (no confiar solo en la extensión)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowed, true)) {
        return ['error' => 'Formato de imagen no permitido. Usa JPG, PNG o WebP'];
    }

    $ext     = $extMap[$mimeType];
    $dir     = dirname(__DIR__) . '/uploads/staff_photos/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['error' => 'No se pudo crear el directorio de fotos'];
    }

    // Nombre único: provider_{id}_{timestamp}_{random}.ext
    $filename = 'provider_' . $providerId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['error' => 'No se pudo mover la foto al servidor'];
    }

    return 'uploads/staff_photos/' . $filename;
}

function pms_table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

function pms_generate_secure_reset_token($bytes = 32)
{
    $bytes = max(16, (int)$bytes);
    if (function_exists('random_bytes')) {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Throwable $e) {
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        try {
            $raw = openssl_random_pseudo_bytes($bytes);
            if ($raw !== false) {
                return bin2hex($raw);
            }
        } catch (Throwable $e) {
        }
    }
    return hash('sha256', uniqid((string)mt_rand(), true) . microtime(true));
}

function pms_users_has_column($conexion, $column)
{
    return pms_table_has_column($conexion, 'usuarios', $column);
}

function pms_users_table_ready($conexion)
{
    return pms_catalog_table_ready($conexion, 'usuarios');
}

function pms_build_user_placeholder_password_payload()
{
    $placeholder = bin2hex(random_bytes(16));
    if (function_exists('hash_password_for_storage')) {
        return hash_password_for_storage($placeholder, []);
    }
    $token = function_exists('generate_user_token') ? generate_user_token() : md5(uniqid(rand(), true));
    $password = function_exists('hash_password')
        ? hash_password($placeholder, $token)
        : hash('sha512', $token . $placeholder);
    return [
        'password' => $password,
        'token' => $token,
    ];
}

function pms_find_user_by_identity($conexion, $identity)
{
    $identity = trim((string)$identity);
    if ($identity === '' || !pms_users_table_ready($conexion)) {
        return null;
    }

    $conditions = [];
    $types = '';
    $params = [];
    if (pms_users_has_column($conexion, 'email')) {
        $conditions[] = 'u.email = ?';
        $types .= 's';
        $params[] = $identity;
    }
    if (pms_users_has_column($conexion, 'usuario')) {
        $conditions[] = 'u.usuario = ?';
        $types .= 's';
        $params[] = $identity;
    }
    if (empty($conditions)) {
        return null;
    }

    $select = [
        'u.id',
        pms_users_has_column($conexion, 'provider_id') ? 'u.provider_id' : 'NULL AS provider_id',
        pms_users_has_column($conexion, 'service_provider_id') ? 'u.service_provider_id' : 'NULL AS service_provider_id',
        pms_users_has_column($conexion, 'nombre') ? 'u.nombre' : 'NULL AS nombre',
        pms_users_has_column($conexion, 'usuario') ? 'u.usuario' : 'NULL AS usuario',
        pms_users_has_column($conexion, 'email') ? 'u.email' : 'NULL AS email',
        pms_users_has_column($conexion, 'activo') ? 'u.activo' : '1 AS activo',
        pms_users_has_column($conexion, 'ppal') ? 'u.ppal' : '0 AS ppal',
        pms_users_has_column($conexion, 'role_id') ? 'u.role_id' : 'NULL AS role_id',
        pms_users_has_column($conexion, 'rol') ? 'u.rol' : 'NULL AS rol',
        pms_users_has_column($conexion, 'token') ? 'u.token' : 'NULL AS token',
    ];

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM usuarios u WHERE (' . implode(' OR ', $conditions) . ')';
    if (pms_users_has_column($conexion, 'is_deleted')) {
        $sql .= ' AND u.is_deleted = 0';
    }
    $sql .= ' ORDER BY u.id ASC LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    bind_stmt_params($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function pms_validate_user_not_linked_elsewhere($conexion, $userId, $currentStaffId = 0)
{
    if ($userId <= 0 || !pms_has_access_columns($conexion)) {
        return null;
    }
    $sql = 'SELECT id, full_name FROM provider_medical_staff WHERE linked_user_id = ?';
    if ($currentStaffId > 0) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['error' => 'db_prepare_failed'];
    }
    if ($currentStaffId > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $currentStaffId);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if ($row) {
        return ['error' => 'user_already_linked_to_other_staff', 'linked_staff' => $row];
    }
    return null;
}

function pms_user_is_sensitive_owner_account($row)
{
    return isset($row['ppal']) && (int)$row['ppal'] === 1;
}

function pms_user_reuse_public_message($status)
{
    switch ((string)$status) {
        case 'new_user_allowed':
            return 'Este email puede usarse para crear el acceso del staff.';
        case 'reusable_existing_staff_user':
            return 'Ya existe un usuario con este email dentro del mismo prestador; se reutilizará para este staff.';
        case 'invalid_email':
            return 'Ingresa un email válido para aprovisionar acceso al staff.';
        default:
            return 'Este email ya pertenece a una cuenta existente que no puede vincularse como staff. Usa otro email.';
    }
}

function pms_evaluate_staff_access_user($conexion, $providerId, $userRow, $currentStaffId = 0)
{
    if (empty($userRow) || empty($userRow['id'])) {
        return [
            'ok' => true,
            'reusable' => false,
            'status' => 'new_user_allowed',
            'message' => pms_user_reuse_public_message('new_user_allowed'),
        ];
    }

    $userId = (int)$userRow['id'];
    $userProviderId = isset($userRow['provider_id']) && $userRow['provider_id'] !== null ? (int)$userRow['provider_id'] : 0;
    $serviceProviderId = isset($userRow['service_provider_id']) && $userRow['service_provider_id'] !== null ? (int)$userRow['service_provider_id'] : 0;
    $roleId = pms_resolve_user_role_id($userRow);
    $linkedElsewhere = pms_validate_user_not_linked_elsewhere($conexion, $userId, $currentStaffId);

    if ($userProviderId <= 0 || $userProviderId !== (int)$providerId) {
        return [
            'ok' => false,
            'status' => 'existing_user_belongs_to_other_provider',
            'message' => pms_user_reuse_public_message('existing_user_belongs_to_other_provider'),
        ];
    }

    if ($serviceProviderId > 0) {
        return [
            'ok' => false,
            'status' => 'existing_user_not_staff_eligible',
            'message' => pms_user_reuse_public_message('existing_user_not_staff_eligible'),
        ];
    }

    if (pms_user_is_sensitive_owner_account($userRow)) {
        return [
            'ok' => false,
            'status' => 'existing_user_sensitive_account',
            'message' => pms_user_reuse_public_message('existing_user_sensitive_account'),
        ];
    }

    if ($roleId !== ROLE_PROVIDER) {
        return [
            'ok' => false,
            'status' => 'existing_user_privileged_or_incompatible_role',
            'message' => pms_user_reuse_public_message('existing_user_privileged_or_incompatible_role'),
        ];
    }

    if ($linkedElsewhere && !empty($linkedElsewhere['error'])) {
        if (($linkedElsewhere['error'] ?? '') === 'user_already_linked_to_other_staff') {
            return [
                'ok' => false,
                'status' => 'user_already_linked_to_other_staff',
                'message' => pms_user_reuse_public_message('user_already_linked_to_other_staff'),
                'linked_staff' => $linkedElsewhere['linked_staff'] ?? null,
            ];
        }

        return [
            'ok' => false,
            'status' => (string)($linkedElsewhere['error'] ?? 'staff_access_resolution_failed'),
            'message' => pms_user_reuse_public_message('staff_access_resolution_failed'),
        ];
    }

    return [
        'ok' => true,
        'reusable' => true,
        'status' => 'reusable_existing_staff_user',
        'message' => pms_user_reuse_public_message('reusable_existing_staff_user'),
        'user' => $userRow,
        'role_id' => $roleId,
    ];
}

function pms_validate_staff_access_email($conexion, $providerId, $email, $currentStaffId = 0)
{
    $email = pms_clean_email($email);
    if ($email === false || $email === '') {
        return [
            'ok' => false,
            'valid' => false,
            'status' => 'invalid_email',
            'message' => pms_user_reuse_public_message('invalid_email'),
        ];
    }

    $existingUser = pms_find_user_by_identity($conexion, $email);
    $evaluation = pms_evaluate_staff_access_user($conexion, $providerId, $existingUser, $currentStaffId);

    return [
        'ok' => true,
        'valid' => !empty($evaluation['ok']),
        'status' => (string)($evaluation['status'] ?? 'new_user_allowed'),
        'message' => (string)($evaluation['message'] ?? pms_user_reuse_public_message('existing_user_not_staff_eligible')),
        'mode' => !empty($evaluation['reusable']) ? 'reuse' : (!empty($existingUser) ? 'blocked' : 'create'),
        'exists' => !empty($existingUser),
    ];
}

function pms_create_staff_user($conexion, $providerId, $providerName, $staffName, $staffEmail)
{
    if (!pms_users_table_ready($conexion)) {
        return ['error' => 'usuarios_table_missing'];
    }
    if (!pms_users_has_column($conexion, 'usuario') || !pms_users_has_column($conexion, 'password') || !pms_users_has_column($conexion, 'token')) {
        return ['error' => 'usuarios_required_columns_missing'];
    }
    if (!pms_users_has_column($conexion, 'provider_id')) {
        return ['error' => 'usuarios_provider_scope_missing'];
    }

    $payload = pms_build_user_placeholder_password_payload();
    $columns = ['usuario', 'password', 'token', 'provider_id'];
    $placeholders = ['?', '?', '?', '?'];
    $types = 'sssi';
    $params = [$staffEmail, $payload['password'], $payload['token'], $providerId];

    if (pms_users_has_column($conexion, 'email')) {
        $columns[] = 'email';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $staffEmail;
    }
    if (pms_users_has_column($conexion, 'nombre')) {
        $columns[] = 'nombre';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $staffName;
    }
    if (pms_users_has_column($conexion, 'activo')) {
        $columns[] = 'activo';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = 1;
    }
    if (pms_users_has_column($conexion, 'avatar')) {
        $columns[] = 'avatar';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = 'img/perfil/default.png';
    }
    if (pms_users_has_column($conexion, 'empresa')) {
        $columns[] = 'empresa';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $providerName;
    }
    if (pms_users_has_column($conexion, 'ppal')) {
        $columns[] = 'ppal';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = 0;
    }
    if (pms_users_has_column($conexion, 'usrlogin')) {
        $columns[] = 'usrlogin';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $staffEmail;
    }
    if (pms_users_has_column($conexion, 'rol')) {
        $columns[] = 'rol';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = (string)ROLE_PROVIDER;
    }
    if (pms_users_has_column($conexion, 'role_id')) {
        $columns[] = 'role_id';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = ROLE_PROVIDER;
    }
    if (pms_users_has_column($conexion, 'cargo')) {
        $columns[] = 'cargo';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = 'Medical Staff';
    }
    if (pms_users_has_column($conexion, 'ciudad')) {
        $columns[] = 'ciudad';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = '';
    }
    if (pms_users_has_column($conexion, 'telefono')) {
        $columns[] = 'telefono';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = '';
    }
    if (pms_users_has_column($conexion, 'celular')) {
        $columns[] = 'celular';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = '';
    }
    if (pms_users_has_column($conexion, 'cambio_password')) {
        $columns[] = 'cambio_password';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = 1;
    }

    $sql = 'INSERT INTO usuarios (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['error' => 'db_prepare_failed'];
    }
    bind_stmt_params($stmt, $types, $params);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    $newId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    if (!$ok || $newId <= 0) {
        return ['error' => 'db_error', 'detail' => $err];
    }

    $user = pms_find_user_by_identity($conexion, $staffEmail);
    if ($user) {
        return ['ok' => true, 'user' => $user, 'provision_status' => 'created_user'];
    }

    return ['ok' => true, 'user' => ['id' => $newId, 'provider_id' => $providerId, 'usuario' => $staffEmail, 'email' => $staffEmail, 'nombre' => $staffName], 'provision_status' => 'created_user'];
}

function pms_resolve_staff_access_user($conexion, $providerId, $providerName, $staffName, $staffEmail, $requestedLinkedUserId, $currentStaffId = 0)
{
    $requestedLinkedUserId = (int)$requestedLinkedUserId;
    if ($requestedLinkedUserId > 0) {
        $linkedUser = pms_fetch_linked_user($conexion, $requestedLinkedUserId, $providerId, $currentStaffId);
        if (!$linkedUser) {
            return ['error' => 'linked_user_not_found_for_provider'];
        }
        if (!empty($linkedUser['error'])) {
            if ($linkedUser['error'] === 'linked_user_already_assigned') {
                return ['error' => 'user_already_linked_to_other_staff', 'linked_staff' => $linkedUser['linked_staff'] ?? null];
            }
            return ['error' => $linkedUser['error']];
        }
        return ['ok' => true, 'user' => $linkedUser, 'provision_status' => 'linked_existing_user'];
    }

    if ($staffEmail === '' || !filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'staff_access_requires_valid_email'];
    }

    $existingUser = pms_find_user_by_identity($conexion, $staffEmail);
    if ($existingUser) {
        $evaluation = pms_evaluate_staff_access_user($conexion, $providerId, $existingUser, $currentStaffId);
        if (empty($evaluation['ok'])) {
            $result = ['error' => $evaluation['status'] ?? 'staff_access_resolution_failed'];
            if (!empty($evaluation['linked_staff'])) {
                $result['linked_staff'] = $evaluation['linked_staff'];
            }
            return $result;
        }
        return ['ok' => true, 'user' => $existingUser, 'provision_status' => 'linked_existing_user'];
    }

    return pms_create_staff_user($conexion, $providerId, $providerName, $staffName, $staffEmail);
}

function pms_issue_staff_access_token($conexion, $userId)
{
    $userId = (int)$userId;
    if ($userId <= 0 || !pms_users_table_ready($conexion)) {
        return ['error' => 'invalid_user'];
    }

    $hasResetToken = pms_users_has_column($conexion, 'password_reset_token');
    $hasResetExpires = pms_users_has_column($conexion, 'password_reset_expires_at');
    $hasResetSentAt = pms_users_has_column($conexion, 'password_reset_sent_at');
    $hasLegacyToken = pms_users_has_column($conexion, 'token');

    if (!$hasResetToken && !$hasLegacyToken) {
        return ['error' => 'password_reset_columns_missing'];
    }

    $token = pms_generate_secure_reset_token(32);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);
    if ($hasResetToken && $hasResetExpires && $hasResetSentAt) {
        $sql = 'UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ?, password_reset_sent_at = NOW() WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['error' => 'db_prepare_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiresAt, $userId);
    } elseif ($hasResetToken && $hasResetExpires) {
        $sql = 'UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['error' => 'db_prepare_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiresAt, $userId);
    } elseif ($hasLegacyToken && $hasResetSentAt) {
        $sql = 'UPDATE usuarios SET token = ?, password_reset_sent_at = NOW() WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['error' => 'db_prepare_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'si', $token, $userId);
    } else {
        $sql = 'UPDATE usuarios SET token = ? WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['error' => 'db_prepare_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'si', $token, $userId);
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);
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
        'expires_at' => $expiresAt,
        'set_password_url' => 'https://medtravel.com.co/set_password.php?token=' . urlencode($token),
        'login_url' => 'https://medtravel.com.co/login.php',
    ];
}

function pms_build_staff_welcome_email_payload($staffName, $providerName, $loginEmail, $setPasswordUrl, $loginUrl, $expiresAt)
{
    $staffName = trim((string)$staffName) !== '' ? trim((string)$staffName) : 'Medical staff member';
    $providerName = trim((string)$providerName) !== '' ? trim((string)$providerName) : 'your provider team';
    $loginEmail = trim((string)$loginEmail);
    $expiresLabel = $expiresAt !== '' ? date('M j, Y g:i A', strtotime($expiresAt)) . ' UTC' : 'within 24 hours';

    $contentHtml = ''
        . '<p style="margin:0 0 14px 0;">Hello ' . htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p style="margin:0 0 14px 0;">' . htmlspecialchars($providerName, ENT_QUOTES, 'UTF-8') . ' has enabled your MedTravel staff access.</p>'
        . '<p style="margin:0 0 14px 0;">Use your email/login <strong>' . htmlspecialchars($loginEmail, ENT_QUOTES, 'UTF-8') . '</strong> to access the admin once you create your password.</p>'
        . '<p style="margin:0 0 14px 0;">Create your password using the secure button below. This invitation expires on <strong>' . htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
        . '<p style="margin:0 0 14px 0;">If the button does not work, copy this secure link into your browser:<br><a href="' . htmlspecialchars($setPasswordUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#0b4ea2; text-decoration:none;">' . htmlspecialchars($setPasswordUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
        . '<p style="margin:0;">After setting your password, you can sign in here: <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#0b4ea2; text-decoration:none;">' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';

    $html = renderMedTravelEmail(
        'Your staff access is ready',
        'Create your password and access MedTravel staff tools.',
        $contentHtml,
        'This access email was generated automatically by MedTravel.',
        [
            'text' => 'Create your password',
            'url' => $setPasswordUrl,
        ],
        'MedTravel Patient Care'
    );

    $alt = "Hello {$staffName},\n\n"
        . "{$providerName} has enabled your MedTravel staff access.\n"
        . "Login email: {$loginEmail}\n"
        . "Create your password: {$setPasswordUrl}\n"
        . "Login after activation: {$loginUrl}\n"
        . "This invitation expires on {$expiresLabel}.\n";

    return [
        'subject' => 'MedTravel – Your staff access is ready',
        'html' => $html,
        'alt' => $alt,
    ];
}

function pms_send_staff_welcome_email($conexion, $toEmail, $payload)
{
    if (!function_exists('sendEmail') || !function_exists('renderMedTravelEmail')) {
        return ['success' => false, 'error' => 'email_functions_unavailable'];
    }
    try {
        $result = sendEmail($toEmail, $payload['subject'], $payload['html'], 'patientcare', ['alt_body' => $payload['alt']], $conexion);
        if ($result === true) {
            return ['success' => true];
        }
        if (is_array($result)) {
            return ['success' => false, 'error' => $result['error'] ?? 'send_failed'];
        }
        return ['success' => false, 'error' => 'send_failed'];
    } catch (Throwable $e) {
        error_log('provider_medical_staff welcome email exception: ' . $e->getMessage());
        return ['success' => false, 'error' => 'send_exception'];
    }
}

function pms_provider_exists($conexion, $providerId)
{
    $sql = 'SELECT id, name FROM providers WHERE id = ?';
    if (pms_table_has_column($conexion, 'providers', 'is_deleted')) {
        $sql .= ' AND is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function pms_clean_text($value, $max = 255)
{
    $text = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, (int)$max, 'UTF-8');
    }
    return substr($text, 0, (int)$max);
}

function pms_clean_email($value)
{
    $email = pms_clean_text($value, 120);
    if ($email === '') {
        return '';
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

function pms_clean_long_text($value)
{
    return trim((string)$value);
}

function pms_requested_flag($key)
{
    return isset($_POST[$key]) ? 1 : 0;
}

function pms_status_select_expr($alias = 'pms')
{
    global $conexion;
    return provider_staff_status_select_expr($conexion, $alias);
}

function pms_sort_select_expr($alias = 'pms')
{
    global $conexion;
    return provider_staff_sort_select_expr($conexion, $alias);
}

function pms_sort_order_sql($alias = 'pms')
{
    global $conexion;
    $column = provider_staff_sort_column_name($conexion);
    if ($column === '') {
        return $alias . '.id';
    }
    return $alias . '.`' . $column . '`';
}

function pms_primary_order_sql($alias = 'pms')
{
    global $conexion;
    if (provider_staff_table_has_column($conexion, 'is_primary_doctor')) {
        return $alias . '.is_primary_doctor DESC, ';
    }
    return '';
}

function pms_normalize_staff_row($row)
{
    $row = provider_staff_normalize_row($row);
    $row['bio_short_preview'] = $row['bio_short'] !== ''
        ? $row['bio_short']
        : trim((string)($row['notes'] ?? ''));
    return $row;
}

function pms_next_sort_order($conexion, $providerId)
{
    $sortExpr = pms_sort_select_expr('pms');
    $stmt = mysqli_prepare(
        $conexion,
        'SELECT COALESCE(MAX(' . $sortExpr . '), 0) AS max_sort
         FROM provider_medical_staff pms
         WHERE pms.provider_id = ?'
    );
    if (!$stmt) {
        return 10;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    $maxSort = isset($row['max_sort']) ? (int)$row['max_sort'] : 0;
    return $maxSort > 0 ? ($maxSort + 10) : 10;
}

function pms_service_label($row)
{
    $serviceName = trim((string)($row['service_name'] ?? ''));
    $categoryName = trim((string)($row['category_name'] ?? ''));
    if ($serviceName === '') {
        $serviceId = (int)($row['service_id'] ?? 0);
        return $serviceId > 0 ? ('Servicio #' . $serviceId) : 'Servicio';
    }
    if ($categoryName === '') {
        return $serviceName;
    }
    return $serviceName . ' · ' . $categoryName;
}

function pms_session_provider_id()
{
    return isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
}

function pms_session_user_id()
{
    foreach (['id_usuario', 'id', 'user_id', 'usuario_id'] as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
            $id = (int)$_SESSION[$key];
            if ($id > 0) {
                return $id;
            }
        }
    }
    return 0;
}

function pms_is_medical_user_role($roleId)
{
    return in_array((int)$roleId, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true);
}

function pms_resolve_user_role_id($row)
{
    if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '') {
        return (int)$row['role_id'];
    }
    return (int)normalize_role_value($row['rol'] ?? null);
}

function pms_assert_provider_scope($providerId)
{
    if ($providerId <= 0) {
        pms_err('provider_id required');
    }
    if (is_role_admin_session()) {
        return;
    }
    $sessionProviderId = pms_session_provider_id();
    if ($sessionProviderId <= 0 || $sessionProviderId !== (int)$providerId) {
        pms_err('forbidden', 403);
    }
    if (is_provider_linked_medical_staff_session()) {
        pms_err('forbidden', 403);
    }
}

function pms_has_access_columns($conexion)
{
    return pms_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')
        && pms_table_has_column($conexion, 'provider_medical_staff', 'can_access_admin');
}

function pms_provider_enabled_services($conexion, $providerId, $activeOnly = true)
{
    if (
        !pms_table_has_column($conexion, 'provider_catalog_services', 'provider_id') ||
        !pms_table_has_column($conexion, 'provider_catalog_services', 'service_id') ||
        !pms_table_has_column($conexion, 'service_catalog', 'id')
    ) {
        return [];
    }

    $hasProviderCatalogServiceId = pms_table_has_column($conexion, 'provider_catalog_services', 'id');
    $hasProviderCatalogServiceActive = pms_table_has_column($conexion, 'provider_catalog_services', 'is_active');
    $hasProviderCatalogSortOrder = pms_table_has_column($conexion, 'provider_catalog_services', 'sort_order');
    $hasProviderCatalogCategory = pms_table_has_column($conexion, 'provider_catalog_services', 'category_id');
    $hasServiceActive = pms_table_has_column($conexion, 'service_catalog', 'is_active');
    $hasServiceDeleted = pms_table_has_column($conexion, 'service_catalog', 'is_deleted');
    $hasCategoryTable = pms_table_has_column($conexion, 'service_categories', 'id');
    $hasServiceCategory = pms_table_has_column($conexion, 'service_catalog', 'category_id');
    $categoryOrderExpr = ($hasCategoryTable && ($hasProviderCatalogCategory || $hasServiceCategory)) ? "COALESCE(cat.name, '')" : "''";

    $select = [
        $hasProviderCatalogServiceId ? 'pcs.id AS provider_catalog_service_id' : 'NULL AS provider_catalog_service_id',
        'sc.id AS service_id',
        'sc.name AS service_name',
        ($hasProviderCatalogSortOrder ? 'pcs.sort_order' : 'NULL') . ' AS sort_order',
        ($hasProviderCatalogCategory ? 'pcs.category_id' : ($hasServiceCategory ? 'sc.category_id' : 'NULL')) . ' AS category_id',
        ($hasCategoryTable && ($hasProviderCatalogCategory || $hasServiceCategory)) ? 'cat.name AS category_name' : "'' AS category_name",
    ];

    $sql = 'SELECT ' . implode(', ', $select) . '
            FROM provider_catalog_services pcs
            INNER JOIN service_catalog sc ON sc.id = pcs.service_id';
    if ($hasCategoryTable && ($hasProviderCatalogCategory || $hasServiceCategory)) {
        $sql .= ' LEFT JOIN service_categories cat ON cat.id = ' . ($hasProviderCatalogCategory ? 'pcs.category_id' : 'sc.category_id');
    }
    $sql .= ' WHERE pcs.provider_id = ?';
    if ($activeOnly && $hasProviderCatalogServiceActive) {
        $sql .= ' AND pcs.is_active = 1';
    }
    if ($activeOnly && $hasServiceActive) {
        $sql .= ' AND sc.is_active = 1';
    }
    if ($hasServiceDeleted) {
        $sql .= ' AND sc.is_deleted = 0';
    }
    $sql .= ' ORDER BY '
        . ($hasProviderCatalogSortOrder ? 'COALESCE(pcs.sort_order, 0) ASC, ' : '')
        . $categoryOrderExpr . ' ASC, sc.name ASC, sc.id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row['provider_catalog_service_id'] = isset($row['provider_catalog_service_id']) && $row['provider_catalog_service_id'] !== null
            ? (int)$row['provider_catalog_service_id']
            : null;
        $row['service_id'] = (int)($row['service_id'] ?? 0);
        $row['category_id'] = isset($row['category_id']) && $row['category_id'] !== null ? (int)$row['category_id'] : null;
        $row['sort_order'] = isset($row['sort_order']) && $row['sort_order'] !== null ? (int)$row['sort_order'] : null;
        $row['label'] = pms_service_label($row);
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function pms_requested_int_ids($keys)
{
    $raw = [];
    foreach ((array)$keys as $key) {
        if (isset($_POST[$key])) {
            $raw = $_POST[$key];
            break;
        }
    }
    if (!is_array($raw)) {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $raw = preg_split('/\s*,\s*/', $raw);
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function pms_requested_provider_catalog_service_ids()
{
    return pms_requested_int_ids(['provider_catalog_service_ids', 'provider_catalog_service_ids[]']);
}

function pms_requested_service_ids()
{
    return pms_requested_int_ids(['service_ids', 'service_ids[]']);
}

function pms_validate_provider_catalog_service_ids($conexion, $providerId, $providerCatalogServiceIds)
{
    $providerCatalogServiceIds = array_values(array_unique(array_map('intval', (array)$providerCatalogServiceIds)));
    if (empty($providerCatalogServiceIds)) {
        return [];
    }

    $allowed = pms_provider_enabled_services($conexion, $providerId, true);
    $allowedById = [];
    foreach ($allowed as $row) {
        $providerCatalogServiceId = (int)($row['provider_catalog_service_id'] ?? 0);
        if ($providerCatalogServiceId > 0) {
            $allowedById[$providerCatalogServiceId] = $row;
        }
    }

    $resolved = [];
    foreach ($providerCatalogServiceIds as $providerCatalogServiceId) {
        if ($providerCatalogServiceId <= 0 || !isset($allowedById[$providerCatalogServiceId])) {
            return [
                'error' => 'invalid_provider_catalog_service',
                'provider_catalog_service_id' => $providerCatalogServiceId,
            ];
        }
        $resolved[] = $allowedById[$providerCatalogServiceId];
    }
    return $resolved;
}

function pms_resolve_legacy_provider_service_ids($conexion, $providerId, $serviceIds)
{
    $serviceIds = array_values(array_unique(array_map('intval', (array)$serviceIds)));
    if (empty($serviceIds)) {
        return [];
    }

    $allowed = pms_provider_enabled_services($conexion, $providerId, true);
    $allowedByServiceId = [];
    foreach ($allowed as $row) {
        $serviceId = (int)($row['service_id'] ?? 0);
        if ($serviceId <= 0) {
            continue;
        }
        if (!isset($allowedByServiceId[$serviceId])) {
            $allowedByServiceId[$serviceId] = [];
        }
        $allowedByServiceId[$serviceId][] = $row;
    }

    $resolved = [];
    foreach ($serviceIds as $serviceId) {
        $candidates = isset($allowedByServiceId[$serviceId]) ? $allowedByServiceId[$serviceId] : [];
        if (empty($candidates)) {
            return ['error' => 'invalid_provider_service', 'service_id' => $serviceId];
        }
        if (count($candidates) > 1) {
            return ['error' => 'ambiguous_provider_service_match', 'service_id' => $serviceId];
        }
        $resolved[] = $candidates[0];
    }

    return $resolved;
}

function pms_resolve_requested_provider_services($conexion, $providerId)
{
    $requestedProviderCatalogServiceIds = pms_requested_provider_catalog_service_ids();
    if (!empty($requestedProviderCatalogServiceIds)) {
        return pms_validate_provider_catalog_service_ids($conexion, $providerId, $requestedProviderCatalogServiceIds);
    }

    $requestedServiceIds = pms_requested_service_ids();
    if (!empty($requestedServiceIds)) {
        return pms_resolve_legacy_provider_service_ids($conexion, $providerId, $requestedServiceIds);
    }

    return [];
}

function pms_fetch_staff_services_map($conexion, $providerId, $staffIds = [], $activeOnly = true)
{
    if (!pms_staff_services_table_ready($conexion)) {
        return [];
    }

    $hasRelActive = pms_table_has_column($conexion, 'provider_medical_staff_services', 'active');
    $hasRelProviderCatalogServiceId = pms_table_has_column($conexion, 'provider_medical_staff_services', 'provider_catalog_service_id');
    $hasProviderCatalogServiceId = pms_table_has_column($conexion, 'provider_catalog_services', 'id');
    $hasProviderCatalogServiceActive = pms_table_has_column($conexion, 'provider_catalog_services', 'is_active');
    $hasProviderCatalogCategory = pms_table_has_column($conexion, 'provider_catalog_services', 'category_id');
    $hasProviderCatalogSortOrder = pms_table_has_column($conexion, 'provider_catalog_services', 'sort_order');
    $hasServiceActive = pms_table_has_column($conexion, 'service_catalog', 'is_active');
    $hasServiceDeleted = pms_table_has_column($conexion, 'service_catalog', 'is_deleted');
    $hasCategoryTable = pms_table_has_column($conexion, 'service_categories', 'id');
    $hasServiceCategory = pms_table_has_column($conexion, 'service_catalog', 'category_id');
    $categoryOrderExpr = ($hasCategoryTable && ($hasProviderCatalogCategory || $hasServiceCategory)) ? "COALESCE(cat.name, '')" : "''";

    $select = [
        'rel.provider_medical_staff_id AS staff_id',
        'COALESCE(pcs.id, pcs_legacy.provider_catalog_service_id) AS provider_catalog_service_id',
        'COALESCE(pcs.service_id, pcs_legacy.service_id, rel.service_id) AS service_id',
        'sc.name AS service_name',
        ($hasProviderCatalogSortOrder ? 'COALESCE(pcs.sort_order, pcs_legacy.sort_order)' : 'NULL') . ' AS sort_order',
        (($hasProviderCatalogCategory ? 'COALESCE(pcs.category_id, pcs_legacy.category_id)' : ($hasServiceCategory ? 'sc.category_id' : 'NULL'))) . ' AS category_id',
        ($hasCategoryTable && ($hasProviderCatalogCategory || $hasServiceCategory)) ? 'cat.name AS category_name' : "'' AS category_name",
    ];

    $sql = 'SELECT ' . implode(', ', $select) . '
            FROM provider_medical_staff_services rel
            INNER JOIN provider_medical_staff pms ON pms.id = rel.provider_medical_staff_id';
    if ($hasProviderCatalogServiceId && $hasRelProviderCatalogServiceId) {
        $sql .= ' LEFT JOIN provider_catalog_services pcs
                    ON pcs.id = rel.provider_catalog_service_id
                   AND pcs.provider_id = pms.provider_id';
        $sql .= ' LEFT JOIN (
                    SELECT
                        provider_id,
                        service_id,
                        MIN(id) AS provider_catalog_service_id'
                        . ($hasProviderCatalogSortOrder ? ', MIN(sort_order) AS sort_order' : ', NULL AS sort_order')
                        . ($hasProviderCatalogCategory ? ', MIN(category_id) AS category_id' : ', NULL AS category_id') . '
                    FROM provider_catalog_services
                    ' . (($activeOnly && $hasProviderCatalogServiceActive) ? 'WHERE is_active = 1 ' : '') . '
                    GROUP BY provider_id, service_id
                    HAVING COUNT(*) = 1
                 ) pcs_legacy
                    ON pcs_legacy.provider_id = pms.provider_id
                   AND pcs_legacy.service_id = rel.service_id';
    } else {
        $sql .= ' LEFT JOIN (
                    SELECT
                        provider_id,
                        service_id,
                        NULL AS provider_catalog_service_id,
                        NULL AS sort_order,
                        NULL AS category_id
                    FROM provider_catalog_services
                    GROUP BY provider_id, service_id
                 ) pcs_legacy
                    ON pcs_legacy.provider_id = pms.provider_id
                   AND pcs_legacy.service_id = rel.service_id';
    }
    $sql .= ' INNER JOIN service_catalog sc
                ON sc.id = COALESCE(pcs.service_id, pcs_legacy.service_id, rel.service_id)';
    if ($hasCategoryTable && ($hasProviderCatalogCategory || $hasServiceCategory)) {
        $sql .= ' LEFT JOIN service_categories cat ON cat.id = '
            . ($hasProviderCatalogCategory ? 'COALESCE(pcs.category_id, pcs_legacy.category_id, sc.category_id)' : 'sc.category_id');
    }
    $sql .= ' WHERE pms.provider_id = ?';
    if ($activeOnly && $hasRelActive) {
        $sql .= ' AND rel.active = 1';
    }
    if ($activeOnly && $hasProviderCatalogServiceActive && $hasProviderCatalogServiceId && $hasRelProviderCatalogServiceId) {
        $sql .= ' AND COALESCE(pcs.is_active, 1) = 1';
    }
    if ($hasServiceActive) {
        $sql .= ' AND sc.is_active = 1';
    }
    if ($hasServiceDeleted) {
        $sql .= ' AND sc.is_deleted = 0';
    }

    $types = 'i';
    $params = [$providerId];
    $staffIds = array_values(array_filter(array_map('intval', (array)$staffIds)));
    if (!empty($staffIds)) {
        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
        $sql .= ' AND rel.provider_medical_staff_id IN (' . $placeholders . ')';
        $types .= str_repeat('i', count($staffIds));
        foreach ($staffIds as $staffId) {
            $params[] = $staffId;
        }
    }
    $sql .= ' ORDER BY '
        . ($hasProviderCatalogSortOrder ? 'COALESCE(pcs.sort_order, pcs_legacy.sort_order, 0) ASC, ' : '')
        . $categoryOrderExpr . ' ASC, sc.name ASC, sc.id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    bind_stmt_params($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $map = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $staffId = (int)($row['staff_id'] ?? 0);
        if ($staffId <= 0) {
            continue;
        }
        $row['provider_catalog_service_id'] = isset($row['provider_catalog_service_id']) && $row['provider_catalog_service_id'] !== null
            ? (int)$row['provider_catalog_service_id']
            : null;
        $row['service_id'] = (int)($row['service_id'] ?? 0);
        $row['category_id'] = isset($row['category_id']) && $row['category_id'] !== null ? (int)$row['category_id'] : null;
        $row['sort_order'] = isset($row['sort_order']) && $row['sort_order'] !== null ? (int)$row['sort_order'] : null;
        $row['label'] = pms_service_label($row);
        if (!isset($map[$staffId])) {
            $map[$staffId] = [];
        }
        $map[$staffId][] = $row;
    }
    mysqli_stmt_close($stmt);
    return $map;
}

function pms_attach_service_payload($rows, $serviceMap)
{
    $out = [];
    foreach ((array)$rows as $row) {
        $staffId = (int)($row['id'] ?? 0);
        $serviceItems = isset($serviceMap[$staffId]) ? $serviceMap[$staffId] : [];
        $providerCatalogServiceIds = [];
        $serviceIds = [];
        $serviceLabels = [];
        foreach ($serviceItems as $serviceItem) {
            if (isset($serviceItem['provider_catalog_service_id']) && $serviceItem['provider_catalog_service_id'] !== null) {
                $providerCatalogServiceIds[] = (int)$serviceItem['provider_catalog_service_id'];
            }
            $serviceIds[] = (int)$serviceItem['service_id'];
            $serviceLabels[] = (string)($serviceItem['label'] ?? pms_service_label($serviceItem));
        }
        $summary = 'Sin servicios asignados';
        $count = count($serviceLabels);
        if ($count > 0) {
            $summaryParts = array_slice($serviceLabels, 0, 3);
            $summary = implode(', ', $summaryParts);
            if ($count > 3) {
                $summary .= ' +' . ($count - 3);
            }
        }
        $row['service_items'] = $serviceItems;
        $row['provider_catalog_service_ids'] = array_values(array_unique($providerCatalogServiceIds));
        $row['service_ids'] = $serviceIds;
        $row['service_count'] = $count;
        $row['service_summary'] = $summary;
        $row['primary_service_label'] = $count > 0 ? $serviceLabels[0] : '';
        $out[] = $row;
    }
    return $out;
}

function pms_replace_staff_services($conexion, $providerId, $staffId, $serviceRows)
{
    if (!pms_staff_services_table_ready($conexion)) {
        if (!empty($serviceRows)) {
            return ['error' => 'staff_services_table_missing'];
        }
        return ['ok' => true];
    }

    $deleteSql = 'DELETE rel
                  FROM provider_medical_staff_services rel
                  INNER JOIN provider_medical_staff pms ON pms.id = rel.provider_medical_staff_id
                  WHERE rel.provider_medical_staff_id = ? AND pms.provider_id = ?';
    $deleteStmt = mysqli_prepare($conexion, $deleteSql);
    if (!$deleteStmt) {
        return ['error' => 'db_prepare_failed'];
    }
    mysqli_stmt_bind_param($deleteStmt, 'ii', $staffId, $providerId);
    $deleted = mysqli_stmt_execute($deleteStmt);
    $deleteErr = mysqli_stmt_error($deleteStmt);
    mysqli_stmt_close($deleteStmt);
    if (!$deleted) {
        return ['error' => 'db_error', 'detail' => $deleteErr];
    }

    $serviceRows = array_values((array)$serviceRows);
    if (empty($serviceRows)) {
        return ['ok' => true];
    }

    $hasActive = pms_table_has_column($conexion, 'provider_medical_staff_services', 'active');
    $hasProviderCatalogServiceId = pms_table_has_column($conexion, 'provider_medical_staff_services', 'provider_catalog_service_id');
    if ($hasProviderCatalogServiceId && $hasActive) {
        $insertSql = 'INSERT INTO provider_medical_staff_services
                        (provider_medical_staff_id, service_id, provider_catalog_service_id, active)
                      VALUES (?, ?, ?, 1)';
    } elseif ($hasProviderCatalogServiceId) {
        $insertSql = 'INSERT INTO provider_medical_staff_services
                        (provider_medical_staff_id, service_id, provider_catalog_service_id)
                      VALUES (?, ?, ?)';
    } elseif ($hasActive) {
        $insertSql = 'INSERT INTO provider_medical_staff_services
                        (provider_medical_staff_id, service_id, active)
                      VALUES (?, ?, 1)';
    } else {
        $insertSql = 'INSERT INTO provider_medical_staff_services
                        (provider_medical_staff_id, service_id)
                      VALUES (?, ?)';
    }
    $insertStmt = mysqli_prepare($conexion, $insertSql);
    if (!$insertStmt) {
        return ['error' => 'db_prepare_failed'];
    }
    foreach ($serviceRows as $serviceRow) {
        $serviceId = (int)($serviceRow['service_id'] ?? 0);
        $providerCatalogServiceId = (int)($serviceRow['provider_catalog_service_id'] ?? 0);
        if ($serviceId <= 0) {
            mysqli_stmt_close($insertStmt);
            return ['error' => 'invalid_provider_service'];
        }
        if ($hasProviderCatalogServiceId) {
            mysqli_stmt_bind_param($insertStmt, 'iii', $staffId, $serviceId, $providerCatalogServiceId);
        } else {
            mysqli_stmt_bind_param($insertStmt, 'ii', $staffId, $serviceId);
        }
        $ok = mysqli_stmt_execute($insertStmt);
        if (!$ok) {
            $err = mysqli_stmt_error($insertStmt);
            mysqli_stmt_close($insertStmt);
            return ['error' => 'db_error', 'detail' => $err];
        }
    }
    mysqli_stmt_close($insertStmt);
    return ['ok' => true];
}

function pms_resolve_provider_service_id($conexion, $providerId, $serviceId = 0, $offerId = 0)
{
    $serviceId = (int)$serviceId;
    $offerId = (int)$offerId;
    if ($serviceId > 0) {
        $resolved = pms_resolve_legacy_provider_service_ids($conexion, $providerId, [$serviceId]);
        if (!empty($resolved['error'])) {
            return ['error' => $resolved['error']];
        }
        $item = $resolved[0];
        return [
            'provider_catalog_service_id' => (int)($item['provider_catalog_service_id'] ?? 0),
            'service_id' => (int)($item['service_id'] ?? 0),
        ];
    }

    if ($offerId <= 0 || !pms_table_has_column($conexion, 'provider_service_offers', 'id')) {
        return ['error' => 'service_id_or_offer_id_required'];
    }

    $select = pms_table_has_column($conexion, 'provider_service_offers', 'provider_catalog_service_id')
        ? 'provider_catalog_service_id, service_id'
        : 'NULL AS provider_catalog_service_id, service_id';
    $stmt = mysqli_prepare(
        $conexion,
        'SELECT ' . $select . ' FROM provider_service_offers WHERE id = ? AND provider_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['error' => 'db_prepare_failed'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $offerId, $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return ['error' => 'offer_not_found'];
    }

    $providerCatalogServiceId = (int)($row['provider_catalog_service_id'] ?? 0);
    if ($providerCatalogServiceId > 0) {
        $resolved = pms_validate_provider_catalog_service_ids($conexion, $providerId, [$providerCatalogServiceId]);
        if (!empty($resolved['error'])) {
            return ['error' => $resolved['error']];
        }
        $item = $resolved[0];
        return [
            'provider_catalog_service_id' => (int)($item['provider_catalog_service_id'] ?? 0),
            'service_id' => (int)($item['service_id'] ?? 0),
        ];
    }

    $resolvedServiceId = (int)($row['service_id'] ?? 0);
    if ($resolvedServiceId <= 0) {
        return ['error' => 'offer_not_found'];
    }
    $resolved = pms_resolve_legacy_provider_service_ids($conexion, $providerId, [$resolvedServiceId]);
    if (!empty($resolved['error'])) {
        return ['error' => $resolved['error']];
    }
    $item = $resolved[0];
    return [
        'provider_catalog_service_id' => (int)($item['provider_catalog_service_id'] ?? 0),
        'service_id' => (int)($item['service_id'] ?? 0),
    ];
}

function pms_fetch_linked_user($conexion, $userId, $providerId, $currentStaffId = 0)
{
    if ($userId <= 0 || !pms_table_ready($conexion)) {
        return null;
    }
    if (!pms_table_has_column($conexion, 'usuarios', 'provider_id')) {
        return null;
    }

    $hasActive = pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasDeleted = pms_table_has_column($conexion, 'usuarios', 'is_deleted');
    $hasEmail = pms_table_has_column($conexion, 'usuarios', 'email');
    $hasRoleId = pms_table_has_column($conexion, 'usuarios', 'role_id');
    $hasRol = pms_table_has_column($conexion, 'usuarios', 'rol');

    $select = [
        'u.id',
        "COALESCE(NULLIF(u.nombre, ''), NULLIF(u.usuario, ''), CONCAT('Usuario #', u.id)) AS nombre",
        "COALESCE(NULLIF(u.usuario, ''), CONCAT('usuario_', u.id)) AS usuario",
        $hasEmail ? "COALESCE(NULLIF(u.email, ''), '') AS email" : "'' AS email",
        $hasActive ? 'u.activo' : '1 AS activo',
        pms_table_has_column($conexion, 'usuarios', 'provider_id') ? 'u.provider_id' : 'NULL AS provider_id',
        pms_table_has_column($conexion, 'usuarios', 'service_provider_id') ? 'u.service_provider_id' : 'NULL AS service_provider_id',
        pms_table_has_column($conexion, 'usuarios', 'ppal') ? 'u.ppal' : '0 AS ppal',
        $hasRoleId ? 'u.role_id' : 'NULL AS role_id',
        $hasRol ? 'u.rol' : 'NULL AS rol',
    ];

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM usuarios u WHERE u.id = ? AND u.provider_id = ?';
    if ($hasDeleted) {
        $sql .= ' AND u.is_deleted = 0';
    }
    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }

    $evaluation = pms_evaluate_staff_access_user($conexion, $providerId, $row, $currentStaffId);
    if (empty($evaluation['ok'])) {
        $status = (string)($evaluation['status'] ?? 'linked_user_not_allowed_for_staff');
        if ($status === 'user_already_linked_to_other_staff') {
            return ['error' => 'linked_user_already_assigned', 'linked_staff' => $evaluation['linked_staff'] ?? null];
        }
        return ['error' => $status];
    }

    $row['role_id_resolved'] = (int)($evaluation['role_id'] ?? pms_resolve_user_role_id($row));
    return $row;
}

function pms_access_status_payload($row)
{
    $linkedUserId = (int)($row['linked_user_id'] ?? 0);
    $canAccessAdmin = (int)($row['can_access_admin'] ?? 0) === 1;
    $linkedUserActive = isset($row['linked_user_active']) ? ((int)$row['linked_user_active'] === 1) : false;

    $payload = [
        'linked_user_label' => 'Sin usuario vinculado',
        'access_status' => 'no_user',
        'access_status_label' => 'Sin usuario vinculado',
    ];

    if ($linkedUserId <= 0) {
        return $payload;
    }

    $userName = trim((string)($row['linked_user_name'] ?? ''));
    $username = trim((string)($row['linked_username'] ?? ''));
    $email = trim((string)($row['linked_user_email'] ?? ''));
    $parts = [];
    if ($userName !== '') {
        $parts[] = $userName;
    }
    if ($username !== '') {
        $parts[] = '@' . $username;
    }
    if ($email !== '') {
        $parts[] = $email;
    }
    $payload['linked_user_label'] = !empty($parts) ? implode(' · ', $parts) : ('Usuario #' . $linkedUserId);

    if (!$canAccessAdmin) {
        $payload['access_status'] = 'linked_without_access';
        $payload['access_status_label'] = 'Usuario vinculado sin acceso';
        return $payload;
    }
    if (!$linkedUserActive) {
        $payload['access_status'] = 'linked_user_inactive';
        $payload['access_status_label'] = 'Usuario vinculado inactivo';
        return $payload;
    }

    $payload['access_status'] = 'enabled';
    $payload['access_status_label'] = 'Médico con acceso propio';
    return $payload;
}

function pms_sync_linked_user_access_state($conexion, $linkedUserId, $enabled)
{
    $linkedUserId = (int)$linkedUserId;
    if ($linkedUserId <= 0 || !pms_users_table_ready($conexion)) {
        return ['ok' => true];
    }

    if (!pms_users_has_column($conexion, 'activo')) {
        return ['ok' => true];
    }

    $activeValue = $enabled ? 1 : 0;
    $stmt = mysqli_prepare($conexion, 'UPDATE usuarios SET activo = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return ['error' => 'db_prepare_failed'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $activeValue, $linkedUserId);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        return ['error' => 'db_error', 'detail' => $err];
    }

    return ['ok' => true];
}

function pms_staff_row($conexion, $staffId, $providerId = 0)
{
    $hasAccessColumns = pms_has_access_columns($conexion);
    $hasUsersTable = pms_table_has_column($conexion, 'usuarios', 'id');
    $hasUserActivo = $hasUsersTable && pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasUserEmail = $hasUsersTable && pms_table_has_column($conexion, 'usuarios', 'email');

    $select = provider_staff_select_columns($conexion, 'pms');
    $select[] = ($hasAccessColumns && $hasUsersTable) ? 'u.nombre AS linked_user_name' : 'NULL AS linked_user_name';
    $select[] = ($hasAccessColumns && $hasUsersTable) ? 'u.usuario AS linked_username' : 'NULL AS linked_username';
    $select[] = ($hasAccessColumns && $hasUsersTable && $hasUserEmail) ? 'u.email AS linked_user_email' : 'NULL AS linked_user_email';
    $select[] = ($hasAccessColumns && $hasUsersTable && $hasUserActivo) ? 'u.activo AS linked_user_active' : 'NULL AS linked_user_active';

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM provider_medical_staff pms';
    if ($hasAccessColumns && $hasUsersTable) {
        $sql .= ' LEFT JOIN usuarios u ON u.id = pms.linked_user_id';
    }
    $sql .= ' WHERE pms.id = ?';
    if ($providerId > 0) {
        $sql .= ' AND pms.provider_id = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    if ($providerId > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $staffId, $providerId);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $staffId);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    $row = pms_normalize_staff_row($row);
    $row = array_merge($row, pms_access_status_payload($row));
    $effectiveProviderId = $providerId > 0 ? $providerId : (int)($row['provider_id'] ?? 0);
    $serviceMap = pms_fetch_staff_services_map($conexion, $effectiveProviderId, [$staffId]);
    $items = pms_attach_service_payload([$row], $serviceMap);
    return !empty($items) ? $items[0] : $row;
}

function pms_fetch_linkable_users($conexion, $providerId, $currentStaffId = 0)
{
    if (!pms_table_has_column($conexion, 'usuarios', 'provider_id')) {
        return [];
    }

    $hasDeleted = pms_table_has_column($conexion, 'usuarios', 'is_deleted');
    $hasActive = pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasEmail = pms_table_has_column($conexion, 'usuarios', 'email');
    $hasRoleId = pms_table_has_column($conexion, 'usuarios', 'role_id');
    $hasRol = pms_table_has_column($conexion, 'usuarios', 'rol');
    $hasServiceProviderId = pms_table_has_column($conexion, 'usuarios', 'service_provider_id');
    $hasPpal = pms_table_has_column($conexion, 'usuarios', 'ppal');

    $select = [
        'u.id',
        "COALESCE(NULLIF(u.nombre, ''), NULLIF(u.usuario, ''), CONCAT('Usuario #', u.id)) AS nombre",
        "COALESCE(NULLIF(u.usuario, ''), CONCAT('usuario_', u.id)) AS usuario",
        $hasEmail ? "COALESCE(NULLIF(u.email, ''), '') AS email" : "'' AS email",
        $hasActive ? 'u.activo' : '1 AS activo',
        'u.provider_id',
        $hasServiceProviderId ? 'u.service_provider_id' : 'NULL AS service_provider_id',
        $hasPpal ? 'u.ppal' : '0 AS ppal',
        $hasRoleId ? 'u.role_id' : 'NULL AS role_id',
        $hasRol ? 'u.rol' : 'NULL AS rol',
    ];

    if (pms_has_access_columns($conexion)) {
        $select[] = 'pms2.id AS linked_staff_id';
        $select[] = 'pms2.full_name AS linked_staff_name';
    } else {
        $select[] = 'NULL AS linked_staff_id';
        $select[] = 'NULL AS linked_staff_name';
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM usuarios u';
    if (pms_has_access_columns($conexion)) {
        $sql .= ' LEFT JOIN provider_medical_staff pms2
                  ON pms2.linked_user_id = u.id';
        if ($currentStaffId > 0) {
            $sql .= ' AND pms2.id <> ' . (int)$currentStaffId;
        }
    }
    $sql .= ' WHERE u.provider_id = ?';
    if ($hasDeleted) {
        $sql .= ' AND u.is_deleted = 0';
    }
    $orderActiveExpr = $hasActive ? 'u.activo' : '1';
    $sql .= ' ORDER BY ' . $orderActiveExpr . ' DESC, u.nombre ASC, u.usuario ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $roleId = pms_resolve_user_role_id($row);
        $evaluation = pms_evaluate_staff_access_user($conexion, $providerId, $row, $currentStaffId);
        $available = !empty($evaluation['ok']) && !empty($evaluation['reusable']);
        $labelParts = [];
        if (!empty($row['nombre'])) {
            $labelParts[] = $row['nombre'];
        }
        if (!empty($row['usuario'])) {
            $labelParts[] = '@' . $row['usuario'];
        }
        if (!empty($row['email'])) {
            $labelParts[] = $row['email'];
        }
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'nombre' => (string)($row['nombre'] ?? ''),
            'usuario' => (string)($row['usuario'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'activo' => (int)($row['activo'] ?? 0),
            'role_id' => $roleId,
            'role_label' => isset(get_available_roles()[$roleId]) ? get_available_roles()[$roleId] : (string)$roleId,
            'linked_staff_id' => isset($row['linked_staff_id']) && $row['linked_staff_id'] !== null ? (int)$row['linked_staff_id'] : null,
            'linked_staff_name' => (string)($row['linked_staff_name'] ?? ''),
            'available' => $available,
            'validation_status' => (string)($evaluation['status'] ?? ''),
            'label' => implode(' · ', array_filter($labelParts)),
        ];
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function pms_list_staff_rows($conexion, $providerId, $activeOnly = false)
{
    $hasAccessColumns = pms_has_access_columns($conexion);
    $hasUsersTable = pms_table_has_column($conexion, 'usuarios', 'id');
    $hasUserActivo = $hasUsersTable && pms_table_has_column($conexion, 'usuarios', 'activo');
    $hasUserEmail = $hasUsersTable && pms_table_has_column($conexion, 'usuarios', 'email');

    $select = provider_staff_select_columns($conexion, 'pms');
    $select[] = ($hasAccessColumns && $hasUsersTable) ? 'u.nombre AS linked_user_name' : 'NULL AS linked_user_name';
    $select[] = ($hasAccessColumns && $hasUsersTable) ? 'u.usuario AS linked_username' : 'NULL AS linked_username';
    $select[] = ($hasAccessColumns && $hasUsersTable && $hasUserEmail) ? 'u.email AS linked_user_email' : 'NULL AS linked_user_email';
    $select[] = ($hasAccessColumns && $hasUsersTable && $hasUserActivo) ? 'u.activo AS linked_user_active' : 'NULL AS linked_user_active';

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM provider_medical_staff pms';
    if ($hasAccessColumns && $hasUsersTable) {
        $sql .= ' LEFT JOIN usuarios u ON u.id = pms.linked_user_id';
    }
    $sql .= ' WHERE pms.provider_id = ?';
    if ($activeOnly) {
        $sql .= ' AND ' . pms_status_select_expr('pms') . ' = 1';
    }
    $sql .= ' ORDER BY ' . pms_status_select_expr('pms') . ' DESC, ' . pms_sort_order_sql('pms') . ' ASC, ' . pms_primary_order_sql('pms') . 'pms.full_name ASC, pms.id ASC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row = pms_normalize_staff_row($row);
        $rows[] = array_merge($row, pms_access_status_payload($row));
    }
    mysqli_stmt_close($stmt);
    return pms_attach_service_payload($rows, pms_fetch_staff_services_map($conexion, $providerId));
}

function pms_fetch_sorted_staff_ids($conexion, $providerId)
{
    $rows = pms_list_staff_rows($conexion, $providerId, false);
    return array_values(array_map('intval', array_column($rows, 'id')));
}

function pms_resequence_staff_sort_order($conexion, $providerId, $orderedIds)
{
    $orderedIds = array_values(array_filter(array_map('intval', (array)$orderedIds)));
    if (empty($orderedIds)) {
        return true;
    }

    $sortColumn = provider_staff_sort_column_name($conexion);
    if ($sortColumn === '') {
        return true;
    }

    $stmt = mysqli_prepare(
        $conexion,
        'UPDATE provider_medical_staff
            SET `' . $sortColumn . '` = ?, updated_at = NOW()
          WHERE id = ? AND provider_id = ?
          LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $position = 10;
    foreach ($orderedIds as $id) {
        mysqli_stmt_bind_param($stmt, 'iii', $position, $id, $providerId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $position += 10;
    }

    mysqli_stmt_close($stmt);
    return true;
}

if (!pms_table_ready($conexion)) {
    pms_err('provider_medical_staff_table_missing — run sql/2026_03_12_provider_medical_staff.sql', 503);
}

$action = isset($_POST['action']) ? trim((string)$_POST['action'])
    : (isset($_GET['action']) ? trim((string)$_GET['action']) : '');

switch ($action) {
    case 'list_staff': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $activeOnly = (int)($_GET['active_only'] ?? $_POST['active_only'] ?? 0) === 1;
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }
        $rows = pms_list_staff_rows($conexion, $providerId, $activeOnly);
        $activeCount = 0;
        foreach ($rows as $row) {
            if ((int)($row['is_active'] ?? $row['active'] ?? 0) === 1) {
                $activeCount++;
            }
        }

        pms_ok([
            'provider' => $provider,
            'items' => $rows,
            'total' => count($rows),
            'active_total' => $activeCount,
        ]);
    }

    case 'list_provider_services': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        pms_ok([
            'provider' => $provider,
            'items' => pms_provider_enabled_services($conexion, $providerId, true),
        ]);
    }

    // ── Catálogos de roles y especialidades para el modal de staff ──────────
    // Sirve entradas del sistema (provider_id IS NULL) + entradas propias del provider.
    // Fallback a arrays hardcoded si las tablas todavía no existen (antes de migrar).
    case 'list_staff_catalogs': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);

        $fallbackRoles = [
            'Lead Doctor', 'Specialist', 'Surgeon', 'Dentist', 'Orthodontist',
            'Oral Surgeon', 'Cosmetic Dentist', 'General Physician', 'Nurse',
            'Patient Coordinator', 'Medical Assistant', 'Anesthesiologist',
            'Therapist', 'Administrative Coordinator',
        ];
        $fallbackSpecialties = [
            'Dentistry', 'Cosmetic Dentistry', 'Orthodontics', 'Oral Surgery',
            'Plastic Surgery', 'Bariatric Surgery', 'Dermatology', 'Ophthalmology',
            'Fertility', 'Orthopedics', 'General Medicine', 'Aesthetic Medicine',
            'Rehabilitation', 'Nutrition',
        ];

        $roles        = [];
        $specialties  = [];

        if (pms_catalog_table_ready($conexion, 'provider_staff_roles')) {
            $stmt = mysqli_prepare(
                $conexion,
                "SELECT name FROM provider_staff_roles
                 WHERE (provider_id IS NULL OR provider_id = ?)
                   AND is_active = 1
                 ORDER BY (provider_id IS NULL) DESC, sort_order ASC, name ASC"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $providerId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($res)) {
                    $roles[] = $row['name'];
                }
                mysqli_stmt_close($stmt);
            }
        }
        if (empty($roles)) {
            $roles = $fallbackRoles;
        }

        if (pms_catalog_table_ready($conexion, 'provider_staff_specialties')) {
            $stmt = mysqli_prepare(
                $conexion,
                "SELECT name FROM provider_staff_specialties
                 WHERE (provider_id IS NULL OR provider_id = ?)
                   AND is_active = 1
                 ORDER BY (provider_id IS NULL) DESC, sort_order ASC, name ASC"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $providerId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($res)) {
                    $specialties[] = $row['name'];
                }
                mysqli_stmt_close($stmt);
            }
        }
        if (empty($specialties)) {
            $specialties = $fallbackSpecialties;
        }

        pms_ok(['roles' => $roles, 'specialties' => $specialties]);
    }

    // ── CRUD: listar items de un catálogo (con id para gestión) ──────────────
    case 'list_staff_catalog_items': {
        $providerId  = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $catalogType = trim($_GET['catalog_type'] ?? $_POST['catalog_type'] ?? '');
        pms_assert_provider_scope($providerId);

        if (!in_array($catalogType, ['roles', 'specialties'], true)) {
            pms_err('invalid_catalog_type');
        }
        $tableName = ($catalogType === 'roles') ? 'provider_staff_roles' : 'provider_staff_specialties';

        if (!pms_catalog_table_ready($conexion, $tableName)) {
            pms_ok(['items' => []]);
        }

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT id, name, is_active, sort_order,
                    (provider_id IS NULL) AS is_system
             FROM `{$tableName}`
             WHERE provider_id IS NULL OR provider_id = ?
             ORDER BY (provider_id IS NULL) DESC, sort_order ASC, name ASC"
        );
        if (!$stmt) {
            pms_err('db_error', 500);
        }
        mysqli_stmt_bind_param($stmt, 'i', $providerId);
        mysqli_stmt_execute($stmt);
        $res  = mysqli_stmt_get_result($stmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $items[] = [
                'id'        => (int)$row['id'],
                'name'      => $row['name'],
                'is_active' => (bool)$row['is_active'],
                'sort_order'=> (int)$row['sort_order'],
                'is_system' => (bool)$row['is_system'],
            ];
        }
        mysqli_stmt_close($stmt);
        pms_ok(['items' => $items]);
    }

    // ── CRUD: crear o actualizar un item del catálogo ─────────────────────────
    case 'save_staff_catalog_item': {
        $providerId  = (int)($_POST['provider_id'] ?? 0);
        $catalogType = trim($_POST['catalog_type'] ?? '');
        $itemId      = (int)($_POST['item_id'] ?? 0);
        $name        = pms_clean_text($_POST['name'] ?? '', 120);
        pms_assert_provider_scope($providerId);

        if (!in_array($catalogType, ['roles', 'specialties'], true)) {
            pms_err('invalid_catalog_type');
        }
        if ($name === '') {
            pms_err('name_required');
        }

        $tableName = ($catalogType === 'roles') ? 'provider_staff_roles' : 'provider_staff_specialties';

        if (!pms_catalog_table_ready($conexion, $tableName)) {
            pms_err('catalog_table_not_ready', 503);
        }

        if ($itemId > 0) {
            // Actualizar: solo permite editar entradas propias del provider (no sistema)
            $stmt = mysqli_prepare(
                $conexion,
                "UPDATE `{$tableName}` SET name = ? WHERE id = ? AND provider_id = ?"
            );
            if (!$stmt) {
                pms_err('db_error', 500);
            }
            mysqli_stmt_bind_param($stmt, 'sii', $name, $itemId, $providerId);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($affected === 0) {
                pms_err('item_not_found_or_not_editable', 404);
            }
            pms_ok(['item_id' => $itemId, 'action' => 'updated']);
        } else {
            // Insertar nueva entrada del provider
            $sortStmt = mysqli_prepare(
                $conexion,
                "SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort
                 FROM `{$tableName}` WHERE provider_id = ?"
            );
            $nextSort = 10;
            if ($sortStmt) {
                mysqli_stmt_bind_param($sortStmt, 'i', $providerId);
                mysqli_stmt_execute($sortStmt);
                $sortRes = mysqli_stmt_get_result($sortStmt);
                $sortRow = mysqli_fetch_assoc($sortRes);
                $nextSort = isset($sortRow['next_sort']) ? (int)$sortRow['next_sort'] : 10;
                mysqli_stmt_close($sortStmt);
            }

            $stmt = mysqli_prepare(
                $conexion,
                "INSERT INTO `{$tableName}` (provider_id, name, sort_order)
                 VALUES (?, ?, ?)"
            );
            if (!$stmt) {
                pms_err('db_error', 500);
            }
            mysqli_stmt_bind_param($stmt, 'isi', $providerId, $name, $nextSort);
            mysqli_stmt_execute($stmt);
            $newId    = (int)mysqli_stmt_insert_id($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($affected === 0) {
                pms_err('insert_failed', 500);
            }
            pms_ok(['item_id' => $newId, 'action' => 'created']);
        }
    }

    // ── CRUD: activar/desactivar un item del catálogo del provider ───────────
    case 'toggle_staff_catalog_item': {
        $providerId  = (int)($_POST['provider_id'] ?? 0);
        $catalogType = trim($_POST['catalog_type'] ?? '');
        $itemId      = (int)($_POST['item_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        if (!in_array($catalogType, ['roles', 'specialties'], true)) {
            pms_err('invalid_catalog_type');
        }
        if ($itemId <= 0) {
            pms_err('item_id_required');
        }

        $tableName = ($catalogType === 'roles') ? 'provider_staff_roles' : 'provider_staff_specialties';

        if (!pms_catalog_table_ready($conexion, $tableName)) {
            pms_err('catalog_table_not_ready', 503);
        }

        // Solo el propio provider puede activar/desactivar sus entradas (no las del sistema)
        $stmt = mysqli_prepare(
            $conexion,
            "UPDATE `{$tableName}` SET is_active = NOT is_active WHERE id = ? AND provider_id = ?"
        );
        if (!$stmt) {
            pms_err('db_error', 500);
        }
        mysqli_stmt_bind_param($stmt, 'ii', $itemId, $providerId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($affected === 0) {
            pms_err('item_not_found_or_not_editable', 404);
        }
        pms_ok(['item_id' => $itemId, 'action' => 'toggled']);
    }

    // ── CRUD: eliminar un item del catálogo del provider ──────────────────────
    case 'delete_staff_catalog_item': {
        $providerId  = (int)($_POST['provider_id'] ?? 0);
        $catalogType = trim($_POST['catalog_type'] ?? '');
        $itemId      = (int)($_POST['item_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        if (!in_array($catalogType, ['roles', 'specialties'], true)) {
            pms_err('invalid_catalog_type');
        }
        if ($itemId <= 0) {
            pms_err('item_id_required');
        }

        $tableName = ($catalogType === 'roles') ? 'provider_staff_roles' : 'provider_staff_specialties';

        if (!pms_catalog_table_ready($conexion, $tableName)) {
            pms_err('catalog_table_not_ready', 503);
        }

        // Solo se pueden eliminar entradas propias del provider (nunca entradas del sistema)
        $stmt = mysqli_prepare(
            $conexion,
            "DELETE FROM `{$tableName}` WHERE id = ? AND provider_id = ?"
        );
        if (!$stmt) {
            pms_err('db_error', 500);
        }
        mysqli_stmt_bind_param($stmt, 'ii', $itemId, $providerId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($affected === 0) {
            pms_err('item_not_found_or_not_deletable', 404);
        }
        pms_ok(['item_id' => $itemId, 'action' => 'deleted']);
    }

    // ── Sedes/clínicas del provider para el modal de staff ───────────────────
    // Fuente controlada: nombre del provider + sedes ya registradas en staff existente.
    // ESTADO: no existe tabla provider_branches todavía.
    // SIGUIENTE PASO: crear tabla provider_branches y reemplazar la query del historial.
    case 'list_provider_clinics': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        // Sede principal: nombre del provider (sede raíz)
        $clinics = [];
        if (!empty($provider['name'])) {
            $clinics[] = [
                'value'  => trim($provider['name']),
                'label'  => trim($provider['name']),
                'source' => 'provider',
            ];
        }

        // Sedes adicionales: valores distintos ya usados por staff del mismo provider
        $additionalQuery = mysqli_prepare(
            $conexion,
            "SELECT DISTINCT TRIM(clinic_name) AS cn
             FROM provider_medical_staff
             WHERE provider_id = ?
               AND TRIM(IFNULL(clinic_name, '')) != ''
             ORDER BY cn ASC
             LIMIT 50"
        );
        if ($additionalQuery) {
            mysqli_stmt_bind_param($additionalQuery, 'i', $providerId);
            mysqli_stmt_execute($additionalQuery);
            $additionalRes = mysqli_stmt_get_result($additionalQuery);
            while ($additionalRow = mysqli_fetch_assoc($additionalRes)) {
                $cn = $additionalRow['cn'];
                // No duplicar la sede principal
                $isDuplicate = false;
                foreach ($clinics as $existing) {
                    if (mb_strtolower($existing['value']) === mb_strtolower($cn)) {
                        $isDuplicate = true;
                        break;
                    }
                }
                if (!$isDuplicate) {
                    $clinics[] = ['value' => $cn, 'label' => $cn, 'source' => 'history'];
                }
            }
            mysqli_stmt_close($additionalQuery);
        }

        pms_ok(['clinics' => $clinics]);
    }

    case 'list_linkable_users': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $currentStaffId = (int)($_GET['staff_id'] ?? $_POST['staff_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        pms_ok([
            'provider' => $provider,
            'items' => pms_fetch_linkable_users($conexion, $providerId, $currentStaffId),
        ]);
    }

    case 'validate_staff_access_email': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $currentStaffId = (int)($_GET['staff_id'] ?? $_POST['staff_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $validation = pms_validate_staff_access_email(
            $conexion,
            $providerId,
            (string)($_GET['email'] ?? $_POST['email'] ?? ''),
            $currentStaffId
        );

        if (empty($validation['ok']) && ($validation['status'] ?? '') === 'invalid_email') {
            pms_err($validation['message'] ?? 'Ingresa un email válido para aprovisionar acceso al staff.', 422, ['status' => 'invalid_email']);
        }

        pms_ok(array_merge(['provider' => $provider], $validation));
    }

    case 'get_staff': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $staffId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        pms_assert_provider_scope($providerId);
        if ($staffId <= 0) {
            pms_err('provider_id and id required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $row = pms_staff_row($conexion, $staffId, $providerId);
        if (!$row) {
            pms_err('staff_not_found', 404);
        }

        pms_ok([
            'item' => $row,
            'provider' => $provider,
            'provider_services' => pms_provider_enabled_services($conexion, $providerId, true),
        ]);
    }

    case 'save_staff': {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $currentRow = $staffId > 0 ? pms_staff_row($conexion, $staffId, $providerId) : null;
        if ($staffId > 0 && !$currentRow) {
            pms_err('staff_not_found', 404);
        }

        $fullName = pms_clean_text($_POST['full_name'] ?? '', 150);
        if ($fullName === '') {
            pms_err('El nombre completo es obligatorio', 422);
        }

        $roleTitle = pms_clean_text($_POST['role_title'] ?? '', 120);
        $specialty = pms_clean_text($_POST['specialty'] ?? '', 120);
        $bioShort = pms_clean_long_text($_POST['bio_short'] ?? '');

        // ── Foto: upload de archivo tiene prioridad sobre URL guardada ────────
        $photo = pms_clean_text($_POST['photo'] ?? '', 255);
        if (!empty($_FILES['photo_file']['tmp_name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = pms_upload_staff_photo($_FILES['photo_file'], $providerId);
            if (is_string($uploadResult)) {
                $photo = $uploadResult; // ruta relativa guardada en BD
            } else {
                pms_err($uploadResult['error'] ?? 'Error al subir la foto', 422);
            }
        }
        $license = pms_clean_text($_POST['professional_license'] ?? '', 120);
        $email = pms_clean_email($_POST['email'] ?? '');
        if ($email === false) {
            pms_err('El correo no tiene un formato válido', 422);
        }
        $phone = pms_clean_text($_POST['phone'] ?? '', 60);
        $clinicName = pms_clean_text($_POST['clinic_name'] ?? '', 180);
        $notes = pms_clean_long_text($_POST['notes'] ?? '');
        $isPrimaryDoctor = pms_requested_flag('is_primary_doctor');
        $isActive = isset($_POST['is_active']) ? pms_requested_flag('is_active') : pms_requested_flag('active');
        $sortOrderRaw = trim((string)($_POST['sort_order'] ?? ''));
        $sortOrder = ($sortOrderRaw === '')
            ? ($currentRow ? (int)($currentRow['sort_order'] ?? 0) : pms_next_sort_order($conexion, $providerId))
            : max(0, (int)$sortOrderRaw);
        $linkedUserId = (int)($_POST['linked_user_id'] ?? 0);
        $canAccessAdmin = isset($_POST['can_access_admin']) ? 1 : 0;
        $currentLinkedUserId = $currentRow ? (int)($currentRow['linked_user_id'] ?? 0) : 0;

        if (($linkedUserId > 0 || $canAccessAdmin === 1) && !pms_has_access_columns($conexion)) {
            pms_err('provider_medical_staff_access_columns_missing — run sql/2026_03_12_provider_staff_access_and_item_assignment.sql', 503);
        }

        $accessProvision = [
            'enabled' => false,
            'linked_user_id' => 0,
            'user' => null,
            'provision_status' => 'access_not_requested',
        ];
        if ($canAccessAdmin === 1) {
            $accessProvision = pms_resolve_staff_access_user(
                $conexion,
                $providerId,
                (string)($provider['name'] ?? ''),
                $fullName,
                $email === false ? '' : (string)$email,
                $linkedUserId,
                $staffId
            );
            if (empty($accessProvision['ok'])) {
                $error = $accessProvision['error'] ?? 'staff_access_resolution_failed';
                if ($error === 'staff_access_requires_valid_email') {
                    pms_err('Debes indicar un email válido para provisionar el acceso o seleccionar un usuario existente del mismo prestador.', 422, ['status' => 'staff_access_requires_valid_email']);
                }
                if ($error === 'existing_user_belongs_to_other_provider') {
                    pms_err('El email indicado ya pertenece a un usuario de otro prestador. Usa otro email o vincula un usuario válido de este prestador.', 422, ['status' => 'existing_user_belongs_to_other_provider']);
                }
                if ($error === 'existing_user_sensitive_account' || $error === 'existing_user_privileged_or_incompatible_role' || $error === 'existing_user_not_staff_eligible') {
                    pms_err('Este email ya pertenece a una cuenta existente que no puede vincularse como staff. Usa otro email.', 422, ['status' => $error]);
                }
                if ($error === 'user_already_linked_to_other_staff') {
                    $linkedStaffName = trim((string)($accessProvision['linked_staff']['full_name'] ?? ''));
                    $message = 'El usuario seleccionado ya está vinculado a otro miembro del staff.';
                    if ($linkedStaffName !== '') {
                        $message .= ' Staff actual: ' . $linkedStaffName . '.';
                    }
                    pms_err($message, 422, ['status' => 'user_already_linked_to_other_staff']);
                }
                if ($error === 'linked_user_must_be_medical_provider_role') {
                    pms_err('El usuario vinculado debe tener rol médico del prestador', 422);
                }
                if ($error === 'linked_user_not_found_for_provider') {
                    pms_err('El usuario vinculado no pertenece a este prestador o no está disponible para este staff.', 422);
                }
                if ($error === 'linked_user_not_allowed_for_staff') {
                    pms_err('El usuario vinculado no está habilitado para usarse como cuenta de staff.', 422, ['status' => 'linked_user_not_allowed_for_staff']);
                }
                pms_err($error, 422);
            }
            $linkedUserId = (int)($accessProvision['user']['id'] ?? 0);
            $accessProvision['enabled'] = true;
            $accessProvision['linked_user_id'] = $linkedUserId;
        } else {
            if ($linkedUserId <= 0 && $currentLinkedUserId > 0) {
                $linkedUserId = $currentLinkedUserId;
            }
        }
        $linkedUserIdSql = $linkedUserId > 0 ? $linkedUserId : 0;

        $validatedServices = pms_resolve_requested_provider_services($conexion, $providerId);
        if (!empty($validatedServices['error'])) {
            if ($validatedServices['error'] === 'ambiguous_provider_service_match') {
                pms_err('Un servicio legado corresponde a múltiples servicios habilitados del prestador. Selecciona el servicio habilitado actualizado.', 422);
            }
            pms_err('Solo puedes asignar al médico servicios activos habilitados para este prestador', 422);
        }

        mysqli_begin_transaction($conexion);

        $savedId = 0;
        $message = '';

        try {
            $statusColumn = provider_staff_status_column_name($conexion);
            $legacyActiveColumn = provider_staff_has_legacy_active_column($conexion);

            if ($staffId > 0) {
                $fields = [];
                $types = '';
                $params = [];

                $fields[] = 'full_name = ?';
                $types .= 's';
                $params[] = $fullName;

                if (provider_staff_table_has_column($conexion, 'role_title')) {
                    $fields[] = 'role_title = ?';
                    $types .= 's';
                    $params[] = $roleTitle;
                }
                if (provider_staff_table_has_column($conexion, 'specialty')) {
                    $fields[] = 'specialty = ?';
                    $types .= 's';
                    $params[] = $specialty;
                }
                if (provider_staff_table_has_column($conexion, 'bio_short')) {
                    $fields[] = 'bio_short = ?';
                    $types .= 's';
                    $params[] = $bioShort;
                }
                if (provider_staff_table_has_column($conexion, 'photo')) {
                    $fields[] = 'photo = ?';
                    $types .= 's';
                    $params[] = $photo;
                }
                if (provider_staff_table_has_column($conexion, 'email')) {
                    $fields[] = 'email = ?';
                    $types .= 's';
                    $params[] = $email;
                }
                if (provider_staff_table_has_column($conexion, 'phone')) {
                    $fields[] = 'phone = ?';
                    $types .= 's';
                    $params[] = $phone;
                }
                if (provider_staff_table_has_column($conexion, 'is_primary_doctor')) {
                    $fields[] = 'is_primary_doctor = ?';
                    $types .= 'i';
                    $params[] = $isPrimaryDoctor;
                }
                if (provider_staff_table_has_column($conexion, 'sort_order')) {
                    $fields[] = 'sort_order = ?';
                    $types .= 'i';
                    $params[] = $sortOrder;
                }
                if (provider_staff_table_has_column($conexion, 'professional_license')) {
                    $fields[] = 'professional_license = ?';
                    $types .= 's';
                    $params[] = $license;
                }
                if (provider_staff_table_has_column($conexion, 'clinic_name')) {
                    $fields[] = 'clinic_name = ?';
                    $types .= 's';
                    $params[] = $clinicName;
                }
                if (provider_staff_table_has_column($conexion, 'notes')) {
                    $fields[] = 'notes = ?';
                    $types .= 's';
                    $params[] = $notes;
                }
                if (pms_has_access_columns($conexion)) {
                    $fields[] = 'linked_user_id = NULLIF(?, 0)';
                    $types .= 'i';
                    $params[] = $linkedUserIdSql;
                    $fields[] = 'can_access_admin = ?';
                    $types .= 'i';
                    $params[] = $canAccessAdmin;
                }
                if ($statusColumn !== '') {
                    $fields[] = '`' . $statusColumn . '` = ?';
                    $types .= 'i';
                    $params[] = $isActive;
                }
                if ($legacyActiveColumn && $statusColumn !== 'active') {
                    $fields[] = 'active = ?';
                    $types .= 'i';
                    $params[] = $isActive;
                }
                if (provider_staff_table_has_column($conexion, 'updated_at')) {
                    $fields[] = 'updated_at = NOW()';
                }

                $sql = 'UPDATE provider_medical_staff SET ' . implode(', ', $fields) . ' WHERE id = ? AND provider_id = ? LIMIT 1';
                $stmt = mysqli_prepare($conexion, $sql);
                if (!$stmt) {
                    throw new Exception('db_prepare_failed');
                }
                $types .= 'ii';
                $params[] = $staffId;
                $params[] = $providerId;
                bind_stmt_params($stmt, $types, $params);
                $ok = mysqli_stmt_execute($stmt);
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                if (!$ok) {
                    throw new Exception('db_error: ' . $err);
                }
                $savedId = $staffId;
                $message = 'Staff médico actualizado correctamente';
            } else {
                $columns = ['provider_id', 'full_name'];
                $placeholders = ['?', '?'];
                $types = 'is';
                $params = [$providerId, $fullName];

                if (provider_staff_table_has_column($conexion, 'role_title')) {
                    $columns[] = 'role_title';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $roleTitle;
                }
                if (provider_staff_table_has_column($conexion, 'specialty')) {
                    $columns[] = 'specialty';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $specialty;
                }
                if (provider_staff_table_has_column($conexion, 'bio_short')) {
                    $columns[] = 'bio_short';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $bioShort;
                }
                if (provider_staff_table_has_column($conexion, 'photo')) {
                    $columns[] = 'photo';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $photo;
                }
                if (provider_staff_table_has_column($conexion, 'email')) {
                    $columns[] = 'email';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $email;
                }
                if (provider_staff_table_has_column($conexion, 'phone')) {
                    $columns[] = 'phone';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $phone;
                }
                if (provider_staff_table_has_column($conexion, 'is_primary_doctor')) {
                    $columns[] = 'is_primary_doctor';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $isPrimaryDoctor;
                }
                if (provider_staff_table_has_column($conexion, 'sort_order')) {
                    $columns[] = 'sort_order';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $sortOrder;
                }
                if (provider_staff_table_has_column($conexion, 'professional_license')) {
                    $columns[] = 'professional_license';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $license;
                }
                if (provider_staff_table_has_column($conexion, 'clinic_name')) {
                    $columns[] = 'clinic_name';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $clinicName;
                }
                if (provider_staff_table_has_column($conexion, 'notes')) {
                    $columns[] = 'notes';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $notes;
                }
                if (pms_has_access_columns($conexion)) {
                    $columns[] = 'linked_user_id';
                    $placeholders[] = 'NULLIF(?, 0)';
                    $types .= 'i';
                    $params[] = $linkedUserIdSql;
                    $columns[] = 'can_access_admin';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $canAccessAdmin;
                }
                if ($statusColumn !== '') {
                    $columns[] = $statusColumn;
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $isActive;
                }
                if ($legacyActiveColumn && $statusColumn !== 'active') {
                    $columns[] = 'active';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $isActive;
                }

                $sql = 'INSERT INTO provider_medical_staff (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = mysqli_prepare($conexion, $sql);
                if (!$stmt) {
                    throw new Exception('db_prepare_failed');
                }
                bind_stmt_params($stmt, $types, $params);
                $ok = mysqli_stmt_execute($stmt);
                $err = mysqli_stmt_error($stmt);
                $savedId = (int)mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt);
                if (!$ok) {
                    throw new Exception('db_error: ' . $err);
                }
                $message = 'Staff médico creado correctamente';
            }

            if ($isPrimaryDoctor === 1 && provider_staff_table_has_column($conexion, 'is_primary_doctor')) {
                $stmtPrimary = mysqli_prepare(
                    $conexion,
                    'UPDATE provider_medical_staff
                        SET is_primary_doctor = CASE WHEN id = ? THEN 1 ELSE 0 END,
                            updated_at = NOW()
                      WHERE provider_id = ?'
                );
                if (!$stmtPrimary) {
                    throw new Exception('db_prepare_failed');
                }
                mysqli_stmt_bind_param($stmtPrimary, 'ii', $savedId, $providerId);
                $okPrimary = mysqli_stmt_execute($stmtPrimary);
                $errPrimary = mysqli_stmt_error($stmtPrimary);
                mysqli_stmt_close($stmtPrimary);
                if (!$okPrimary) {
                    throw new Exception('db_error: ' . $errPrimary);
                }
            }

            $replaceResult = pms_replace_staff_services($conexion, $providerId, $savedId, $validatedServices);
            if (!empty($replaceResult['error'])) {
                if ($replaceResult['error'] === 'staff_services_table_missing') {
                    throw new Exception('provider_medical_staff_services_table_missing — run sql/2026_03_12_provider_medical_staff_services.sql');
                }
                throw new Exception(!empty($replaceResult['detail']) ? $replaceResult['detail'] : $replaceResult['error']);
            }

            if ($linkedUserId > 0) {
                $syncAccess = pms_sync_linked_user_access_state($conexion, $linkedUserId, $canAccessAdmin === 1);
                if (!empty($syncAccess['error'])) {
                    throw new Exception(!empty($syncAccess['detail']) ? $syncAccess['detail'] : $syncAccess['error']);
                }
            }

            mysqli_commit($conexion);
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $status = (
                strpos($e->getMessage(), 'provider_medical_staff_services_table_missing') !== false ||
                strpos($e->getMessage(), 'provider_medical_staff_access_columns_missing') !== false
            ) ? 503 : 500;
            pms_err($e->getMessage(), $status);
        }

        $responseStatus = $staffId > 0 ? 'updated' : 'created';
        $postCommitWarnings = [];
        $mailMeta = null;
        if (!empty($accessProvision['enabled']) && $linkedUserId > 0) {
            $tokenResult = pms_issue_staff_access_token($conexion, $linkedUserId);
            if (empty($tokenResult['ok'])) {
                $responseStatus = ($accessProvision['provision_status'] === 'created_user')
                    ? 'created_user_mail_failed'
                    : 'linked_existing_user_mail_failed';
                $postCommitWarnings[] = 'No fue posible emitir el token seguro de acceso.';
            } else {
                $welcomePayload = pms_build_staff_welcome_email_payload(
                    $fullName,
                    (string)($provider['name'] ?? ''),
                    (string)($accessProvision['user']['email'] ?? $accessProvision['user']['usuario'] ?? $email),
                    $tokenResult['set_password_url'],
                    $tokenResult['login_url'],
                    (string)$tokenResult['expires_at']
                );
                $mailMeta = pms_send_staff_welcome_email(
                    $conexion,
                    (string)($accessProvision['user']['email'] ?? $accessProvision['user']['usuario'] ?? $email),
                    $welcomePayload
                );
                if (!empty($mailMeta['success'])) {
                    $responseStatus = ($accessProvision['provision_status'] === 'created_user')
                        ? 'created_user_and_mail_sent'
                        : 'linked_existing_user_mail_sent';
                } else {
                    $responseStatus = ($accessProvision['provision_status'] === 'created_user')
                        ? 'created_user_mail_failed'
                        : 'linked_existing_user_mail_failed';
                    $postCommitWarnings[] = 'El acceso fue provisionado pero el correo de bienvenida no se pudo enviar.';
                }
            }
        }

        $savedRow = pms_staff_row($conexion, $savedId, $providerId);
        if (!$savedRow) {
            pms_err('staff_saved_but_reload_failed', 500);
        }

        pms_ok([
            'message' => $message,
            'status' => $responseStatus,
            'item' => $savedRow,
            'warnings' => $postCommitWarnings,
            'access_provision' => [
                'enabled' => !empty($accessProvision['enabled']),
                'linked_user_id' => $linkedUserId > 0 ? $linkedUserId : null,
                'provision_status' => $accessProvision['provision_status'] ?? 'access_not_requested',
                'mail_sent' => !empty($mailMeta['success']),
            ],
        ]);
    }

    case 'list_assignable_staff': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $serviceId = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
        $offerId = (int)($_GET['offer_id'] ?? $_POST['offer_id'] ?? 0);
        pms_assert_provider_scope($providerId);

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $resolved = pms_resolve_provider_service_id($conexion, $providerId, $serviceId, $offerId);
        if (!empty($resolved['error'])) {
            pms_err($resolved['error'], 422);
        }

        $rows = pms_list_staff_rows($conexion, $providerId, true);
        $targetProviderCatalogServiceId = (int)($resolved['provider_catalog_service_id'] ?? 0);
        $targetServiceId = (int)($resolved['service_id'] ?? 0);
        $items = [];
        foreach ($rows as $row) {
            $providerCatalogServiceIds = array_map('intval', (array)($row['provider_catalog_service_ids'] ?? []));
            $serviceIds = array_map('intval', (array)($row['service_ids'] ?? []));
            $matchesProviderCatalogService = ($targetProviderCatalogServiceId > 0)
                ? in_array($targetProviderCatalogServiceId, $providerCatalogServiceIds, true)
                : false;
            $matchesLegacyService = ($targetServiceId > 0)
                ? in_array($targetServiceId, $serviceIds, true)
                : false;
            if ($matchesProviderCatalogService || (!$matchesProviderCatalogService && $matchesLegacyService)) {
                $items[] = $row;
            }
        }

        pms_ok([
            'provider' => $provider,
            'provider_catalog_service_id' => $targetProviderCatalogServiceId > 0 ? $targetProviderCatalogServiceId : null,
            'service_id' => $targetServiceId,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    case 'reorder_staff': {
        $providerId = (int)($_POST['provider_id'] ?? $_GET['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $direction = trim((string)($_POST['direction'] ?? $_GET['direction'] ?? ''));
        pms_assert_provider_scope($providerId);
        if ($staffId <= 0 || !in_array($direction, ['up', 'down'], true)) {
            pms_err('provider_id, id and direction are required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }
        if (!pms_staff_row($conexion, $staffId, $providerId)) {
            pms_err('staff_not_found', 404);
        }

        $orderedIds = pms_fetch_sorted_staff_ids($conexion, $providerId);
        $currentIndex = array_search($staffId, $orderedIds, true);
        if ($currentIndex === false) {
            pms_err('staff_not_found', 404);
        }

        $swapIndex = ($direction === 'up') ? ($currentIndex - 1) : ($currentIndex + 1);
        if ($swapIndex < 0 || $swapIndex >= count($orderedIds)) {
            $rows = pms_list_staff_rows($conexion, $providerId, false);
            pms_ok([
                'items' => $rows,
                'provider' => $provider,
                'message' => 'No hay más movimientos disponibles',
            ]);
        }

        $tmp = $orderedIds[$currentIndex];
        $orderedIds[$currentIndex] = $orderedIds[$swapIndex];
        $orderedIds[$swapIndex] = $tmp;

        mysqli_begin_transaction($conexion);
        try {
            if (!pms_resequence_staff_sort_order($conexion, $providerId, $orderedIds)) {
                throw new Exception('db_error: reorder_failed');
            }
            mysqli_commit($conexion);
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            pms_err($e->getMessage(), 500);
        }

        $rows = pms_list_staff_rows($conexion, $providerId, false);
        pms_ok([
            'items' => $rows,
            'provider' => $provider,
            'message' => 'Orden del staff actualizado',
        ]);
    }

    case 'toggle_staff': {
        $providerId = (int)($_POST['provider_id'] ?? $_GET['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $value = (int)($_POST['value'] ?? $_GET['value'] ?? -1);
        pms_assert_provider_scope($providerId);
        if ($staffId <= 0 || ($value !== 0 && $value !== 1)) {
            pms_err('provider_id, id and value are required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }
        if (!pms_staff_row($conexion, $staffId, $providerId)) {
            pms_err('staff_not_found', 404);
        }

        $statusColumn = provider_staff_status_column_name($conexion);
        $legacyActiveColumn = provider_staff_has_legacy_active_column($conexion);
        $fields = [];
        $types = '';
        $params = [];
        if ($statusColumn !== '') {
            $fields[] = '`' . $statusColumn . '` = ?';
            $types .= 'i';
            $params[] = $value;
        }
        if ($legacyActiveColumn && $statusColumn !== 'active') {
            $fields[] = 'active = ?';
            $types .= 'i';
            $params[] = $value;
        }
        if (empty($fields)) {
            pms_err('provider_medical_staff_status_column_missing — run sql/2026_03_12_provider_medical_staff.sql', 503);
        }
        if (provider_staff_table_has_column($conexion, 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        $stmt = mysqli_prepare(
            $conexion,
            'UPDATE provider_medical_staff
                SET ' . implode(', ', $fields) . '
              WHERE id = ? AND provider_id = ?
              LIMIT 1'
        );
        if (!$stmt) {
            pms_err('db_prepare_failed', 500);
        }
        $types .= 'ii';
        $params[] = $staffId;
        $params[] = $providerId;
        bind_stmt_params($stmt, $types, $params);
        $ok = mysqli_stmt_execute($stmt);
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            pms_err('db_error: ' . $err, 500);
        }

        $row = pms_staff_row($conexion, $staffId, $providerId);
        pms_ok([
            'item' => $row,
            'message' => ($value === 1) ? 'Registro activado' : 'Registro desactivado',
            'provider' => $provider,
        ]);
    }

    case 'toggle_staff_access': {
        $providerId = (int)($_POST['provider_id'] ?? $_GET['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $value = (int)($_POST['value'] ?? $_GET['value'] ?? -1);
        pms_assert_provider_scope($providerId);
        if ($staffId <= 0 || ($value !== 0 && $value !== 1)) {
            pms_err('provider_id, id and value are required');
        }
        if (!pms_has_access_columns($conexion)) {
            pms_err('provider_medical_staff_access_columns_missing — run sql/2026_03_12_provider_staff_access_and_item_assignment.sql', 503);
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $currentRow = pms_staff_row($conexion, $staffId, $providerId);
        if (!$currentRow) {
            pms_err('staff_not_found', 404);
        }

        $linkedUserId = (int)($currentRow['linked_user_id'] ?? 0);
        if ($linkedUserId <= 0) {
            pms_err('Este staff no tiene un usuario vinculado para gestionar acceso al panel.', 422, ['status' => 'staff_linked_user_required']);
        }

        mysqli_begin_transaction($conexion);
        try {
            $fields = ['can_access_admin = ?'];
            $types = 'i';
            $params = [$value];
            if (provider_staff_table_has_column($conexion, 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }

            $stmt = mysqli_prepare(
                $conexion,
                'UPDATE provider_medical_staff
                    SET ' . implode(', ', $fields) . '
                  WHERE id = ? AND provider_id = ?
                  LIMIT 1'
            );
            if (!$stmt) {
                throw new Exception('db_prepare_failed');
            }
            $types .= 'ii';
            $params[] = $staffId;
            $params[] = $providerId;
            bind_stmt_params($stmt, $types, $params);
            $ok = mysqli_stmt_execute($stmt);
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            if (!$ok) {
                throw new Exception('db_error: ' . $err);
            }

            $syncAccess = pms_sync_linked_user_access_state($conexion, $linkedUserId, $value === 1);
            if (!empty($syncAccess['error'])) {
                throw new Exception(!empty($syncAccess['detail']) ? $syncAccess['detail'] : $syncAccess['error']);
            }

            mysqli_commit($conexion);
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            pms_err($e->getMessage(), 500);
        }

        $row = pms_staff_row($conexion, $staffId, $providerId);
        pms_ok([
            'item' => $row,
            'message' => ($value === 1) ? 'Acceso al panel activado' : 'Acceso al panel desactivado',
            'provider' => $provider,
        ]);
    }

    default:
        pms_err('action_required', 400);
}
