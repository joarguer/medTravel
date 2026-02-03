<?php
session_start();
include("../include/conexion.php");
require_once __DIR__ . '/../include/roles.php';

$is_admin = is_role_admin_session();
$provider_id = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : null;
$tipo = $_POST['tipo'] ?? '';

function json_exit($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!$conexion) {
    json_exit(['status' => 'error', 'message' => 'No DB connection']);
}

// Helpers
function sanitize_text($txt) {
    return trim($txt ?? '');
}

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text ?: uniqid('post-');
}

// List posts (admin: all, provider: own)
if ($tipo === 'list') {
    global $conexion, $is_admin, $provider_id;
    $rows = [];
    $sql = "SELECT bp.id, bp.title, bp.status, bp.created_at, bp.updated_at, bp.published_at, bp.provider_id, 
                   COALESCE(p.name, bp.author_name) AS provider_name
            FROM blog_posts bp
            LEFT JOIN providers p ON bp.provider_id = p.id";
    if (!$is_admin && $provider_id) {
        $sql .= " WHERE bp.provider_id = " . intval($provider_id);
    }
    $sql .= " ORDER BY bp.created_at DESC";
    $res = mysqli_query($conexion, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    json_exit(['status' => 'ok', 'posts' => $rows]);
}

// Get single
if ($tipo === 'get') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) json_exit(['status' => 'error', 'message' => 'Invalid id']);
    $res = mysqli_query($conexion, "SELECT * FROM blog_posts WHERE id = $id LIMIT 1");
    $post = mysqli_fetch_assoc($res);
    if (!$post) json_exit(['status' => 'error', 'message' => 'Not found']);
    if (!$is_admin && $provider_id && intval($post['provider_id']) !== $provider_id) {
        json_exit(['status' => 'error', 'message' => 'Not authorized']);
    }
    json_exit(['status' => 'ok', 'post' => $post]);
}

// Save (create/update)
if ($tipo === 'save') {
    $id = intval($_POST['id'] ?? 0);
    $title = sanitize_text($_POST['title'] ?? '');
    $slug = slugify($_POST['slug'] ?? $title);
    $excerpt = sanitize_text($_POST['excerpt'] ?? '');
    $cover_image = sanitize_text($_POST['cover_image'] ?? '');
    $body = $_POST['body'] ?? '';
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $author_name = sanitize_text($_POST['author_name'] ?? ($_SESSION['nombre_usuario'] ?? 'MedTravel'));
    $post_provider_id = $is_admin ? intval($_POST['provider_id'] ?? $provider_id) : $provider_id;

    if ($title === '' || $body === '') {
        json_exit(['status' => 'error', 'message' => 'Title and body are required']);
    }

    // Prevent providers assigning others
    if (!$is_admin && $provider_id && $post_provider_id !== $provider_id) {
        $post_provider_id = $provider_id;
    }

    if ($id > 0) {
        // check ownership
        if (!$is_admin && $provider_id) {
            $check = mysqli_query($conexion, "SELECT provider_id FROM blog_posts WHERE id = $id");
            $row = mysqli_fetch_assoc($check);
            if (!$row || intval($row['provider_id']) !== $provider_id) {
                json_exit(['status' => 'error', 'message' => 'Not authorized']);
            }
        }
        $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'issssssssi', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $status, $status, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        json_exit($ok ? ['status' => 'ok', 'id' => $id] : ['status' => 'error', 'message' => mysqli_error($conexion)]);
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_name, title, slug, excerpt, body, cover_image, status, published_at) VALUES (?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
        mysqli_stmt_bind_param($stmt, 'issssssss', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $status, $status);
        $ok = mysqli_stmt_execute($stmt);
        $newId = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        json_exit($ok ? ['status' => 'ok', 'id' => $newId] : ['status' => 'error', 'message' => mysqli_error($conexion)]);
    }
}

// Delete (soft delete not implemented, hard delete)
if ($tipo === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) json_exit(['status' => 'error', 'message' => 'Invalid id']);
    if (!$is_admin && $provider_id) {
        $check = mysqli_query($conexion, "SELECT provider_id FROM blog_posts WHERE id = $id");
        $row = mysqli_fetch_assoc($check);
        if (!$row || intval($row['provider_id']) !== $provider_id) {
            json_exit(['status' => 'error', 'message' => 'Not authorized']);
        }
    }
    $ok = mysqli_query($conexion, "DELETE FROM blog_posts WHERE id = $id");
    json_exit($ok ? ['status' => 'ok'] : ['status' => 'error', 'message' => mysqli_error($conexion)]);
}

// Upload cover image (optionally persisting to an existing post)
if ($tipo === 'upload_cover') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_exit(['status' => 'error', 'message' => 'No file']);
    }
    $file = $_FILES['file'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed, true)) {
        json_exit(['status' => 'error', 'message' => 'Tipo de archivo no permitido']);
    }
    $dir = '../../img/blog/';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'cover_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $path = $dir . $filename;
    $webPath = 'img/blog/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        json_exit(['status' => 'error', 'message' => 'Error al mover el archivo']);
    }
    // Si viene post_id, persistir en la tabla
    $post_id = intval($_POST['post_id'] ?? 0);
    if ($post_id > 0) {
        $webPathEsc = mysqli_real_escape_string($conexion, $webPath);
        mysqli_query($conexion, "UPDATE blog_posts SET cover_image = '{$webPathEsc}' WHERE id = {$post_id}");
    }
    json_exit(['status' => 'ok', 'path' => $webPath]);
}

json_exit(['status' => 'error', 'message' => 'Tipo no soportado']);
