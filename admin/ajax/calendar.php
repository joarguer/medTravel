<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../include/email_config.php';
require_once '../../inc/calendar_utils.php';
require_once '../../inc/inbox_utils.php';

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
            $scopeWhere = ' AND ((bri.provider_id IS NOT NULL AND bri.provider_id = ?) OR (bri.service_provider_id IS NOT NULL AND bri.service_provider_id = ?))';
            $scopeTypes = 'ii';
            $scopeParams = [$providerId, $providerId];
        } else {
            $scopeWhere = ' AND ((bri.service_provider_id IS NOT NULL AND bri.service_provider_id = ?) OR (bri.provider_id IS NOT NULL AND bri.provider_id = ?))';
            $scopeTypes = 'ii';
            $scopeParams = [$serviceProviderId, $serviceProviderId];
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
    $providerIdExpr = calendar_table_has_column($conexion, 'booking_request_items', 'provider_id') ? 'bri.provider_id' : 'NULL';
    $serviceProviderIdExpr = calendar_table_has_column($conexion, 'booking_request_items', 'service_provider_id') ? 'bri.service_provider_id' : 'NULL';

    $sql = "SELECT bri.id AS item_id, bri.booking_request_id AS request_id,
                   {$providerIdExpr} AS provider_id,
                   {$serviceProviderIdExpr} AS service_provider_id
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

function calendar_resolve_provider_context_from_item($itemRow)
{
    $providerId = (int)($itemRow['provider_id'] ?? 0);
    if ($providerId > 0) {
        return ['kind' => 'providers', 'id' => $providerId];
    }
    $serviceProviderId = (int)($itemRow['service_provider_id'] ?? 0);
    if ($serviceProviderId > 0) {
        return ['kind' => 'service_providers', 'id' => $serviceProviderId];
    }
    return ['kind' => '', 'id' => 0];
}

function calendar_get_provider_capacity($conexion, $providerContext)
{
    $kind = trim((string)($providerContext['kind'] ?? ''));
    $providerId = (int)($providerContext['id'] ?? 0);
    if ($providerId <= 0 || $kind === '') {
        return 1;
    }
    if (!calendar_table_exists($conexion, $kind) || !calendar_table_has_column($conexion, $kind, 'calendar_capacity')) {
        return 1;
    }

    $sql = "SELECT calendar_capacity FROM `{$kind}` WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 1;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 1;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    $capacity = (int)($row['calendar_capacity'] ?? 1);
    return ($capacity > 0) ? $capacity : 1;
}

function calendar_count_overlaps_for_provider($conexion, $providerContext, $startAt, $endAt, $excludeEventId = 0)
{
    $kind = trim((string)($providerContext['kind'] ?? ''));
    $providerId = (int)($providerContext['id'] ?? 0);
    $startAt = trim((string)$startAt);
    $endAt = trim((string)$endAt);
    $excludeEventId = (int)$excludeEventId;

    if ($providerId <= 0 || $kind === '' || $startAt === '') {
        return 0;
    }
    if ($endAt === '') {
        $endAt = $startAt;
    }

    $hasItemsSoftDelete = calendar_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $providerColumn = ($kind === 'providers') ? 'provider_id' : (($kind === 'service_providers') ? 'service_provider_id' : '');
    if ($providerColumn === '' || !calendar_table_has_column($conexion, 'booking_request_items', $providerColumn)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS overlap_count
            FROM calendar_events ce
            INNER JOIN booking_request_items bri ON bri.id = ce.item_id
            WHERE ce.event_type = 'ITEM'
              AND COALESCE(ce.status, 'scheduled') <> 'cancelled'
              AND ce.start_at < ?
              AND COALESCE(ce.end_at, ce.start_at) > ?
              AND bri.`{$providerColumn}` = ?";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    if ($excludeEventId > 0) {
        $sql .= " AND ce.id <> ?";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 0;
    }
    if ($excludeEventId > 0) {
        mysqli_stmt_bind_param($stmt, 'ssii', $endAt, $startAt, $providerId, $excludeEventId);
    } else {
        mysqli_stmt_bind_param($stmt, 'ssi', $endAt, $startAt, $providerId);
    }
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int)($row['overlap_count'] ?? 0);
}

function calendar_fetch_request_row($conexion, $requestId)
{
    if ((int)$requestId <= 0) {
        return null;
    }
    if (!calendar_table_exists($conexion, 'booking_requests')) {
        return null;
    }
    $hasRequestsSoftDelete = calendar_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $sql = "SELECT id, client_user_id FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $requestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
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

function calendar_resolve_patientcare_email($conexion)
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

function calendar_fetch_booking_contact($conexion, $requestId)
{
    if ((int)$requestId <= 0) {
        return null;
    }
    if (!calendar_table_exists($conexion, 'booking_requests')) {
        return null;
    }
    $hasRequestsSoftDelete = calendar_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $sql = "SELECT id, name, email, client_user_id FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $requestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function calendar_fetch_item_provider_email($conexion, $itemId)
{
    if ((int)$itemId <= 0) {
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

function calendar_insert_inbox_message($conexion, $threadId, $threadType, $requestId, $itemId, $senderRole, $senderUserId, $body)
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
        $senderRole = 'ADMIN';
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

function calendar_format_schedule_label($startAt, $endAt, $allDay)
{
    $startAt = trim((string)$startAt);
    $endAt = trim((string)$endAt);
    $allDay = ((int)$allDay === 1);
    if ($startAt === '') {
        return 'date/time to be confirmed';
    }
    if ($allDay) {
        $startDate = substr($startAt, 0, 10);
        if ($endAt !== '' && substr($endAt, 0, 10) !== '' && substr($endAt, 0, 10) !== $startDate) {
            return $startDate . ' to ' . substr($endAt, 0, 10) . ' (all day)';
        }
        return $startDate . ' (all day)';
    }
    if ($endAt !== '') {
        return $startAt . ' to ' . $endAt;
    }
    return $startAt;
}

function calendar_notify_provider_proposed_change($conexion, $eventRow, $bookingContact, $adminEmail)
{
    if (!function_exists('sendEmail')) {
        return;
    }
    $requestId = (int)($eventRow['request_id'] ?? 0);
    $itemId = (int)($eventRow['item_id'] ?? 0);
    $eventTitle = trim((string)($eventRow['title'] ?? 'Schedule update'));
    $scheduleLabel = calendar_format_schedule_label($eventRow['start_at'] ?? '', $eventRow['end_at'] ?? '', $eventRow['all_day'] ?? 0);

    $subject = 'MedTravel update - proposed schedule for request #' . $requestId;
    $clientName = trim((string)($bookingContact['name'] ?? 'Patient'));
    if ($clientName === '') {
        $clientName = 'Patient';
    }
    $clientHtml = '<p>Hello ' . htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Your provider proposed a new schedule.</p>'
        . '<p><strong>Request ID:</strong> #' . $requestId . '<br>'
        . '<strong>Service:</strong> ' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Proposed schedule:</strong> ' . htmlspecialchars($scheduleLabel, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Please review this proposal in your Inbox or calendar.</p>';
    $clientAlt = "Hello {$clientName},\n\nYour provider proposed a new schedule.\nRequest ID: #{$requestId}\nService: {$eventTitle}\nProposed schedule: {$scheduleLabel}\n\nPlease review this proposal in your Inbox or calendar.";

    $clientEmail = trim((string)($bookingContact['email'] ?? ''));
    if (filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            sendEmail($clientEmail, $subject, $clientHtml, 'patientcare', ['alt_body' => $clientAlt], $conexion);
        } catch (Throwable $e) {
            error_log('calendar_provider_notify client_send_failed request_id=' . $requestId . ' item_id=' . $itemId . ' err=' . $e->getMessage());
        }
    }

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $adminHtml = '<p>Provider proposed a new schedule.</p>'
            . '<p><strong>Request ID:</strong> #' . $requestId . '<br>'
            . '<strong>Item ID:</strong> #' . $itemId . '<br>'
            . '<strong>Service:</strong> ' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '<br>'
            . '<strong>Proposed schedule:</strong> ' . htmlspecialchars($scheduleLabel, ENT_QUOTES, 'UTF-8') . '</p>';
        $adminAlt = "Provider proposed a new schedule.\nRequest ID: #{$requestId}\nItem ID: #{$itemId}\nService: {$eventTitle}\nProposed schedule: {$scheduleLabel}";
        try {
            sendEmail($adminEmail, '[ADMIN] ' . $subject, $adminHtml, 'patientcare', ['alt_body' => $adminAlt], $conexion);
        } catch (Throwable $e) {
            error_log('calendar_provider_notify admin_send_failed request_id=' . $requestId . ' item_id=' . $itemId . ' err=' . $e->getMessage());
        }
    }
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

if ($action === 'list_threads') {
    if (!calendar_table_exists($conexion, 'booking_request_items') || !calendar_table_exists($conexion, 'booking_requests')) {
        calendar_admin_err('booking_items_not_available', 409);
    }

    $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 300);
    if ($limit < 1) {
        $limit = 300;
    } elseif ($limit > 1000) {
        $limit = 1000;
    }

    $hasItemsSoftDelete = calendar_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasRequestsSoftDelete = calendar_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasItemStatus = calendar_table_has_column($conexion, 'booking_request_items', 'item_status');
    $itemStatusExpr = $hasItemStatus
        ? "CASE
                WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                ELSE bri.item_status
           END"
        : "'pending_provider'";

    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id AS request_id,
                {$itemStatusExpr} AS item_status,
                COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            LEFT JOIN service_catalog sc ON sc.id = o.service_id
            LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
            WHERE 1=1";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    if (empty($scope['is_admin'])) {
        $sql .= (string)$scope['scope_where'];
    }
    $sql .= " ORDER BY br.created_at DESC, bri.id DESC LIMIT " . $limit;

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        calendar_admin_err('prepare_failed', 500);
    }
    if (!empty($scope['scope_types'])) {
        $bindParams = $scope['scope_params'];
        if (!calendar_bind_stmt_params($stmt, (string)$scope['scope_types'], $bindParams)) {
            mysqli_stmt_close($stmt);
            calendar_admin_err('bind_failed', 500);
        }
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        calendar_admin_err('execute_failed: ' . $err, 500);
    }

    $res = mysqli_stmt_get_result($stmt);
    $threads = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $itemId = (int)($row['item_id'] ?? 0);
        $requestId = (int)($row['request_id'] ?? 0);
        if ($itemId <= 0 || $requestId <= 0) {
            continue;
        }
        $itemName = trim((string)($row['item_name'] ?? ''));
        if ($itemName === '') {
            $itemName = 'Item #' . $itemId;
        }
        $threads[] = [
            'thread_id' => 'ITEM:' . $itemId,
            'thread_type' => 'ITEM',
            'item_id' => $itemId,
            'request_id' => $requestId,
            'status' => (string)($row['item_status'] ?? 'pending_provider'),
            'label' => 'Request #' . $requestId . ' - ' . $itemName,
        ];
    }
    mysqli_stmt_close($stmt);

    calendar_admin_ok(['threads' => $threads]);
}

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
    $sql .= " AND ((ce.event_type = 'CARE' AND ce.request_id IS NOT NULL AND ce.item_id IS NULL) OR (ce.event_type = 'ITEM' AND ce.item_id IS NOT NULL))";
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
    $isProviderActor = empty($scope['is_admin']);
    $providerContext = ['kind' => '', 'id' => 0];

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
            calendar_admin_err('item_not_found_or_forbidden', $isProviderActor ? 403 : 404);
        }
        $providerContext = calendar_resolve_provider_context_from_item($itemRow);
        $requestId = (int)$itemRow['request_id'];
    } else {
        if ($requestId <= 0) {
            calendar_admin_err('request_id_required', 422);
        }
        if ($itemId > 0) {
            calendar_admin_err('care_event_cannot_have_item_id', 400);
        }
        $itemId = 0;
    }
    if ($isProviderActor) {
        $status = 'proposed';
    }

    if ($eventType === 'ITEM') {
        $capacity = calendar_get_provider_capacity($conexion, $providerContext);
        $overlapCount = calendar_count_overlaps_for_provider($conexion, $providerContext, (string)$startAt, (string)$endAt, 0);
        if ($overlapCount >= $capacity) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'code' => 'CONFLICT',
                'error' => 'This time conflicts with another scheduled event.',
            ]);
            exit;
        }
    }

    $requestRow = calendar_fetch_request_row($conexion, $requestId);
    if (!$requestRow) {
        calendar_admin_err('request_not_found', 404);
    }
    $clientUserId = (int)($requestRow['client_user_id'] ?? 0);
    if ($clientUserId <= 0) {
        calendar_admin_err('request_client_user_required', 422);
    }
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
    $providerIdentifier = ((int)$providerContext['id'] > 0) ? (int)$providerContext['id'] : (int)$scope['provider_identifier'];
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
    if ($isProviderActor) {
        $eventForNotify = $eventRow ?: [
            'request_id' => $requestId,
            'item_id' => $itemId,
            'title' => $title,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'all_day' => $allDay,
            'event_type' => $eventType,
            'thread_id' => $threadId,
            'status' => $status,
        ];
        $scheduleLabel = calendar_format_schedule_label($startAt, $endAt, $allDay);
        $autoMessage = 'Provider proposed a new schedule for: ' . $title . ' at ' . $scheduleLabel . '.';
        calendar_insert_inbox_message(
            $conexion,
            (string)$threadId,
            'ITEM',
            (int)$requestId,
            (int)$itemId,
            'PROVIDER',
            (int)$scope['user_id'],
            $autoMessage
        );
        $bookingContact = calendar_fetch_booking_contact($conexion, $requestId);
        $adminEmail = calendar_resolve_patientcare_email($conexion);
        calendar_notify_provider_proposed_change($conexion, $eventForNotify, $bookingContact ?: [], $adminEmail);
    }
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
    $isProviderActor = empty($scope['is_admin']);
    $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : (int)($existing['request_id'] ?? 0);
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : (int)($existing['item_id'] ?? 0);
    $providerContext = ['kind' => '', 'id' => 0];

    if ($title === '' || $startAt === null) {
        calendar_admin_err('title_and_start_required', 422);
    }

    if ($eventType === 'ITEM') {
        if ($itemId <= 0) {
            calendar_admin_err('item_id_required', 422);
        }
        $itemRow = calendar_fetch_scoped_item_admin($conexion, $itemId, $scope);
        if (!$itemRow) {
            calendar_admin_err('item_not_found_or_forbidden', $isProviderActor ? 403 : 404);
        }
        $providerContext = calendar_resolve_provider_context_from_item($itemRow);
        $requestId = (int)$itemRow['request_id'];
    } else {
        if ($requestId <= 0) {
            calendar_admin_err('request_id_required', 422);
        }
        if ($itemId > 0) {
            calendar_admin_err('care_event_cannot_have_item_id', 400);
        }
        $itemId = 0;
    }

    $requestRow = calendar_fetch_request_row($conexion, $requestId);
    if (!$requestRow) {
        calendar_admin_err('request_not_found', 404);
    }
    $clientUserId = (int)($requestRow['client_user_id'] ?? 0);
    if ($clientUserId <= 0) {
        calendar_admin_err('request_client_user_required', 422);
    }
    $threadId = calendar_build_thread_id($eventType, $requestId, $itemId);
    if ($isProviderActor) {
        $status = 'proposed';
    }
    $providerIdentifier = ((int)$providerContext['id'] > 0) ? (int)$providerContext['id'] : (int)$scope['provider_identifier'];

    if ($eventType === 'ITEM') {
        $capacity = calendar_get_provider_capacity($conexion, $providerContext);
        $overlapCount = calendar_count_overlaps_for_provider($conexion, $providerContext, (string)$startAt, (string)$endAt, $eventId);
        if ($overlapCount >= $capacity) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'code' => 'CONFLICT',
                'error' => 'This time conflicts with another scheduled event.',
            ]);
            exit;
        }
    }

    $sql = "UPDATE calendar_events
            SET title = ?, description = ?, start_at = ?, end_at = ?, all_day = ?, event_type = ?, request_id = ?, item_id = ?, thread_id = ?, provider_id = ?, client_user_id = ?, status = ?, updated_at = NOW()
            WHERE id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        calendar_admin_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param(
        $stmt,
        'ssssisiisiisi',
        $title,
        $description,
        $startAt,
        $endAt,
        $allDay,
        $eventType,
        $requestId,
        $itemId,
        $threadId,
        $providerIdentifier,
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
    if ($isProviderActor) {
        $scheduleLabel = calendar_format_schedule_label($startAt, $endAt, $allDay);
        $autoMessage = 'Provider proposed a new schedule for: ' . $title . ' at ' . $scheduleLabel . '.';
        calendar_insert_inbox_message(
            $conexion,
            (string)$threadId,
            'ITEM',
            (int)$requestId,
            (int)$itemId,
            'PROVIDER',
            (int)$scope['user_id'],
            $autoMessage
        );
        $notifyRow = $row ?: [
            'request_id' => $requestId,
            'item_id' => $itemId,
            'title' => $title,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'all_day' => $allDay,
            'event_type' => $eventType,
            'thread_id' => $threadId,
            'status' => $status,
        ];
        $bookingContact = calendar_fetch_booking_contact($conexion, $requestId);
        $adminEmail = calendar_resolve_patientcare_email($conexion);
        calendar_notify_provider_proposed_change($conexion, $notifyRow, $bookingContact ?: [], $adminEmail);
    }
    calendar_admin_ok(['event' => calendar_json_event_row($row ?: $existing)]);
}

if ($action === 'delete_event') {
    $eventId = (int)($_POST['id'] ?? 0);
    if ($eventId <= 0) {
        calendar_admin_err('invalid_event_id', 422);
    }
    $existing = calendar_fetch_event_row_admin($conexion, $eventId, $scope);
    if (!$existing) {
        calendar_admin_err('event_not_found_or_forbidden', 404);
    }
    if (empty($scope['is_admin']) && strtoupper((string)($existing['event_type'] ?? '')) !== 'ITEM') {
        calendar_admin_err('forbidden_care_for_provider', 403);
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
