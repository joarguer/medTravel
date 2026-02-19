<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../../inc/calendar_utils.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

function calendar_admin_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function calendar_admin_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function calendar_admin_scope()
{
    $providerId = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
    $serviceProviderId = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;
    $isAdmin = is_role_admin_session();
    $userId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
    $roleId = current_role_id();

    if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
        return ['ok' => false, 'message' => 'forbidden', 'status' => 403];
    }
    if (!$isAdmin && $providerId <= 0 && $serviceProviderId <= 0) {
        return ['ok' => false, 'message' => 'forbidden', 'status' => 403];
    }

    $scopeWhere = '';
    $scopeTypes = '';
    $scopeParams = [];
    if (!$isAdmin) {
        if ($providerId > 0 && $serviceProviderId > 0) {
            $scopeWhere = ' AND (bri.provider_id = ? OR bri.service_provider_id = ?)';
            $scopeTypes = 'ii';
            $scopeParams = [$providerId, $serviceProviderId];
        } elseif ($providerId > 0) {
            $scopeWhere = ' AND bri.provider_id = ?';
            $scopeTypes = 'i';
            $scopeParams = [$providerId];
        } else {
            $scopeWhere = ' AND bri.service_provider_id = ?';
            $scopeTypes = 'i';
            $scopeParams = [$serviceProviderId];
        }
    }

    $roleLabel = 'PROVIDER';
    if ($isAdmin) {
        $roleLabel = ((int)$roleId === (int)ROLE_ADMINISTRATIVE) ? 'PATIENTCARE' : 'ADMIN';
    }
    $providerIdentifier = $providerId > 0 ? $providerId : $serviceProviderId;

    return [
        'ok' => true,
        'is_admin' => $isAdmin,
        'user_id' => $userId,
        'role_label' => $roleLabel,
        'provider_identifier' => $providerIdentifier,
        'scope_where' => $scopeWhere,
        'scope_types' => $scopeTypes,
        'scope_params' => $scopeParams,
    ];
}

function calendar_fetch_scoped_item_admin($conexion, $itemId, $scope)
{
    if (!calendar_table_exists($conexion, 'booking_request_items')) {
        return null;
    }
    $hasItemsSoftDelete = calendar_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasRequestsSoftDelete = calendar_table_has_column($conexion, 'booking_requests', 'is_deleted');

    $sql = "SELECT bri.id AS item_id, bri.booking_request_id AS request_id
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            WHERE bri.id = ?";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= (string)$scope['scope_where'];
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    $types = 'i' . (string)$scope['scope_types'];
    $params = array_merge([(int)$itemId], (array)$scope['scope_params']);
    if (!calendar_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function calendar_fetch_request_client_user_id($conexion, $requestId)
{
    if ((int)$requestId <= 0) {
        return 0;
    }
    if (!calendar_table_exists($conexion, 'booking_requests') || !calendar_table_has_column($conexion, 'booking_requests', 'client_user_id')) {
        return 0;
    }
    $hasRequestsSoftDelete = calendar_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $sql = "SELECT client_user_id FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $requestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int)($row['client_user_id'] ?? 0);
}

function calendar_fetch_event_row_admin($conexion, $eventId, $scope)
{
    $sql = "SELECT ce.*
            FROM calendar_events ce";
    if (empty($scope['is_admin'])) {
        $sql .= " INNER JOIN booking_request_items bri ON bri.id = ce.item_id";
    }
    $sql .= " WHERE ce.id = ?";
    if (empty($scope['is_admin'])) {
        $hasItemsSoftDelete = calendar_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        if ($hasItemsSoftDelete) {
            $sql .= " AND bri.is_deleted = 0";
        }
        $sql .= " AND ce.event_type = 'ITEM'";
        $sql .= (string)$scope['scope_where'];
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    $types = 'i' . (string)(empty($scope['is_admin']) ? $scope['scope_types'] : '');
    $params = array_merge([(int)$eventId], (array)(empty($scope['is_admin']) ? $scope['scope_params'] : []));
    if (!calendar_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

if (!isset($conexion) || !$conexion) {
    calendar_admin_err('db_not_available', 500);
}
if (!calendar_table_exists($conexion, 'calendar_events')) {
    calendar_admin_err('calendar_events_not_available', 409);
}

$scope = calendar_admin_scope();
if (empty($scope['ok'])) {
    calendar_admin_err((string)($scope['message'] ?? 'forbidden'), (int)($scope['status'] ?? 403));
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list_events'));

if ($action === 'list_events') {
    $start = calendar_parse_datetime_input($_GET['start'] ?? $_POST['start'] ?? '');
    $end = calendar_parse_datetime_input($_GET['end'] ?? $_POST['end'] ?? '');
    if ($start === null) {
        $start = date('Y-m-d 00:00:00', strtotime('-60 days'));
    }
    if ($end === null) {
        $end = date('Y-m-d 23:59:59', strtotime('+120 days'));
    }

    $sql = "SELECT ce.*
            FROM calendar_events ce";
    if (empty($scope['is_admin'])) {
        $sql .= " INNER JOIN booking_request_items bri ON bri.id = ce.item_id";
    }
    $sql .= " WHERE ce.start_at <= ? AND COALESCE(ce.end_at, ce.start_at) >= ?";
    if (empty($scope['is_admin'])) {
        $hasItemsSoftDelete = calendar_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        if ($hasItemsSoftDelete) {
            $sql .= " AND bri.is_deleted = 0";
        }
        $sql .= " AND ce.event_type = 'ITEM'";
        $sql .= (string)$scope['scope_where'];
    }
    $sql .= " ORDER BY ce.start_at ASC, ce.id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        calendar_admin_err('prepare_failed', 500);
    }
    $types = 'ss' . (string)(empty($scope['is_admin']) ? $scope['scope_types'] : '');
    $params = array_merge([$end, $start], (array)(empty($scope['is_admin']) ? $scope['scope_params'] : []));
    if (!calendar_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        calendar_admin_err('execute_failed: ' . $err, 500);
    }
    $res = mysqli_stmt_get_result($stmt);
    $events = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $events[] = calendar_json_event_row($row);
    }
    mysqli_stmt_close($stmt);

    calendar_admin_ok(['events' => $events]);
}

if ($action === 'create_event') {
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $startAt = calendar_parse_datetime_input($_POST['start_at'] ?? '');
    $endAt = calendar_parse_datetime_input($_POST['end_at'] ?? '');
    $allDay = isset($_POST['all_day']) ? (int)((int)$_POST['all_day'] === 1) : 0;
    $eventType = calendar_normalize_event_type($_POST['event_type'] ?? '');
    $requestId = (int)($_POST['request_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $status = calendar_normalize_status($_POST['status'] ?? 'scheduled');

    if ($title === '' || $startAt === null) {
        calendar_admin_err('title_and_start_required', 422);
    }
    if ($eventType === '') {
        calendar_admin_err('invalid_event_type', 422);
    }
    if (empty($scope['is_admin']) && $eventType !== 'ITEM') {
        calendar_admin_err('forbidden_care_for_provider', 403);
    }

    if ($eventType === 'ITEM') {
        if ($itemId <= 0) {
            calendar_admin_err('item_id_required', 422);
        }
        $itemRow = calendar_fetch_scoped_item_admin($conexion, $itemId, $scope);
        if (!$itemRow) {
            calendar_admin_err('item_not_found_or_forbidden', 404);
        }
        $requestId = (int)$itemRow['request_id'];
    } else {
        if ($requestId <= 0) {
            calendar_admin_err('request_id_required', 422);
        }
    }

    $clientUserId = calendar_fetch_request_client_user_id($conexion, $requestId);
    $threadId = calendar_build_thread_id($eventType, $requestId, $itemId);

    $sql = "INSERT INTO calendar_events
                (title, description, start_at, end_at, all_day, event_type, request_id, item_id, thread_id, created_by_role, created_by_user_id, provider_id, client_user_id, status, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        calendar_admin_err('prepare_failed', 500);
    }
    $createdByRole = (string)$scope['role_label'];
    $createdByUserId = (int)$scope['user_id'];
    $providerIdentifier = (int)$scope['provider_identifier'];
    mysqli_stmt_bind_param(
        $stmt,
        'ssssisiissiiis',
        $title,
        $description,
        $startAt,
        $endAt,
        $allDay,
        $eventType,
        $requestId,
        $itemId,
        $threadId,
        $createdByRole,
        $createdByUserId,
        $providerIdentifier,
        $clientUserId,
        $status
    );
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        calendar_admin_err('insert_failed: ' . $err, 500);
    }
    $eventId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    $eventRow = calendar_fetch_event_row_admin($conexion, $eventId, ['is_admin' => true, 'scope_types' => '', 'scope_params' => [], 'scope_where' => '']);
    calendar_admin_ok(['event' => calendar_json_event_row($eventRow ?: [
        'id' => $eventId,
        'title' => $title,
        'description' => $description,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'all_day' => $allDay,
        'event_type' => $eventType,
        'request_id' => $requestId,
        'item_id' => $itemId,
        'thread_id' => $threadId,
        'status' => $status,
    ])]);
}

if ($action === 'update_event') {
    $eventId = (int)($_POST['id'] ?? 0);
    if ($eventId <= 0) {
        calendar_admin_err('invalid_event_id', 422);
    }

    $existing = calendar_fetch_event_row_admin($conexion, $eventId, $scope);
    if (!$existing) {
        calendar_admin_err('event_not_found_or_forbidden', 404);
    }

    $eventType = calendar_normalize_event_type($_POST['event_type'] ?? $existing['event_type'] ?? '');
    if ($eventType === '') {
        calendar_admin_err('invalid_event_type', 422);
    }
    if (empty($scope['is_admin']) && $eventType !== 'ITEM') {
        calendar_admin_err('forbidden_care_for_provider', 403);
    }

    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : (string)$existing['title'];
    $description = isset($_POST['description']) ? trim((string)$_POST['description']) : (string)($existing['description'] ?? '');
    $startAt = isset($_POST['start_at']) ? calendar_parse_datetime_input($_POST['start_at']) : (string)$existing['start_at'];
    $endAt = isset($_POST['end_at']) ? calendar_parse_datetime_input($_POST['end_at']) : (string)($existing['end_at'] ?? '');
    $allDay = isset($_POST['all_day']) ? (int)((int)$_POST['all_day'] === 1) : (int)($existing['all_day'] ?? 0);
    $status = isset($_POST['status']) ? calendar_normalize_status($_POST['status']) : (string)$existing['status'];
    $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : (int)($existing['request_id'] ?? 0);
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : (int)($existing['item_id'] ?? 0);

    if ($title === '' || $startAt === null) {
        calendar_admin_err('title_and_start_required', 422);
    }

    if ($eventType === 'ITEM') {
        if ($itemId <= 0) {
            calendar_admin_err('item_id_required', 422);
        }
        $itemRow = calendar_fetch_scoped_item_admin($conexion, $itemId, $scope);
        if (!$itemRow) {
            calendar_admin_err('item_not_found_or_forbidden', 404);
        }
        $requestId = (int)$itemRow['request_id'];
    } elseif ($requestId <= 0) {
        calendar_admin_err('request_id_required', 422);
    }

    $clientUserId = calendar_fetch_request_client_user_id($conexion, $requestId);
    $threadId = calendar_build_thread_id($eventType, $requestId, $itemId);

    $sql = "UPDATE calendar_events
            SET title = ?, description = ?, start_at = ?, end_at = ?, all_day = ?, event_type = ?, request_id = ?, item_id = ?, thread_id = ?, client_user_id = ?, status = ?, updated_at = NOW()
            WHERE id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        calendar_admin_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param(
        $stmt,
        'ssssisiisisi',
        $title,
        $description,
        $startAt,
        $endAt,
        $allDay,
        $eventType,
        $requestId,
        $itemId,
        $threadId,
        $clientUserId,
        $status,
        $eventId
    );
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        calendar_admin_err('update_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmt);

    $row = calendar_fetch_event_row_admin($conexion, $eventId, $scope);
    calendar_admin_ok(['event' => calendar_json_event_row($row ?: $existing)]);
}

if ($action === 'delete_event') {
    if (empty($scope['is_admin'])) {
        calendar_admin_err('forbidden', 403);
    }
    $eventId = (int)($_POST['id'] ?? 0);
    if ($eventId <= 0) {
        calendar_admin_err('invalid_event_id', 422);
    }
    $stmt = mysqli_prepare($conexion, "DELETE FROM calendar_events WHERE id = ? LIMIT 1");
    if (!$stmt) {
        calendar_admin_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param($stmt, 'i', $eventId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        calendar_admin_err('delete_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmt);
    calendar_admin_ok(['id' => $eventId]);
}

calendar_admin_err('invalid_action', 400);
