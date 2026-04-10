<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';

$clientUserId = get_client_user_id();
$data = [];

if (!isset($conexion) || !$conexion || !client_table_exists($conexion, 'booking_requests')) {
    echo json_encode(['ok' => true, 'data' => []]);
    exit;
}
$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    echo json_encode(['ok' => true, 'data' => []]);
    exit;
}

$hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasTimeline = client_table_has_column($conexion, 'booking_requests', 'timeline');
$hasStatus = client_table_has_column($conexion, 'booking_requests', 'status');

$bookingSql = "SELECT br.id, br.created_at, br.destination";
$bookingSql .= $hasTimeline ? ", br.timeline" : ", '' AS timeline";
$bookingSql .= $hasStatus ? ", br.status" : ", 'pending' AS status";
$bookingSql .= " FROM booking_requests br WHERE " . $ownerScope['sql'];
if ($hasBookingSoftDelete) {
    $bookingSql .= " AND br.is_deleted = 0";
}
$bookingSql .= " ORDER BY br.created_at DESC";

$bookings = [];
$bookingIds = [];
$stmtBookings = mysqli_prepare($conexion, $bookingSql);
if ($stmtBookings) {
    $bookingTypes = $ownerScope['types'];
    $bookingParams = $ownerScope['params'];
    if (client_bind_params($stmtBookings, $bookingTypes, $bookingParams) && mysqli_stmt_execute($stmtBookings)) {
        $res = mysqli_stmt_get_result($stmtBookings);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)$row['id'];
            $bookingIds[] = $id;
            $bookings[$id] = [
                'id' => $id,
                'created_at' => (string)($row['created_at'] ?? ''),
                'destination' => (string)($row['destination'] ?? ''),
                'timeline' => (string)($row['timeline'] ?? ''),
                'status' => client_status_label($row['status'] ?? ''),
                'items' => [],
                'item_statuses' => [],
                'last_update' => (string)($row['created_at'] ?? ''),
            ];
        }
    }
    mysqli_stmt_close($stmtBookings);
}

if (!empty($bookingIds) && client_table_exists($conexion, 'booking_request_items')) {
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasItemStatus = client_table_has_column($conexion, 'booking_request_items', 'item_status');
    $hasItemType = client_table_has_column($conexion, 'booking_request_items', 'item_type');
    $hasOfferId = client_table_has_column($conexion, 'booking_request_items', 'offer_id');
    $hasServiceId = client_table_has_column($conexion, 'booking_request_items', 'medtravel_service_id');
    $hasUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');
    $hasCreatedAt = client_table_has_column($conexion, 'booking_request_items', 'created_at');

    $in = implode(',', array_map('intval', $bookingIds));
    $eventExpr = "'0000-00-00 00:00:00'";
    if ($hasUpdatedAt && $hasCreatedAt) {
        $eventExpr = 'COALESCE(bri.updated_at, bri.created_at)';
    } elseif ($hasUpdatedAt) {
        $eventExpr = 'bri.updated_at';
    } elseif ($hasCreatedAt) {
        $eventExpr = 'bri.created_at';
    }

    $itemNameExpr = "CONCAT('Item #', bri.id)";
    if ($hasItemType && $hasOfferId && $hasServiceId) {
        $itemNameExpr = "CASE
            WHEN bri.item_type = 'medical_offer' THEN CONCAT('Medical offer #', COALESCE(bri.offer_id, bri.id))
            WHEN bri.item_type = 'complementary_service' THEN CONCAT('Complementary service #', COALESCE(bri.medtravel_service_id, bri.id))
            ELSE CONCAT('Item #', bri.id)
        END";
    }

    $statusExpr = $hasItemStatus
        ? "CASE WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin','pending_review') THEN 'pending_provider' ELSE bri.item_status END"
        : "'pending_provider'";

    $itemsSql = "SELECT bri.booking_request_id, {$itemNameExpr} AS item_name, {$statusExpr} AS item_status, {$eventExpr} AS item_event_at
                 FROM booking_request_items bri
                 WHERE bri.booking_request_id IN ({$in})";
    if ($hasItemsSoftDelete) {
        $itemsSql .= " AND bri.is_deleted = 0";
    }
    $itemsSql .= " ORDER BY bri.id ASC";

    $itemsRes = mysqli_query($conexion, $itemsSql);
    if ($itemsRes) {
        while ($itemRow = mysqli_fetch_assoc($itemsRes)) {
            $bookingId = (int)$itemRow['booking_request_id'];
            if (!isset($bookings[$bookingId])) {
                continue;
            }
            $itemName = trim((string)($itemRow['item_name'] ?? ''));
            $itemStatus = client_status_label($itemRow['item_status'] ?? '');
            $itemEventAt = trim((string)($itemRow['item_event_at'] ?? ''));
            if ($itemName !== '') {
                $bookings[$bookingId]['items'][] = $itemName;
            }
            if ($itemStatus !== '') {
                $bookings[$bookingId]['item_statuses'][] = $itemStatus;
            }
            if ($itemEventAt !== '' && strtotime($itemEventAt) > strtotime((string)$bookings[$bookingId]['last_update'])) {
                $bookings[$bookingId]['last_update'] = $itemEventAt;
            }
        }
    }
}

foreach ($bookings as $booking) {
    $serviceSummary = 'No itemized services';
    if (!empty($booking['items'])) {
        $uniqueItems = array_values(array_unique($booking['items']));
        $serviceSummary = $uniqueItems[0];
        if (count($uniqueItems) > 1) {
            $serviceSummary .= ' (+' . (count($uniqueItems) - 1) . ' more)';
        }
    }

    $statusSummary = $booking['status'];
    if (!empty($booking['item_statuses'])) {
        $statuses = array_values(array_filter(array_map('client_status_label', (array)$booking['item_statuses'])));
        if (in_array('provider_rejected', $statuses, true)) {
            $statusSummary = 'provider_rejected';
        } elseif (in_array('provider_proposed_change', $statuses, true) || in_array('awaiting_client', $statuses, true)) {
            $statusSummary = 'awaiting_client';
        } elseif (in_array('pending_provider', $statuses, true)) {
            $statusSummary = 'pending_provider';
        } elseif (count(array_diff($statuses, ['provider_confirmed', 'client_accepted'])) === 0) {
            $statusSummary = 'provider_confirmed';
        } else {
            $statusSummary = $statuses[0];
        }
    }

    $data[] = [
        'id' => (int)$booking['id'],
        'created_at' => (string)$booking['created_at'],
        'destination' => (string)$booking['destination'],
        'timeline' => (string)$booking['timeline'],
        'service' => $serviceSummary,
        'status' => $statusSummary,
        'last_update' => (string)$booking['last_update'],
        'view_url' => '/client/request_detail.php?id=' . (int)$booking['id'],
    ];
}

echo json_encode(['ok' => true, 'data' => $data]);
