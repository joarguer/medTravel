<?php
header('Content-Type: application/json; charset=utf-8');

define('INBOX_BOOTSTRAP_ONLY', true);
require_once __DIR__ . '/inbox.php';

if (!function_exists('mt_realtime_make_token')) {
    require_once __DIR__ . '/../../inc/realtime_auth.php';
}

function admin_realtime_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

if (!isset($conexion) || !$conexion) {
    admin_realtime_err('db_not_available', 500);
}

$scope = admin_inbox_build_scope();
if (empty($scope['ok'])) {
    admin_realtime_err((string)($scope['message'] ?? 'forbidden'), (int)($scope['status'] ?? 403));
}

$threadIdInput = (string)($_POST['thread_id'] ?? $_GET['thread_id'] ?? '');
$threadType = (string)($_POST['thread_type'] ?? $_GET['thread_type'] ?? '');
$requestId = (int)($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
$itemId = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);

$ctx = admin_inbox_resolve_context($conexion, $scope, $threadType, $requestId, $itemId, $threadIdInput);
if (empty($ctx['ok'])) {
    admin_realtime_err((string)($ctx['message'] ?? 'invalid_thread'), (int)($ctx['status'] ?? 400));
}

$role = strtoupper((string)($scope['reader_role'] ?? 'ADMIN'));
$token = mt_realtime_make_token((string)$ctx['thread_id'], (int)($scope['user_id'] ?? 0), $role);
if ($token === '') {
    admin_realtime_err('realtime_not_configured', 503);
}

echo json_encode([
    'ok' => true,
    'thread_id' => (string)$ctx['thread_id'],
    'token' => $token,
]);
