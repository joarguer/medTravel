<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../../inc/inbox_utils.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

function admin_notif_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

if (!isset($conexion) || !$conexion) {
    admin_notif_err('db_not_available', 500);
}

if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
    admin_notif_err('forbidden', 403);
}

$providerId = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$serviceProviderId = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;
$isAdmin = is_role_admin_session();
$userId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
$roleId = current_role_id();

if (!$isAdmin && $providerId <= 0 && $serviceProviderId <= 0) {
    admin_notif_err('forbidden', 403);
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

$readerRole = 'PROVIDER';
if ($isAdmin) {
    $readerRole = ((int)$roleId === (int)ROLE_ADMINISTRATIVE) ? 'PATIENTCARE' : 'ADMIN';
}

$payload = ['count' => 0, 'items' => []];
if (inbox_table_exists($conexion, 'inbox_messages') && inbox_table_exists($conexion, 'inbox_thread_reads') && inbox_table_exists($conexion, 'booking_request_items') && inbox_table_exists($conexion, 'booking_requests')) {
    $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $threads = [];
    $limit = 250;

    if ($isAdmin) {
        $careSql = "SELECT id AS request_id, destination, created_at
                    FROM booking_requests
                    WHERE 1=1";
        if ($hasRequestsSoftDelete) {
            $careSql .= " AND is_deleted = 0";
        }
        $careSql .= " ORDER BY created_at DESC LIMIT " . $limit;
        $careRes = mysqli_query($conexion, $careSql);
        if ($careRes) {
            while ($row = mysqli_fetch_assoc($careRes)) {
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
    $itemSql .= $scopeWhere;
    $itemSql .= " ORDER BY br.created_at DESC, bri.id DESC LIMIT " . $limit;

    $stmtItem = mysqli_prepare($conexion, $itemSql);
    if ($stmtItem) {
        if ($scopeTypes !== '') {
            $types = $scopeTypes;
            $params = $scopeParams;
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

    $threads = inbox_enrich_threads_with_meta($conexion, $threads, $readerRole, $userId);
    $payload = inbox_build_notifications_payload($threads, '/admin/app_inbox.php', 12);
}

echo json_encode([
    'ok' => true,
    'count' => (int)($payload['count'] ?? 0),
    'items' => is_array($payload['items'] ?? null) ? $payload['items'] : [],
]);
