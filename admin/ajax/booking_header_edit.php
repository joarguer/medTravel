<?php
session_start();
include("../include/conexion.php");
require_once __DIR__ . '/../include/roles.php';
require_once __DIR__ . '/../../inc/booking_page_header.php';

$is_admin = is_role_admin_session();
$can_manage_booking_header = $is_admin || user_can(PERM_CONTENT_MANAGE);
$tipo = $_POST['tipo'] ?? '';

function booking_header_json_exit($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function booking_header_remove_managed_file($storedPath)
{
    $storedPath = trim((string)$storedPath);
    if ($storedPath === '' || preg_match('~^https?://~i', $storedPath) || strpos($storedPath, '//') === 0) {
        return;
    }

    $cleaned = preg_replace('/\\?.*$/', '', $storedPath);
    $cleaned = str_replace('\\', '/', $cleaned);
    $cleaned = ltrim($cleaned, '/');
    if ($cleaned === '' || strpos($cleaned, 'img/site/booking/') !== 0 || strpos($cleaned, '..') !== false) {
        return;
    }

    $rootDir = dirname(__DIR__, 2);
    $uploadsDir = realpath($rootDir . '/img/site/booking');
    if (!$uploadsDir) {
        return;
    }

    $targetPath = $rootDir . '/' . $cleaned;
    $resolvedPath = realpath($targetPath);
    if ($resolvedPath && strpos(str_replace('\\', '/', $resolvedPath), str_replace('\\', '/', $uploadsDir)) === 0 && is_file($resolvedPath)) {
        @unlink($resolvedPath);
    }
}

function booking_header_get_or_create($conexion)
{
    if (!$conexion || !mt_booking_page_header_table_exists($conexion)) {
        return mt_booking_page_header_defaults();
    }

    $header = mt_booking_page_header_fetch($conexion);
    if (!empty($header['id'])) {
        return $header;
    }

    $defaults = mt_booking_page_header_defaults();
    $stmt = mysqli_prepare($conexion, "INSERT INTO booking_page_header (title, subtitle, bg_image, activo) VALUES (?, ?, ?, 0)");
    if (!$stmt) {
        return $defaults;
    }
    mysqli_stmt_bind_param($stmt, 'sss', $defaults['title'], $defaults['subtitle'], $defaults['bg_image']);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return $defaults;
    }
    mysqli_stmt_close($stmt);

    return mt_booking_page_header_fetch($conexion);
}

if (!$can_manage_booking_header) {
    booking_header_json_exit(['status' => 'error', 'message' => 'Not authorized'], 403);
}

if (!$conexion) {
    booking_header_json_exit(['status' => 'error', 'message' => 'No DB connection'], 500);
}

if (!mt_booking_page_header_table_exists($conexion)) {
    booking_header_json_exit(['status' => 'error', 'message' => 'The booking_page_header table does not exist yet. Run the SQL migration first.'], 409);
}

if ($tipo === 'get_header') {
    booking_header_json_exit(['status' => 'ok', 'header' => booking_header_get_or_create($conexion)]);
}

if ($tipo === 'save_header') {
    $header = booking_header_get_or_create($conexion);
    $id = (int)($header['id'] ?? 0);
    if ($id <= 0) {
        booking_header_json_exit(['status' => 'error', 'message' => 'Unable to initialize booking header settings.'], 500);
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $bgImage = trim((string)($_POST['bg_image'] ?? ''));

    if ($title === '') {
        $title = 'Online Booking';
    }
    if (mb_strlen($title, 'UTF-8') > 255) {
        booking_header_json_exit(['status' => 'error', 'message' => 'The header title is too long.'], 422);
    }
    if (mb_strlen($subtitle, 'UTF-8') > 1000) {
        booking_header_json_exit(['status' => 'error', 'message' => 'The header subtitle is too long.'], 422);
    }

    if ($bgImage !== '' && (preg_match('~^https?://~i', $bgImage) || strpos($bgImage, '//') === 0)) {
        booking_header_json_exit(['status' => 'error', 'message' => 'External image URLs are not allowed for the booking header.'], 422);
    }

    $bgImage = preg_replace('/\\?.*$/', '', $bgImage);
    $bgImage = str_replace('\\', '/', (string)$bgImage);
    $bgImage = ltrim((string)$bgImage, '/');
    if ($bgImage !== '' && (strpos($bgImage, 'img/site/booking/') !== 0 || strpos($bgImage, '..') !== false)) {
        booking_header_json_exit(['status' => 'error', 'message' => 'The booking header image path is invalid. Use the upload control.'], 422);
    }

    $stmt = mysqli_prepare($conexion, "UPDATE booking_page_header SET title = ?, subtitle = ?, bg_image = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        booking_header_json_exit(['status' => 'error', 'message' => mysqli_error($conexion)], 500);
    }
    mysqli_stmt_bind_param($stmt, 'sssi', $title, $subtitle, $bgImage, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        booking_header_json_exit(['status' => 'error', 'message' => mysqli_error($conexion)], 500);
    }

    booking_header_json_exit(['status' => 'ok', 'header' => mt_booking_page_header_fetch($conexion)]);
}

if ($tipo === 'upload_header_image') {
    $header = booking_header_get_or_create($conexion);
    $id = (int)($header['id'] ?? 0);
    if ($id <= 0) {
        booking_header_json_exit(['status' => 'error', 'message' => 'Unable to initialize booking header settings.'], 500);
    }

    if (!isset($_FILES['image']) || !is_array($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        booking_header_json_exit(['status' => 'error', 'message' => 'Error uploading header image.'], 422);
    }

    $file = $_FILES['image'];
    $maxBytes = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) {
        booking_header_json_exit(['status' => 'error', 'message' => 'The header image must be 5MB or smaller.'], 422);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $mime = trim((string)($file['type'] ?? ''));
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($file['tmp_name']);
        if ($detected) {
            $mime = $detected;
        }
    } elseif (function_exists('mime_content_type')) {
        $detected = mime_content_type($file['tmp_name']);
        if ($detected) {
            $mime = $detected;
        }
    }

    if (!isset($allowed[$mime])) {
        booking_header_json_exit(['status' => 'error', 'message' => 'Only JPG, PNG, GIF, or WEBP images are allowed.'], 422);
    }

    $uploadDir = '../../img/site/booking/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        booking_header_json_exit(['status' => 'error', 'message' => 'Unable to create the booking header directory.'], 500);
    }
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        booking_header_json_exit(['status' => 'error', 'message' => 'The booking header directory is not writable.'], 500);
    }

    $filename = 'booking_header_' . time() . '.' . $allowed[$mime];
    $targetPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        booking_header_json_exit(['status' => 'error', 'message' => 'Unable to move the uploaded image.'], 500);
    }

    $storedPath = 'img/site/booking/' . $filename;
    $stmt = mysqli_prepare($conexion, "UPDATE booking_page_header SET bg_image = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        @unlink($targetPath);
        booking_header_json_exit(['status' => 'error', 'message' => mysqli_error($conexion)], 500);
    }
    mysqli_stmt_bind_param($stmt, 'si', $storedPath, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        @unlink($targetPath);
        booking_header_json_exit(['status' => 'error', 'message' => mysqli_error($conexion)], 500);
    }

    booking_header_remove_managed_file($header['bg_image'] ?? '');
    booking_header_json_exit(['status' => 'ok', 'path' => $storedPath, 'header' => mt_booking_page_header_fetch($conexion)]);
}

booking_header_json_exit(['status' => 'error', 'message' => 'Invalid operation'], 400);
