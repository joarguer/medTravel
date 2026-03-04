<?php
header('Content-Type: application/json; charset=utf-8');

define('INBOX_BOOTSTRAP_ONLY', true);
require_once __DIR__ . '/inbox.php';

if (!function_exists('mt_realtime_make_token')) {
    require_once __DIR__ . '/../../inc/realtime_auth.php';
}

function client_realtime_err($message, $status = 400, $code = '')
{
    http_response_code($status);
    $payload = ['ok' => false, 'message' => $message];
    if ($code !== '') {
        $payload['code'] = $code;
    }
    echo json_encode($payload);
    exit;
}

if (!isset($conexion) || !$conexion) {
    client_realtime_err('db_not_available', 500);
}

$clientUserId = get_client_user_id();
if ($clientUserId <= 0) {
    client_realtime_err('unauthorized', 401);
}

$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if (($ownerScope['sql'] ?? '1=0') === '1=0') {
    client_realtime_err('booking_owner_scope_unavailable', 409);
}

$threadIdInput = (string)($_POST['thread_id'] ?? $_GET['thread_id'] ?? '');
$threadType = (string)($_POST['thread_type'] ?? $_GET['thread_type'] ?? '');
$requestId = (int)($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
$itemId = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);

$ctx = client_inbox_resolve_context($conexion, $ownerScope, $threadType, $requestId, $itemId, $threadIdInput);
if (empty($ctx['ok'])) {
    client_realtime_err((string)($ctx['message'] ?? 'invalid_thread'), (int)($ctx['status'] ?? 400));
}

$token = mt_realtime_make_token((string)$ctx['thread_id'], $clientUserId, 'CLIENT');
if ($token === '') {
    client_realtime_err('realtime_not_configured', 503);
}

echo json_encode([
    'ok' => true,
    'thread_id' => (string)$ctx['thread_id'],
    'token' => $token,
]);
