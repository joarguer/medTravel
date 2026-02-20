<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';

function client_doc_err($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function client_doc_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

if (!isset($conexion) || !$conexion) {
    client_doc_err('db_not_available', 500);
}
if (!client_table_exists($conexion, 'client_documents')) {
    client_doc_err('client_documents_not_available', 409);
}

$hasDocRequestId = client_table_has_column($conexion, 'client_documents', 'booking_request_id');
$hasDocItemId = client_table_has_column($conexion, 'client_documents', 'item_id');
if (!$hasDocRequestId || !$hasDocItemId) {
    client_doc_err('client_documents_scope_missing', 409);
}

$clientUserId = get_client_user_id();
if ($clientUserId <= 0) {
    client_doc_err('invalid_client', 403);
}

$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    client_doc_err('booking_owner_scope_unavailable', 409);
}

$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$bookingId = $requestId;

$hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
$hasRequestsSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');

if ($bookingId <= 0 && $itemId > 0 && client_table_exists($conexion, 'booking_request_items')) {
    $sql = "SELECT bri.booking_request_id
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if ($stmt) {
        $types = 'i' . $ownerScope['types'];
        $params = array_merge([$itemId], $ownerScope['params']);
        if (client_bind_params($stmt, $types, $params) && mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            if ($row) {
                $bookingId = (int)($row['booking_request_id'] ?? 0);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if ($bookingId <= 0) {
    client_doc_err('invalid_booking_id', 422);
}

$verifySql = "SELECT br.id FROM booking_requests br WHERE br.id = ? AND (" . $ownerScope['sql'] . ")";
if ($hasRequestsSoftDelete) {
    $verifySql .= " AND br.is_deleted = 0";
}
$verifySql .= " LIMIT 1";
$stmtVerify = mysqli_prepare($conexion, $verifySql);
if (!$stmtVerify) {
    client_doc_err('prepare_failed', 500);
}
$verifyTypes = 'i' . $ownerScope['types'];
$verifyParams = array_merge([$bookingId], $ownerScope['params']);
if (!client_bind_params($stmtVerify, $verifyTypes, $verifyParams) || !mysqli_stmt_execute($stmtVerify)) {
    mysqli_stmt_close($stmtVerify);
    client_doc_err('execute_failed', 500);
}
$verifyRes = mysqli_stmt_get_result($stmtVerify);
$verifyRow = $verifyRes ? mysqli_fetch_assoc($verifyRes) : null;
mysqli_stmt_close($stmtVerify);
if (!$verifyRow) {
    client_doc_err('request_not_found', 404);
}

if ($itemId > 0 && client_table_exists($conexion, 'booking_request_items')) {
    $itemSql = "SELECT bri.id
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                WHERE bri.id = ? AND bri.booking_request_id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $itemSql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $itemSql .= " AND br.is_deleted = 0";
    }
    $itemSql .= " LIMIT 1";
    $stmtItem = mysqli_prepare($conexion, $itemSql);
    if (!$stmtItem) {
        client_doc_err('prepare_failed', 500);
    }
    $itemTypes = 'ii' . $ownerScope['types'];
    $itemParams = array_merge([$itemId, $bookingId], $ownerScope['params']);
    if (!client_bind_params($stmtItem, $itemTypes, $itemParams) || !mysqli_stmt_execute($stmtItem)) {
        mysqli_stmt_close($stmtItem);
        client_doc_err('execute_failed', 500);
    }
    $itemRes = mysqli_stmt_get_result($stmtItem);
    $itemRow = $itemRes ? mysqli_fetch_assoc($itemRes) : null;
    mysqli_stmt_close($stmtItem);
    if (!$itemRow) {
        client_doc_err('item_not_found', 404);
    }
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
    client_doc_err('file_required', 422);
}

$file = $_FILES['document'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    client_doc_err('upload_error', 400);
}

$maxFileSize = 10 * 1024 * 1024;
if ($file['size'] > $maxFileSize) {
    client_doc_err('file_too_large', 422);
}

$allowedTypes = [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes, true)) {
    client_doc_err('file_type_not_allowed', 422);
}

$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions, true)) {
    client_doc_err('file_extension_not_allowed', 422);
}

$documentType = strtolower(trim((string)($_POST['document_type'] ?? 'other')));
$allowedDocTypes = [
    'passport',
    'id_card',
    'medical_history',
    'lab_results',
    'prescription',
    'invoice',
    'contract',
    'consent_form',
    'insurance',
    'photos',
    'other'
];
if (!in_array($documentType, $allowedDocTypes, true)) {
    $documentType = 'other';
}

$uploadRoot = __DIR__ . '/../../uploads/medical_docs/';
$clientDir = $uploadRoot . 'client_' . $clientUserId . '/';
if (!is_dir($clientDir)) {
    if (!mkdir($clientDir, 0755, true)) {
        client_doc_err('upload_dir_not_created', 500);
    }
}

$filename = uniqid('doc_' . $clientUserId . '_') . '.' . $fileExtension;
$filePath = 'client_' . $clientUserId . '/' . $filename;
$fullPath = $clientDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    client_doc_err('file_save_failed', 500);
}

$requiredCols = ['client_id', 'file_path', 'filename', 'original_filename'];
foreach ($requiredCols as $col) {
    if (!client_table_has_column($conexion, 'client_documents', $col)) {
        @unlink($fullPath);
        client_doc_err('client_documents_missing_columns', 409);
    }
}

$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

$columns = [];
$placeholders = [];
$types = '';
$params = [];

$columns[] = 'client_id';
$placeholders[] = '?';
$types .= 'i';
$params[] = $clientUserId;

if (client_table_has_column($conexion, 'client_documents', 'document_type')) {
    $columns[] = 'document_type';
    $placeholders[] = '?';
    $types .= 's';
    $params[] = $documentType;
}

$columns[] = 'file_path';
$placeholders[] = '?';
$types .= 's';
$params[] = $filePath;

$columns[] = 'filename';
$placeholders[] = '?';
$types .= 's';
$params[] = $filename;

$columns[] = 'original_filename';
$placeholders[] = '?';
$types .= 's';
$params[] = (string)$file['name'];

if (client_table_has_column($conexion, 'client_documents', 'file_size')) {
    $columns[] = 'file_size';
    $placeholders[] = '?';
    $types .= 'i';
    $params[] = (int)$file['size'];
}

if (client_table_has_column($conexion, 'client_documents', 'mime_type')) {
    $columns[] = 'mime_type';
    $placeholders[] = '?';
    $types .= 's';
    $params[] = $mimeType;
}

if (client_table_has_column($conexion, 'client_documents', 'file_extension')) {
    $columns[] = 'file_extension';
    $placeholders[] = '?';
    $types .= 's';
    $params[] = $fileExtension;
}

if ($title !== '' && client_table_has_column($conexion, 'client_documents', 'title')) {
    $columns[] = 'title';
    $placeholders[] = '?';
    $types .= 's';
    $params[] = $title;
}

if ($description !== '' && client_table_has_column($conexion, 'client_documents', 'description')) {
    $columns[] = 'description';
    $placeholders[] = '?';
    $types .= 's';
    $params[] = $description;
}

if (client_table_has_column($conexion, 'client_documents', 'shared_with_provider')) {
    $columns[] = 'shared_with_provider';
    $placeholders[] = '?';
    $types .= 'i';
    $params[] = 1;
}

if (client_table_has_column($conexion, 'client_documents', 'uploaded_by')) {
    $columns[] = 'uploaded_by';
    $placeholders[] = '?';
    $types .= 'i';
    $params[] = $clientUserId;
}

$columns[] = 'booking_request_id';
$placeholders[] = '?';
$types .= 'i';
$params[] = $bookingId;

$columns[] = 'item_id';
$placeholders[] = '?';
$types .= 'i';
$params[] = $itemId > 0 ? $itemId : null;

$insertSql = "INSERT INTO client_documents (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
$stmtInsert = mysqli_prepare($conexion, $insertSql);
if (!$stmtInsert) {
    @unlink($fullPath);
    client_doc_err('insert_prepare_failed', 500);
}

if (!client_bind_params($stmtInsert, $types, $params) || !mysqli_stmt_execute($stmtInsert)) {
    $err = mysqli_stmt_error($stmtInsert);
    mysqli_stmt_close($stmtInsert);
    @unlink($fullPath);
    client_doc_err('insert_failed: ' . $err, 500);
}

$documentId = (int)mysqli_insert_id($conexion);
mysqli_stmt_close($stmtInsert);

client_doc_ok([
    'document_id' => $documentId,
    'file_path' => $filePath,
    'original_filename' => (string)$file['name']
]);
