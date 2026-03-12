<?php
require_once __DIR__ . '/../include/conexion.php';
require_once __DIR__ . '/../include/roles.php';

require_login_ajax();

function table_exists($conexion, $table)
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$table];
}

function table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

$docId = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : (isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0);
if ($docId <= 0) {
    http_response_code(422);
    echo 'invalid_document_id';
    exit;
}

if (!isset($conexion) || !$conexion) {
    http_response_code(500);
    echo 'db_not_available';
    exit;
}

if (!table_exists($conexion, 'client_documents')) {
    http_response_code(409);
    echo 'client_documents_not_available';
    exit;
}

if (!table_has_column($conexion, 'client_documents', 'booking_request_id') || !table_has_column($conexion, 'client_documents', 'item_id')) {
    http_response_code(409);
    echo 'client_documents_scope_missing';
    exit;
}

$isAdminSession = is_role_admin_session() || user_can(PERM_BOOKING_VIEW) || user_can(PERM_BOOKING_MANAGE);
$providerId = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$serviceProviderId = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;

$docSql = "SELECT id, client_id, booking_request_id, item_id, file_path, original_filename, filename, mime_type, shared_with_provider
           FROM client_documents WHERE id = ? LIMIT 1";
$stmtDoc = mysqli_prepare($conexion, $docSql);
if (!$stmtDoc) {
    http_response_code(500);
    echo 'db_prepare_error';
    exit;
}
mysqli_stmt_bind_param($stmtDoc, 'i', $docId);
if (!mysqli_stmt_execute($stmtDoc)) {
    mysqli_stmt_close($stmtDoc);
    http_response_code(500);
    echo 'db_error';
    exit;
}
$resDoc = mysqli_stmt_get_result($stmtDoc);
$docRow = $resDoc ? mysqli_fetch_assoc($resDoc) : null;
mysqli_stmt_close($stmtDoc);
if (!$docRow) {
    http_response_code(404);
    echo 'document_not_found';
    exit;
}

$bookingRequestId = (int)($docRow['booking_request_id'] ?? 0);
$itemId = (int)($docRow['item_id'] ?? 0);
$sharedWithProvider = (int)($docRow['shared_with_provider'] ?? 0) === 1;

if (!$isAdminSession) {
    if (!$sharedWithProvider) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
    if ($bookingRequestId <= 0) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
    if ($providerId <= 0 && $serviceProviderId <= 0) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }

    if (!table_exists($conexion, 'booking_request_items')) {
        http_response_code(409);
        echo 'booking_items_not_available';
        exit;
    }

    $hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasRequestsSoftDelete = table_has_column($conexion, 'booking_requests', 'is_deleted');

    if ($providerId > 0) {
        $scopeSql = "SELECT bri.id
                     FROM booking_request_items bri
                     INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                     WHERE bri.booking_request_id = ? AND bri.item_type = 'medical_offer' AND bri.provider_id = ?";
    } else {
        $scopeSql = "SELECT bri.id
                     FROM booking_request_items bri
                     INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                     WHERE bri.booking_request_id = ? AND bri.item_type = 'complementary_service' AND bri.service_provider_id = ?";
    }

    if ($hasItemsSoftDelete) {
        $scopeSql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $scopeSql .= " AND br.is_deleted = 0";
    }

    if ($itemId > 0) {
        $scopeSql .= " AND bri.id = ?";
    }
    $scopeSql .= " LIMIT 1";

    $stmtScope = mysqli_prepare($conexion, $scopeSql);
    if (!$stmtScope) {
        http_response_code(500);
        echo 'db_prepare_error';
        exit;
    }

    if ($itemId > 0) {
        if ($providerId > 0) {
            mysqli_stmt_bind_param($stmtScope, 'iii', $bookingRequestId, $providerId, $itemId);
        } else {
            mysqli_stmt_bind_param($stmtScope, 'iii', $bookingRequestId, $serviceProviderId, $itemId);
        }
    } else {
        if ($providerId > 0) {
            mysqli_stmt_bind_param($stmtScope, 'ii', $bookingRequestId, $providerId);
        } else {
            mysqli_stmt_bind_param($stmtScope, 'ii', $bookingRequestId, $serviceProviderId);
        }
    }

    if (!mysqli_stmt_execute($stmtScope)) {
        mysqli_stmt_close($stmtScope);
        http_response_code(500);
        echo 'db_error';
        exit;
    }
    $resScope = mysqli_stmt_get_result($stmtScope);
    $scopeRow = $resScope ? mysqli_fetch_assoc($resScope) : null;
    mysqli_stmt_close($stmtScope);

    if (!$scopeRow) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
}

$filePath = trim((string)($docRow['file_path'] ?? ''));
if ($filePath === '') {
    http_response_code(404);
    echo 'file_not_found';
    exit;
}

$normalized = preg_replace('/\\\\+/', '/', $filePath);
$normalized = ltrim($normalized, '/');
if (stripos($normalized, 'uploads/medical_docs/') === 0) {
    $normalized = substr($normalized, strlen('uploads/medical_docs/'));
}
if (stripos($normalized, 'medical_docs/') === 0) {
    $normalized = substr($normalized, strlen('medical_docs/'));
}
$normalized = ltrim((string)$normalized, '/');
if ($normalized === '') {
    http_response_code(404);
    echo 'file_not_found';
    exit;
}

$baseDir = realpath(__DIR__ . '/../../uploads/medical_docs');
if ($baseDir === false) {
    http_response_code(500);
    echo 'uploads_not_available';
    exit;
}

$fullPath = realpath($baseDir . '/' . $normalized);
if ($fullPath === false || strpos($fullPath, $baseDir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    echo 'invalid_path';
    exit;
}

if (!is_file($fullPath)) {
    http_response_code(404);
    echo 'file_not_found';
    exit;
}

$downloadName = (string)($docRow['original_filename'] ?? 'document');
$mimeType = trim((string)($docRow['mime_type'] ?? ''));
if ($mimeType === '') {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fullPath);
    finfo_close($finfo);
    if ($mimeType === '') {
        $mimeType = 'application/octet-stream';
    }
}

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
exit;
