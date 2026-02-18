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

function has_soft_delete_columns($conexion, $table) {
    return table_has_column($conexion, $table, 'is_deleted')
        && table_has_column($conexion, $table, 'deleted_at')
        && table_has_column($conexion, $table, 'deleted_by');
}

function deleted_filter_sql($hasSoftDelete, $showDeleted, $columnPrefix = '') {
    if (!$hasSoftDelete) {
        return $showDeleted ? " AND 1=0" : "";
    }
    $col = $columnPrefix !== '' ? "{$columnPrefix}.is_deleted" : "is_deleted";
    return $showDeleted ? " AND {$col} = 1" : " AND {$col} = 0";
}

if (!is_role_admin_session()) {
    json_err('forbidden', 403);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$sessionUserId = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

if ($sessionUserId <= 0) {
    json_err('invalid_session_user', 401);
}

$providersHasSoftDelete = has_soft_delete_columns($conexion, 'providers');
$serviceProvidersHasSoftDelete = has_soft_delete_columns($conexion, 'service_providers');
$medtravelHasSoftDelete = has_soft_delete_columns($conexion, 'medtravel_services_catalog');

if ($action === 'list_providers') {
    $showDeleted = read_show_deleted();
    $sql = "SELECT id, name, type, city, is_active";
    if ($providersHasSoftDelete) {
        $sql .= ", is_deleted, deleted_at, deleted_by";
    }
    $sql .= " FROM providers WHERE kind = 'medical'";
    $sql .= deleted_filter_sql($providersHasSoftDelete, $showDeleted);
    $sql .= " ORDER BY id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) json_err('db_error: ' . mysqli_error($conexion), 500);

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    json_ok(['data' => $rows]);
}

if ($action === 'list_service_providers') {
    $showDeleted = read_show_deleted();
    $sql = "SELECT id, provider_name, provider_type, contact_email, is_active";
    if ($serviceProvidersHasSoftDelete) {
        $sql .= ", is_deleted, deleted_at, deleted_by";
    }
    $sql .= " FROM service_providers WHERE 1=1";
    $sql .= deleted_filter_sql($serviceProvidersHasSoftDelete, $showDeleted);
    $sql .= " ORDER BY id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) json_err('db_error: ' . mysqli_error($conexion), 500);

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    json_ok(['data' => $rows]);
}

if ($action === 'list_medtravel_services') {
    $showDeleted = read_show_deleted();
    $sql = "SELECT id, service_name, service_type, availability_status, is_active";
    if ($medtravelHasSoftDelete) {
        $sql .= ", is_deleted, deleted_at, deleted_by";
    }
    $sql .= " FROM medtravel_services_catalog WHERE 1=1";
    $sql .= deleted_filter_sql($medtravelHasSoftDelete, $showDeleted);
    $sql .= " ORDER BY id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) json_err('db_error: ' . mysqli_error($conexion), 500);

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    json_ok(['data' => $rows]);
}

if ($action === 'soft_delete_provider') {
    if (!$providersHasSoftDelete) {
        json_err('soft_delete_columns_missing_on_providers', 500);
    }

    $providerId = read_int_param(['provider_id', 'id']);
    if ($providerId <= 0) {
        json_err('invalid_provider_id', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE providers
         SET is_deleted = 1,
             deleted_at = NOW(),
             deleted_by = ?,
             is_active = 0
         WHERE id = ? AND is_deleted = 0
         LIMIT 1"
    );

    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    mysqli_stmt_bind_param($stmt, 'ii', $sessionUserId, $providerId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected < 1) {
        json_err('provider_not_found_or_already_deleted', 404);
    }

    json_ok(['message' => 'Provider eliminado (soft)']);
}

if ($action === 'restore_provider') {
    if (!$providersHasSoftDelete) {
        json_err('soft_delete_columns_missing_on_providers', 500);
    }

    $providerId = read_int_param(['provider_id', 'id']);
    if ($providerId <= 0) {
        json_err('invalid_provider_id', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE providers
         SET is_deleted = 0,
             deleted_at = NULL,
             deleted_by = NULL,
             is_active = 1
         WHERE id = ? AND is_deleted = 1
         LIMIT 1"
    );

    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected < 1) {
        json_err('provider_not_found_or_not_deleted', 404);
    }

    json_ok(['message' => 'Provider restaurado']);
}

if ($action === 'soft_delete_service_provider') {
    if (!$serviceProvidersHasSoftDelete) {
        json_err('soft_delete_columns_missing_on_service_providers', 500);
    }

    $serviceProviderId = read_int_param(['service_provider_id', 'id']);
    if ($serviceProviderId <= 0) {
        json_err('invalid_service_provider_id', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE service_providers
         SET is_deleted = 1,
             deleted_at = NOW(),
             deleted_by = ?,
             is_active = 0
         WHERE id = ? AND is_deleted = 0
         LIMIT 1"
    );

    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    mysqli_stmt_bind_param($stmt, 'ii', $sessionUserId, $serviceProviderId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected < 1) {
        json_err('service_provider_not_found_or_already_deleted', 404);
    }

    json_ok(['message' => 'Service provider eliminado (soft)']);
}

if ($action === 'restore_service_provider') {
    if (!$serviceProvidersHasSoftDelete) {
        json_err('soft_delete_columns_missing_on_service_providers', 500);
    }

    $serviceProviderId = read_int_param(['service_provider_id', 'id']);
    if ($serviceProviderId <= 0) {
        json_err('invalid_service_provider_id', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE service_providers
         SET is_deleted = 0,
             deleted_at = NULL,
             deleted_by = NULL,
             is_active = 1
         WHERE id = ? AND is_deleted = 1
         LIMIT 1"
    );

    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    mysqli_stmt_bind_param($stmt, 'i', $serviceProviderId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected < 1) {
        json_err('service_provider_not_found_or_not_deleted', 404);
    }

    json_ok(['message' => 'Service provider restaurado']);
}

if ($action === 'soft_delete_medtravel_service') {
    if (!$medtravelHasSoftDelete) {
        json_err('soft_delete_columns_missing_on_medtravel_services_catalog', 500);
    }

    $serviceId = read_int_param(['medtravel_service_id', 'service_id', 'id']);
    if ($serviceId <= 0) {
        json_err('invalid_medtravel_service_id', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE medtravel_services_catalog
         SET is_deleted = 1,
             deleted_at = NOW(),
             deleted_by = ?,
             is_active = 0
         WHERE id = ? AND is_deleted = 0
         LIMIT 1"
    );

    if (!$stmt) json_err('db_prepare_error', 500);

    mysqli_stmt_bind_param($stmt, 'ii', $sessionUserId, $serviceId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected < 1) {
        json_err('medtravel_service_not_found_or_already_deleted', 404);
    }

    json_ok(['message' => 'MedTravel service eliminado (soft)']);
}

if ($action === 'restore_medtravel_service') {
    if (!$medtravelHasSoftDelete) {
        json_err('soft_delete_columns_missing_on_medtravel_services_catalog', 500);
    }

    $serviceId = read_int_param(['medtravel_service_id', 'service_id', 'id']);
    if ($serviceId <= 0) {
        json_err('invalid_medtravel_service_id', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE medtravel_services_catalog
         SET is_deleted = 0,
             deleted_at = NULL,
             deleted_by = NULL,
             is_active = 1
         WHERE id = ? AND is_deleted = 1
         LIMIT 1"
    );

    if (!$stmt) json_err('db_prepare_error', 500);

    mysqli_stmt_bind_param($stmt, 'i', $serviceId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected < 1) {
        json_err('medtravel_service_not_found_or_not_deleted', 404);
    }

    json_ok(['message' => 'MedTravel service restaurado']);
}

json_err('invalid_action', 400);
