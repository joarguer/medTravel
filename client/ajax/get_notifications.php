<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';

$clientUserId = get_client_user_id();
$payload = ['count' => 0, 'items' => []];

if (isset($conexion) && $conexion) {
    $payload = client_fetch_notifications($conexion, $clientUserId, 12);
}

echo json_encode([
    'ok' => true,
    'count' => (int)($payload['count'] ?? 0),
    'items' => is_array($payload['items'] ?? null) ? $payload['items'] : [],
]);

