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

function admin_inbox_status_label($status)
{
    $status = trim((string)$status);
    if ($status === '') {
        return 'pending';
    }
    if ($status === 'pending_admin' || $status === 'pending_review') {
        return 'pending_provider';
    }
    return $status;
}

function admin_inbox_status_is_update($status)
{
    $status = admin_inbox_status_label($status);
    return in_array($status, [
        'provider_confirmed',
        'provider_rejected',
        'provider_proposed_change',
        'awaiting_client',
        'client_accepted',
        'client_rejected',
        'cancelled',
    ], true);
}

function admin_inbox_free_message_state($conexion, $bookingRequestId, $scope, $feeLocked)
{
    $bookingRequestId = (int)$bookingRequestId;
    $feeLocked = !empty($feeLocked);
    $isAdmin = !empty($scope['is_admin']);

    $status = 'pending';
    if ($bookingRequestId > 0 && inbox_table_exists($conexion, 'booking_requests') && inbox_table_has_column($conexion, 'booking_requests', 'status')) {
        $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
        $statusSql = "SELECT status FROM booking_requests WHERE id = ?";
        if ($hasRequestsSoftDelete) {
            $statusSql .= " AND is_deleted = 0";
        }
        $statusSql .= " LIMIT 1";

        $stmtStatus = mysqli_prepare($conexion, $statusSql);
        if ($stmtStatus) {
            mysqli_stmt_bind_param($stmtStatus, 'i', $bookingRequestId);
            if (mysqli_stmt_execute($stmtStatus)) {
                $statusRes = mysqli_stmt_get_result($stmtStatus);
                $statusRow = $statusRes ? mysqli_fetch_assoc($statusRes) : null;
                if ($statusRow) {
                    $status = admin_inbox_status_label((string)($statusRow['status'] ?? 'pending'));
                }
            }
            mysqli_stmt_close($stmtStatus);
        }
    }

    $stageAllowsFreeMessage = admin_inbox_status_is_update($status);
    $canSendFreeMessage = $isAdmin ? true : (!$feeLocked && $stageAllowsFreeMessage);
    $reason = '';
    if (!$isAdmin) {
        if ($feeLocked) {
            $reason = 'fee_locked';
        } elseif (!$stageAllowsFreeMessage) {
            $reason = 'initial_review';
        }
    }

    return [
        'booking_status' => $status,
        'stage_allows_free_message' => $stageAllowsFreeMessage,
        'can_send_free_message' => $canSendFreeMessage,
        'blocked_reason' => $reason,
        'notice' => $stageAllowsFreeMessage ? '' : 'Messaging will be available after the initial review. Please use the options above.',
    ];
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

function admin_inbox_decode_payload($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    return $decoded;
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

if ($action === 'list_messages' || $action === 'send_message' || $action === 'send_quick_reply' || $action === 'send_structured_action' || $action === 'mark_read') {
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
    $freeMessageState = admin_inbox_free_message_state($conexion, $bookingRequestId, $scope, $feeLocked);
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

    $documents = [];
    $documentsError = '';
    if (inbox_table_exists($conexion, 'client_documents')) {
        $docHasRequestId = inbox_table_has_column($conexion, 'client_documents', 'booking_request_id');
        $docHasItemId = inbox_table_has_column($conexion, 'client_documents', 'item_id');
        if (!$docHasRequestId || !$docHasItemId) {
            $documentsError = 'client_documents_scope_missing';
        } else {
            $clientId = 0;
            $clientEmail = '';
            if (inbox_table_exists($conexion, 'booking_requests') && $bookingRequestId > 0) {
                $hasBrClientUserId = inbox_table_has_column($conexion, 'booking_requests', 'client_user_id');
                $hasBrEmail = inbox_table_has_column($conexion, 'booking_requests', 'email');
                $selectCols = $hasBrClientUserId ? 'client_user_id' : 'NULL AS client_user_id';
                $selectCols .= $hasBrEmail ? ', email' : ", '' AS email";
                $stmtClient = mysqli_prepare($conexion, "SELECT {$selectCols} FROM booking_requests WHERE id = ? LIMIT 1");
                if ($stmtClient) {
                    mysqli_stmt_bind_param($stmtClient, 'i', $bookingRequestId);
                    if (mysqli_stmt_execute($stmtClient)) {
                        $resClient = mysqli_stmt_get_result($stmtClient);
                        $rowClient = $resClient ? mysqli_fetch_assoc($resClient) : null;
                        if ($rowClient) {
                            $clientId = (int)($rowClient['client_user_id'] ?? 0);
                            $clientEmail = trim((string)($rowClient['email'] ?? ''));
                        }
                    }
                    mysqli_stmt_close($stmtClient);
                }
            }
            if ($clientId <= 0 && $clientEmail !== '' && inbox_table_exists($conexion, 'clientes') && inbox_table_has_column($conexion, 'clientes', 'email')) {
                $hasClientesClientUserId = inbox_table_has_column($conexion, 'clientes', 'client_user_id');
                $clientSelect = $hasClientesClientUserId ? 'client_user_id' : 'id';
                $stmtLookup = mysqli_prepare($conexion, "SELECT {$clientSelect} AS client_user_id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
                if ($stmtLookup) {
                    mysqli_stmt_bind_param($stmtLookup, 's', $clientEmail);
                    if (mysqli_stmt_execute($stmtLookup)) {
                        $resLookup = mysqli_stmt_get_result($stmtLookup);
                        $rowLookup = $resLookup ? mysqli_fetch_assoc($resLookup) : null;
                        if ($rowLookup) {
                            $clientId = (int)($rowLookup['client_user_id'] ?? 0);
                        }
                    }
                    mysqli_stmt_close($stmtLookup);
                }
            }

            if ($clientId > 0) {
                $selectCols = ['id', 'file_path', 'filename', 'original_filename', 'document_type', 'booking_request_id', 'item_id'];
                if (inbox_table_has_column($conexion, 'client_documents', 'file_size')) {
                    $selectCols[] = 'file_size';
                }
                if (inbox_table_has_column($conexion, 'client_documents', 'mime_type')) {
                    $selectCols[] = 'mime_type';
                }
                if (inbox_table_has_column($conexion, 'client_documents', 'title')) {
                    $selectCols[] = 'title';
                }
                if (inbox_table_has_column($conexion, 'client_documents', 'description')) {
                    $selectCols[] = 'description';
                }
                $orderByColumn = inbox_table_has_column($conexion, 'client_documents', 'uploaded_at') ? 'uploaded_at' : 'id';
                $docSql = "SELECT " . implode(', ', $selectCols) . " FROM client_documents WHERE client_id = ?";
                $docTypes = 'i';
                $docParams = [$clientId];
                if (inbox_table_has_column($conexion, 'client_documents', 'shared_with_provider')) {
                    $docSql .= " AND shared_with_provider = 1";
                }
                $docSql .= " AND booking_request_id = ?";
                $docTypes .= 'i';
                $docParams[] = $bookingRequestId;
                if ((int)$ctx['item_id'] > 0) {
                    $docSql .= " AND (item_id = ? OR item_id IS NULL)";
                    $docTypes .= 'i';
                    $docParams[] = (int)$ctx['item_id'];
                }
                $docSql .= " ORDER BY " . $orderByColumn . " DESC";
                $stmtDocs = mysqli_prepare($conexion, $docSql);
                if ($stmtDocs) {
                    if (inbox_bind_stmt_params($stmtDocs, $docTypes, $docParams) && mysqli_stmt_execute($stmtDocs)) {
                        $docRes = mysqli_stmt_get_result($stmtDocs);
                        while ($docRes && ($docRow = mysqli_fetch_assoc($docRes))) {
                            $docRow['download_url'] = '/admin/ajax/download_medical_document.php?doc_id=' . (int)($docRow['id'] ?? 0);
                            $documents[] = $docRow;
                        }
                    }
                    mysqli_stmt_close($stmtDocs);
                }
            }
        }
    }

    admin_inbox_ok([
        'thread_id' => $ctx['thread_id'],
        'thread_type' => $ctx['thread_type'],
        'request_id' => (int)$ctx['request_id'],
        'booking_request_id' => (int)$ctx['request_id'],
        'item_id' => (int)$ctx['item_id'],
        'fee_locked' => $feeLocked ? 1 : 0,
        'can_send_free_message' => !empty($freeMessageState['can_send_free_message']),
        'free_message_blocked_reason' => (string)($freeMessageState['blocked_reason'] ?? ''),
        'free_message_notice' => (string)($freeMessageState['notice'] ?? ''),
        'documents' => $documents,
        'documents_error' => $documentsError,
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
    $freeMessageState = admin_inbox_free_message_state($conexion, $bookingRequestId, $scope, $feeLocked);
    $canSendFreeMessage = !empty($freeMessageState['can_send_free_message']);
    if ($feeLocked) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'code' => 'FEE_REQUIRED',
            'error' => 'Coordination Fee required',
        ]);
        exit;
    }
    if (!$canSendFreeMessage) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'code' => 'FREE_MESSAGE_BLOCKED',
            'error' => 'Messaging is temporarily limited. Please use the options above.',
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
        'REQUEST_PHOTOS' => 'REQUEST PHOTOS',
        'FINAL_APPROVED' => 'FINAL_APPROVED',
        'FINAL_NOT_ELIGIBLE' => 'FINAL_NOT_ELIGIBLE'
    ];
    if ($key === '' || !isset($quickReplies[$key])) {
        admin_inbox_err('invalid_reply_key', 422);
    }

    $finalStatus = null;
    if ($key === 'FINAL_APPROVED') {
        $finalStatus = 'provider_confirmed';
    } elseif ($key === 'FINAL_NOT_ELIGIBLE') {
        $finalStatus = 'provider_rejected';
    }

    if ($finalStatus !== null) {
        if (!inbox_table_exists($conexion, 'booking_request_items')) {
            admin_inbox_err('booking_items_not_available', 409);
        }
        if (!inbox_table_has_column($conexion, 'booking_request_items', 'item_status')) {
            admin_inbox_err('item_status_not_available', 409);
        }

        $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasItemUpdatedAt = inbox_table_has_column($conexion, 'booking_request_items', 'updated_at');
        $hasProviderResponseAt = inbox_table_has_column($conexion, 'booking_request_items', 'provider_response_at');
        $hasProviderResponseBy = inbox_table_has_column($conexion, 'booking_request_items', 'provider_response_by');

        $setParts = ['bri.item_status = ?'];
        $types = 's';
        $params = [$finalStatus];
        if ($hasItemUpdatedAt) {
            $setParts[] = 'bri.updated_at = NOW()';
        }
        if ($hasProviderResponseAt) {
            $setParts[] = 'bri.provider_response_at = NOW()';
        }
        if ($hasProviderResponseBy) {
            $setParts[] = 'bri.provider_response_by = ?';
            $types .= 'i';
            $params[] = (int)$scope['user_id'];
        }

        $sql = "UPDATE booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                SET " . implode(', ', $setParts) . "
                WHERE bri.id = ?";
        $types .= 'i';
        $params[] = (int)$ctx['item_id'];
        if ($hasItemsSoftDelete) {
            $sql .= ' AND bri.is_deleted = 0';
        }
        $sql .= (string)$scope['scope_where'];
        $sql .= ' LIMIT 1';

        $finalTypes = $types . (string)$scope['scope_types'];
        $finalParams = array_merge($params, (array)$scope['scope_params']);

        $stmtUpdate = mysqli_prepare($conexion, $sql);
        if (!$stmtUpdate) {
            admin_inbox_err('prepare_failed', 500);
        }
        if (!inbox_bind_stmt_params($stmtUpdate, $finalTypes, $finalParams) || !mysqli_stmt_execute($stmtUpdate)) {
            $err = mysqli_stmt_error($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);
            admin_inbox_err('update_failed: ' . $err, 500);
        }
        mysqli_stmt_close($stmtUpdate);
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

if ($action === 'send_structured_action') {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        admin_inbox_err('method_not_allowed', 405);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        admin_inbox_err('inbox_messages_not_available', 409);
    }
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        admin_inbox_err('booking_items_not_available', 409);
    }
    if (!inbox_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        admin_inbox_err('item_status_not_available', 409);
    }
    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        admin_inbox_err('invalid_thread_type', 422);
    }

    $actionType = strtoupper(trim((string)($_POST['action_type'] ?? '')));
    $allowedActionTypes = ['REQUEST_ADDITIONAL_INFO', 'PROPOSE_QUOTE_ADJUSTMENT'];
    if (!in_array($actionType, $allowedActionTypes, true)) {
        admin_inbox_err('invalid_action_type', 422);
    }

    $payload = admin_inbox_decode_payload((string)($_POST['payload_json'] ?? ''));
    if ($payload === null) {
        admin_inbox_err('invalid_payload_json', 422);
    }

    $messagePrefix = '';
    $structuredPayload = [];
    if ($actionType === 'REQUEST_ADDITIONAL_INFO') {
        $allowedTypes = ['labs', 'imaging', 'photos', 'medical_history', 'other'];
        $requiredTypes = [];
        if (isset($payload['required_types']) && is_array($payload['required_types'])) {
            foreach ($payload['required_types'] as $t) {
                $type = strtolower(trim((string)$t));
                if ($type !== '' && in_array($type, $allowedTypes, true) && !in_array($type, $requiredTypes, true)) {
                    $requiredTypes[] = $type;
                }
            }
        }
        if (empty($requiredTypes)) {
            admin_inbox_err('required_types_missing', 422);
        }
        $note = trim((string)($payload['note'] ?? ''));
        if (mb_strlen($note) > 500) {
            admin_inbox_err('note_too_long', 422);
        }

        $messagePrefix = '[REQUEST_INFO] ';
        $structuredPayload = [
            'action_type' => 'REQUEST_ADDITIONAL_INFO',
            'required_types' => $requiredTypes,
            'note' => $note,
        ];
    } else {
        $amountRaw = trim((string)($payload['amount'] ?? ''));
        $amount = is_numeric($amountRaw) ? (float)$amountRaw : 0;
        if ($amount <= 0) {
            admin_inbox_err('invalid_amount', 422);
        }
        $currency = strtoupper(trim((string)($payload['currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }
        if (mb_strlen($currency) > 10) {
            admin_inbox_err('invalid_currency', 422);
        }
        $notes = trim((string)($payload['notes'] ?? ''));
        if (mb_strlen($notes) > 500) {
            admin_inbox_err('notes_too_long', 422);
        }

        $messagePrefix = '[PROPOSE_QUOTE] ';
        $structuredPayload = [
            'action_type' => 'PROPOSE_QUOTE_ADJUSTMENT',
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'notes' => $notes,
        ];
    }

    $jsonPayload = json_encode($structuredPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonPayload === false) {
        admin_inbox_err('payload_encode_failed', 500);
    }
    $message = $messagePrefix . $jsonPayload;
    if (mb_strlen($message) > 2000) {
        admin_inbox_err('message_too_long', 422);
    }

    $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasItemUpdatedAt = inbox_table_has_column($conexion, 'booking_request_items', 'updated_at');
    $hasProviderResponseAt = inbox_table_has_column($conexion, 'booking_request_items', 'provider_response_at');
    $hasProviderResponseBy = inbox_table_has_column($conexion, 'booking_request_items', 'provider_response_by');

    $setParts = ['bri.item_status = ?'];
    $types = 's';
    $params = ['awaiting_client'];
    if ($hasItemUpdatedAt) {
        $setParts[] = 'bri.updated_at = NOW()';
    }
    if ($hasProviderResponseAt) {
        $setParts[] = 'bri.provider_response_at = NOW()';
    }
    if ($hasProviderResponseBy) {
        $setParts[] = 'bri.provider_response_by = ?';
        $types .= 'i';
        $params[] = (int)$scope['user_id'];
    }

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET " . implode(', ', $setParts) . "
            WHERE bri.id = ?";
    $types .= 'i';
    $params[] = (int)$ctx['item_id'];
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    $sql .= (string)$scope['scope_where'];
    $sql .= ' LIMIT 1';

    $updateTypes = $types . (string)$scope['scope_types'];
    $updateParams = array_merge($params, (array)$scope['scope_params']);
    $stmtUpdate = mysqli_prepare($conexion, $sql);
    if (!$stmtUpdate) {
        admin_inbox_err('prepare_failed', 500);
    }
    if (!inbox_bind_stmt_params($stmtUpdate, $updateTypes, $updateParams) || !mysqli_stmt_execute($stmtUpdate)) {
        $err = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        admin_inbox_err('update_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmtUpdate);

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
        'item_status' => 'awaiting_client',
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
