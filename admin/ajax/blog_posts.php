<?php
session_start();
include("../include/conexion.php");
require_once __DIR__ . '/../include/roles.php';

$is_admin = is_role_admin_session();
$can_manage_all_posts = $is_admin || user_can(PERM_CONTENT_MANAGE);
$provider_id = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
$current_user_id = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;
$tipo = $_POST['tipo'] ?? '';

function json_exit($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!$conexion) {
    json_exit(['status' => 'error', 'message' => 'No DB connection']);
}

if (!$can_manage_all_posts && $provider_id <= 0) {
    json_exit(['status' => 'error', 'message' => 'Provider scope not configured']);
}

// Helpers
function sanitize_text($txt) {
    return trim($txt ?? '');
}

function provider_exists($conexion, $provider_id) {
    $provider_id = (int)$provider_id;
    if ($provider_id <= 0) {
        return false;
    }
    $stmt = mysqli_prepare($conexion, "SELECT id FROM providers WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $found_id);
    $exists = mysqli_stmt_fetch($stmt) ? true : false;
    mysqli_stmt_close($stmt);
    return $exists;
}

function current_user_display_name($conexion, $current_user_id) {
    $current_user_id = (int)$current_user_id;
    if ($current_user_id <= 0) {
        return '';
    }
    $stmt = mysqli_prepare($conexion, "SELECT COALESCE(NULLIF(TRIM(nombre), ''), NULLIF(TRIM(usuario), ''), '') AS display_name FROM usuarios WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $current_user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $display_name);
    $resolved = mysqli_stmt_fetch($stmt) ? trim((string)$display_name) : '';
    mysqli_stmt_close($stmt);
    return $resolved;
}

function current_provider_name($conexion, $provider_id) {
    $provider_id = (int)$provider_id;
    if ($provider_id <= 0) {
        return '';
    }
    $stmt = mysqli_prepare($conexion, "SELECT COALESCE(NULLIF(TRIM(name), ''), '') AS provider_name FROM providers WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $provider_name);
    $resolved = mysqli_stmt_fetch($stmt) ? trim((string)$provider_name) : '';
    mysqli_stmt_close($stmt);
    return $resolved;
}

function normalize_provider_author_name($conexion, $current_user_id, $provider_id) {
    $display_name = current_user_display_name($conexion, $current_user_id);
    if ($display_name !== '') {
        return $display_name;
    }
    $provider_name = current_provider_name($conexion, $provider_id);
    if ($provider_name !== '') {
        return $provider_name;
    }
    return 'Specialist Contributor';
}

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text ?: uniqid('post-');
}

function fetch_blog_post($conexion, $id) {
    $stmt = mysqli_prepare($conexion, "SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $post = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $post;
}

function blog_posts_has_author_user_id($conexion) {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $hasColumn = false;
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM blog_posts LIKE 'author_user_id'");
    if ($res && mysqli_num_rows($res) > 0) {
        $hasColumn = true;
    }
    if ($res) {
        mysqli_free_result($res);
    }

    return $hasColumn;
}

function blog_posts_has_video_url($conexion) {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $hasColumn = false;
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM blog_posts LIKE 'video_url'");
    if ($res && mysqli_num_rows($res) > 0) {
        $hasColumn = true;
    }
    if ($res) {
        mysqli_free_result($res);
    }

    return $hasColumn;
}

function blog_posts_has_video_file($conexion) {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $hasColumn = false;
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM blog_posts LIKE 'video_file'");
    if ($res && mysqli_num_rows($res) > 0) {
        $hasColumn = true;
    }
    if ($res) {
        mysqli_free_result($res);
    }

    return $hasColumn;
}

function normalize_blog_video_url($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('~^https?://~i', $url)) {
        return false;
    }

    $parts = @parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return false;
    }

    $host = strtolower((string)$parts['host']);
    $host = preg_replace('~^www\.~', '', $host);
    $path = isset($parts['path']) ? trim((string)$parts['path']) : '';
    $pathSegments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

    if ($host === 'youtu.be') {
        $videoId = $pathSegments[0] ?? '';
        return preg_match('~^[A-Za-z0-9_-]{11}$~', $videoId) ? 'https://www.youtube.com/watch?v=' . $videoId : false;
    }

    if (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
        $videoId = '';
        if ($path === '/watch' && !empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $videoId = trim((string)($query['v'] ?? ''));
        } elseif (($pathSegments[0] ?? '') === 'embed' || ($pathSegments[0] ?? '') === 'shorts') {
            $videoId = trim((string)($pathSegments[1] ?? ''));
        }
        return preg_match('~^[A-Za-z0-9_-]{11}$~', $videoId) ? 'https://www.youtube.com/watch?v=' . $videoId : false;
    }

    if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
        $videoId = '';
        if (($pathSegments[0] ?? '') === 'video') {
            $videoId = trim((string)($pathSegments[1] ?? ''));
        } else {
            $videoId = trim((string)($pathSegments[count($pathSegments) - 1] ?? ''));
        }
        return preg_match('~^\d+$~', $videoId) ? 'https://vimeo.com/' . $videoId : false;
    }

    return false;
}

function normalize_blog_video_file_path($path) {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $path);
    $normalized = ltrim($normalized, '/');
    $normalized = preg_replace('~^(\.\./)+~', '', $normalized);

    if (!preg_match('~^img/blog/videos/[A-Za-z0-9._-]+\.mp4(?:\?[A-Za-z0-9]+)?$~i', $normalized)) {
        return false;
    }

    return $normalized;
}

function blog_random_suffix($length) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function blog_upload_error_message($errorCode) {
    switch ((int)$errorCode) {
        case UPLOAD_ERR_OK:
            return '';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the server upload limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'The upload was only partially completed.';
        case UPLOAD_ERR_NO_FILE:
            return 'No video file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Temporary upload directory is missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not write the uploaded file.';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension blocked the upload.';
        default:
            return 'Unable to process the uploaded file.';
    }
}

function blog_remove_managed_video_file($path) {
    $normalized = normalize_blog_video_file_path($path);
    if ($normalized === false || $normalized === '') {
        return;
    }

    $cleanPath = preg_replace('~\?.*$~', '', $normalized);
    $fullPath = realpath(__DIR__ . '/../../' . $cleanPath);
    $basePath = realpath(__DIR__ . '/../../img/blog/videos');
    if ($fullPath && $basePath && strpos($fullPath, $basePath) === 0 && is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function normalize_blog_cover_image_path($path) {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $path) || strpos($path, '//') === 0) {
        return false;
    }

    $normalized = str_replace('\\', '/', $path);
    $normalized = ltrim($normalized, '/');
    $normalized = preg_replace('~^(\.\./)+~', '', $normalized);

    if (!preg_match('~^img/blog/cover_[A-Za-z0-9._-]+\.(jpg|jpeg|png|gif|webp)(?:\?[A-Za-z0-9]+)?$~i', $normalized)) {
        return false;
    }

    return $normalized;
}

function blog_remove_managed_cover_image($path) {
    $normalized = normalize_blog_cover_image_path($path);
    if ($normalized === false || $normalized === '') {
        return;
    }

    $cleanPath = preg_replace('~\?.*$~', '', $normalized);
    $fullPath = realpath(__DIR__ . '/../../' . $cleanPath);
    $basePath = realpath(__DIR__ . '/../../img/blog');
    if ($fullPath && $basePath && strpos($fullPath, $basePath) === 0 && is_file($fullPath)) {
        @unlink($fullPath);
    }
}

// List posts (admin: all, provider: own)
if ($tipo === 'list') {
    global $conexion, $can_manage_all_posts, $provider_id;
    $rows = [];
    $sql = "SELECT bp.id, bp.title, bp.status, bp.created_at, bp.updated_at, bp.published_at, bp.provider_id,
                   bp.author_name,
                   COALESCE(p.name, '') AS provider_name
            FROM blog_posts bp
            LEFT JOIN providers p ON bp.provider_id = p.id";
    if (!$can_manage_all_posts && $provider_id) {
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
    $post = fetch_blog_post($conexion, $id);
    if (!$post) json_exit(['status' => 'error', 'message' => 'Not found']);
    if (!$can_manage_all_posts && intval($post['provider_id']) !== $provider_id) {
        json_exit(['status' => 'error', 'message' => 'Not authorized']);
    }
    json_exit(['status' => 'ok', 'post' => $post]);
}

// Save (create/update)
if ($tipo === 'save') {
    $hasAuthorUserId = blog_posts_has_author_user_id($conexion);
    $hasVideoUrl = blog_posts_has_video_url($conexion);
    $hasVideoFile = blog_posts_has_video_file($conexion);
    $id = intval($_POST['id'] ?? 0);
    $title = sanitize_text($_POST['title'] ?? '');
    $slug = slugify($_POST['slug'] ?? $title);
    $excerpt = sanitize_text($_POST['excerpt'] ?? '');
    $cover_image = sanitize_text($_POST['cover_image'] ?? '');
    $video_url = trim((string)($_POST['video_url'] ?? ''));
    $video_file = trim((string)($_POST['video_file'] ?? ''));
    $body = $_POST['body'] ?? '';
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $author_name = sanitize_text($_POST['author_name'] ?? ($_SESSION['nombre_usuario'] ?? 'MedTravel'));
    $post_provider_id = $can_manage_all_posts ? intval($_POST['provider_id'] ?? $provider_id) : $provider_id;
    $posted_author_user_id = intval($_POST['author_user_id'] ?? 0);
    $author_user_id = null;
    $existing_post = null;

    if ($title === '' || $body === '') {
        json_exit(['status' => 'error', 'message' => 'Title and body are required']);
    }

    if ($can_manage_all_posts) {
        if ($post_provider_id > 0 && !provider_exists($conexion, $post_provider_id)) {
            json_exit(['status' => 'error', 'message' => 'Selected medical contributor does not exist.']);
        }
        if ($author_name === '') {
            $author_name = 'MedTravel Editorial Team';
        }
    } else {
        $post_provider_id = (int)$provider_id;
        $author_name = normalize_provider_author_name($conexion, $current_user_id, $post_provider_id);
    }

    if ($hasVideoUrl) {
        $normalized_video_url = normalize_blog_video_url($video_url);
        if ($normalized_video_url === false) {
            json_exit(['status' => 'error', 'message' => 'Video URL must be a valid YouTube or Vimeo link']);
        }
        $video_url = $normalized_video_url;
    } else {
        $video_url = '';
    }

    if ($hasVideoFile) {
        $normalized_video_file = normalize_blog_video_file_path($video_file);
        if ($normalized_video_file === false) {
            json_exit(['status' => 'error', 'message' => 'Uploaded video path is invalid. Use the MP4 upload control.']);
        }
        $video_file = $normalized_video_file;
    } else {
        $video_file = '';
    }

    // Prevent providers assigning others
    if (!$can_manage_all_posts && $post_provider_id !== $provider_id) {
        $post_provider_id = $provider_id;
    }

    if ($id > 0) {
        $existing_post = fetch_blog_post($conexion, $id);
        if (!$existing_post) {
            json_exit(['status' => 'error', 'message' => 'Not found']);
        }

        // check ownership
        if (!$can_manage_all_posts) {
            if (intval($existing_post['provider_id']) !== $provider_id) {
                json_exit(['status' => 'error', 'message' => 'Not authorized']);
            }
        }

        if ($hasAuthorUserId) {
            if (!$can_manage_all_posts) {
                $author_user_id = $current_user_id > 0 ? $current_user_id : null;
            } elseif ($posted_author_user_id > 0) {
                $author_user_id = $posted_author_user_id;
            } elseif (!empty($existing_post['author_user_id'])) {
                $author_user_id = intval($existing_post['author_user_id']);
            }
            if ($hasVideoUrl && $hasVideoFile) {
                $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_user_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, video_url=?, video_file=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'iissssssssssi', $post_provider_id, $author_user_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_url, $video_file, $status, $status, $id);
            } elseif ($hasVideoUrl) {
                $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_user_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, video_url=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'iisssssssssi', $post_provider_id, $author_user_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_url, $status, $status, $id);
            } elseif ($hasVideoFile) {
                $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_user_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, video_file=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'iisssssssssi', $post_provider_id, $author_user_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_file, $status, $status, $id);
            } else {
                $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_user_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'iissssssssi', $post_provider_id, $author_user_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $status, $status, $id);
            }
        } else {
            if ($hasVideoUrl && $hasVideoFile) {
                $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, video_url=?, video_file=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'issssssssssi', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_url, $video_file, $status, $status, $id);
            } elseif ($hasVideoUrl) {
                $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, video_url=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'isssssssssi', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_url, $status, $status, $id);
            } elseif ($hasVideoFile) {
                $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, video_file=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'isssssssssi', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_file, $status, $status, $id);
            } else {
                $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET provider_id=?, author_name=?, title=?, slug=?, excerpt=?, body=?, cover_image=?, status=?, published_at=IF(?='published', COALESCE(published_at, NOW()), NULL) WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'issssssssi', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $status, $status, $id);
            }
        }
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($ok && $hasVideoFile) {
            $oldVideoFile = trim((string)($existing_post['video_file'] ?? ''));
            if ($oldVideoFile !== '' && $oldVideoFile !== $video_file) {
                blog_remove_managed_video_file($oldVideoFile);
            }
        }
        json_exit($ok ? ['status' => 'ok', 'id' => $id] : ['status' => 'error', 'message' => mysqli_error($conexion)]);
    } else {
        if ($hasAuthorUserId) {
            if (!$can_manage_all_posts) {
                $author_user_id = $current_user_id > 0 ? $current_user_id : null;
            } elseif ($posted_author_user_id > 0) {
                $author_user_id = $posted_author_user_id;
            }
            if ($hasVideoUrl && $hasVideoFile) {
                $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_user_id, author_name, title, slug, excerpt, body, cover_image, video_url, video_file, status, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
                mysqli_stmt_bind_param($stmt, 'iissssssssss', $post_provider_id, $author_user_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_url, $video_file, $status, $status);
            } elseif ($hasVideoUrl) {
                $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_user_id, author_name, title, slug, excerpt, body, cover_image, video_url, status, published_at) VALUES (?,?,?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
                mysqli_stmt_bind_param($stmt, 'iisssssssss', $post_provider_id, $author_user_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_url, $status, $status);
            } elseif ($hasVideoFile) {
                $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_user_id, author_name, title, slug, excerpt, body, cover_image, video_file, status, published_at) VALUES (?,?,?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
                mysqli_stmt_bind_param($stmt, 'iisssssssss', $post_provider_id, $author_user_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_file, $status, $status);
            } else {
                $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_user_id, author_name, title, slug, excerpt, body, cover_image, status, published_at) VALUES (?,?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
                mysqli_stmt_bind_param($stmt, 'iissssssss', $post_provider_id, $author_user_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $status, $status);
            }
        } else {
            if ($hasVideoUrl && $hasVideoFile) {
                $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_name, title, slug, excerpt, body, cover_image, video_url, video_file, status, published_at) VALUES (?,?,?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
                mysqli_stmt_bind_param($stmt, 'issssssssss', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_url, $video_file, $status, $status);
            } elseif ($hasVideoUrl) {
                $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_name, title, slug, excerpt, body, cover_image, video_url, status, published_at) VALUES (?,?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
                mysqli_stmt_bind_param($stmt, 'isssssssss', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_url, $status, $status);
            } elseif ($hasVideoFile) {
                $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_name, title, slug, excerpt, body, cover_image, video_file, status, published_at) VALUES (?,?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
                mysqli_stmt_bind_param($stmt, 'isssssssss', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $video_file, $status, $status);
            } else {
                $stmt = mysqli_prepare($conexion, "INSERT INTO blog_posts (provider_id, author_name, title, slug, excerpt, body, cover_image, status, published_at) VALUES (?,?,?,?,?,?,?,?, IF(?='published', NOW(), NULL))");
                mysqli_stmt_bind_param($stmt, 'issssssss', $post_provider_id, $author_name, $title, $slug, $excerpt, $body, $cover_image, $status, $status);
            }
        }
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

    $row = fetch_blog_post($conexion, $id);
    if (!$row) {
        json_exit(['status' => 'error', 'message' => 'Not found']);
    }

    if (!$can_manage_all_posts) {
        if (intval($row['provider_id']) !== $provider_id) {
            json_exit(['status' => 'error', 'message' => 'Not authorized']);
        }
    }

    $ok = mysqli_query($conexion, "DELETE FROM blog_posts WHERE id = $id");
    if (!$ok) {
        json_exit(['status' => 'error', 'message' => mysqli_error($conexion)]);
    }

    blog_remove_managed_cover_image($row['cover_image'] ?? '');
    blog_remove_managed_video_file($row['video_file'] ?? '');

    json_exit(['status' => 'ok']);
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
        if (!$can_manage_all_posts) {
            $post = fetch_blog_post($conexion, $post_id);
            if (!$post || intval($post['provider_id']) !== $provider_id) {
                json_exit(['status' => 'error', 'message' => 'Not authorized']);
            }
        }
        $webPathEsc = mysqli_real_escape_string($conexion, $webPath);
        mysqli_query($conexion, "UPDATE blog_posts SET cover_image = '{$webPathEsc}' WHERE id = {$post_id}");
    }
    json_exit(['status' => 'ok', 'path' => $webPath]);
}

if ($tipo === 'upload_video') {
    $hasVideoFile = blog_posts_has_video_file($conexion);
    if (!$hasVideoFile) {
        json_exit(['status' => 'error', 'message' => 'The video_file column does not exist yet. Run the SQL migration first.']);
    }

    $upload_error = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!isset($_FILES['file']) || !is_array($_FILES['file']) || $upload_error !== UPLOAD_ERR_OK) {
        json_exit([
            'status' => 'error',
            'message' => blog_upload_error_message($upload_error),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ]);
    }

    $file = $_FILES['file'];
    $maxBytes = 25 * 1024 * 1024;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) {
        json_exit(['status' => 'error', 'message' => 'The uploaded MP4 must be 25MB or smaller.']);
    }

    $safe_name = basename((string)$file['name']);
    $extension = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
    if ($extension !== 'mp4') {
        json_exit(['status' => 'error', 'message' => 'Only MP4 video files are allowed.']);
    }

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

    $allowed_mimes = ['video/mp4', 'video/x-m4v', 'application/mp4', 'application/octet-stream'];
    if ($mime !== '' && !in_array($mime, $allowed_mimes, true)) {
        json_exit(['status' => 'error', 'message' => 'The uploaded file is not a valid MP4 video.']);
    }

    $uploadDir = '../../img/blog/videos/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        json_exit(['status' => 'error', 'message' => 'Unable to create the blog video directory.']);
    }
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        json_exit(['status' => 'error', 'message' => 'The blog video directory is not writable.']);
    }

    $filename = 'video_' . time() . '_' . blog_random_suffix(6) . '.mp4';
    $path = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        json_exit(['status' => 'error', 'message' => 'Unable to move the uploaded video file.']);
    }

    $webPath = 'img/blog/videos/' . $filename . '?' . rand();
    $post_id = intval($_POST['post_id'] ?? 0);
    if ($post_id > 0) {
        $post = fetch_blog_post($conexion, $post_id);
        if (!$post) {
            @unlink($path);
            json_exit(['status' => 'error', 'message' => 'Post not found.']);
        }
        if (!$can_manage_all_posts && intval($post['provider_id']) !== $provider_id) {
            @unlink($path);
            json_exit(['status' => 'error', 'message' => 'Not authorized']);
        }

        $stmt = mysqli_prepare($conexion, "UPDATE blog_posts SET video_file = ? WHERE id = ?");
        if (!$stmt) {
            @unlink($path);
            json_exit(['status' => 'error', 'message' => mysqli_error($conexion)]);
        }
        mysqli_stmt_bind_param($stmt, 'si', $webPath, $post_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            @unlink($path);
            json_exit(['status' => 'error', 'message' => mysqli_error($conexion)]);
        }

        $oldVideoFile = trim((string)($post['video_file'] ?? ''));
        if ($oldVideoFile !== '' && $oldVideoFile !== $webPath) {
            blog_remove_managed_video_file($oldVideoFile);
        }
    }

    json_exit(['status' => 'ok', 'path' => $webPath]);
}

json_exit(['status' => 'error', 'message' => 'Tipo no soportado']);
