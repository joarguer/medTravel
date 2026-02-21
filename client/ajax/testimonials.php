<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';

function testimonials_json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function testimonials_json_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$clientUserId = get_client_user_id();
$clientName = trim((string)($_SESSION['nombre_usuario'] ?? 'Client'));
if ($clientName === '') {
    $clientName = 'Client';
}

if ($action === 'get_mine') {
    $row = null;
    $stmt = mysqli_prepare($conexion, "SELECT id, client_name, client_location, rating, comment, avatar_path, status, created_at, approved_at, updated_at FROM testimonials WHERE client_user_id = ? ORDER BY created_at DESC LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $clientUserId);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
        }
        mysqli_stmt_close($stmt);
    }
    testimonials_json_ok(['data' => $row]);
}

if ($action === 'create_or_update') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
    if ($rating < 1) {
        $rating = 1;
    }
    if ($rating > 5) {
        $rating = 5;
    }
    $comment = trim((string)($_POST['comment'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));
    if ($comment === '') {
        testimonials_json_error('comment_required');
    }

    $now = date('Y-m-d H:i:s');

    $pendingId = 0;
    $stmt = mysqli_prepare($conexion, "SELECT id FROM testimonials WHERE client_user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $clientUserId);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $pendingId);
            mysqli_stmt_fetch($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    if ($pendingId > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE testimonials SET client_name = ?, client_location = ?, rating = ?, comment = ?, updated_at = ? WHERE id = ? AND client_user_id = ?");
        if (!$stmt) {
            testimonials_json_error('db_error');
        }
        mysqli_stmt_bind_param($stmt, 'ssissii', $clientName, $location, $rating, $comment, $now, $pendingId, $clientUserId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            testimonials_json_error('db_error');
        }
        mysqli_stmt_close($stmt);
        testimonials_json_ok(['status' => 'pending', 'id' => $pendingId]);
    }

    $rejectedId = 0;
    $stmt = mysqli_prepare($conexion, "SELECT id FROM testimonials WHERE client_user_id = ? AND status = 'rejected' ORDER BY created_at DESC LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $clientUserId);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $rejectedId);
            mysqli_stmt_fetch($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    if ($rejectedId > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE testimonials SET client_name = ?, client_location = ?, rating = ?, comment = ?, status = 'pending', updated_at = ? WHERE id = ? AND client_user_id = ?");
        if (!$stmt) {
            testimonials_json_error('db_error');
        }
        mysqli_stmt_bind_param($stmt, 'ssissii', $clientName, $location, $rating, $comment, $now, $rejectedId, $clientUserId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            testimonials_json_error('db_error');
        }
        mysqli_stmt_close($stmt);
        testimonials_json_ok(['status' => 'pending', 'id' => $rejectedId]);
    }

    $stmt = mysqli_prepare($conexion, "INSERT INTO testimonials (client_user_id, client_name, client_location, rating, comment, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
    if (!$stmt) {
        testimonials_json_error('db_error');
    }
    mysqli_stmt_bind_param($stmt, 'ississ', $clientUserId, $clientName, $location, $rating, $comment, $now);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        testimonials_json_error('db_error');
    }
    $newId = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    testimonials_json_ok(['status' => 'pending', 'id' => $newId]);
}

testimonials_json_error('invalid_action');
