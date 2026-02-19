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

$clientUserId = get_client_user_id();
$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$message = trim((string)($_POST['message'] ?? ''));

if ($bookingId <= 0) {
    client_send_error('invalid_booking_id');
}
if ($message === '') {
    client_send_error('message_required');
}
if (mb_strlen($message) > 2000) {
    client_send_error('message_too_long');
}

if (!isset($conexion) || !$conexion || !client_table_exists($conexion, 'booking_requests') || !client_table_has_column($conexion, 'booking_requests', 'client_user_id')) {
    client_send_error('booking_requests_not_available', 409);
}

$hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasAdditionalNotes = client_table_has_column($conexion, 'booking_requests', 'additional_notes');
if (!$hasAdditionalNotes) {
    client_send_error('additional_notes_not_available', 409);
}

$verifySql = "SELECT id, additional_notes FROM booking_requests WHERE id = ? AND client_user_id = ?";
if ($hasBookingSoftDelete) {
    $verifySql .= " AND is_deleted = 0";
}
$verifySql .= " LIMIT 1";

$stmtVerify = mysqli_prepare($conexion, $verifySql);
if (!$stmtVerify) {
    client_send_error('prepare_failed', 500);
}
mysqli_stmt_bind_param($stmtVerify, 'ii', $bookingId, $clientUserId);
if (!mysqli_stmt_execute($stmtVerify)) {
    mysqli_stmt_close($stmtVerify);
    client_send_error('execute_failed', 500);
}
$verifyRes = mysqli_stmt_get_result($stmtVerify);
$bookingRow = $verifyRes ? mysqli_fetch_assoc($verifyRes) : null;
mysqli_stmt_close($stmtVerify);

if (!$bookingRow) {
    client_send_error('request_not_found', 404);
}

$stamp = date('Y-m-d H:i:s');
$entry = '[CLIENT_MESSAGE][' . $stamp . '] ' . preg_replace('/\s+/', ' ', $message);
$currentNotes = trim((string)($bookingRow['additional_notes'] ?? ''));
$newNotes = $currentNotes !== '' ? ($currentNotes . "\n" . $entry) : $entry;

$updateSql = "UPDATE booking_requests SET additional_notes = ?";
$hasUpdatedAt = client_table_has_column($conexion, 'booking_requests', 'updated_at');
if ($hasUpdatedAt) {
    $updateSql .= ", updated_at = NOW()";
}
$updateSql .= " WHERE id = ? AND client_user_id = ?";
if ($hasBookingSoftDelete) {
    $updateSql .= " AND is_deleted = 0";
}
$updateSql .= " LIMIT 1";

$stmtUpdate = mysqli_prepare($conexion, $updateSql);
if (!$stmtUpdate) {
    client_send_error('update_prepare_failed', 500);
}
mysqli_stmt_bind_param($stmtUpdate, 'sii', $newNotes, $bookingId, $clientUserId);
if (!mysqli_stmt_execute($stmtUpdate)) {
    $err = mysqli_stmt_error($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
    client_send_error('update_failed: ' . $err, 500);
}
mysqli_stmt_close($stmtUpdate);

echo json_encode([
    'ok' => true,
    'message' => [
        'sender' => 'client',
        'type' => 'client_message',
        'body' => $message,
        'time' => $stamp,
    ],
]);

