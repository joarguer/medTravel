<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../../inc/realtime.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!is_role_admin_session()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'forbidden_admin_only']);
    exit;
}

$userId = (int)($_SESSION['id_usuario'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'invalid_user']);
    exit;
}

if (!function_exists('mt_realtime_make_admin_token')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'realtime_helper_missing']);
    exit;
}

$token = mt_realtime_make_admin_token($userId, 'ADMIN');
if ($token === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'realtime_secret_missing']);
    exit;
}

echo json_encode(['ok' => true, 'token' => $token]);
