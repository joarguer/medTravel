<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';

function client_send_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function client_build_thread_actor($threadType, $itemId)
{
    $threadType = strtoupper(trim((string)$threadType));
    if ($threadType === 'ITEM' && (int)$itemId > 0) {
        return 'THREAD:ITEM:' . (int)$itemId;
    }
    return 'THREAD:CARE';
}

$clientUserId = get_client_user_id();
$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$message = trim((string)($_POST['message'] ?? ''));
$threadType = strtoupper(trim((string)($_POST['thread_type'] ?? '')));
$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

if ($bookingId <= 0) {
    client_send_error('invalid_booking_id');
}
if ($message === '') {
    client_send_error('message_required');
}
if (mb_strlen($message) > 2000) {
    client_send_error('message_too_long');
}

$legacyMode = ($threadType === '');
if ($threadType === '') {
    $threadType = 'CARE';
}
if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
    client_send_error('invalid_thread_type', 422);
}

if (!isset($conexion) || !$conexion || !client_table_exists($conexion, 'booking_requests')) {
    client_send_error('booking_requests_not_available', 409);
}
$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    client_send_error('booking_owner_scope_unavailable', 409);
}

$hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasAdditionalNotes = client_table_has_column($conexion, 'booking_requests', 'additional_notes');
if (!$hasAdditionalNotes) {
    client_send_error('additional_notes_not_available', 409);
}

$verifySql = "SELECT br.id, br.additional_notes FROM booking_requests br WHERE br.id = ? AND (" . $ownerScope['sql'] . ")";
if ($hasBookingSoftDelete) {
    $verifySql .= " AND br.is_deleted = 0";
}
$verifySql .= " LIMIT 1";

$stmtVerify = mysqli_prepare($conexion, $verifySql);
if (!$stmtVerify) {
    client_send_error('prepare_failed', 500);
}
$verifyTypes = 'i' . $ownerScope['types'];
$verifyParams = array_merge([$bookingId], $ownerScope['params']);
if (!client_bind_params($stmtVerify, $verifyTypes, $verifyParams) || !mysqli_stmt_execute($stmtVerify)) {
    mysqli_stmt_close($stmtVerify);
    client_send_error('execute_failed', 500);
}
$verifyRes = mysqli_stmt_get_result($stmtVerify);
$bookingRow = $verifyRes ? mysqli_fetch_assoc($verifyRes) : null;
mysqli_stmt_close($stmtVerify);

if (!$bookingRow) {
    client_send_error('request_not_found', 404);
}

if ($threadType === 'ITEM') {
    if ($itemId <= 0) {
        client_send_error('invalid_item_id', 422);
    }
    if (!client_table_exists($conexion, 'booking_request_items')) {
        client_send_error('booking_items_not_available', 409);
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
        client_send_error('prepare_failed', 500);
    }
    $itemTypes = 'ii' . $ownerScope['types'];
    $itemParams = array_merge([$itemId, $bookingId], $ownerScope['params']);
    if (!client_bind_params($stmtItemCheck, $itemTypes, $itemParams) || !mysqli_stmt_execute($stmtItemCheck)) {
        mysqli_stmt_close($stmtItemCheck);
        client_send_error('execute_failed', 500);
    }
    $itemRes = mysqli_stmt_get_result($stmtItemCheck);
    $itemRow = $itemRes ? mysqli_fetch_assoc($itemRes) : null;
    mysqli_stmt_close($stmtItemCheck);
    if (!$itemRow) {
        client_send_error('request_not_found', 404);
    }
}

$stamp = date('Y-m-d H:i:s');
$normalizedMessage = preg_replace('/\s+/', ' ', $message);
$normalizedMessage = trim((string)$normalizedMessage);

if ($legacyMode) {
    $entry = '[CLIENT_MESSAGE][' . $stamp . '] ' . $normalizedMessage;
} else {
    $entry = '[CLIENT_MESSAGE][' . $stamp . '][' . client_build_thread_actor($threadType, $itemId) . '] ' . $normalizedMessage;
}

$currentNotes = trim((string)($bookingRow['additional_notes'] ?? ''));
$newNotes = $currentNotes !== '' ? ($currentNotes . "\n" . $entry) : $entry;

$updateSql = "UPDATE booking_requests SET additional_notes = ?";
$hasUpdatedAt = client_table_has_column($conexion, 'booking_requests', 'updated_at');
if ($hasUpdatedAt) {
    $updateSql .= ", updated_at = NOW()";
}
$updateSql .= " WHERE id = ? AND (" . str_replace('br.', '', $ownerScope['sql']) . ")";
if ($hasBookingSoftDelete) {
    $updateSql .= " AND is_deleted = 0";
}
$updateSql .= " LIMIT 1";

$stmtUpdate = mysqli_prepare($conexion, $updateSql);
if (!$stmtUpdate) {
    client_send_error('update_prepare_failed', 500);
}
$updateTypes = 'si' . $ownerScope['types'];
$updateParams = array_merge([$newNotes, $bookingId], $ownerScope['params']);
if (!client_bind_params($stmtUpdate, $updateTypes, $updateParams) || !mysqli_stmt_execute($stmtUpdate)) {
    $err = mysqli_stmt_error($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
    client_send_error('update_failed: ' . $err, 500);
}
mysqli_stmt_close($stmtUpdate);

echo json_encode([
    'ok' => true,
    'thread_type' => $threadType,
    'item_id' => $threadType === 'ITEM' ? $itemId : 0,
    'message' => [
        'sender' => 'client',
        'type' => 'client_message',
        'body' => $message,
        'time' => $stamp,
        'thread_type' => $threadType,
        'thread_item_id' => $threadType === 'ITEM' ? $itemId : 0,
    ],
]);
