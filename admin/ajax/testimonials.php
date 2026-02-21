<?php
session_start();
include('../include/include.php');
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!is_role_admin_session()) {
    echo json_encode(['ok' => false, 'message' => 'forbidden']);
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$adminUserId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;

function testimonials_admin_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function testimonials_admin_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

if ($action === 'list') {
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $allowed = ['pending', 'approved', 'rejected', 'archived'];
    if ($status !== '' && !in_array($status, $allowed, true)) {
        $status = '';
    }

    $sql = "SELECT id, client_user_id, client_name, client_location, rating, comment, avatar_path, status, created_at, updated_at, approved_at, approved_by FROM testimonials";
    if ($status !== '') {
        $statusEsc = mysqli_real_escape_string($conexion, $status);
        $sql .= " WHERE status = '{$statusEsc}'";
    }
    $sql .= " ORDER BY created_at DESC, id DESC";

    $rows = [];
    $res = mysqli_query($conexion, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    testimonials_admin_ok(['data' => $rows]);
}

if ($action === 'approve') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        testimonials_admin_error('invalid_id');
    }

    $stmt = mysqli_prepare($conexion, "SELECT client_user_id FROM testimonials WHERE id = ? LIMIT 1");
    if (!$stmt) {
        testimonials_admin_error('db_error');
    }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        testimonials_admin_error('db_error');
    }
    mysqli_stmt_bind_result($stmt, $clientUserId);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found) {
        testimonials_admin_error('not_found', 404);
    }

    $now = date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($conexion, "UPDATE testimonials SET status = 'approved', approved_at = ?, approved_by = ?, updated_at = ? WHERE id = ?");
    if (!$stmt) {
        testimonials_admin_error('db_error');
    }
    mysqli_stmt_bind_param($stmt, 'sisi', $now, $adminUserId, $now, $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        testimonials_admin_error('db_error');
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion, "UPDATE testimonials SET status = 'archived', updated_at = ? WHERE client_user_id = ? AND id <> ? AND status = 'approved'");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sii', $now, $clientUserId, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    testimonials_admin_ok(['status' => 'approved']);
}

if ($action === 'reject') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        testimonials_admin_error('invalid_id');
    }
    $now = date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($conexion, "UPDATE testimonials SET status = 'rejected', updated_at = ? WHERE id = ?");
    if (!$stmt) {
        testimonials_admin_error('db_error');
    }
    mysqli_stmt_bind_param($stmt, 'si', $now, $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        testimonials_admin_error('db_error');
    }
    mysqli_stmt_close($stmt);
    testimonials_admin_ok(['status' => 'rejected']);
}

testimonials_admin_error('invalid_action');
