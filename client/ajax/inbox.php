<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../../admin/include/email_config.php';
require_once __DIR__ . '/../include/client_notifications.php';
require_once __DIR__ . '/../../inc/inbox_utils.php';
require_once __DIR__ . '/../../inc/email_template.php';
require_once __DIR__ . '/../../inc/interaction_email.php';
require_once __DIR__ . '/../../inc/fee_gate.php';
require_once __DIR__ . '/../../inc/commission_gate.php';

function client_inbox_err($message, $code = 400, $errorCode = '')
{
    http_response_code($code);
    $payload = ['ok' => false, 'message' => $message];
    if ($errorCode !== '') {
        $payload['code'] = $errorCode;
    }
    echo json_encode($payload);
    exit;
}

function client_inbox_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function client_inbox_compose_locked($reason, $ctx, $clientUserId)
{
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_BLOCK_SEND_MESSAGE reason=' . (string)$reason
            . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
            . ' user_id=' . (int)$clientUserId
            . ' request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
        );
    }
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'success' => false,
        'error' => 'compose_locked',
        'reason' => (string)$reason,
    ]);
    exit;
}

function client_inbox_notify_message($conexion, $ctx, $message)
{
    if (!function_exists('send_interaction_email')) {
        return;
    }
    $threadType = (string)($ctx['thread_type'] ?? '');
    $requestId = (int)($ctx['request_id'] ?? 0);
    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($requestId <= 0) {
        return;
    }

    $meta = interaction_email_request_meta($conexion, $threadType, $requestId, $itemId);
    $serviceTitle = trim((string)($meta['title'] ?? 'Request #' . $requestId));
    $destination = trim((string)($meta['subtitle'] ?? ''));
    $actorLabel = interaction_email_actor_label('CLIENT');
    $snippet = interaction_email_safe_snippet($message, 120);
    if ($snippet === '') {
        $snippet = 'New message received.';
    }

    $subject = 'MedTravel update - ' . $actorLabel . ' message for Request #' . $requestId;
    $contentHtml = '<p><strong>Actor:</strong> ' . htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Request:</strong> #' . $requestId . '<br>'
        . '<strong>Service:</strong> ' . htmlspecialchars($serviceTitle, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($destination !== '') {
        $contentHtml .= '<p><strong>Destination:</strong> ' . htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $contentHtml .= '<p><strong>Message:</strong> ' . htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') . '</p>';

    $ctaUrl = 'https://medtravel.com.co/admin/app_inbox.php?request_id=' . $requestId
        . '&thread_type=' . urlencode((string)$meta['thread_type'])
        . '&item_id=' . (int)$meta['item_id'];
    $textBody = "Actor: {$actorLabel}\nRequest: #{$requestId}\nService: {$serviceTitle}";
    if ($destination !== '') {
        $textBody .= "\nDestination: {$destination}";
    }
    $textBody .= "\nMessage: {$snippet}\nInbox: {$ctaUrl}";

    $adminEmail = interaction_email_resolve_patientcare_email($conexion);
    $providerEmail = ($threadType === 'ITEM' && $itemId > 0)
        ? interaction_email_fetch_provider_email($conexion, $itemId)
        : '';

    $metaSend = [
        'preheader' => $snippet,
        'cta' => ['text' => 'Open Inbox', 'url' => $ctaUrl],
    ];

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        send_interaction_email($adminEmail, $subject, $contentHtml, $textBody, $metaSend, $conexion);
    }
    if (filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        send_interaction_email($providerEmail, $subject, $contentHtml, $textBody, $metaSend, $conexion);
    }
}

function client_inbox_fee_gate_state($conexion, $bookingRequestId)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0 || !inbox_table_exists($conexion, 'booking_requests')) {
        return [
            'fee_required' => 0,
            'fee_status' => 'pending',
            'fee_locked' => false,
        ];
    }

    $hasFeeRequired = client_table_has_column($conexion, 'booking_requests', 'fee_required');
    $hasFeeStatus = client_table_has_column($conexion, 'booking_requests', 'fee_status');
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');

    $sql = "SELECT " . ($hasFeeRequired ? "fee_required" : "0 AS fee_required") . ", " . ($hasFeeStatus ? "fee_status" : "'pending' AS fee_status") . "\n"
         . "FROM booking_requests WHERE id = ?";
    if ($hasBookingSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [
            'fee_required' => 0,
            'fee_status' => 'pending',
            'fee_locked' => false,
        ];
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [
            'fee_required' => 0,
            'fee_status' => 'pending',
            'fee_locked' => false,
        ];
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    $feeRequired = (int)($row['fee_required'] ?? 0) === 1 ? 1 : 0;
    $feeStatus = strtolower(trim((string)($row['fee_status'] ?? 'pending')));
    if ($feeStatus === '') {
        $feeStatus = 'pending';
    }

    return [
        'fee_required' => $feeRequired,
        'fee_status' => $feeStatus,
        'fee_locked' => ($feeRequired === 1 && $feeStatus !== 'paid'),
    ];
}

function client_inbox_free_message_state($conexion, $bookingRequestId, $feeGate = null)
{
    $bookingRequestId = (int)$bookingRequestId;
    $feeGate = is_array($feeGate) ? $feeGate : client_inbox_fee_gate_state($conexion, $bookingRequestId);
    $feeLocked = !empty($feeGate['fee_locked']);

    $status = 'pending';
    if ($bookingRequestId > 0 && inbox_table_exists($conexion, 'booking_requests') && client_table_has_column($conexion, 'booking_requests', 'status')) {
        $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
        $statusSql = "SELECT status FROM booking_requests WHERE id = ?";
        if ($hasBookingSoftDelete) {
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
                    $status = client_status_label((string)($statusRow['status'] ?? 'pending'));
                }
            }
            mysqli_stmt_close($stmtStatus);
        }
    }

    $stageAllowsFreeMessage = client_status_is_update($status);
    $canSendFreeMessage = (!$feeLocked && $stageAllowsFreeMessage);
    $reason = '';
    if ($feeLocked) {
        $reason = 'fee_locked';
    } elseif (!$stageAllowsFreeMessage) {
        $reason = 'initial_review';
    }

    $notice = '';
    if ($feeLocked) {
        $notice = 'Free-form messaging is locked until the coordination fee is paid. Please use the structured actions above.';
    } elseif (!$stageAllowsFreeMessage) {
        $notice = 'Free-form messaging is locked until the initial review is complete. Please use the structured actions above.';
    }

    return [
        'booking_status' => $status,
        'stage_allows_free_message' => $stageAllowsFreeMessage,
        'can_send_free_message' => $canSendFreeMessage,
        'blocked_reason' => $reason,
        'notice' => $notice,
    ];
}

function client_inbox_is_structured_message($message, $quickActions = [])
{
    $text = ltrim((string)$message);
    if ($text === '') {
        return false;
    }
    if (stripos($text, '[ACTION]') === 0) {
        return true;
    }
    if (stripos($text, '[REPLY]') === 0) {
        return true;
    }
    if (!empty($quickActions)) {
        foreach ($quickActions as $actionText) {
            if ($actionText === '') {
                continue;
            }
            if (strcasecmp($text, $actionText) === 0) {
                return true;
            }
            if (stripos($text, '[ACTION] ') === 0 && strcasecmp(trim(substr($text, 9)), $actionText) === 0) {
                return true;
            }
        }
    }
    return false;
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

if (defined('INBOX_BOOTSTRAP_ONLY') && INBOX_BOOTSTRAP_ONLY) {
    return;
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

if ($action === 'list_messages' || $action === 'mark_read' || $action === 'send_message' || $action === 'send_quick_action' || $action === 'send_structured_action' || $action === 'accept_dates' || $action === 'reject_dates' || $action === 'final_accept_and_pay' || $action === 'final_decline') {
    $threadIdInput = (string)($_GET['thread_id'] ?? $_POST['thread_id'] ?? '');
    $threadType = (string)($_GET['thread_type'] ?? $_POST['thread_type'] ?? '');
    $requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? $_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
    $itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

    $ctx = client_inbox_resolve_context($conexion, $ownerScope, $threadType, $requestId, $itemId, $threadIdInput);
    if (empty($ctx['ok'])) {
        client_inbox_err((string)($ctx['message'] ?? 'invalid_thread'), (int)($ctx['status'] ?? 400));
    }

    $bookingRequestId = (int)($ctx['request_id'] ?? 0);
    $isCareThread = (strtoupper((string)($ctx['thread_type'] ?? '')) === 'CARE');
    $feeGate = client_inbox_fee_gate_state($conexion, $bookingRequestId);
    $feeLocked = !$isCareThread && !empty($feeGate['fee_locked']);
    $feeRequired = (int)($feeGate['fee_required'] ?? 0);
    $feeStatus = (string)($feeGate['fee_status'] ?? 'pending');
    $commissionGate = commission_gate_status($conexion, $bookingRequestId, (int)($ctx['item_id'] ?? 0));
    $commissionGateEnabled = !empty($commissionGate['enabled']);
    $commissionPaid = !empty($commissionGate['paid']);
    $commissionLocked = $commissionGateEnabled && !$commissionPaid;
    $freeMessageState = client_inbox_free_message_state($conexion, $bookingRequestId, $feeGate);
    $canSendFreeMessage = !empty($freeMessageState['can_send_free_message']);
    if (!$feeLocked && !$isCareThread) {
        if ($commissionGateEnabled) {
            if (!$commissionPaid) {
                $canSendFreeMessage = false;
                $freeMessageState['can_send_free_message'] = false;
                $freeMessageState['blocked_reason'] = 'commission';
                $freeMessageState['notice'] = 'Messaging is locked until the commission is paid. Please contact MedTravel if you need help.';
            } else {
                $canSendFreeMessage = true;
                $freeMessageState['can_send_free_message'] = true;
                $freeMessageState['blocked_reason'] = '';
                $freeMessageState['notice'] = '';
            }
        } else {
            $canSendFreeMessage = true;
            $freeMessageState['can_send_free_message'] = true;
            $freeMessageState['blocked_reason'] = '';
            $freeMessageState['notice'] = '';
        }
    }
    if ($action === 'send_message') {
        $messageInput = trim((string)($_POST['message'] ?? ''));
        $structuredAllowlist = [
            'Please confirm availability for my dates.',
            'My dates are flexible.',
            'I have uploaded medical documents.',
            "I don't have the requested documents yet."
        ];
        $isStructured = client_inbox_is_structured_message($messageInput, $structuredAllowlist);
        if ($commissionLocked && !$isStructured) {
            client_inbox_compose_locked('commission', $ctx, $clientUserId);
        }
        if ($feeLocked && !$isStructured) {
            client_inbox_compose_locked('fee', $ctx, $clientUserId);
        }
        if (!$isCareThread && !$canSendFreeMessage && !$isStructured) {
            client_inbox_compose_locked('review', $ctx, $clientUserId);
        }
    }
}

if ($action === 'list_messages') {
    $messages = [];
    $sinceId = (int)($_GET['since_id'] ?? $_POST['since_id'] ?? 0);
    if (inbox_table_exists($conexion, 'inbox_messages')) {
        if ($sinceId > 0) {
            $stmt = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? AND id > ? ORDER BY id ASC");
        } else {
            $stmt = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? ORDER BY id ASC");
        }
        if ($stmt) {
            $threadId = (string)$ctx['thread_id'];
            if ($sinceId > 0) {
                mysqli_stmt_bind_param($stmt, 'si', $threadId, $sinceId);
            } else {
                mysqli_stmt_bind_param($stmt, 's', $threadId);
            }
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

    if ($sinceId <= 0 && empty($messages) && trim((string)($ctx['additional_notes'] ?? '')) !== '') {
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

    $hasStructuredItemActions = false;
    $structuredItemId = 0;
    if (strtoupper((string)($ctx['thread_type'] ?? '')) === 'CARE'
        && inbox_table_exists($conexion, 'inbox_messages')
        && inbox_table_exists($conexion, 'booking_request_items')) {
        $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
        $structuredSql = "SELECT im.item_id
                          FROM inbox_messages im
                          INNER JOIN booking_request_items bri ON bri.id = im.item_id
                          INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                          WHERE im.request_id = ?
                            AND im.thread_type = 'ITEM'
                            AND im.item_id > 0
                            AND (" . $ownerScope['sql'] . ")
                            AND (
                                im.body LIKE '[REPLY] PROPOSED_DATES%'
                                OR im.body LIKE '[REPLY] FINAL_APPROVED%'
                                OR im.body LIKE '[REQUEST_INFO] %'
                                OR im.body LIKE '[PROPOSE_QUOTE] %'
                            )";
        if ($hasItemsSoftDelete) {
            $structuredSql .= " AND bri.is_deleted = 0";
        }
        if ($hasBookingSoftDelete) {
            $structuredSql .= " AND br.is_deleted = 0";
        }
        $structuredSql .= " ORDER BY im.id ASC LIMIT 1";

        $stmtStructured = mysqli_prepare($conexion, $structuredSql);
        if ($stmtStructured) {
            $types = 'i' . $ownerScope['types'];
            $params = array_merge([(int)$ctx['request_id']], $ownerScope['params']);
            if (inbox_bind_stmt_params($stmtStructured, $types, $params) && mysqli_stmt_execute($stmtStructured)) {
                $structuredRes = mysqli_stmt_get_result($stmtStructured);
                $structuredRow = $structuredRes ? mysqli_fetch_assoc($structuredRes) : null;
                if ($structuredRow) {
                    $structuredItemId = (int)($structuredRow['item_id'] ?? 0);
                    $hasStructuredItemActions = $structuredItemId > 0;
                }
            }
            mysqli_stmt_close($stmtStructured);
        }
    }

    $documents = [];
    $threadTypeUpper = strtoupper((string)($ctx['thread_type'] ?? ''));
    $isItemThreadForDocs = ($threadTypeUpper === 'ITEM') && ((int)($ctx['item_id'] ?? 0) > 0);
    $isCareThreadForDocs = ($threadTypeUpper === 'CARE');
    if (($isItemThreadForDocs || $isCareThreadForDocs) && client_table_exists($conexion, 'client_documents')) {
        $docHasRequestId = client_table_has_column($conexion, 'client_documents', 'booking_request_id');
        $docHasItemId = client_table_has_column($conexion, 'client_documents', 'item_id');
        if ($docHasRequestId && $docHasItemId) {
            $docHasClientUserId = client_table_has_column($conexion, 'client_documents', 'client_user_id');
            $clientesHasClientUserId = client_table_has_column($conexion, 'clientes', 'client_user_id');
            $clientesHasUserId = client_table_has_column($conexion, 'clientes', 'user_id');
            $clientesMapCol = $clientesHasClientUserId ? 'client_user_id' : ($clientesHasUserId ? 'user_id' : '');

            $selectCols = ['id', 'file_path', 'filename', 'original_filename', 'document_type', 'booking_request_id', 'item_id'];
            if (client_table_has_column($conexion, 'client_documents', 'file_size')) {
                $selectCols[] = 'file_size';
            }
            if (client_table_has_column($conexion, 'client_documents', 'mime_type')) {
                $selectCols[] = 'mime_type';
            }
            if (client_table_has_column($conexion, 'client_documents', 'title')) {
                $selectCols[] = 'title';
            }
            if (client_table_has_column($conexion, 'client_documents', 'description')) {
                $selectCols[] = 'description';
            }
            $orderByColumn = client_table_has_column($conexion, 'client_documents', 'uploaded_at') ? 'uploaded_at' : 'id';

            $docSql = "SELECT " . implode(', ', $selectCols) . " FROM client_documents cd";
            $docTypes = '';
            $docParams = [];

            if ($docHasClientUserId && $clientUserId > 0) {
                $docSql .= " WHERE (cd.client_user_id = ?";
                $docTypes .= 'i';
                $docParams[] = $clientUserId;
                if ($clientesMapCol !== '') {
                    $docSql .= " OR (cd.client_user_id IS NULL AND EXISTS (SELECT 1 FROM clientes c WHERE c.id = cd.client_id AND c." . $clientesMapCol . " = ?))";
                    $docTypes .= 'i';
                    $docParams[] = $clientUserId;
                }
                $docSql .= ")";
            } else {
                $clientIdForDocs = 0;
                if ($clientesMapCol !== '') {
                    $stmtClient = mysqli_prepare($conexion, "SELECT id FROM clientes WHERE " . $clientesMapCol . " = ? ORDER BY id DESC LIMIT 1");
                    if ($stmtClient) {
                        mysqli_stmt_bind_param($stmtClient, 'i', $clientUserId);
                        if (mysqli_stmt_execute($stmtClient)) {
                            $resClient = mysqli_stmt_get_result($stmtClient);
                            $rowClient = $resClient ? mysqli_fetch_assoc($resClient) : null;
                            if ($rowClient) {
                                $clientIdForDocs = (int)($rowClient['id'] ?? 0);
                            }
                        }
                        mysqli_stmt_close($stmtClient);
                    }
                }
                if ($clientIdForDocs > 0) {
                    $docSql .= " WHERE cd.client_id = ?";
                    $docTypes .= 'i';
                    $docParams[] = $clientIdForDocs;
                } else {
                    $docSql .= " WHERE 1=1"; // booking_request_id filter below scopes the query
                }
            }

            $docSql .= " AND cd.booking_request_id = ?";
            $docTypes .= 'i';
            $docParams[] = (int)$ctx['request_id'];

            if ($isItemThreadForDocs && (int)($ctx['item_id'] ?? 0) > 0) {
                $docSql .= " AND (cd.item_id = ? OR cd.item_id IS NULL)";
                $docTypes .= 'i';
                $docParams[] = (int)$ctx['item_id'];
            } elseif ($isCareThreadForDocs) {
                $docSql .= " AND (cd.item_id IS NULL OR cd.item_id = 0)";
            }

            $docSql .= " ORDER BY " . $orderByColumn . " DESC";

            $stmtDocs = mysqli_prepare($conexion, $docSql);
            if ($stmtDocs) {
                if (inbox_bind_stmt_params($stmtDocs, $docTypes, $docParams) && mysqli_stmt_execute($stmtDocs)) {
                    $docRes = mysqli_stmt_get_result($stmtDocs);
                    while ($docRes && ($docRow = mysqli_fetch_assoc($docRes))) {
                        $filePath = ltrim((string)($docRow['file_path'] ?? ''), '/');
                        $docRow['download_url'] = $filePath !== '' ? '/uploads/medical_docs/' . $filePath : '';
                        $documents[] = $docRow;
                    }
                }
                mysqli_stmt_close($stmtDocs);
            }
        }
    }

    client_inbox_ok([
        'thread_id' => $ctx['thread_id'],
        'thread_type' => $ctx['thread_type'],
        'request_id' => (int)$ctx['request_id'],
        'booking_id' => (int)$ctx['request_id'],
        'item_id' => (int)$ctx['item_id'],
        'since_id' => $sinceId,
        'has_structured_item_actions' => $hasStructuredItemActions,
        'structured_item_id' => $structuredItemId,
        'fee_required' => $feeRequired,
        'fee_status' => $feeStatus,
        'fee_locked' => !empty($feeLocked),
        'fee_message' => !empty($feeLocked) ? 'Unlock after Coordination Fee.' : '',
        'commission_gate_enabled' => $commissionGateEnabled ? 1 : 0,
        'commission_paid' => $commissionPaid ? 1 : 0,
        'commission_message' => $commissionLocked
            ? 'Provider details and free messaging unlock after the commission payment is completed.'
            : '',
        'can_send_free_message' => !empty($freeMessageState['can_send_free_message']),
        'free_message_blocked_reason' => (string)($freeMessageState['blocked_reason'] ?? ''),
        'free_message_notice' => (string)($freeMessageState['notice'] ?? ''),
        'documents' => $documents,
        'messages' => $messages,
    ]);
}

if ($action === 'send_message') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_SEND_MESSAGE_ENTER endpoint=client/ajax/inbox.php action=send_message'
            . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
            . ' request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
        );
    }
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
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_provider_email($conexion, $itemId, $emailSource);
        mt_email_debug_log(
            'CLIENT_NOTIFY_PROVIDER_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        $notifyResult = notify_new_message_to_provider(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            'CLIENT',
            $message,
            $resolvedEmail,
            $emailSource
        );
        mt_email_debug_log('CLIENT_NOTIFY_PROVIDER_DONE result=' . json_encode($notifyResult));
    } else {
        notify_new_message_to_provider($conexion, $requestId, $itemId, $threadType, 'CLIENT', $message);
    }

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
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'send_quick_action') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_SEND_QUICK_ACTION_ENTER endpoint=client/ajax/inbox.php action=send_quick_action'
            . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
            . ' request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
        );
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }

    $actionKey = strtoupper(trim((string)($_POST['action_key'] ?? '')));
    $quickActions = [
        'REQUEST_AVAILABILITY' => 'Please confirm availability for my dates.',
        'DATES_FLEXIBLE' => 'My dates are flexible.',
        'DOCS_UPLOADED' => 'I have uploaded medical documents.',
        'DOCS_NOT_AVAILABLE' => 'I don\'t have the requested documents yet.'
    ];
    if ($actionKey === '' || !isset($quickActions[$actionKey])) {
        client_inbox_err('invalid_action_key', 422);
    }

    $message = '[ACTION] ' . $quickActions[$actionKey];
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
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_provider_email($conexion, $itemId, $emailSource);
        mt_email_debug_log(
            'CLIENT_NOTIFY_PROVIDER_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        $notifyResult = notify_new_message_to_provider(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            'CLIENT',
            $message,
            $resolvedEmail,
            $emailSource
        );
        mt_email_debug_log('CLIENT_NOTIFY_PROVIDER_DONE result=' . json_encode($notifyResult));
    } else {
        notify_new_message_to_provider($conexion, $requestId, $itemId, $threadType, 'CLIENT', $message);
    }

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
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'send_structured_action') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_SEND_STRUCTURED_ENTER endpoint=client/ajax/inbox.php action=send_structured_action'
            . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
            . ' request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
        );
    }
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        client_inbox_err('booking_items_not_available', 409);
    }
    if (!client_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        client_inbox_err('item_status_not_available', 409);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        client_inbox_err('invalid_thread_type', 422);
    }

    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($itemId <= 0) {
        client_inbox_err('invalid_item_id', 422);
    }

    $actionType = strtoupper(trim((string)($_POST['action_type'] ?? '')));
    $allowedTypes = ['ACCEPT_PROPOSAL', 'REQUEST_CHANGES', 'REJECT_PROPOSAL'];
    if (!in_array($actionType, $allowedTypes, true)) {
        client_inbox_err('invalid_action_type', 422);
    }

    $notes = trim((string)($_POST['notes'] ?? ''));
    if (mb_strlen($notes) > 500) {
        client_inbox_err('notes_too_long', 422);
    }

    $targetStatus = 'provider_proposed_change';
    if ($actionType === 'ACCEPT_PROPOSAL') {
        $targetStatus = 'client_accepted';
    } elseif ($actionType === 'REJECT_PROPOSAL') {
        $targetStatus = 'client_rejected';
    }

    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasItemUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET bri.item_status = ?";
    if ($hasItemUpdatedAt) {
        $sql .= ', bri.updated_at = NOW()';
    }
    $sql .= " WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasBookingSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $types = 'si' . $ownerScope['types'];
    $params = array_merge([$targetStatus, $itemId], $ownerScope['params']);

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        client_inbox_err('prepare_failed', 500);
    }
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        client_inbox_err('update_failed: ' . $err, 500);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected <= 0) {
        client_inbox_err('not_found_or_no_change', 404);
    }

    $payload = [
        'action_type' => $actionType,
        'notes' => $notes,
    ];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        client_inbox_err('payload_encode_failed', 500);
    }
    $message = '[PROPOSAL_RESPONSE] ' . $payloadJson;

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmtMsg) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    mysqli_stmt_bind_param($stmtMsg, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_provider_email($conexion, $itemId, $emailSource);
        mt_email_debug_log(
            'CLIENT_NOTIFY_PROVIDER_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        $notifyResult = notify_new_message_to_provider(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            'CLIENT',
            $message,
            $resolvedEmail,
            $emailSource
        );
        mt_email_debug_log('CLIENT_NOTIFY_PROVIDER_DONE result=' . json_encode($notifyResult));
    } else {
        notify_new_message_to_provider($conexion, $requestId, $itemId, $threadType, 'CLIENT', $message);
    }

    client_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_id' => $requestId,
        'item_id' => $itemId,
        'item_status' => $targetStatus,
        'message' => [
            'id' => $messageId,
            'sender' => 'client',
            'body' => $message,
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'accept_dates' || $action === 'reject_dates') {
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        client_inbox_err('booking_items_not_available', 409);
    }
    if (!client_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        client_inbox_err('item_status_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        client_inbox_err('invalid_thread_type', 422);
    }

    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($itemId <= 0) {
        client_inbox_err('invalid_item_id', 422);
    }

    $targetStatus = ($action === 'accept_dates') ? 'awaiting_client' : 'provider_proposed_change';
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasItemUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET bri.item_status = ?";
    if ($hasItemUpdatedAt) {
        $sql .= ', bri.updated_at = NOW()';
    }
    $sql .= " WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasBookingSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $types = 'si' . $ownerScope['types'];
    $params = array_merge([$targetStatus, $itemId], $ownerScope['params']);

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        client_inbox_err('prepare_failed', 500);
    }
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        client_inbox_err('update_failed: ' . $err, 500);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected <= 0) {
        client_inbox_err('not_found_or_no_change', 404);
    }

    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }

    $message = ($action === 'accept_dates')
        ? '[ACTION] Client accepted proposed dates'
        : '[ACTION] Client rejected proposed dates';

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmtMsg) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    mysqli_stmt_bind_param($stmtMsg, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);
    $createdAt = date('Y-m-d H:i:s');

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
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'final_accept_and_pay' || $action === 'final_decline') {
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        client_inbox_err('booking_items_not_available', 409);
    }
    if (!client_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        client_inbox_err('item_status_not_available', 409);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        client_inbox_err('invalid_thread_type', 422);
    }

    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($itemId <= 0) {
        client_inbox_err('invalid_item_id', 422);
    }

    if ($action === 'final_accept_and_pay') {
        $threadId = (string)$ctx['thread_id'];
        $stmtCheck = mysqli_prepare($conexion, "SELECT id FROM inbox_messages WHERE thread_id = ? AND body LIKE '[REPLY] FINAL_APPROVED%' LIMIT 1");
        if (!$stmtCheck) {
            client_inbox_err('prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmtCheck, 's', $threadId);
        if (!mysqli_stmt_execute($stmtCheck)) {
            $err = mysqli_stmt_error($stmtCheck);
            mysqli_stmt_close($stmtCheck);
            client_inbox_err('check_failed: ' . $err, 500);
        }
        $resCheck = mysqli_stmt_get_result($stmtCheck);
        $hasApproved = $resCheck && mysqli_fetch_assoc($resCheck);
        mysqli_stmt_close($stmtCheck);
        if (!$hasApproved) {
            client_inbox_err('final_approval_required', 409);
        }
    }

    $targetStatus = ($action === 'final_accept_and_pay') ? 'client_accepted' : 'client_rejected';
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasItemUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET bri.item_status = ?";
    if ($hasItemUpdatedAt) {
        $sql .= ', bri.updated_at = NOW()';
    }
    $sql .= " WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasBookingSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $types = 'si' . $ownerScope['types'];
    $params = array_merge([$targetStatus, $itemId], $ownerScope['params']);

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        client_inbox_err('prepare_failed', 500);
    }
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        client_inbox_err('update_failed: ' . $err, 500);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected <= 0) {
        client_inbox_err('not_found_or_no_change', 404);
    }

    $message = ($action === 'final_accept_and_pay')
        ? '[ACTION] FINAL_ACCEPT_AND_PAY'
        : '[ACTION] FINAL_DECLINE';

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmtMsg) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    mysqli_stmt_bind_param($stmtMsg, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);
    $createdAt = date('Y-m-d H:i:s');

    client_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_id' => $requestId,
        'item_id' => $itemId,
        'item_status' => $targetStatus,
        'message' => [
            'id' => $messageId,
            'sender' => 'client',
            'body' => $message,
            'time' => $createdAt,
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
