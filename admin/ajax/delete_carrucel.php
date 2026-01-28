<?php
session_start();
include('../include/conexion.php');
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');
 
$response = ['ok' => false];
$id_param = $_POST['id'] ?? '';
$id = filter_var($id_param, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    $response['error'] = 'invalid_id';
    echo json_encode($response);
    exit;
}

$img_path = null;
if ($select = mysqli_prepare($conexion, "SELECT img FROM carrucel WHERE id = ? LIMIT 1")) {
    mysqli_stmt_bind_param($select, 'i', $id);
    mysqli_stmt_execute($select);
    mysqli_stmt_bind_result($select, $img_path);
    mysqli_stmt_fetch($select);
    mysqli_stmt_close($select);
} else {
    $response['error'] = 'select_prepare_failed';
    echo json_encode($response);
    exit;
}

$update = mysqli_prepare($conexion, "UPDATE carrucel SET activo = '1' WHERE id = ? LIMIT 1"); // soft delete via activo flag
if (!$update) {
    $response['error'] = 'update_prepare_failed';
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($update, 'i', $id);
if (!mysqli_stmt_execute($update)) {
    $response['error'] = 'update_execute_failed';
    mysqli_stmt_close($update);
    echo json_encode($response);
    exit;
}

if (mysqli_stmt_affected_rows($update) === 0) {
    $response['error'] = 'not_found';
    mysqli_stmt_close($update);
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($update);

if ($img_path) {
    $cleaned = preg_replace('/\\?.*$/', '', $img_path);
    $cleaned = str_replace('\\', '/', $cleaned);
    $cleaned = ltrim($cleaned, '/');
    // Normalize the stored path and enforce it stays under the known uploads folder before unlinking.
    if ($cleaned !== '' && strpos($cleaned, 'img/carrucel/') === 0 && strpos($cleaned, '..') === false) {
        $root_dir = dirname(__DIR__, 2);
        $uploads_dir = realpath($root_dir . '/img/carrucel');
        if ($uploads_dir) {
            $target_path = $root_dir . '/' . $cleaned;
            $resolved_path = realpath($target_path);
            if ($resolved_path && strpos(str_replace('\\', '/', $resolved_path), str_replace('\\', '/', $uploads_dir)) === 0) {
                if (is_file($resolved_path)) {
                    unlink($resolved_path);
                }
            }
        }
    }
}

$response['ok'] = true;
echo json_encode($response);
exit;
