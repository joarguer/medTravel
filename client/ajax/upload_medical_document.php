<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../../admin/include/email_config.php';
require_once __DIR__ . '/../include/client_notifications.php';
require_once __DIR__ . '/../../inc/email_template.php';
require_once __DIR__ . '/../../inc/interaction_email.php';

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

function client_doc_document_label($type)
{
    $map = [
        'passport' => 'Passport',
        'id_card' => 'ID card',
        'medical_history' => 'Medical history',
        'lab_results' => 'Lab results',
        'prescription' => 'Prescription',
        'invoice' => 'Invoice',
        'contract' => 'Contract',
        'consent_form' => 'Consent form',
        'insurance' => 'Insurance',
        'photos' => 'Photos',
        'other' => 'Other'
    ];
    $key = strtolower(trim((string)$type));
    return isset($map[$key]) ? $map[$key] : ($key !== '' ? $key : 'Other');
}

function client_doc_notify_upload($conexion, $threadType, $bookingId, $itemId, $documentType)
{
    if (!function_exists('send_interaction_email')) {
        return;
    }
    $threadType = strtoupper(trim((string)$threadType));
    $bookingId = (int)$bookingId;
    $itemId = (int)$itemId;
    if ($bookingId <= 0) {
        return;
    }

    $meta = interaction_email_request_meta($conexion, $threadType, $bookingId, $itemId);
    $serviceTitle = trim((string)($meta['title'] ?? 'Request #' . $bookingId));
    $destination = trim((string)($meta['subtitle'] ?? ''));
    $docLabel = client_doc_document_label($documentType);
    $actorLabel = interaction_email_actor_label('CLIENT');
    $snippet = 'A medical document was uploaded: ' . $docLabel;

    $subject = 'MedTravel update - ' . $actorLabel . ' uploaded a document for Request #' . $bookingId;
    $contentHtml = '<p><strong>Actor:</strong> ' . htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Request:</strong> #' . $bookingId . '<br>'
        . '<strong>Service:</strong> ' . htmlspecialchars($serviceTitle, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($destination !== '') {
        $contentHtml .= '<p><strong>Destination:</strong> ' . htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $contentHtml .= '<p><strong>Update:</strong> ' . htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') . '</p>';

    $ctaUrl = 'https://medtravel.com.co/admin/app_inbox.php?request_id=' . $bookingId
        . '&thread_type=' . urlencode((string)$meta['thread_type'])
        . '&item_id=' . (int)$meta['item_id'];
    $textBody = "Actor: {$actorLabel}\nRequest: #{$bookingId}\nService: {$serviceTitle}";
    if ($destination !== '') {
        $textBody .= "\nDestination: {$destination}";
    }
    $textBody .= "\nUpdate: {$snippet}\nInbox: {$ctaUrl}";

    $adminEmail = interaction_email_resolve_patientcare_email($conexion);
    $providerEmail = ($threadType === 'ITEM' && $itemId > 0)
        ? interaction_email_fetch_provider_email($conexion, $itemId)
        : '';

    $metaSend = [
        'preheader' => $snippet,
        'cta' => ['text' => 'Open Inbox', 'url' => $ctaUrl],
    ];

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        send_interaction_email($adminEmail, $subject, $contentHtml, $textBody, $metaSend, $conexion);
    }
    if (filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        send_interaction_email($providerEmail, $subject, $contentHtml, $textBody, $metaSend, $conexion);
    }
}

function client_doc_normalize_upload_files($files)
{
    if (!is_array($files) || !isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [[
            'name' => (string)($files['name'] ?? ''),
            'type' => (string)($files['type'] ?? ''),
            'tmp_name' => (string)($files['tmp_name'] ?? ''),
            'error' => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($files['size'] ?? 0),
        ]];
    }

    $normalized = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
            'name' => (string)($files['name'][$i] ?? ''),
            'type' => (string)($files['type'][$i] ?? ''),
            'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
            'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($files['size'][$i] ?? 0),
        ];
    }

    return $normalized;
}

function client_doc_enum_first_value($type)
{
    if (!preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", (string)$type, $m)) {
        return '';
    }
    if (empty($m[1][0])) {
        return '';
    }
    return stripcslashes((string)$m[1][0]);
}

if (!isset($conexion) || !$conexion) {
    client_doc_err('db_not_available', 500);
}
$tableName = 'client_documents';
$tableExists = client_table_exists($conexion, $tableName);
if (!$tableExists) {
    client_doc_err('client_documents_not_available', 409);
}

$bookingRequestIdInput = isset($_POST['booking_request_id']) ? (int)$_POST['booking_request_id'] : 0;
$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$threadType = strtoupper(trim((string)($_POST['thread_type'] ?? '')));
$requiredSchemaCols = ['client_id', 'file_path', 'filename', 'original_filename', 'booking_request_id', 'item_id'];
$existingCols = [];
$showColsSql = "SHOW COLUMNS FROM `client_documents`";
$db = $conexion;
if (!$db) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'db_not_available']);
    exit;
}

$showColsRes = mysqli_query($db, $showColsSql);
if ($showColsRes === false) {
    client_doc_err('db_schema_check_failed', 500);
}

while ($row = mysqli_fetch_assoc($showColsRes)) {
    $field = strtolower(trim((string)($row['Field'] ?? '')));
    if ($field !== '') {
        $existingCols[$field] = true;
    }
}

if (empty($existingCols)) {
    client_doc_err('db_schema_check_failed', 500);
}
$missingCols = [];
foreach ($requiredSchemaCols as $col) {
    if (!isset($existingCols[strtolower($col)])) {
        $missingCols[] = $col;
    }
}

if (!empty($missingCols)) {
    client_doc_err('client_documents_schema_missing', 409);
}

$clientUserId = get_client_user_id();
if ($clientUserId <= 0) {
    client_doc_err('invalid_client', 403);
}

$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    client_doc_err('booking_owner_scope_unavailable', 409);
}

if ($threadType === 'CARE') {
    $itemId = 0;
}
$bookingId = $bookingRequestIdInput > 0 ? $bookingRequestIdInput : $requestId;

if ($bookingId <= 0 && $itemId <= 0) {
    client_doc_err('client_documents_scope_missing', 422);
}

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

$verifySql = "SELECT br.id, " . (client_table_has_column($conexion, 'booking_requests', 'email') ? 'br.email' : "''") . " AS booking_email FROM booking_requests br WHERE br.id = ? AND (" . $ownerScope['sql'] . ")";
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
    client_doc_err('forbidden', 403);
}

$resolvedClientId = 0;
$sessionClientEmail = strtolower(trim((string)client_get_session_email()));
$bookingClientEmail = strtolower(trim((string)($verifyRow['booking_email'] ?? '')));
$resolvedClientEmail = $bookingClientEmail !== '' ? $bookingClientEmail : $sessionClientEmail;

if ($resolvedClientEmail === '' || !client_table_exists($conexion, 'clientes') || !client_table_has_column($conexion, 'clientes', 'email')) {
    client_doc_err('client_not_resolved', 422);
}

$stmtClient = mysqli_prepare($conexion, "SELECT id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
if ($stmtClient) {
    mysqli_stmt_bind_param($stmtClient, 's', $resolvedClientEmail);
    if (!mysqli_stmt_execute($stmtClient)) {
        mysqli_stmt_close($stmtClient);
        client_doc_err('execute_failed', 500);
    }
    $resClient = mysqli_stmt_get_result($stmtClient);
    $rowClient = $resClient ? mysqli_fetch_assoc($resClient) : null;
    mysqli_stmt_close($stmtClient);
    if ($rowClient) {
        $resolvedClientId = (int)($rowClient['id'] ?? 0);
    }
}

if ($resolvedClientId <= 0) {
    $displayName = trim((string)get_client_display_name());
    $nameParts = preg_split('/\s+/', $displayName);
    $fallbackNombre = trim((string)($nameParts[0] ?? 'Client'));
    $fallbackApellido = trim((string)(count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'Client'));

    $requiredCols = [];
    $requiredTypes = [];
    $showClientesCols = mysqli_query($conexion, "SHOW COLUMNS FROM `clientes`");
    if ($showClientesCols) {
        while ($colRow = mysqli_fetch_assoc($showClientesCols)) {
            $field = trim((string)($colRow['Field'] ?? ''));
            $type = strtolower(trim((string)($colRow['Type'] ?? '')));
            $isNullable = strtoupper(trim((string)($colRow['Null'] ?? 'YES')));
            $defaultVal = $colRow['Default'] ?? null;
            $extra = strtolower(trim((string)($colRow['Extra'] ?? '')));

            if ($field === '' || strpos($extra, 'auto_increment') !== false) {
                continue;
            }
            if ($isNullable === 'NO' && $defaultVal === null) {
                $requiredCols[] = $field;
                $requiredTypes[$field] = $type;
            }
        }
    }

    if (empty($requiredCols)) {
        client_doc_err('client_not_resolved', 422);
    }

    $insertCols = [];
    $insertVals = [];
    $insertTypes = '';
    $insertParams = [];

    foreach ($requiredCols as $field) {
        $fieldLower = strtolower($field);
        $type = (string)($requiredTypes[$field] ?? '');
        $value = null;

        if ($fieldLower === 'email') {
            $value = $resolvedClientEmail;
            $insertTypes .= 's';
        } elseif ($fieldLower === 'nombre') {
            $value = $fallbackNombre;
            $insertTypes .= 's';
        } elseif ($fieldLower === 'apellido') {
            $value = $fallbackApellido;
            $insertTypes .= 's';
        } elseif (strpos($type, 'enum(') === 0 || strpos($type, 'set(') === 0) {
            $value = client_doc_enum_first_value($type);
            $insertTypes .= 's';
        } elseif (preg_match('/int|decimal|float|double|real|bit/', $type)) {
            $value = 0;
            $insertTypes .= 'i';
        } elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
            $value = date('Y-m-d H:i:s');
            $insertTypes .= 's';
        } elseif (strpos($type, 'date') !== false) {
            $value = date('Y-m-d');
            $insertTypes .= 's';
        } elseif (strpos($type, 'time') !== false) {
            $value = date('H:i:s');
            $insertTypes .= 's';
        } elseif (strpos($type, 'year') !== false) {
            $value = date('Y');
            $insertTypes .= 's';
        } else {
            $value = '';
            $insertTypes .= 's';
        }

        $insertCols[] = $field;
        $insertVals[] = '?';
        $insertParams[] = $value;
    }

    $insertClienteSql = "INSERT INTO clientes (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
    $stmtInsertCliente = mysqli_prepare($conexion, $insertClienteSql);
    if (!$stmtInsertCliente) {
        client_doc_err('client_not_resolved', 422);
    }
    if (!client_bind_params($stmtInsertCliente, $insertTypes, $insertParams) || !mysqli_stmt_execute($stmtInsertCliente)) {
        mysqli_stmt_close($stmtInsertCliente);
        client_doc_err('client_not_resolved', 422);
    }
    $resolvedClientId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtInsertCliente);
}

if ($resolvedClientId <= 0) {
    client_doc_err('client_not_resolved', 422);
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

if (isset($_FILES['client_doc_files'])) {
    $files = client_doc_normalize_upload_files($_FILES['client_doc_files']);
} elseif (isset($_FILES['document'])) {
    $files = client_doc_normalize_upload_files($_FILES['document']);
} else {
    $files = [];
}

if (empty($files)) {
    client_doc_err('file_required', 422);
}

$maxFileSize = 10 * 1024 * 1024;

$allowedTypes = [
    'application/pdf',
    'application/x-pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];

$documentTypeMaster = strtolower(trim((string)($_POST['document_type'] ?? 'other')));
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
if (!in_array($documentTypeMaster, $allowedDocTypes, true)) {
    $documentTypeMaster = 'other';
}

$titleMaster = trim((string)($_POST['title'] ?? ''));
$descriptionMaster = trim((string)($_POST['description'] ?? ''));

$metaByIndex = [];
$metaRaw = trim((string)($_POST['meta_json'] ?? ''));
if ($metaRaw !== '') {
    $decodedMeta = json_decode($metaRaw, true);
    if (is_array($decodedMeta)) {
        $metaByIndex = $decodedMeta;
    }
}

$uploadRoot = __DIR__ . '/../../uploads/medical_docs/';
$clientDir = $uploadRoot . 'client_' . $clientUserId . '/';
if (!is_dir($clientDir)) {
    if (!mkdir($clientDir, 0755, true)) {
        client_doc_err('upload_dir_not_created', 500);
    }
}

$hasDocumentType = client_table_has_column($conexion, 'client_documents', 'document_type');
$hasFileSize = client_table_has_column($conexion, 'client_documents', 'file_size');
$hasMimeType = client_table_has_column($conexion, 'client_documents', 'mime_type');
$hasFileExtension = client_table_has_column($conexion, 'client_documents', 'file_extension');
$hasTitle = client_table_has_column($conexion, 'client_documents', 'title');
$hasDescription = client_table_has_column($conexion, 'client_documents', 'description');
$hasSharedWithProvider = client_table_has_column($conexion, 'client_documents', 'shared_with_provider');
$hasUploadedBy = client_table_has_column($conexion, 'client_documents', 'uploaded_by');
$hasClientUserId = client_table_has_column($conexion, 'client_documents', 'client_user_id');

$results = [];
$uploadedCount = 0;
$firstUploaded = null;

foreach ($files as $index => $file) {
    $originalFilename = trim((string)($file['name'] ?? ''));
    $fileError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fileError === UPLOAD_ERR_NO_FILE) {
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'file_required',
            'original_filename' => $originalFilename,
        ];
        continue;
    }
    if ($fileError !== UPLOAD_ERR_OK) {
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'upload_error',
            'original_filename' => $originalFilename,
        ];
        continue;
    }

    $fileSize = (int)($file['size'] ?? 0);
    if ($fileSize > $maxFileSize) {
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'file_too_large',
            'original_filename' => $originalFilename,
        ];
        continue;
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'invalid_tmp_file',
            'original_filename' => $originalFilename,
        ];
        continue;
    }

    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedExtensions, true)) {
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'file_extension_not_allowed',
            'original_filename' => $originalFilename,
        ];
        continue;
    }

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $mimeType = (string)(@mime_content_type($tmpName) ?: '');
    }
    if ($mimeType === '') {
        $mimeType = 'application/octet-stream';
    }
    if ($mimeType !== 'application/octet-stream' && !in_array($mimeType, $allowedTypes, true)) {
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'file_type_not_allowed',
            'original_filename' => $originalFilename,
        ];
        continue;
    }

    $meta = is_array($metaByIndex[$index] ?? null) ? $metaByIndex[$index] : [];
    $documentType = strtolower(trim((string)($meta['doc_type'] ?? $meta['document_type'] ?? $documentTypeMaster)));
    if (!in_array($documentType, $allowedDocTypes, true)) {
        $documentType = 'other';
    }
    $title = trim((string)($meta['title'] ?? $titleMaster));
    $description = trim((string)($meta['description'] ?? $descriptionMaster));

    $filename = uniqid('doc_' . $clientUserId . '_') . '.' . $fileExtension;
    $filePath = 'client_' . $clientUserId . '/' . $filename;
    $fullPath = $clientDir . $filename;

    if (!move_uploaded_file($tmpName, $fullPath)) {
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'file_save_failed',
            'original_filename' => (string)($file['name'] ?? ''),
        ];
        continue;
    }

    $columns = [];
    $placeholders = [];
    $types = '';
    $params = [];

    $columns[] = 'client_id';
    $placeholders[] = '?';
    $types .= 'i';
    $params[] = $resolvedClientId;

    if ($hasDocumentType) {
        $columns[] = 'document_type';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $documentType;
    }

    if ($hasClientUserId) {
        $columns[] = 'client_user_id';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $clientUserId;
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
    $params[] = $originalFilename;

    if ($hasFileSize) {
        $columns[] = 'file_size';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $fileSize;
    }

    if ($hasMimeType) {
        $columns[] = 'mime_type';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $mimeType;
    }

    if ($hasFileExtension) {
        $columns[] = 'file_extension';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $fileExtension;
    }

    if ($title !== '' && $hasTitle) {
        $columns[] = 'title';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $title;
    }

    if ($description !== '' && $hasDescription) {
        $columns[] = 'description';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $description;
    }

    if ($hasSharedWithProvider) {
        $columns[] = 'shared_with_provider';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = 1;
    }

    if ($hasUploadedBy) {
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
    if ($itemId > 0) {
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $itemId;
    } else {
        $placeholders[] = 'NULL';
    }

    $insertSql = "INSERT INTO client_documents (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmtInsert = mysqli_prepare($conexion, $insertSql);
    if (!$stmtInsert) {
        @unlink($fullPath);
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'insert_prepare_failed',
            'original_filename' => $originalFilename,
        ];
        continue;
    }

    if (!client_bind_params($stmtInsert, $types, $params) || !mysqli_stmt_execute($stmtInsert)) {
        $err = mysqli_stmt_error($stmtInsert);
        mysqli_stmt_close($stmtInsert);
        @unlink($fullPath);
        $results[] = [
            'index' => $index,
            'ok' => false,
            'message' => 'insert_failed: ' . $err,
            'original_filename' => $originalFilename,
        ];
        continue;
    }

    $documentId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtInsert);
    $uploadedCount++;

    client_doc_notify_upload($conexion, $threadType, $bookingId, $itemId, $documentType);

    $successItem = [
        'index' => $index,
        'ok' => true,
        'document_id' => $documentId,
        'file_path' => $filePath,
        'original_filename' => $originalFilename,
    ];
    if ($firstUploaded === null) {
        $firstUploaded = $successItem;
    }
    $results[] = $successItem;
}

if ($uploadedCount <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'upload_failed',
        'results' => $results,
    ]);
    exit;
}

$warnings = [];
foreach ($results as $r) {
    if (empty($r['ok'])) {
        $warnings[] = $r;
    }
}

$response = [
    'uploaded_count' => $uploadedCount,
    'results' => $results,
];

if ($firstUploaded) {
    $response['document_id'] = (int)($firstUploaded['document_id'] ?? 0);
    $response['file_path'] = (string)($firstUploaded['file_path'] ?? '');
    $response['original_filename'] = (string)($firstUploaded['original_filename'] ?? '');
}

if (!empty($warnings)) {
    $response['warning'] = 'partial_upload';
    $response['warnings'] = $warnings;
}

client_doc_ok($response);
