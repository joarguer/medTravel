<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';
require_once __DIR__ . '/../../inc/inbox_utils.php';

$clientUserId = get_client_user_id();
$payload = ['count' => 0, 'items' => []];

if (isset($conexion) && $conexion && inbox_table_exists($conexion, 'inbox_messages') && inbox_table_exists($conexion, 'inbox_thread_reads')) {
    $ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
    if ($ownerScope['sql'] !== '1=0') {
        $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
        $threads = [];
        $limit = 200;

        $careSql = "SELECT br.id AS request_id, br.destination, br.created_at
                    FROM booking_requests br
                    WHERE " . $ownerScope['sql'];
        if ($hasBookingSoftDelete) {
            $careSql .= " AND br.is_deleted = 0";
        }
        $careSql .= " ORDER BY br.created_at DESC LIMIT " . $limit;

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
                        'thread_type' => 'CARE',
                        'request_id' => $requestId,
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
            $itemSql .= " ORDER BY br.created_at DESC, bri.id DESC LIMIT " . $limit;

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
                            'thread_type' => 'ITEM',
                            'request_id' => $requestId,
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
        $payload = inbox_build_notifications_payload($threads, '/client/app_inbox.php', 12);
    }
}

echo json_encode([
    'ok' => true,
    'count' => (int)($payload['count'] ?? 0),
    'items' => is_array($payload['items'] ?? null) ? $payload['items'] : [],
]);
