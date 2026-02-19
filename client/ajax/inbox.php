<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';
require_once __DIR__ . '/../../inc/inbox_utils.php';

function client_inbox_err($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function client_inbox_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function client_inbox_resolve_context($conexion, $ownerScope, $threadType, $requestId, $itemId, $threadIdInput)
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

    if (!inbox_table_exists($conexion, 'booking_requests')) {
        return ['ok' => false, 'message' => 'booking_requests_not_available', 'status' => 409];
    }

    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasAdditionalNotes = client_table_has_column($conexion, 'booking_requests', 'additional_notes');

    if ($threadType === 'CARE') {
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'invalid_request_id', 'status' => 422];
        }
        $sql = "SELECT br.id AS request_id, br.destination";
        $sql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
        $sql .= " FROM booking_requests br WHERE br.id = ? AND (" . $ownerScope['sql'] . ")";
        if ($hasBookingSoftDelete) {
            $sql .= " AND br.is_deleted = 0";
        }
        $sql .= " LIMIT 1";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'prepare_failed', 'status' => 500];
        }
        $types = 'i' . $ownerScope['types'];
        $params = array_merge([$requestId], $ownerScope['params']);
        if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'message' => 'execute_failed', 'status' => 500];
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'message' => 'forbidden_or_not_found', 'status' => 404];
        }
        return [
            'ok' => true,
            'thread_id' => inbox_thread_id('CARE', $requestId, 0),
            'thread_type' => 'CARE',
            'request_id' => $requestId,
            'item_id' => 0,
            'destination' => (string)($row['destination'] ?? ''),
            'additional_notes' => (string)($row['additional_notes'] ?? ''),
        ];
    }

    if ($itemId <= 0) {
        return ['ok' => false, 'message' => 'invalid_item_id', 'status' => 422];
    }
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        return ['ok' => false, 'message' => 'booking_items_not_available', 'status' => 409];
    }
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');

    $sql = "SELECT bri.id AS item_id, bri.booking_request_id AS request_id, br.destination";
    $sql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
    $sql .= " FROM booking_request_items bri
             INNER JOIN booking_requests br ON br.id = bri.booking_request_id
             WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    if ($hasBookingSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'message' => 'prepare_failed', 'status' => 500];
    }
    $types = 'i' . $ownerScope['types'];
    $params = array_merge([$itemId], $ownerScope['params']);
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'message' => 'execute_failed', 'status' => 500];
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return ['ok' => false, 'message' => 'forbidden_or_not_found', 'status' => 404];
    }

    $requestId = (int)($row['request_id'] ?? 0);
    return [
        'ok' => true,
        'thread_id' => inbox_thread_id('ITEM', $requestId, $itemId),
        'thread_type' => 'ITEM',
        'request_id' => $requestId,
        'item_id' => $itemId,
        'destination' => (string)($row['destination'] ?? ''),
        'additional_notes' => (string)($row['additional_notes'] ?? ''),
    ];
}

$clientUserId = get_client_user_id();
if (!isset($conexion) || !$conexion) {
    client_inbox_err('db_not_available', 500);
}

$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    client_inbox_err('booking_owner_scope_unavailable', 409);
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
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $threads = [];

    $careSql = "SELECT br.id AS request_id,
                       br.destination,
                       br.created_at
                FROM booking_requests br
                WHERE " . $ownerScope['sql'];
    if ($hasBookingSoftDelete) {
        $careSql .= " AND br.is_deleted = 0";
    }
    $careSql .= " ORDER BY br.created_at DESC LIMIT " . (int)$limit;
    $stmtCare = mysqli_prepare($conexion, $careSql);
    if ($stmtCare) {
        $types = $ownerScope['types'];
        $params = $ownerScope['params'];
        if (inbox_bind_stmt_params($stmtCare, $types, $params) && mysqli_stmt_execute($stmtCare)) {
            $res = mysqli_stmt_get_result($stmtCare);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $requestId = (int)($row['request_id'] ?? 0);
                if ($requestId <= 0) {
                    continue;
                }
                $threads[] = [
                    'thread_id' => inbox_thread_id('CARE', $requestId, 0),
                    'thread_key' => inbox_thread_id('CARE', $requestId, 0),
                    'thread_type' => 'CARE',
                    'request_id' => $requestId,
                    'booking_id' => $requestId,
                    'item_id' => 0,
                    'title' => 'General - Request #' . $requestId,
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
        mysqli_stmt_close($stmtCare);
    }

    if (inbox_table_exists($conexion, 'booking_request_items')) {
        $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
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
                    WHERE " . $ownerScope['sql'];
        if ($hasItemsSoftDelete) {
            $itemSql .= " AND bri.is_deleted = 0";
        }
        if ($hasBookingSoftDelete) {
            $itemSql .= " AND br.is_deleted = 0";
        }
        $itemSql .= " ORDER BY br.created_at DESC, bri.id DESC LIMIT " . (int)$limit;

        $stmtItem = mysqli_prepare($conexion, $itemSql);
        if ($stmtItem) {
            $types = $ownerScope['types'];
            $params = $ownerScope['params'];
            if (inbox_bind_stmt_params($stmtItem, $types, $params) && mysqli_stmt_execute($stmtItem)) {
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
                        'booking_id' => $requestId,
                        'item_id' => $itemId,
                        'title' => $itemName . ' - Request #' . $requestId,
                        'subtitle' => trim((string)($row['destination'] ?? '')),
                        'updated_at' => (string)($row['created_at'] ?? ''),
                    ];
                }
            }
            mysqli_stmt_close($stmtItem);
        }
    }

    $threads = inbox_enrich_threads_with_meta($conexion, $threads, 'CLIENT', $clientUserId);
    $totalUnread = 0;
    foreach ($threads as $t) {
        $totalUnread += (int)($t['unread_count'] ?? 0);
    }

    client_inbox_ok([
        'threads' => $threads,
        'unread_count' => $totalUnread,
    ]);
}

if ($action === 'list_messages' || $action === 'mark_read' || $action === 'send_message') {
    $threadIdInput = (string)($_GET['thread_id'] ?? $_POST['thread_id'] ?? '');
    $threadType = (string)($_GET['thread_type'] ?? $_POST['thread_type'] ?? '');
    $requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? $_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
    $itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

    $ctx = client_inbox_resolve_context($conexion, $ownerScope, $threadType, $requestId, $itemId, $threadIdInput);
    if (empty($ctx['ok'])) {
        client_inbox_err((string)($ctx['message'] ?? 'invalid_thread'), (int)($ctx['status'] ?? 400));
    }
}

if ($action === 'list_messages') {
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

    client_inbox_ok([
        'thread_id' => $ctx['thread_id'],
        'thread_type' => $ctx['thread_type'],
        'request_id' => (int)$ctx['request_id'],
        'booking_id' => (int)$ctx['request_id'],
        'item_id' => (int)$ctx['item_id'],
        'messages' => $messages,
    ]);
}

if ($action === 'send_message') {
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        client_inbox_err('message_required', 422);
    }
    if (mb_strlen($message) > 2000) {
        client_inbox_err('message_too_long', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmt) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    $itemId = (int)$ctx['item_id'];
    mysqli_stmt_bind_param($stmt, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    client_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_id' => $requestId,
        'item_id' => $itemId,
        'message' => [
            'id' => $messageId,
            'sender' => 'client',
            'body' => $message,
            'time' => date('Y-m-d H:i:s'),
        ],
    ]);
}

if ($action === 'mark_read') {
    if (!inbox_table_exists($conexion, 'inbox_thread_reads') || !inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_read_state_not_available', 409);
    }

    $maxId = 0;
    $stmtMax = mysqli_prepare($conexion, "SELECT COALESCE(MAX(id), 0) AS max_id FROM inbox_messages WHERE thread_id = ?");
    if ($stmtMax) {
        $threadId = (string)$ctx['thread_id'];
        mysqli_stmt_bind_param($stmtMax, 's', $threadId);
        if (mysqli_stmt_execute($stmtMax)) {
            $resMax = mysqli_stmt_get_result($stmtMax);
            $rowMax = $resMax ? mysqli_fetch_assoc($resMax) : null;
            $maxId = (int)($rowMax['max_id'] ?? 0);
        }
        mysqli_stmt_close($stmtMax);
    }

    $upsert = "INSERT INTO inbox_thread_reads (thread_id, reader_role, reader_user_id, last_read_message_id, last_read_at)
               VALUES (?, 'CLIENT', ?, ?, NOW())
               ON DUPLICATE KEY UPDATE
                 last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), VALUES(last_read_message_id)),
                 last_read_at = NOW()";
    $stmtUpsert = mysqli_prepare($conexion, $upsert);
    if (!$stmtUpsert) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    mysqli_stmt_bind_param($stmtUpsert, 'sii', $threadId, $clientUserId, $maxId);
    if (!mysqli_stmt_execute($stmtUpsert)) {
        $err = mysqli_stmt_error($stmtUpsert);
        mysqli_stmt_close($stmtUpsert);
        client_inbox_err('mark_read_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmtUpsert);

    client_inbox_ok([
        'thread_id' => $threadId,
        'last_read_message_id' => $maxId,
    ]);
}

client_inbox_err('invalid_action', 400);
