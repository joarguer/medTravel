<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';
require_once __DIR__ . '/../../inc/calendar_utils.php';

function calendar_client_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function calendar_client_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

$clientUserId = get_client_user_id();
if (!isset($conexion) || !$conexion) {
    calendar_client_err('db_not_available', 500);
}
if (!calendar_table_exists($conexion, 'calendar_events')) {
    calendar_client_err('calendar_events_not_available', 409);
}
if (!calendar_table_exists($conexion, 'booking_requests')) {
    calendar_client_err('booking_requests_not_available', 409);
}

$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    calendar_client_err('booking_owner_scope_unavailable', 409);
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list_events'));

if ($action === 'create_event' || $action === 'update_event' || $action === 'delete_event') {
    calendar_client_err('forbidden_read_only', 403);
}

if ($action !== 'list_events') {
    calendar_client_err('invalid_action', 400);
}

$filterRequestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

$start = calendar_parse_datetime_input($_GET['start'] ?? $_POST['start'] ?? '');
$end = calendar_parse_datetime_input($_GET['end'] ?? $_POST['end'] ?? '');
if ($start === null) {
    $start = date('Y-m-d 00:00:00', strtotime('-60 days'));
}
if ($end === null) {
    $end = date('Y-m-d 23:59:59', strtotime('+120 days'));
}

$hasBookingSoftDelete = calendar_table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasCalendarClientUser = calendar_table_has_column($conexion, 'calendar_events', 'client_user_id');

if ($filterRequestId > 0) {
    $scopeSql = "SELECT br.id
                 FROM booking_requests br
                 WHERE br.id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasBookingSoftDelete) {
        $scopeSql .= " AND br.is_deleted = 0";
    }
    $scopeSql .= " LIMIT 1";
    $scopeStmt = mysqli_prepare($conexion, $scopeSql);
    if (!$scopeStmt) {
        calendar_client_err('prepare_failed', 500);
    }
    $scopeTypes = 'i' . $ownerScope['types'];
    $scopeParams = array_merge([$filterRequestId], $ownerScope['params']);
    if (!calendar_bind_stmt_params($scopeStmt, $scopeTypes, $scopeParams) || !mysqli_stmt_execute($scopeStmt)) {
        $err = mysqli_stmt_error($scopeStmt);
        mysqli_stmt_close($scopeStmt);
        calendar_client_err('execute_failed: ' . $err, 500);
    }
    $scopeRes = mysqli_stmt_get_result($scopeStmt);
    $scopeRow = $scopeRes ? mysqli_fetch_assoc($scopeRes) : null;
    mysqli_stmt_close($scopeStmt);
    if (!$scopeRow) {
        calendar_client_err('forbidden', 403);
    }
}

$sql = "SELECT ce.*
        FROM calendar_events ce
        INNER JOIN booking_requests br ON br.id = ce.request_id
        WHERE ce.start_at <= ? AND COALESCE(ce.end_at, ce.start_at) >= ?";
$sql .= " AND ((ce.event_type = 'CARE' AND ce.request_id IS NOT NULL AND ce.item_id IS NULL) OR (ce.event_type = 'ITEM' AND ce.request_id IS NOT NULL AND ce.item_id IS NOT NULL))";

$types = 'ss';
$params = [$end, $start];

if ($hasCalendarClientUser && $clientUserId > 0) {
    $sql .= " AND (ce.client_user_id = ? OR (ce.client_user_id IS NULL AND (" . $ownerScope['sql'] . ")))";
    $types .= 'i' . $ownerScope['types'];
    $params[] = $clientUserId;
    $params = array_merge($params, $ownerScope['params']);
} else {
    $sql .= " AND (" . $ownerScope['sql'] . ")";
    $types .= $ownerScope['types'];
    $params = array_merge($params, $ownerScope['params']);
}

if ($hasBookingSoftDelete) {
    $sql .= " AND br.is_deleted = 0";
}
if ($filterRequestId > 0) {
    $sql .= " AND ce.request_id = ?";
    $types .= 'i';
    $params[] = $filterRequestId;
}
$sql .= " ORDER BY ce.start_at ASC, ce.id ASC";

$stmt = mysqli_prepare($conexion, $sql);
if (!$stmt) {
    calendar_client_err('prepare_failed', 500);
}
if (!calendar_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    calendar_client_err('execute_failed: ' . $err, 500);
}
$res = mysqli_stmt_get_result($stmt);
$events = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $events[] = calendar_json_event_row($row);
}
mysqli_stmt_close($stmt);

calendar_client_ok(['events' => $events]);
