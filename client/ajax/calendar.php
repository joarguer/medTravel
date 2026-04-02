<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../../admin/include/email_config.php';
require_once __DIR__ . '/../include/client_notifications.php';
require_once __DIR__ . '/../../inc/calendar_utils.php';
require_once __DIR__ . '/../../inc/inbox_utils.php';
require_once __DIR__ . '/../../inc/fee_gate.php';
require_once __DIR__ . '/../../inc/google_calendar.php';

function calendar_client_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function calendar_client_err($message, $status = 400, $errorCode = '')
{
    http_response_code($status);
    $payload = ['ok' => false, 'message' => $message];
    if ($errorCode !== '') {
        $payload['code'] = $errorCode;
    }
    echo json_encode($payload);
    exit;
}

function client_calendar_build_in_clause($count)
{
    $count = (int)$count;
    if ($count <= 0) {
        return '';
    }
    return implode(',', array_fill(0, $count, '?'));
}

function client_calendar_parse_structured_message_payload($prefix, $body)
{
    $prefix = trim((string)$prefix);
    $body = trim((string)$body);
    if ($prefix === '' || $body === '' || strpos($body, $prefix) !== 0) {
        return null;
    }

    $json = trim(substr($body, strlen($prefix)));
    if ($json === '') {
        return null;
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function client_calendar_event_is_google_synced($row)
{
    $integrationMode = strtolower(trim((string)($row['integration_mode'] ?? '')));
    $googleEventId = trim((string)($row['google_event_id'] ?? ''));
    if ($integrationMode === 'internal_only') {
        return false;
    }
    return $googleEventId !== '' || in_array($integrationMode, ['calendar_only', 'calendar_plus_meet'], true);
}

function client_calendar_reconcile_cancelled_google_events($conexion, array $rows)
{
    if (empty($rows) || !calendar_table_exists($conexion, 'inbox_messages')) {
        return $rows;
    }

    $candidateItemIds = [];
    foreach ($rows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? 'scheduled')));
        $itemId = (int)($row['item_id'] ?? 0);
        if ($itemId <= 0 || $status === 'cancelled' || !client_calendar_event_is_google_synced($row)) {
            continue;
        }
        $candidateItemIds[$itemId] = $itemId;
    }

    if (empty($candidateItemIds)) {
        return $rows;
    }

    $itemIds = array_values($candidateItemIds);
    $inClause = client_calendar_build_in_clause(count($itemIds));
    if ($inClause === '') {
        return $rows;
    }

    $sql = "SELECT item_id, body
            FROM inbox_messages
            WHERE thread_type = 'ITEM'
              AND item_id IN ({$inClause})
              AND body LIKE '[MEETING_CANCELLED] %'";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return $rows;
    }

    $types = str_repeat('i', count($itemIds));
    $params = $itemIds;
    if (!calendar_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return $rows;
    }

    $res = mysqli_stmt_get_result($stmt);
    $cancelledEventIds = [];
    $cancelledGoogleIds = [];
    while ($res && ($messageRow = mysqli_fetch_assoc($res))) {
        $payload = client_calendar_parse_structured_message_payload('[MEETING_CANCELLED]', (string)($messageRow['body'] ?? ''));
        if (!$payload) {
            continue;
        }
        $calendarEventId = (int)($payload['calendar_event_id'] ?? 0);
        if ($calendarEventId > 0) {
            $cancelledEventIds[$calendarEventId] = true;
        }
        $googleEventId = trim((string)($payload['event_id'] ?? ''));
        if ($googleEventId !== '') {
            $cancelledGoogleIds[$googleEventId] = true;
        }
    }
    mysqli_stmt_close($stmt);

    if (empty($cancelledEventIds) && empty($cancelledGoogleIds)) {
        return $rows;
    }

    $updateIds = [];
    foreach ($rows as &$row) {
        $eventId = (int)($row['id'] ?? 0);
        $googleEventId = trim((string)($row['google_event_id'] ?? ''));
        $matched = ($eventId > 0 && isset($cancelledEventIds[$eventId]))
            || ($googleEventId !== '' && isset($cancelledGoogleIds[$googleEventId]));
        if (!$matched) {
            continue;
        }
        if (strtolower(trim((string)($row['status'] ?? 'scheduled'))) !== 'cancelled') {
            $row['status'] = 'cancelled';
            if ($eventId > 0) {
                $updateIds[$eventId] = $eventId;
            }
        }
    }
    unset($row);

    if (!empty($updateIds)) {
        $ids = array_values($updateIds);
        $updateInClause = client_calendar_build_in_clause(count($ids));
        if ($updateInClause !== '') {
            $updateSql = "UPDATE calendar_events
                          SET status = 'cancelled', updated_at = NOW()
                          WHERE id IN ({$updateInClause})
                            AND COALESCE(status, 'scheduled') <> 'cancelled'";
            $updateStmt = mysqli_prepare($conexion, $updateSql);
            if ($updateStmt) {
                $updateTypes = str_repeat('i', count($ids));
                $updateParams = $ids;
                if (calendar_bind_stmt_params($updateStmt, $updateTypes, $updateParams)) {
                    mysqli_stmt_execute($updateStmt);
                }
                mysqli_stmt_close($updateStmt);
            }
        }
    }

    return $rows;
}

function client_calendar_resolve_patientcare_email($conexion)
{
    if (!function_exists('loadEmailAccountsFromDB')) {
        return '';
    }
    $accounts = loadEmailAccountsFromDB($conexion);
    if (!is_array($accounts) || empty($accounts['patientcare']) || !is_array($accounts['patientcare'])) {
        return '';
    }
    $email = trim((string)($accounts['patientcare']['reply_to'] ?? ''));
    if ($email === '') {
        $email = trim((string)($accounts['patientcare']['from_email'] ?? ''));
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function client_calendar_fetch_item_provider_email($conexion, $itemId)
{
    $itemId = (int)$itemId;
    if ($itemId <= 0) {
        return '';
    }
    if (!calendar_table_exists($conexion, 'booking_request_items') || !calendar_table_exists($conexion, 'usuarios')) {
        return '';
    }
    $hasUsersDeleted = calendar_table_has_column($conexion, 'usuarios', 'is_deleted');
    $hasUsersActive = calendar_table_has_column($conexion, 'usuarios', 'activo');

    $sql = "SELECT u.email
            FROM booking_request_items bri
            INNER JOIN usuarios u ON (
                (bri.provider_id IS NOT NULL AND bri.provider_id > 0 AND u.provider_id = bri.provider_id)
                OR
                (bri.service_provider_id IS NOT NULL AND bri.service_provider_id > 0 AND u.service_provider_id = bri.service_provider_id)
            )
            WHERE bri.id = ?";
    if ($hasUsersDeleted) {
        $sql .= " AND u.is_deleted = 0";
    }
    if ($hasUsersActive) {
        $sql .= " AND u.activo = 1";
    }
    $sql .= " AND u.email IS NOT NULL AND u.email <> '' ORDER BY u.id ASC LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $itemId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return '';
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    $email = trim((string)($row['email'] ?? ''));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function client_calendar_insert_inbox_message($conexion, $threadId, $threadType, $requestId, $itemId, $senderRole, $senderUserId, $body)
{
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        return 0;
    }
    $threadId = trim((string)$threadId);
    $threadType = strtoupper(trim((string)$threadType));
    $senderRole = strtoupper(trim((string)$senderRole));
    $body = trim((string)$body);
    if ($threadId === '' || $body === '' || !in_array($threadType, ['CARE', 'ITEM'], true)) {
        return 0;
    }
    if (!in_array($senderRole, ['CLIENT', 'PROVIDER', 'ADMIN', 'PATIENTCARE'], true)) {
        $senderRole = 'CLIENT';
    }
    $requestId = (int)$requestId;
    $itemId = (int)$itemId;
    $senderUserId = (int)$senderUserId;

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ssiisis', $threadId, $threadType, $requestId, $itemId, $senderRole, $senderUserId, $body);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $messageId;
}

function client_calendar_fetch_scoped_event($conexion, $eventId, $ownerScope, $clientUserId, $hasCalendarClientUser, $hasBookingSoftDelete)
{
    $eventId = (int)$eventId;
    if ($eventId <= 0) {
        return null;
    }

    $sql = "SELECT ce.*
            FROM calendar_events ce
            INNER JOIN booking_requests br ON br.id = ce.request_id
            WHERE ce.id = ?";
    $types = 'i';
    $params = [$eventId];
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
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    if (!calendar_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function client_calendar_notify_acceptance($conexion, $eventRow, $adminEmail, $providerEmail)
{
    if (!function_exists('sendEmail')) {
        return;
    }
    $requestId = (int)($eventRow['request_id'] ?? 0);
    $itemId = (int)($eventRow['item_id'] ?? 0);
    $title = trim((string)($eventRow['title'] ?? 'Schedule proposal'));
    $start = trim((string)($eventRow['start_at'] ?? ''));
    $end = trim((string)($eventRow['end_at'] ?? ''));
    $allDay = ((int)($eventRow['all_day'] ?? 0) === 1);
    $schedule = $start;
    if ($allDay) {
        $schedule = substr($start, 0, 10) . ' (all day)';
    } elseif ($start !== '' && $end !== '') {
        $schedule = $start . ' to ' . $end;
    }
    if ($schedule === '') {
        $schedule = 'date/time not specified';
    }

    $subject = 'MedTravel update - patient accepted schedule for request #' . $requestId;
    $html = '<p>Patient accepted the proposed schedule.</p>'
        . '<p><strong>Request ID:</strong> #' . $requestId . '<br>'
        . '<strong>Event:</strong> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Schedule:</strong> ' . htmlspecialchars($schedule, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($itemId > 0) {
        $html .= '<p><strong>Item ID:</strong> #' . $itemId . '</p>';
    }
    $alt = "Patient accepted the proposed schedule.\nRequest ID: #{$requestId}\nEvent: {$title}\nSchedule: {$schedule}";
    if ($itemId > 0) {
        $alt .= "\nItem ID: #{$itemId}";
    }

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            sendEmail($adminEmail, '[ADMIN] ' . $subject, $html, 'patientcare', ['alt_body' => $alt], $conexion);
        } catch (Throwable $e) {
            error_log('calendar_client_accept admin_send_failed request_id=' . $requestId . ' item_id=' . $itemId . ' err=' . $e->getMessage());
        }
    }
    if (filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            sendEmail($providerEmail, $subject, $html, 'patientcare', ['alt_body' => $alt], $conexion);
        } catch (Throwable $e) {
            error_log('calendar_client_accept provider_send_failed request_id=' . $requestId . ' item_id=' . $itemId . ' err=' . $e->getMessage());
        }
    }
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

if (!in_array($action, ['list_events', 'accept_event', 'cancel_event'], true)) {
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

if ($action === 'accept_event') {
    $eventId = (int)($_POST['id'] ?? 0);
    if ($eventId <= 0) {
        calendar_client_err('invalid_event_id', 422);
    }

    $eventRow = client_calendar_fetch_scoped_event($conexion, $eventId, $ownerScope, $clientUserId, $hasCalendarClientUser, $hasBookingSoftDelete);
    if (!$eventRow) {
        calendar_client_err('forbidden_or_not_found', 404);
    }

    $eventRequestId = (int)($eventRow['request_id'] ?? 0);
    if ($eventRequestId > 0 && is_booking_fee_required($conexion, $eventRequestId)) {
        calendar_client_err('coordination_fee_required', 403, 'FEE_REQUIRED');
    }

    $status = calendar_normalize_status($eventRow['status'] ?? '');
    if ($status !== 'proposed') {
        calendar_client_err('event_not_proposed', 409);
    }

    $threadType = calendar_normalize_event_type($eventRow['event_type'] ?? '');
    $requestId = (int)($eventRow['request_id'] ?? 0);
    $itemId = (int)($eventRow['item_id'] ?? 0);
    if ($threadType === 'CARE' && $itemId > 0) {
        calendar_client_err('invalid_event_integrity', 409);
    }
    if ($threadType === 'ITEM' && $itemId <= 0) {
        calendar_client_err('invalid_event_integrity', 409);
    }
    $threadId = trim((string)($eventRow['thread_id'] ?? ''));
    if ($threadId === '') {
        $threadId = calendar_build_thread_id($threadType, $requestId, $itemId);
    }

    mysqli_begin_transaction($conexion);

    $stmtUpdate = mysqli_prepare($conexion, "UPDATE calendar_events SET status = 'confirmed', updated_at = NOW() WHERE id = ? LIMIT 1");
    if (!$stmtUpdate) {
        mysqli_rollback($conexion);
        calendar_client_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param($stmtUpdate, 'i', $eventId);
    if (!mysqli_stmt_execute($stmtUpdate)) {
        $err = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        mysqli_rollback($conexion);
        calendar_client_err('update_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmtUpdate);
    $eventRow['status'] = 'confirmed';
    $eventRow['updated_at'] = date('Y-m-d H:i:s');

    if ($threadType === 'ITEM' && $itemId > 0) {
        $syncResult = google_calendar_sync_item_status_for_transition($conexion, $itemId, 'appointment_confirmed');
        if (empty($syncResult['ok'])) {
            mysqli_rollback($conexion);
            calendar_client_err((string)($syncResult['error'] ?? 'item_status_sync_failed'), 409);
        }
    }

    client_calendar_insert_inbox_message(
        $conexion,
        (string)$threadId,
        $threadType !== '' ? $threadType : 'CARE',
        $requestId,
        $itemId,
        'CLIENT',
        $clientUserId,
        'Patient accepted the proposed schedule.'
    );

    mysqli_commit($conexion);

    $adminEmail = client_calendar_resolve_patientcare_email($conexion);
    $providerEmail = ($threadType === 'ITEM' && $itemId > 0) ? client_calendar_fetch_item_provider_email($conexion, $itemId) : '';
    client_calendar_notify_acceptance($conexion, $eventRow, $adminEmail, $providerEmail);

    calendar_client_ok(['event' => calendar_json_event_row($eventRow)]);
}

if ($action === 'cancel_event') {
    $eventId = (int)($_POST['id'] ?? 0);
    if ($eventId <= 0) {
        calendar_client_err('invalid_event_id', 422);
    }

    $eventRow = client_calendar_fetch_scoped_event($conexion, $eventId, $ownerScope, $clientUserId, $hasCalendarClientUser, $hasBookingSoftDelete);
    if (!$eventRow) {
        calendar_client_err('forbidden_or_not_found', 404);
    }

    $cancelResult = google_calendar_cancel_calendar_event($conexion, $eventRow, [
        'role' => 'CLIENT',
        'user_id' => $clientUserId,
    ]);
    if (empty($cancelResult['ok'])) {
        calendar_client_err((string)($cancelResult['error'] ?? 'meeting_cancel_failed'), 409);
    }

    calendar_client_ok([
        'id' => $eventId,
        'event' => [
            'id' => $eventId,
            'status' => 'cancelled',
            'integration_mode' => (string)($cancelResult['integration_mode'] ?? ''),
            'google_event_id' => (string)($cancelResult['google_event_id'] ?? ''),
        ],
        'message' => [
            'id' => (int)($cancelResult['message_id'] ?? 0),
            'body' => (string)($cancelResult['message_body'] ?? ''),
        ],
    ]);
}

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
$rows = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $rows[] = $row;
}
mysqli_stmt_close($stmt);

$rows = client_calendar_reconcile_cancelled_google_events($conexion, $rows);
$events = [];
foreach ($rows as $row) {
    if (strtolower(trim((string)($row['status'] ?? 'scheduled'))) === 'cancelled') {
        continue;
    }
    $events[] = calendar_json_event_row($row);
}

calendar_client_ok(['events' => $events]);
