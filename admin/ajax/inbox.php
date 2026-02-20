<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../../inc/inbox_utils.php';
require_once '../../inc/fee_gate.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

function admin_inbox_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function admin_inbox_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function admin_inbox_build_scope()
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
            $scopeWhere = " AND ((bri.provider_id = ? AND bri.item_type = 'medical_offer') OR (bri.service_provider_id = ? AND bri.item_type = 'complementary_service'))";
            $scopeTypes = 'ii';
            $scopeParams = [$providerId, $serviceProviderId];
        } elseif ($providerId > 0) {
            $scopeWhere = " AND bri.provider_id = ? AND bri.item_type = 'medical_offer'";
            $scopeTypes = 'i';
            $scopeParams = [$providerId];
        } else {
            $scopeWhere = " AND bri.service_provider_id = ? AND bri.item_type = 'complementary_service'";
            $scopeTypes = 'i';
            $scopeParams = [$serviceProviderId];
        }
    }

    $readerRole = 'PROVIDER';
    if ($isAdmin) {
        $readerRole = ((int)$roleId === (int)ROLE_ADMINISTRATIVE) ? 'PATIENTCARE' : 'ADMIN';
    }

    return [
        'ok' => true,
        'is_admin' => $isAdmin,
        'user_id' => $userId,
        'reader_role' => $readerRole,
        'provider_id' => $providerId,
        'service_provider_id' => $serviceProviderId,
        'scope_where' => $scopeWhere,
        'scope_types' => $scopeTypes,
        'scope_params' => $scopeParams,
    ];
}

function admin_inbox_fetch_scoped_item($conexion, $itemId, $scope)
{
    $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasAdditionalNotes = inbox_table_has_column($conexion, 'booking_requests', 'additional_notes');

    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id AS request_id,
                br.destination";
    $sql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
    $sql .= " FROM booking_request_items bri
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
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function admin_inbox_fetch_scoped_care($conexion, $requestId)
{
    $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasAdditionalNotes = inbox_table_has_column($conexion, 'booking_requests', 'additional_notes');

    $sql = "SELECT id AS request_id, destination";
    $sql .= $hasAdditionalNotes ? ", additional_notes" : ", '' AS additional_notes";
    $sql .= " FROM booking_requests WHERE id = ?";
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

function admin_inbox_resolve_context($conexion, $scope, $threadType, $requestId, $itemId, $threadIdInput)
{
    $threadType = strtoupper(trim((string)$threadType));
    $requestId = (int)$requestId;
    $itemId = (int)$itemId;
    $threadIdInput = trim((string)$threadIdInput);

    if ($threadIdInput !== '') {
        $parsed = inbox_parse_thread_id($threadIdInput);
        if (empty($parsed['ok'])) {
            return ['ok' => false, 'message' => 'invalid_thread_id', 'status' => 422];
        }
        $threadType = (string)$parsed['thread_type'];
        if ($threadType === 'CARE') {
            $requestId = (int)$parsed['request_id'];
            $itemId = 0;
        } else {
            $itemId = (int)$parsed['item_id'];
        }
    }

    if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
        return ['ok' => false, 'message' => 'invalid_thread_type', 'status' => 422];
    }

    if ($threadType === 'CARE') {
        if (empty($scope['is_admin'])) {
            return ['ok' => false, 'message' => 'forbidden', 'status' => 403];
        }
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'invalid_request_id', 'status' => 422];
        }
        $care = admin_inbox_fetch_scoped_care($conexion, $requestId);
        if (!$care) {
            return ['ok' => false, 'message' => 'not_found', 'status' => 404];
        }
        return [
            'ok' => true,
            'thread_id' => inbox_thread_id('CARE', $requestId, 0),
            'thread_type' => 'CARE',
            'request_id' => $requestId,
            'item_id' => 0,
            'destination' => (string)($care['destination'] ?? ''),
            'additional_notes' => (string)($care['additional_notes'] ?? ''),
        ];
    }

    if ($itemId <= 0) {
        return ['ok' => false, 'message' => 'invalid_item_id', 'status' => 422];
    }

    $item = admin_inbox_fetch_scoped_item($conexion, $itemId, $scope);
    if (!$item) {
        return ['ok' => false, 'message' => 'not_found', 'status' => 404];
    }
    $requestId = (int)($item['request_id'] ?? 0);

    return [
        'ok' => true,
        'thread_id' => inbox_thread_id('ITEM', $requestId, $itemId),
        'thread_type' => 'ITEM',
        'request_id' => $requestId,
        'item_id' => $itemId,
        'destination' => (string)($item['destination'] ?? ''),
        'additional_notes' => (string)($item['additional_notes'] ?? ''),
    ];
}

if (!isset($conexion) || !$conexion) {
    admin_inbox_err('db_not_available', 500);
}
if (!inbox_table_exists($conexion, 'booking_requests') || !inbox_table_exists($conexion, 'booking_request_items')) {
    admin_inbox_err('booking_tables_not_available', 409);
}

$scope = admin_inbox_build_scope();
if (empty($scope['ok'])) {
    admin_inbox_err((string)($scope['message'] ?? 'forbidden'), (int)($scope['status'] ?? 403));
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list_threads'));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 200);
if ($limit < 1) {
    $limit = 200;
}
if ($limit > 500) {
    $limit = 500;
}

if ($action === 'list_threads') {
    $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $threads = [];

    if (!empty($scope['is_admin'])) {
        $careSql = "SELECT id AS request_id, destination, created_at
                    FROM booking_requests
                    WHERE 1=1";
        if ($hasRequestsSoftDelete) {
            $careSql .= " AND is_deleted = 0";
        }
        $careSql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        $careRes = mysqli_query($conexion, $careSql);
        if ($careRes) {
            while ($row = mysqli_fetch_assoc($careRes)) {
                $requestId = (int)($row['request_id'] ?? 0);
                if ($requestId <= 0) {
                    continue;
                }
                $threads[] = [
                    'thread_id' => inbox_thread_id('CARE', $requestId, 0),
                    'thread_key' => inbox_thread_id('CARE', $requestId, 0),
                    'thread_type' => 'CARE',
                    'request_id' => $requestId,
                    'booking_request_id' => $requestId,
                    'item_id' => 0,
                    'title' => 'General - Request #' . $requestId,
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
    }

    $itemSql = "SELECT
                    bri.id AS item_id,
                    bri.booking_request_id AS request_id,
                    COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                    br.destination,
                    br.created_at
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                LEFT JOIN service_catalog sc ON sc.id = o.service_id
                LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                WHERE 1=1";
    if ($hasItemsSoftDelete) {
        $itemSql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $itemSql .= " AND br.is_deleted = 0";
    }
    $itemSql .= (string)$scope['scope_where'];
    $itemSql .= " ORDER BY br.created_at DESC, bri.id DESC LIMIT " . (int)$limit;

    $stmtItem = mysqli_prepare($conexion, $itemSql);
    if ($stmtItem) {
        if ((string)$scope['scope_types'] !== '') {
            $types = (string)$scope['scope_types'];
            $params = (array)$scope['scope_params'];
            inbox_bind_stmt_params($stmtItem, $types, $params);
        }
        if (mysqli_stmt_execute($stmtItem)) {
            $res = mysqli_stmt_get_result($stmtItem);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $requestId = (int)($row['request_id'] ?? 0);
                $itemId = (int)($row['item_id'] ?? 0);
                if ($requestId <= 0 || $itemId <= 0) {
                    continue;
                }
                $itemName = trim((string)($row['item_name'] ?? ''));
                if ($itemName === '') {
                    $itemName = 'Item #' . $itemId;
                }
                $threads[] = [
                    'thread_id' => inbox_thread_id('ITEM', $requestId, $itemId),
                    'thread_key' => inbox_thread_id('ITEM', $requestId, $itemId),
                    'thread_type' => 'ITEM',
                    'request_id' => $requestId,
                    'booking_request_id' => $requestId,
                    'item_id' => $itemId,
                    'title' => $itemName . ' - Request #' . $requestId,
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
        mysqli_stmt_close($stmtItem);
    }

    $threads = inbox_enrich_threads_with_meta($conexion, $threads, (string)$scope['reader_role'], (int)$scope['user_id']);
    $totalUnread = 0;
    foreach ($threads as $t) {
        $totalUnread += (int)($t['unread_count'] ?? 0);
    }

    admin_inbox_ok([
        'threads' => $threads,
        'unread_count' => $totalUnread,
    ]);
}

if ($action === 'list_messages' || $action === 'send_message' || $action === 'send_quick_reply' || $action === 'mark_read') {
    $threadIdInput = (string)($_GET['thread_id'] ?? $_POST['thread_id'] ?? '');
    $threadType = (string)($_GET['thread_type'] ?? $_POST['thread_type'] ?? '');
    $requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? $_GET['booking_request_id'] ?? $_POST['booking_request_id'] ?? 0);
    $itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

    $ctx = admin_inbox_resolve_context($conexion, $scope, $threadType, $requestId, $itemId, $threadIdInput);
    if (empty($ctx['ok'])) {
        admin_inbox_err((string)($ctx['message'] ?? 'invalid_thread'), (int)($ctx['status'] ?? 400));
    }
}

if ($action === 'list_messages') {
    $bookingRequestId = (int)($ctx['request_id'] ?? 0);
    $feeLocked = (!empty($bookingRequestId) && empty($scope['is_admin']))
        ? is_booking_fee_required($conexion, $bookingRequestId)
        : false;
    $messages = [];
    if (inbox_table_exists($conexion, 'inbox_messages')) {
        $stmt = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? ORDER BY id ASC");
        if ($stmt) {
            $threadId = (string)$ctx['thread_id'];
            mysqli_stmt_bind_param($stmt, 's', $threadId);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $messages[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'sender' => inbox_sender_to_ui($row['sender_role'] ?? ''),
                        'body' => (string)($row['body'] ?? ''),
                        'time' => (string)($row['created_at'] ?? ''),
                        'thread_type' => (string)$ctx['thread_type'],
                        'thread_item_id' => (int)$ctx['item_id'],
                    ];
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (empty($messages) && trim((string)($ctx['additional_notes'] ?? '')) !== '') {
        $legacy = inbox_parse_legacy_messages((string)$ctx['additional_notes']);
        $legacy = inbox_filter_legacy_messages($legacy, (string)$ctx['thread_type'], (int)$ctx['item_id']);
        foreach ($legacy as $idx => $m) {
            $messages[] = [
                'id' => 'legacy-' . ($idx + 1),
                'sender' => (string)($m['sender'] ?? 'system'),
                'body' => (string)($m['body'] ?? ''),
                'time' => (string)($m['time'] ?? ''),
                'thread_type' => (string)$ctx['thread_type'],
                'thread_item_id' => (int)$ctx['item_id'],
            ];
        }
    }

    admin_inbox_ok([
        'thread_id' => $ctx['thread_id'],
        'thread_type' => $ctx['thread_type'],
        'request_id' => (int)$ctx['request_id'],
        'booking_request_id' => (int)$ctx['request_id'],
        'item_id' => (int)$ctx['item_id'],
        'fee_locked' => $feeLocked ? 1 : 0,
        'messages' => $messages,
    ]);
}

if ($action === 'send_message') {
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        admin_inbox_err('inbox_messages_not_available', 409);
    }
    $bookingRequestId = (int)($ctx['request_id'] ?? 0);
    $feeLocked = (!empty($bookingRequestId) && empty($scope['is_admin']))
        ? is_booking_fee_required($conexion, $bookingRequestId)
        : false;
    if ($feeLocked) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'code' => 'FEE_REQUIRED',
            'error' => 'Coordination Fee required',
        ]);
        exit;
    }
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        admin_inbox_err('message_required', 422);
    }
    if (mb_strlen($message) > 2000) {
        admin_inbox_err('message_too_long', 422);
    }

    $senderRole = (string)$scope['reader_role'];
    $senderUserId = (int)$scope['user_id'];
    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        admin_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    $itemId = (int)$ctx['item_id'];
    mysqli_stmt_bind_param($stmt, 'ssiisis', $threadId, $threadType, $requestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        admin_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    admin_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_request_id' => $requestId,
        'item_id' => $itemId,
        'message' => [
            'id' => $messageId,
            'sender' => inbox_sender_to_ui($senderRole),
            'body' => $message,
            'time' => date('Y-m-d H:i:s'),
        ],
    ]);
}

if ($action === 'send_quick_reply') {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        admin_inbox_err('method_not_allowed', 405);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        admin_inbox_err('inbox_messages_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        admin_inbox_err('invalid_thread_type', 422);
    }

    $key = strtoupper(trim((string)($_POST['reply_key'] ?? '')));
    $quickReplies = [
        'DATES_AVAILABLE' => 'Dates available',
        'DATES_NOT_AVAILABLE' => 'Dates not available',
        'REQUEST_MEDICAL_HISTORY' => 'REQUEST HISTORY',
        'REQUEST_LABS' => 'REQUEST LABS',
        'REQUEST_IMAGING' => 'REQUEST IMAGING',
        'REQUEST_PHOTOS' => 'REQUEST PHOTOS'
    ];
    if ($key === '' || !isset($quickReplies[$key])) {
        admin_inbox_err('invalid_reply_key', 422);
    }

    $message = '[REPLY] ' . $quickReplies[$key];
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    $itemId = (int)$ctx['item_id'];
    $senderRole = (string)$scope['reader_role'];
    $senderUserId = (int)$scope['user_id'];

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        admin_inbox_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param($stmt, 'ssiisis', $threadId, $threadType, $requestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        admin_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    admin_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_request_id' => $requestId,
        'item_id' => $itemId,
        'message' => [
            'id' => $messageId,
            'sender' => inbox_sender_to_ui($senderRole),
            'body' => $message,
            'time' => date('Y-m-d H:i:s'),
        ],
    ]);
}

if ($action === 'mark_read') {
    if (!inbox_table_exists($conexion, 'inbox_thread_reads') || !inbox_table_exists($conexion, 'inbox_messages')) {
        admin_inbox_err('inbox_read_state_not_available', 409);
    }

    $threadId = (string)$ctx['thread_id'];
    $maxId = 0;
    $stmtMax = mysqli_prepare($conexion, "SELECT COALESCE(MAX(id), 0) AS max_id FROM inbox_messages WHERE thread_id = ?");
    if ($stmtMax) {
        mysqli_stmt_bind_param($stmtMax, 's', $threadId);
        if (mysqli_stmt_execute($stmtMax)) {
            $resMax = mysqli_stmt_get_result($stmtMax);
            $rowMax = $resMax ? mysqli_fetch_assoc($resMax) : null;
            $maxId = (int)($rowMax['max_id'] ?? 0);
        }
        mysqli_stmt_close($stmtMax);
    }

    $readerRole = (string)$scope['reader_role'];
    $readerUserId = (int)$scope['user_id'];
    $upsert = "INSERT INTO inbox_thread_reads (thread_id, reader_role, reader_user_id, last_read_message_id, last_read_at)
               VALUES (?, ?, ?, ?, NOW())
               ON DUPLICATE KEY UPDATE
                 last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), VALUES(last_read_message_id)),
                 last_read_at = NOW()";
    $stmt = mysqli_prepare($conexion, $upsert);
    if (!$stmt) {
        admin_inbox_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param($stmt, 'ssii', $threadId, $readerRole, $readerUserId, $maxId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        admin_inbox_err('mark_read_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmt);

    admin_inbox_ok([
        'thread_id' => $threadId,
        'last_read_message_id' => $maxId,
    ]);
}

admin_inbox_err('invalid_action', 400);
