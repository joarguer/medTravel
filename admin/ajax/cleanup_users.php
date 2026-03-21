<?php
require_once __DIR__ . '/../include/conexion.php';
require_once __DIR__ . '/../include/roles.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

function json_ok($data = []) {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function json_err($message, $status = 400) {
    http_response_code((int)$status);
    echo json_encode(['ok' => false, 'error' => $message, 'message' => $message]);
    exit;
}

function table_has_column($conexion, $table, $column) {
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

function table_exists($conexion, $table) {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $q = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$table];
}

function read_int_param($keys, $default = 0) {
    foreach ($keys as $key) {
        if (isset($_POST[$key])) return intval($_POST[$key]);
        if (isset($_GET[$key])) return intval($_GET[$key]);
    }
    return intval($default);
}

function read_show_deleted() {
    $raw = $_POST['show_deleted'] ?? $_GET['show_deleted'] ?? 0;
    return intval($raw) === 1 ? 1 : 0;
}

function usuarios_has_soft_delete($conexion) {
    return table_has_column($conexion, 'usuarios', 'is_deleted')
        && table_has_column($conexion, 'usuarios', 'deleted_at')
        && table_has_column($conexion, 'usuarios', 'deleted_by');
}

function cleanup_user_protection_reason($conexion, $userId) {
    $userId = (int)$userId;
    if ($userId <= 0) {
        return '';
    }
    if ($userId === 1) {
        return 'Superusuario global protegido';
    }

    if (table_exists($conexion, 'usuarios')) {
        $select = ['id'];
        if (table_has_column($conexion, 'usuarios', 'provider_id')) {
            $select[] = 'provider_id';
        }
        if (table_has_column($conexion, 'usuarios', 'service_provider_id')) {
            $select[] = 'service_provider_id';
        }
        $stmt = mysqli_prepare($conexion, "SELECT " . implode(', ', $select) . " FROM usuarios WHERE id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($row) {
                if (array_key_exists('provider_id', $row) && (int)($row['provider_id'] ?? 0) > 0) {
                    return 'Usuario scoped a provider medico';
                }
                if (array_key_exists('service_provider_id', $row) && (int)($row['service_provider_id'] ?? 0) > 0) {
                    return 'Usuario scoped a service provider';
                }
            }
        }
    }

    if (table_exists($conexion, 'provider_users') && table_has_column($conexion, 'provider_users', 'user_id')) {
        $stmt = mysqli_prepare($conexion, "SELECT provider_id FROM provider_users WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($row && (int)($row['provider_id'] ?? 0) > 0) {
                return 'Usuario con ownership/admin explicito de provider';
            }
        }
    }

    if (table_exists($conexion, 'provider_medical_staff') && table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')) {
        $stmt = mysqli_prepare($conexion, "SELECT id FROM provider_medical_staff WHERE linked_user_id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($row && (int)($row['id'] ?? 0) > 0) {
                return 'Usuario vinculado a staff medico';
            }
        }
    }

    return '';
}

if (!is_role_admin_session()) {
    json_err('forbidden', 403);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$sessionUserId = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

if ($sessionUserId <= 0) {
    json_err('invalid_session_user', 401);
}

if ($action === 'list_users') {
    $showDeleted = read_show_deleted();
    $hasSoftDelete = table_has_column($conexion, 'usuarios', 'is_deleted');

    $sql = "SELECT id, usuario, nombre, email, activo";
    if ($hasSoftDelete) {
        $sql .= ", is_deleted, deleted_at, deleted_by";
    }
    $sql .= " FROM usuarios WHERE 1=1";
    if ($hasSoftDelete) {
        $sql .= $showDeleted ? " AND is_deleted = 1" : " AND is_deleted = 0";
    } elseif ($showDeleted) {
        json_ok(['data' => []]);
    }
    $sql .= " ORDER BY id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        json_err('db_error: ' . mysqli_error($conexion), 500);
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $protectionReason = cleanup_user_protection_reason($conexion, (int)($row['id'] ?? 0));
        $isProtected = $protectionReason !== '';
        $isGlobalSuperuser = (int)($row['id'] ?? 0) === 1;
        $row['cleanup_protection_reason'] = $protectionReason;
        $row['can_soft_delete'] = $isProtected ? 0 : 1;
        $row['can_restore'] = $isGlobalSuperuser ? 0 : 1;
        $rows[] = $row;
    }

    json_ok(['data' => $rows]);
}

if ($action === 'soft_delete_user') {
    if (!usuarios_has_soft_delete($conexion)) {
        json_err('soft_delete_columns_missing_on_usuarios', 500);
    }

    $userId = read_int_param(['user_id', 'id']);
    if ($userId <= 0) {
        json_err('invalid_user_id', 422);
    }

    if ($userId === $sessionUserId) {
        json_err('cannot_soft_delete_logged_user', 422);
    }

    $protectionReason = cleanup_user_protection_reason($conexion, $userId);
    if ($protectionReason !== '') {
        json_err('cleanup_user_protected: ' . $protectionReason, 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE usuarios
         SET is_deleted = 1,
             deleted_at = NOW(),
             deleted_by = ?,
             activo = 0
         WHERE id = ? AND is_deleted = 0
         LIMIT 1"
    );

    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    mysqli_stmt_bind_param($stmt, 'ii', $sessionUserId, $userId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected < 1) {
        json_err('user_not_found_or_already_deleted', 404);
    }

    json_ok(['message' => 'Usuario eliminado (soft)']);
}

if ($action === 'restore_user') {
    if (!usuarios_has_soft_delete($conexion)) {
        json_err('soft_delete_columns_missing_on_usuarios', 500);
    }

    $userId = read_int_param(['user_id', 'id']);
    if ($userId <= 0) {
        json_err('invalid_user_id', 422);
    }

    if ($userId === 1) {
        json_err('cleanup_user_protected: Superusuario global protegido', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE usuarios
         SET is_deleted = 0,
             deleted_at = NULL,
             deleted_by = NULL,
             activo = 1
         WHERE id = ? AND is_deleted = 1
         LIMIT 1"
    );

    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected < 1) {
        json_err('user_not_found_or_not_deleted', 404);
    }

    json_ok(['message' => 'Usuario restaurado']);
}

json_err('invalid_action', 400);
