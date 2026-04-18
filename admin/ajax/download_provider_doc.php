<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('../include/conexion.php');
require_once('../include/roles.php');

require_login_ajax();

$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if ($itemId <= 0) {
    http_response_code(400);
    exit('Parámetro inválido');
}

$isAdmin  = is_role_admin_session();
$roleId   = current_role_id();
$providerId = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;

$scopeId = 0;
if (in_array((int)$roleId, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true) && $providerId > 0) {
    $scopeId = $providerId;
} elseif (!$isAdmin && $providerId > 0) {
    $scopeId = $providerId;
}

if ($scopeId <= 0) {
    http_response_code(403);
    exit('Acceso denegado');
}

$sql = 'SELECT pd.file_path, pd.mime_type, pd.original_filename, pd.file_extension
        FROM provider_documents pd
        JOIN provider_verification_items pvi ON pvi.evidence_document_id = pd.id
        WHERE pvi.id = ? AND pvi.provider_id = ?
        LIMIT 1';

$stmt = mysqli_prepare($conexion, $sql);
if (!$stmt) {
    http_response_code(500);
    exit('Error DB');
}
mysqli_stmt_bind_param($stmt, 'ii', $itemId, $scopeId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$doc = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$doc || empty($doc['file_path'])) {
    http_response_code(404);
    exit('Documento no encontrado');
}

$filePath = '../uploads/provider_documents/' . $doc['file_path'];
if (!file_exists($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Archivo no encontrado en disco');
}

$mime     = $doc['mime_type'] ?: 'application/octet-stream';
$origName = $doc['original_filename'] ?: basename($doc['file_path']);

$inlineMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$disposition = in_array($mime, $inlineMimes, true) ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $origName) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
