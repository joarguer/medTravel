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
