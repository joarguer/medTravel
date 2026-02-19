<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';

function client_messages_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function parse_client_note_messages($additionalNotes)
{
    $messages = [];
    $notes = trim((string)$additionalNotes);
    if ($notes === '') {
        return $messages;
    }

    $lines = preg_split('/\R+/', $notes);
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }

        if (!preg_match('/^\[(CLIENT_MESSAGE|PROVIDER_MESSAGE)\]\[(.*?)\](?:\[(.*?)\])?\s*(.*)$/', $line, $m)) {
            continue;
        }

        $senderType = strtoupper((string)$m[1]);
        $actorRaw = isset($m[3]) ? trim((string)$m[3]) : '';
        $threadType = 'CARE';
        $threadItemId = 0;

        if ($actorRaw !== '') {
            if (preg_match('/(?:^|\|)THREAD:ITEM:(\d+)/i', $actorRaw, $scopeMatch)) {
                $threadType = 'ITEM';
                $threadItemId = (int)$scopeMatch[1];
            } elseif (preg_match('/(?:^|\|)THREAD:CARE(?:\||$)/i', $actorRaw)) {
                $threadType = 'CARE';
            }
        }

        $messages[] = [
            'sender' => ($senderType === 'PROVIDER_MESSAGE') ? 'provider' : 'client',
            'type' => strtolower($senderType),
            'body' => trim((string)$m[4]),
            'time' => trim((string)$m[2]),
            'thread_type' => $threadType,
            'thread_item_id' => $threadItemId,
        ];
    }

    return $messages;
}

$clientUserId = get_client_user_id();
$mode = trim((string)($_GET['mode'] ?? $_POST['mode'] ?? ''));

if (!isset($conexion) || !$conexion || !client_table_exists($conexion, 'booking_requests')) {
    client_messages_error('booking_requests_not_available', 409);
}

$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    client_messages_error('booking_owner_scope_unavailable', 409);
}

$hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasAdditionalNotes = client_table_has_column($conexion, 'booking_requests', 'additional_notes');
$hasSpecialRequest = client_table_has_column($conexion, 'booking_requests', 'special_request');
$hasBookingUpdatedAt = client_table_has_column($conexion, 'booking_requests', 'updated_at');

if ($mode === 'threads') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 200);
    if ($limit < 1) {
        $limit = 200;
    }
    if ($limit > 500) {
        $limit = 500;
    }

    $threads = [];

    $bookingSql = "SELECT br.id,
                          br.destination,
                          br.created_at,
                          " . ($hasBookingUpdatedAt ? 'COALESCE(br.updated_at, br.created_at)' : 'br.created_at') . " AS thread_updated_at
                   FROM booking_requests br
                   WHERE " . $ownerScope['sql'];
    if ($hasBookingSoftDelete) {
        $bookingSql .= " AND br.is_deleted = 0";
    }
    $bookingSql .= " ORDER BY thread_updated_at DESC LIMIT " . (int)$limit;

    $stmtBooking = mysqli_prepare($conexion, $bookingSql);
    if (!$stmtBooking) {
        client_messages_error('prepare_failed', 500);
    }
    $bookingTypes = $ownerScope['types'];
    $bookingParams = $ownerScope['params'];
    if (!client_bind_params($stmtBooking, $bookingTypes, $bookingParams) || !mysqli_stmt_execute($stmtBooking)) {
        mysqli_stmt_close($stmtBooking);
        client_messages_error('execute_failed', 500);
    }
    $bookingRes = mysqli_stmt_get_result($stmtBooking);
    while ($bookingRes && ($row = mysqli_fetch_assoc($bookingRes))) {
        $bookingId = (int)($row['id'] ?? 0);
        if ($bookingId <= 0) {
            continue;
        }
        $threads[] = [
            'thread_key' => 'CARE:' . $bookingId,
            'thread_type' => 'CARE',
            'booking_id' => $bookingId,
            'item_id' => 0,
            'title' => 'General - Request #' . $bookingId,
            'subtitle' => trim((string)($row['destination'] ?? '')),
            'updated_at' => (string)($row['thread_updated_at'] ?? $row['created_at'] ?? ''),
        ];
    }
    mysqli_stmt_close($stmtBooking);

    if (client_table_exists($conexion, 'booking_request_items')) {
        $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasItemCreatedAt = client_table_has_column($conexion, 'booking_request_items', 'created_at');
        $hasItemUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');
        $hasProviderResponseAt = client_table_has_column($conexion, 'booking_request_items', 'provider_response_at');

        $itemTimeExpr = 'br.created_at';
        if ($hasProviderResponseAt && $hasItemUpdatedAt && $hasItemCreatedAt) {
            $itemTimeExpr = 'COALESCE(bri.provider_response_at, bri.updated_at, bri.created_at, br.created_at)';
        } elseif ($hasItemUpdatedAt && $hasItemCreatedAt) {
            $itemTimeExpr = 'COALESCE(bri.updated_at, bri.created_at, br.created_at)';
        } elseif ($hasItemCreatedAt) {
            $itemTimeExpr = 'COALESCE(bri.created_at, br.created_at)';
        }

        $itemSql = "SELECT bri.id AS item_id,
                           bri.booking_request_id,
                           COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                           br.destination,
                           {$itemTimeExpr} AS thread_updated_at
                    FROM booking_request_items bri
                    INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                    LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                    LEFT JOIN service_catalog sc ON sc.id = o.service_id
                    LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                    WHERE " . $ownerScope['sql'];
        if ($hasBookingSoftDelete) {
            $itemSql .= " AND br.is_deleted = 0";
        }
        if ($hasItemsSoftDelete) {
            $itemSql .= " AND bri.is_deleted = 0";
        }
        $itemSql .= " ORDER BY thread_updated_at DESC, bri.id DESC LIMIT " . (int)$limit;

        $stmtItems = mysqli_prepare($conexion, $itemSql);
        if ($stmtItems) {
            $itemTypes = $ownerScope['types'];
            $itemParams = $ownerScope['params'];
            if (client_bind_params($stmtItems, $itemTypes, $itemParams) && mysqli_stmt_execute($stmtItems)) {
                $resItems = mysqli_stmt_get_result($stmtItems);
                while ($resItems && ($row = mysqli_fetch_assoc($resItems))) {
                    $itemId = (int)($row['item_id'] ?? 0);
                    $bookingId = (int)($row['booking_request_id'] ?? 0);
                    if ($itemId <= 0 || $bookingId <= 0) {
                        continue;
                    }
                    $itemName = trim((string)($row['item_name'] ?? ''));
                    if ($itemName === '') {
                        $itemName = 'Item #' . $itemId;
                    }
                    $threads[] = [
                        'thread_key' => 'ITEM:' . $itemId,
                        'thread_type' => 'ITEM',
                        'booking_id' => $bookingId,
                        'item_id' => $itemId,
                        'title' => $itemName . ' - Request #' . $bookingId,
                        'subtitle' => trim((string)($row['destination'] ?? '')),
                        'updated_at' => (string)($row['thread_updated_at'] ?? ''),
                    ];
                }
            }
            mysqli_stmt_close($stmtItems);
        }
    }

    usort($threads, function ($a, $b) {
        $ta = strtotime((string)($a['updated_at'] ?? ''));
        $tb = strtotime((string)($b['updated_at'] ?? ''));
        if ($ta === $tb) {
            return strcmp((string)($a['thread_key'] ?? ''), (string)($b['thread_key'] ?? ''));
        }
        return ($ta > $tb) ? -1 : 1;
    });

    echo json_encode([
        'ok' => true,
        'threads' => $threads,
    ]);
    exit;
}

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0);
if ($bookingId <= 0) {
    client_messages_error('invalid_booking_id');
}

$threadType = strtoupper(trim((string)($_GET['thread_type'] ?? $_POST['thread_type'] ?? '')));
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : (isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0);
$legacyMode = ($threadType === '');
if ($threadType === '') {
    $threadType = 'CARE';
}
if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
    client_messages_error('invalid_thread_type', 422);
}

$bookingSql = "SELECT br.id";
$bookingSql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
$bookingSql .= $hasSpecialRequest ? ", br.special_request" : ", '' AS special_request";
$bookingSql .= " FROM booking_requests br WHERE br.id = ? AND (" . $ownerScope['sql'] . ")";
if ($hasBookingSoftDelete) {
    $bookingSql .= " AND br.is_deleted = 0";
}
$bookingSql .= " LIMIT 1";

$stmtBooking = mysqli_prepare($conexion, $bookingSql);
if (!$stmtBooking) {
    client_messages_error('prepare_failed', 500);
}
$bookingTypes = 'i' . $ownerScope['types'];
$bookingParams = array_merge([$bookingId], $ownerScope['params']);
if (!client_bind_params($stmtBooking, $bookingTypes, $bookingParams) || !mysqli_stmt_execute($stmtBooking)) {
    mysqli_stmt_close($stmtBooking);
    client_messages_error('execute_failed', 500);
}
$bookingRes = mysqli_stmt_get_result($stmtBooking);
$booking = $bookingRes ? mysqli_fetch_assoc($bookingRes) : null;
mysqli_stmt_close($stmtBooking);

if (!$booking) {
    client_messages_error('request_not_found', 404);
}

if ($threadType === 'ITEM') {
    if ($itemId <= 0) {
        client_messages_error('invalid_item_id', 422);
    }
    if (!client_table_exists($conexion, 'booking_request_items')) {
        client_messages_error('booking_items_not_available', 409);
    }
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $itemCheckSql = "SELECT bri.id
                     FROM booking_request_items bri
                     INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                     WHERE bri.id = ? AND bri.booking_request_id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $itemCheckSql .= " AND bri.is_deleted = 0";
    }
    if ($hasBookingSoftDelete) {
        $itemCheckSql .= " AND br.is_deleted = 0";
    }
    $itemCheckSql .= " LIMIT 1";

    $stmtItemCheck = mysqli_prepare($conexion, $itemCheckSql);
    if (!$stmtItemCheck) {
        client_messages_error('prepare_failed', 500);
    }
    $itemTypes = 'ii' . $ownerScope['types'];
    $itemParams = array_merge([$itemId, $bookingId], $ownerScope['params']);
    if (!client_bind_params($stmtItemCheck, $itemTypes, $itemParams) || !mysqli_stmt_execute($stmtItemCheck)) {
        mysqli_stmt_close($stmtItemCheck);
        client_messages_error('execute_failed', 500);
    }
    $itemRes = mysqli_stmt_get_result($stmtItemCheck);
    $itemRow = $itemRes ? mysqli_fetch_assoc($itemRes) : null;
    mysqli_stmt_close($stmtItemCheck);
    if (!$itemRow) {
        client_messages_error('request_not_found', 404);
    }
}

$messages = [];
$specialRequest = trim((string)($booking['special_request'] ?? ''));
if ($legacyMode && $specialRequest !== '') {
    $messages[] = [
        'sender' => 'client',
        'type' => 'initial_request',
        'body' => $specialRequest,
        'time' => '',
        'thread_type' => 'CARE',
        'thread_item_id' => 0,
    ];
}

$parsed = parse_client_note_messages((string)($booking['additional_notes'] ?? ''));
foreach ($parsed as $m) {
    $mThreadType = strtoupper((string)($m['thread_type'] ?? 'CARE'));
    $mItemId = (int)($m['thread_item_id'] ?? 0);

    if ($legacyMode) {
        $messages[] = $m;
        continue;
    }

    if ($threadType === 'CARE') {
        if ($mThreadType !== 'ITEM') {
            $messages[] = $m;
        }
    } elseif ($mThreadType === 'ITEM' && $mItemId === $itemId) {
        $messages[] = $m;
    }
}

if (client_table_exists($conexion, 'booking_request_items')) {
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasProviderNotes = client_table_has_column($conexion, 'booking_request_items', 'provider_notes');
    $hasRejectReason = client_table_has_column($conexion, 'booking_request_items', 'provider_reject_reason');
    $hasItemStatus = client_table_has_column($conexion, 'booking_request_items', 'item_status');
    $hasResponseAt = client_table_has_column($conexion, 'booking_request_items', 'provider_response_at');
    $hasUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');
    $hasCreatedAt = client_table_has_column($conexion, 'booking_request_items', 'created_at');

    if ($hasProviderNotes || $hasRejectReason || $hasItemStatus) {
        $eventExpr = "''";
        if ($hasResponseAt && $hasUpdatedAt && $hasCreatedAt) {
            $eventExpr = 'COALESCE(provider_response_at, updated_at, created_at)';
        } elseif ($hasUpdatedAt && $hasCreatedAt) {
            $eventExpr = 'COALESCE(updated_at, created_at)';
        } elseif ($hasCreatedAt) {
            $eventExpr = 'created_at';
        }

        $sql = "SELECT id";
        $sql .= $hasProviderNotes ? ", provider_notes" : ", '' AS provider_notes";
        $sql .= $hasRejectReason ? ", provider_reject_reason" : ", '' AS provider_reject_reason";
        $sql .= $hasItemStatus ? ", item_status" : ", '' AS item_status";
        $sql .= ", {$eventExpr} AS event_at";
        $sql .= " FROM booking_request_items WHERE booking_request_id = ?";
        if ($threadType === 'ITEM' && !$legacyMode) {
            $sql .= " AND id = ?";
        }
        if ($hasItemsSoftDelete) {
            $sql .= " AND is_deleted = 0";
        }
        $sql .= " ORDER BY id ASC";

        $stmtItems = mysqli_prepare($conexion, $sql);
        if ($stmtItems) {
            if ($threadType === 'ITEM' && !$legacyMode) {
                mysqli_stmt_bind_param($stmtItems, 'ii', $bookingId, $itemId);
            } else {
                mysqli_stmt_bind_param($stmtItems, 'i', $bookingId);
            }
            if (mysqli_stmt_execute($stmtItems)) {
                $resItems = mysqli_stmt_get_result($stmtItems);
                while ($resItems && ($row = mysqli_fetch_assoc($resItems))) {
                    $rowItemId = (int)($row['id'] ?? 0);
                    $providerNotes = trim((string)($row['provider_notes'] ?? ''));
                    $rejectReason = trim((string)($row['provider_reject_reason'] ?? ''));
                    $status = client_status_label($row['item_status'] ?? '');
                    $eventAt = trim((string)($row['event_at'] ?? ''));

                    if ($providerNotes !== '') {
                        $messages[] = [
                            'sender' => 'provider',
                            'type' => 'provider_note',
                            'body' => $providerNotes,
                            'time' => $eventAt,
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }
                    if ($rejectReason !== '') {
                        $messages[] = [
                            'sender' => 'provider',
                            'type' => 'provider_reject_reason',
                            'body' => 'Rejection reason: ' . $rejectReason,
                            'time' => $eventAt,
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }
                    if ($status !== '' && client_status_is_update($status)) {
                        $messages[] = [
                            'sender' => 'system',
                            'type' => 'status_update',
                            'body' => 'Service status updated to: ' . $status,
                            'time' => $eventAt,
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }
                }
            }
            mysqli_stmt_close($stmtItems);
        }
    }
}

if (!$legacyMode) {
    $filtered = [];
    foreach ($messages as $m) {
        $mThreadType = strtoupper((string)($m['thread_type'] ?? 'CARE'));
        $mItemId = (int)($m['thread_item_id'] ?? 0);
        if ($threadType === 'CARE') {
            if ($mThreadType !== 'ITEM') {
                $filtered[] = $m;
            }
        } elseif ($mThreadType === 'ITEM' && $mItemId === $itemId) {
            $filtered[] = $m;
        }
    }
    $messages = $filtered;
}

usort($messages, function ($a, $b) {
    $ta = strtotime((string)($a['time'] ?? ''));
    $tb = strtotime((string)($b['time'] ?? ''));
    if ($ta === $tb) {
        return 0;
    }
    return ($ta < $tb) ? -1 : 1;
});

echo json_encode([
    'ok' => true,
    'booking_id' => $bookingId,
    'thread_type' => $threadType,
    'item_id' => $threadType === 'ITEM' ? $itemId : 0,
    'messages' => $messages,
]);
