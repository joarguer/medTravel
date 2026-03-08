<?php
session_start();
include("../include/conexion.php");
require_once __DIR__ . '/../include/roles.php';
require_once __DIR__ . '/../../inc/blog_header.php';

$is_admin = is_role_admin_session();
$can_manage_blog_header = $is_admin || user_can(PERM_CONTENT_MANAGE);
$tipo = $_POST['tipo'] ?? '';

function blog_header_json_exit($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function blog_header_remove_managed_file($storedPath)
{
    $storedPath = trim((string)$storedPath);
    if ($storedPath === '' || preg_match('~^https?://~i', $storedPath) || strpos($storedPath, '//') === 0) {
        return;
    }

    $cleaned = preg_replace('/\\?.*$/', '', $storedPath);
    $cleaned = str_replace('\\', '/', $cleaned);
    $cleaned = ltrim($cleaned, '/');
    if ($cleaned === '' || strpos($cleaned, 'img/site/blog/') !== 0 || strpos($cleaned, '..') !== false) {
        return;
    }

    $rootDir = dirname(__DIR__, 2);
    $uploadsDir = realpath($rootDir . '/img/site/blog');
    if (!$uploadsDir) {
        return;
    }

    $targetPath = $rootDir . '/' . $cleaned;
    $resolvedPath = realpath($targetPath);
    if ($resolvedPath && strpos(str_replace('\\', '/', $resolvedPath), str_replace('\\', '/', $uploadsDir)) === 0 && is_file($resolvedPath)) {
        @unlink($resolvedPath);
    }
}

function blog_header_get_or_create($conexion)
{
    if (!$conexion || !mt_blog_header_table_exists($conexion)) {
        return mt_blog_header_defaults();
    }

    $header = mt_blog_fetch_header($conexion);
    if (!empty($header['id'])) {
        return $header;
    }

    $defaults = mt_blog_header_defaults();
    $stmt = mysqli_prepare($conexion, "INSERT INTO blog_header (title, subtitle, bg_image, activo) VALUES (?, ?, ?, 0)");
    if (!$stmt) {
        return $defaults;
    }
    mysqli_stmt_bind_param($stmt, 'sss', $defaults['title'], $defaults['subtitle'], $defaults['bg_image']);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return $defaults;
    }
    mysqli_stmt_close($stmt);

    return mt_blog_fetch_header($conexion);
}

if (!$can_manage_blog_header) {
    blog_header_json_exit(['status' => 'error', 'message' => 'Not authorized'], 403);
}

if (!$conexion) {
    blog_header_json_exit(['status' => 'error', 'message' => 'No DB connection'], 500);
}

if (!mt_blog_header_table_exists($conexion)) {
    blog_header_json_exit(['status' => 'error', 'message' => 'The blog_header table does not exist yet. Run the SQL migration first.'], 409);
}

if ($tipo === 'get_header') {
    blog_header_json_exit(['status' => 'ok', 'header' => blog_header_get_or_create($conexion)]);
}

if ($tipo === 'save_header') {
    $header = blog_header_get_or_create($conexion);
    $id = (int)($header['id'] ?? 0);
    if ($id <= 0) {
        blog_header_json_exit(['status' => 'error', 'message' => 'Unable to initialize blog header settings.'], 500);
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $bgImage = trim((string)($_POST['bg_image'] ?? ''));

    if ($title === '') {
        $title = 'Our Blog';
    }
    if (mb_strlen($title, 'UTF-8') > 255) {
        blog_header_json_exit(['status' => 'error', 'message' => 'The header title is too long.'], 422);
    }
    if (mb_strlen($subtitle, 'UTF-8') > 1000) {
        blog_header_json_exit(['status' => 'error', 'message' => 'The header subtitle is too long.'], 422);
    }

    if ($bgImage !== '' && (preg_match('~^https?://~i', $bgImage) || strpos($bgImage, '//') === 0)) {
        blog_header_json_exit(['status' => 'error', 'message' => 'External image URLs are not allowed for the blog header.'], 422);
    }

    $bgImage = preg_replace('/\\?.*$/', '', $bgImage);
    $bgImage = str_replace('\\', '/', (string)$bgImage);
    $bgImage = ltrim((string)$bgImage, '/');
    if ($bgImage !== '' && (strpos($bgImage, 'img/site/blog/') !== 0 || strpos($bgImage, '..') !== false)) {
        blog_header_json_exit(['status' => 'error', 'message' => 'The blog header image path is invalid. Use the upload control.'], 422);
    }

    $stmt = mysqli_prepare($conexion, "UPDATE blog_header SET title = ?, subtitle = ?, bg_image = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        blog_header_json_exit(['status' => 'error', 'message' => mysqli_error($conexion)], 500);
    }
    mysqli_stmt_bind_param($stmt, 'sssi', $title, $subtitle, $bgImage, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        blog_header_json_exit(['status' => 'error', 'message' => mysqli_error($conexion)], 500);
    }

    blog_header_json_exit(['status' => 'ok', 'header' => mt_blog_fetch_header($conexion)]);
}

if ($tipo === 'upload_header_image') {
    $header = blog_header_get_or_create($conexion);
    $id = (int)($header['id'] ?? 0);
    if ($id <= 0) {
        blog_header_json_exit(['status' => 'error', 'message' => 'Unable to initialize blog header settings.'], 500);
    }

    if (!isset($_FILES['image']) || !is_array($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        blog_header_json_exit(['status' => 'error', 'message' => 'Error uploading header image.'], 422);
    }

    $file = $_FILES['image'];
    $maxBytes = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) {
        blog_header_json_exit(['status' => 'error', 'message' => 'The header image must be 5MB or smaller.'], 422);
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
        blog_header_json_exit(['status' => 'error', 'message' => 'Only JPG, PNG, GIF, or WEBP images are allowed.'], 422);
    }

    $uploadDir = '../../img/site/blog/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        blog_header_json_exit(['status' => 'error', 'message' => 'Unable to create the blog header directory.'], 500);
    }
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        blog_header_json_exit(['status' => 'error', 'message' => 'The blog header directory is not writable.'], 500);
    }

    $filename = 'blog_header_' . time() . '.' . $allowed[$mime];
    $targetPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        blog_header_json_exit(['status' => 'error', 'message' => 'Unable to move the uploaded image.'], 500);
    }

    $storedPath = 'img/site/blog/' . $filename;
    $stmt = mysqli_prepare($conexion, "UPDATE blog_header SET bg_image = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        @unlink($targetPath);
        blog_header_json_exit(['status' => 'error', 'message' => mysqli_error($conexion)], 500);
    }
    mysqli_stmt_bind_param($stmt, 'si', $storedPath, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        @unlink($targetPath);
        blog_header_json_exit(['status' => 'error', 'message' => mysqli_error($conexion)], 500);
    }

    blog_header_remove_managed_file($header['bg_image'] ?? '');
    blog_header_json_exit(['status' => 'ok', 'path' => $storedPath, 'header' => mt_blog_fetch_header($conexion)]);
}

blog_header_json_exit(['status' => 'error', 'message' => 'Invalid operation'], 400);
