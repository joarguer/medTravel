<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../../inc/realtime.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!is_role_admin_session()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'forbidden']);
    exit;
}

$secret = defined('MT_REALTIME_HMAC_SECRET') ? MT_REALTIME_HMAC_SECRET : '';
if ($secret === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'realtime_secret_missing']);
    exit;
}

$userId = (int)($_SESSION['id_usuario'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'invalid_user']);
    exit;
}

$role = 'ADMIN';
$exp = time() + 600;
$payload = $userId . '|' . $role . '|' . $exp;
$payloadB64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
$signature = hash_hmac('sha256', $payload, $secret);
$token = $payloadB64 . '.' . $signature;

echo json_encode(['ok' => true, 'token' => $token]);
