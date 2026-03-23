<?php
include '../include/conexion.php';
require_once '../include/email_config.php';
require_once '../include/roles.php';
require_once '../../inc/inbox_utils.php';
require_once '../../inc/email_template.php';
require_once '../../inc/interaction_email.php';
require_once '../../inc/fee_gate.php';
require_once '../../inc/commission_gate.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

function admin_inbox_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function admin_inbox_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function admin_inbox_notify_message($conexion, $ctx, $senderRole, $message)
{
    if (!function_exists('send_interaction_email')) {
        return;
    }
    $requestId = (int)($ctx['request_id'] ?? 0);
    if ($requestId <= 0) {
        return;
    }
    $threadType = (string)($ctx['thread_type'] ?? '');
    $itemId = (int)($ctx['item_id'] ?? 0);
    $threadId = (string)($ctx['thread_id'] ?? '');
    $clientEmail = interaction_email_fetch_client_email($conexion, $requestId);
    if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $meta = interaction_email_request_meta($conexion, $threadType, $requestId, $itemId);
    $serviceTitle = trim((string)($meta['title'] ?? 'Request #' . $requestId));
    $destination = trim((string)($meta['subtitle'] ?? ''));
    $actorLabel = interaction_email_actor_label($senderRole);
    $snippet = interaction_email_safe_snippet($message, 120);
    if ($snippet === '') {
        $snippet = 'New message received.';
    }

    $subject = 'MedTravel update - ' . $actorLabel . ' message for Request #' . $requestId;
    $contentHtml = '<p><strong>Actor:</strong> ' . htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Request:</strong> #' . $requestId . '<br>'
        . '<strong>Service:</strong> ' . htmlspecialchars($serviceTitle, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($destination !== '') {
        $contentHtml .= '<p><strong>Destination:</strong> ' . htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $contentHtml .= '<p><strong>Message:</strong> ' . htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') . '</p>';

    $ctaUrl = 'https://medtravel.com.co/client/app_inbox.php?thread_id=' . urlencode($threadId);
    $textBody = "Actor: {$actorLabel}\nRequest: #{$requestId}\nService: {$serviceTitle}";
    if ($destination !== '') {
        $textBody .= "\nDestination: {$destination}";
    }
    $textBody .= "\nMessage: {$snippet}\nInbox: {$ctaUrl}";

    $metaSend = [
        'preheader' => $snippet,
        'cta' => ['text' => 'Open Inbox', 'url' => $ctaUrl],
    ];

    send_interaction_email($clientEmail, $subject, $contentHtml, $textBody, $metaSend, $conexion);
}

function admin_inbox_normalize_upload_files($files)
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

function admin_inbox_array_value($value, $index, $default = '')
{
    if (is_array($value)) {
        return isset($value[$index]) ? (string)$value[$index] : (string)$default;
    }
    return $index === 0 ? (string)$value : (string)$default;
}

function admin_inbox_document_title_fallback($filename)
{
    $filename = trim((string)$filename);
    if ($filename === '') {
        return 'Documento';
    }
    $title = preg_replace('/\.[a-z0-9]{2,8}$/i', '', $filename);
    $title = preg_replace('/[_\-]+/', ' ', (string)$title);
    $title = trim((string)preg_replace('/\s+/', ' ', (string)$title));
    return $title !== '' ? $title : $filename;
}

function admin_inbox_normalize_document_type($value)
{
    $key = strtolower(trim((string)$value));
    $map = [
        'history' => 'medical_history',
        'medical_history' => 'medical_history',
        'labs' => 'lab_results',
        'lab_results' => 'lab_results',
        'imaging' => 'diagnostic_imaging',
        'diagnostic_imaging' => 'diagnostic_imaging',
        'photos' => 'photos',
        'quote' => 'quote',
        'consent_form' => 'consent_form',
        'medical_order' => 'medical_order',
        'prescription' => 'prescription',
        'administrative_document' => 'administrative_document',
        'invoice' => 'administrative_document',
        'contract' => 'administrative_document',
        'insurance' => 'administrative_document',
        'passport' => 'administrative_document',
        'id_card' => 'administrative_document',
        'other' => 'other',
    ];
    return isset($map[$key]) ? $map[$key] : 'other';
}

function admin_inbox_resolve_document_owner($conexion, $bookingRequestId)
{
    $bookingRequestId = (int)$bookingRequestId;
    $clientUserId = 0;
    $clientId = 0;
    $clientEmail = '';
    if ($bookingRequestId <= 0 || !inbox_table_exists($conexion, 'booking_requests')) {
        return ['client_user_id' => 0, 'client_id' => 0, 'client_email' => ''];
    }

    $hasBrClientUserId = inbox_table_has_column($conexion, 'booking_requests', 'client_user_id');
    $hasBrEmail = inbox_table_has_column($conexion, 'booking_requests', 'email');
    $selectCols = $hasBrClientUserId ? 'client_user_id' : 'NULL AS client_user_id';
    $selectCols .= $hasBrEmail ? ', email' : ", '' AS email";
    $stmtClient = mysqli_prepare($conexion, "SELECT {$selectCols} FROM booking_requests WHERE id = ? LIMIT 1");
    if ($stmtClient) {
        mysqli_stmt_bind_param($stmtClient, 'i', $bookingRequestId);
        if (mysqli_stmt_execute($stmtClient)) {
            $resClient = mysqli_stmt_get_result($stmtClient);
            $rowClient = $resClient ? mysqli_fetch_assoc($resClient) : null;
            if ($rowClient) {
                $clientUserId = (int)($rowClient['client_user_id'] ?? 0);
                $clientEmail = trim((string)($rowClient['email'] ?? ''));
            }
        }
        mysqli_stmt_close($stmtClient);
    }

    if (inbox_table_exists($conexion, 'clientes') && inbox_table_has_column($conexion, 'clientes', 'email')) {
        $clientesHasClientUserId = inbox_table_has_column($conexion, 'clientes', 'client_user_id');
        $clientesHasUserId = inbox_table_has_column($conexion, 'clientes', 'user_id');
        $clientesMapCol = $clientesHasClientUserId ? 'client_user_id' : ($clientesHasUserId ? 'user_id' : '');

        if ($clientEmail !== '') {
            $clientSelect = $clientesMapCol !== '' ? ($clientesMapCol . ' AS client_user_id') : '0 AS client_user_id';
            $stmtLookup = mysqli_prepare($conexion, "SELECT {$clientSelect}, id AS client_id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
            if ($stmtLookup) {
                mysqli_stmt_bind_param($stmtLookup, 's', $clientEmail);
                if (mysqli_stmt_execute($stmtLookup)) {
                    $resLookup = mysqli_stmt_get_result($stmtLookup);
                    $rowLookup = $resLookup ? mysqli_fetch_assoc($resLookup) : null;
                    if ($rowLookup) {
                        if ($clientUserId <= 0) {
                            $clientUserId = (int)($rowLookup['client_user_id'] ?? 0);
                        }
                        $clientId = (int)($rowLookup['client_id'] ?? 0);
                    }
                }
                mysqli_stmt_close($stmtLookup);
            }
        }

        if ($clientId <= 0 && $clientUserId > 0 && $clientesMapCol !== '') {
            $stmtByUser = mysqli_prepare($conexion, "SELECT id FROM clientes WHERE {$clientesMapCol} = ? ORDER BY id DESC LIMIT 1");
            if ($stmtByUser) {
                mysqli_stmt_bind_param($stmtByUser, 'i', $clientUserId);
                if (mysqli_stmt_execute($stmtByUser)) {
                    $resByUser = mysqli_stmt_get_result($stmtByUser);
                    $rowByUser = $resByUser ? mysqli_fetch_assoc($resByUser) : null;
                    if ($rowByUser) {
                        $clientId = (int)($rowByUser['id'] ?? 0);
                    }
                }
                mysqli_stmt_close($stmtByUser);
            }
        }
    }

    if ($clientId <= 0 && $clientEmail !== '') {
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

        if (!empty($requiredCols)) {
            $insertCols = [];
            $insertVals = [];
            $insertTypes = '';
            $insertParams = [];

            foreach ($requiredCols as $field) {
                $fieldLower = strtolower($field);
                $type = (string)($requiredTypes[$field] ?? '');
                $value = null;

                if ($fieldLower === 'email') {
                    $value = $clientEmail;
                    $insertTypes .= 's';
                } elseif ($fieldLower === 'nombre') {
                    $value = 'Client';
                    $insertTypes .= 's';
                } elseif ($fieldLower === 'apellido') {
                    $value = 'Client';
                    $insertTypes .= 's';
                } elseif (($fieldLower === 'client_user_id' || $fieldLower === 'user_id') && $clientUserId > 0) {
                    $value = $clientUserId;
                    $insertTypes .= 'i';
                } elseif (strpos($type, 'enum(') === 0 || strpos($type, 'set(') === 0) {
                    if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $m) && !empty($m[1][0])) {
                        $value = stripcslashes((string)$m[1][0]);
                    } else {
                        $value = '';
                    }
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
            if ($stmtInsertCliente && inbox_bind_stmt_params($stmtInsertCliente, $insertTypes, $insertParams) && mysqli_stmt_execute($stmtInsertCliente)) {
                $clientId = (int)mysqli_insert_id($conexion);
            }
            if ($stmtInsertCliente) {
                mysqli_stmt_close($stmtInsertCliente);
            }
        }
    }

    return [
        'client_user_id' => $clientUserId,
        'client_id' => $clientId,
        'client_email' => $clientEmail,
    ];
}

function admin_inbox_status_label($status)
{
    $status = trim((string)$status);
    if ($status === '') {
        return 'pending';
    }
    if ($status === 'pending_admin' || $status === 'pending_review') {
        return 'pending_provider';
    }
    return $status;
}

function admin_inbox_status_is_update($status)
{
    $status = admin_inbox_status_label($status);
    return in_array($status, [
        'provider_confirmed',
        'provider_rejected',
        'provider_proposed_change',
        'awaiting_client',
        'client_accepted',
        'client_rejected',
        'cancelled',
    ], true);
}

function admin_inbox_patient_label($name, $requestId)
{
    $label = trim((string)$name);
    if ($label !== '') {
        return $label;
    }
    $requestId = (int)$requestId;
    if ($requestId > 0) {
        return 'Patient Request #' . $requestId;
    }
    return 'Patient';
}

function admin_inbox_free_message_state($conexion, $bookingRequestId, $scope, $feeLocked)
{
    $bookingRequestId = (int)$bookingRequestId;
    $feeLocked = !empty($feeLocked);
    $isAdmin = !empty($scope['is_admin']);

    $canSendFreeMessage = $isAdmin ? true : !$feeLocked;
    $reason = $feeLocked ? 'fee_locked' : '';

    return [
        'booking_status' => '',
        'stage_allows_free_message' => true,
        'can_send_free_message' => $canSendFreeMessage,
        'blocked_reason' => $reason,
        'notice' => $feeLocked ? 'La mensajería libre está bloqueada por la condición de coordinación. Las acciones formales siguen disponibles.' : '',
    ];
}

function admin_inbox_build_scope()
{
    $providerId = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
    $serviceProviderId = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;
    $isAdmin = is_role_admin_session();
    $userId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
    $roleId = current_role_id();

    if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
        return ['ok' => false, 'message' => 'forbidden', 'status' => 403];
    }
    if (!$isAdmin && $providerId <= 0 && $serviceProviderId <= 0) {
        return ['ok' => false, 'message' => 'forbidden', 'status' => 403];
    }

    $scopeWhere = '';
    $scopeTypes = '';
    $scopeParams = [];
    if (!$isAdmin) {
        if ($providerId > 0 && $serviceProviderId > 0) {
            $scopeWhere = " AND ((bri.provider_id = ? AND bri.item_type = 'medical_offer') OR (bri.service_provider_id = ? AND bri.item_type = 'complementary_service'))";
            $scopeTypes = 'ii';
            $scopeParams = [$providerId, $serviceProviderId];
        } elseif ($providerId > 0) {
            $scopeWhere = " AND bri.provider_id = ? AND bri.item_type = 'medical_offer'";
            $scopeTypes = 'i';
            $scopeParams = [$providerId];
        } else {
            $scopeWhere = " AND bri.service_provider_id = ? AND bri.item_type = 'complementary_service'";
            $scopeTypes = 'i';
            $scopeParams = [$serviceProviderId];
        }
    }

    $readerRole = 'PROVIDER';
    if ($isAdmin) {
        $readerRole = ((int)$roleId === (int)ROLE_ADMINISTRATIVE) ? 'PATIENTCARE' : 'ADMIN';
    }

    return [
        'ok' => true,
        'is_admin' => $isAdmin,
        'user_id' => $userId,
        'reader_role' => $readerRole,
        'provider_id' => $providerId,
        'service_provider_id' => $serviceProviderId,
        'scope_where' => $scopeWhere,
        'scope_types' => $scopeTypes,
        'scope_params' => $scopeParams,
    ];
}

function admin_inbox_fetch_scoped_item($conexion, $itemId, $scope)
{
    $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasAdditionalNotes = inbox_table_has_column($conexion, 'booking_requests', 'additional_notes');

    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id AS request_id,
                br.destination";
    $sql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
    $sql .= " FROM booking_request_items bri
             INNER JOIN booking_requests br ON br.id = bri.booking_request_id
             WHERE bri.id = ?";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= (string)$scope['scope_where'];
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    $types = 'i' . (string)$scope['scope_types'];
    $params = array_merge([(int)$itemId], (array)$scope['scope_params']);
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function admin_inbox_fetch_scoped_care($conexion, $requestId)
{
    $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasAdditionalNotes = inbox_table_has_column($conexion, 'booking_requests', 'additional_notes');

    $sql = "SELECT id AS request_id, destination";
    $sql .= $hasAdditionalNotes ? ", additional_notes" : ", '' AS additional_notes";
    $sql .= " FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $requestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function admin_inbox_resolve_context($conexion, $scope, $threadType, $requestId, $itemId, $threadIdInput)
{
    $threadType = strtoupper(trim((string)$threadType));
    $requestId = (int)$requestId;
    $itemId = (int)$itemId;
    $threadIdInput = trim((string)$threadIdInput);

    if ($threadIdInput !== '') {
        $parsed = inbox_parse_thread_id($threadIdInput);
        if (empty($parsed['ok'])) {
            return ['ok' => false, 'message' => 'invalid_thread_id', 'status' => 422];
        }
        $threadType = (string)$parsed['thread_type'];
        if ($threadType === 'CARE') {
            $requestId = (int)$parsed['request_id'];
            $itemId = 0;
        } else {
            $itemId = (int)$parsed['item_id'];
        }
    }

    if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
        return ['ok' => false, 'message' => 'invalid_thread_type', 'status' => 422];
    }

    if ($threadType === 'CARE') {
        if (empty($scope['is_admin'])) {
            return ['ok' => false, 'message' => 'forbidden', 'status' => 403];
        }
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'invalid_request_id', 'status' => 422];
        }
        $care = admin_inbox_fetch_scoped_care($conexion, $requestId);
        if (!$care) {
            return ['ok' => false, 'message' => 'not_found', 'status' => 404];
        }
        return [
            'ok' => true,
            'thread_id' => inbox_thread_id('CARE', $requestId, 0),
            'thread_type' => 'CARE',
            'request_id' => $requestId,
            'item_id' => 0,
            'destination' => (string)($care['destination'] ?? ''),
            'additional_notes' => (string)($care['additional_notes'] ?? ''),
        ];
    }

    if (!empty($scope['is_admin'])) {
        return ['ok' => false, 'message' => 'forbidden', 'status' => 403];
    }

    if ($itemId <= 0) {
        return ['ok' => false, 'message' => 'invalid_item_id', 'status' => 422];
    }

    $item = admin_inbox_fetch_scoped_item($conexion, $itemId, $scope);
    if (!$item) {
        return ['ok' => false, 'message' => 'not_found', 'status' => 404];
    }
    $requestId = (int)($item['request_id'] ?? 0);

    return [
        'ok' => true,
        'thread_id' => inbox_thread_id('ITEM', $requestId, $itemId),
        'thread_type' => 'ITEM',
        'request_id' => $requestId,
        'item_id' => $itemId,
        'destination' => (string)($item['destination'] ?? ''),
        'additional_notes' => (string)($item['additional_notes'] ?? ''),
    ];
}

function admin_inbox_decode_payload($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    return $decoded;
}

if (defined('INBOX_BOOTSTRAP_ONLY') && INBOX_BOOTSTRAP_ONLY) {
    return;
}

if (!isset($conexion) || !$conexion) {
    admin_inbox_err('db_not_available', 500);
}
if (!inbox_table_exists($conexion, 'booking_requests') || !inbox_table_exists($conexion, 'booking_request_items')) {
    admin_inbox_err('booking_tables_not_available', 409);
}

$scope = admin_inbox_build_scope();
if (empty($scope['ok'])) {
    admin_inbox_err((string)($scope['message'] ?? 'forbidden'), (int)($scope['status'] ?? 403));
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list_threads'));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 200);
if ($limit < 1) {
    $limit = 200;
}
if ($limit > 500) {
    $limit = 500;
}

if ($action === 'list_threads') {
    $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasBookingName = inbox_table_has_column($conexion, 'booking_requests', 'name');
    $hasBookingStatus = inbox_table_has_column($conexion, 'booking_requests', 'status');
    $hasBookingClientUserId = inbox_table_has_column($conexion, 'booking_requests', 'client_user_id');
    $hasItemStatus = inbox_table_has_column($conexion, 'booking_request_items', 'item_status');
    $usuariosTableExists = inbox_table_exists($conexion, 'usuarios');
    $hasUsuariosNombre = $usuariosTableExists && inbox_table_has_column($conexion, 'usuarios', 'nombre');

    $patientNameParts = [];
    if ($hasBookingName) {
        $patientNameParts[] = "NULLIF(TRIM(br.name), '')";
    }
    if ($hasBookingClientUserId && $hasUsuariosNombre) {
        $patientNameParts[] = "NULLIF(TRIM(u_cli.nombre), '')";
    }
    $patientNameExpr = !empty($patientNameParts)
        ? 'COALESCE(' . implode(', ', $patientNameParts) . ", '')"
        : "''";
    $patientJoin = ($hasBookingClientUserId && $hasUsuariosNombre)
        ? ' LEFT JOIN usuarios u_cli ON u_cli.id = br.client_user_id'
        : '';
    $bookingStatusExpr = $hasBookingStatus
        ? "COALESCE(NULLIF(TRIM(br.status), ''), 'pending')"
        : "'pending'";
    $itemStatusExpr = $hasItemStatus
        ? "CASE WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider' ELSE bri.item_status END"
        : "'pending_provider'";
    $threads = [];

    if (!empty($scope['is_admin'])) {
        $careSql = "SELECT br.id AS request_id,
                           br.destination,
                           br.created_at,
                           {$patientNameExpr} AS patient_name,
                           {$bookingStatusExpr} AS booking_status
                    FROM booking_requests br{$patientJoin}
                    WHERE 1=1";
        if ($hasRequestsSoftDelete) {
            $careSql .= " AND br.is_deleted = 0";
        }
        $careSql .= " ORDER BY br.created_at DESC LIMIT " . (int)$limit;
        $careRes = mysqli_query($conexion, $careSql);
        if ($careRes) {
            while ($row = mysqli_fetch_assoc($careRes)) {
                $requestId = (int)($row['request_id'] ?? 0);
                if ($requestId <= 0) {
                    continue;
                }
                $threads[] = [
                    'thread_id' => inbox_thread_id('CARE', $requestId, 0),
                    'thread_key' => inbox_thread_id('CARE', $requestId, 0),
                    'thread_type' => 'CARE',
                    'request_id' => $requestId,
                    'booking_request_id' => $requestId,
                    'item_id' => 0,
                    'title' => 'General - Request #' . $requestId,
                    'patient_name' => admin_inbox_patient_label($row['patient_name'] ?? '', $requestId),
                    'status_label' => admin_inbox_status_label($row['booking_status'] ?? 'pending'),
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
    }

    if (empty($scope['is_admin'])) {
        $itemSql = "SELECT
                        bri.id AS item_id,
                        bri.booking_request_id AS request_id,
                        COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                        {$patientNameExpr} AS patient_name,
                        {$itemStatusExpr} AS item_status_label,
                        br.destination,
                        br.created_at
                    FROM booking_request_items bri
                    INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                    LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                    LEFT JOIN service_catalog sc ON sc.id = o.service_id
                    LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                    {$patientJoin}
                    WHERE 1=1";
        if ($hasItemsSoftDelete) {
            $itemSql .= " AND bri.is_deleted = 0";
        }
        if ($hasRequestsSoftDelete) {
            $itemSql .= " AND br.is_deleted = 0";
        }
        $itemSql .= (string)$scope['scope_where'];
        $itemSql .= " ORDER BY br.created_at DESC, bri.id DESC LIMIT " . (int)$limit;

        $stmtItem = mysqli_prepare($conexion, $itemSql);
        if ($stmtItem) {
            if ((string)$scope['scope_types'] !== '') {
                $types = (string)$scope['scope_types'];
                $params = (array)$scope['scope_params'];
                inbox_bind_stmt_params($stmtItem, $types, $params);
            }
            if (mysqli_stmt_execute($stmtItem)) {
                $res = mysqli_stmt_get_result($stmtItem);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $requestId = (int)($row['request_id'] ?? 0);
                    $itemId = (int)($row['item_id'] ?? 0);
                    if ($requestId <= 0 || $itemId <= 0) {
                        continue;
                    }
                    $itemName = trim((string)($row['item_name'] ?? ''));
                    if ($itemName === '') {
                        $itemName = 'Item #' . $itemId;
                    }
                    $threads[] = [
                        'thread_id' => inbox_thread_id('ITEM', $requestId, $itemId),
                        'thread_key' => inbox_thread_id('ITEM', $requestId, $itemId),
                        'thread_type' => 'ITEM',
                        'request_id' => $requestId,
                        'booking_request_id' => $requestId,
                        'item_id' => $itemId,
                        'title' => $itemName . ' - Request #' . $requestId,
                        'patient_name' => admin_inbox_patient_label($row['patient_name'] ?? '', $requestId),
                        'status_label' => admin_inbox_status_label($row['item_status_label'] ?? 'pending_provider'),
                        'subtitle' => trim((string)($row['destination'] ?? '')),
                        'updated_at' => (string)($row['created_at'] ?? ''),
                    ];
                }
            }
            mysqli_stmt_close($stmtItem);
        }
    }

    $threads = inbox_enrich_threads_with_meta($conexion, $threads, (string)$scope['reader_role'], (int)$scope['user_id']);
    $totalUnread = 0;
    foreach ($threads as $t) {
        $totalUnread += (int)($t['unread_count'] ?? 0);
    }

    admin_inbox_ok([
        'threads' => $threads,
        'unread_count' => $totalUnread,
    ]);
}

if ($action === 'list_messages' || $action === 'send_message' || $action === 'send_quick_reply' || $action === 'send_structured_action' || $action === 'mark_read' || $action === 'upload_documents') {
    $threadIdInput = (string)($_GET['thread_id'] ?? $_POST['thread_id'] ?? '');
    $threadType = (string)($_GET['thread_type'] ?? $_POST['thread_type'] ?? '');
    $requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? $_GET['booking_request_id'] ?? $_POST['booking_request_id'] ?? 0);
    $itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

    $ctx = admin_inbox_resolve_context($conexion, $scope, $threadType, $requestId, $itemId, $threadIdInput);
    if (empty($ctx['ok'])) {
        admin_inbox_err((string)($ctx['message'] ?? 'invalid_thread'), (int)($ctx['status'] ?? 400));
    }
}

if ($action === 'list_messages') {
    $bookingRequestId = (int)($ctx['request_id'] ?? 0);
    $feeLocked = (!empty($bookingRequestId) && empty($scope['is_admin']))
        ? is_booking_fee_required($conexion, $bookingRequestId)
        : false;
    $commissionGate = commission_gate_status($conexion, $bookingRequestId, (int)($ctx['item_id'] ?? 0));
    $commissionGateEnabled = !empty($commissionGate['enabled']);
    $commissionPaid = !empty($commissionGate['paid']);
    $freeMessageState = admin_inbox_free_message_state($conexion, $bookingRequestId, $scope, $feeLocked);
    $isProviderItemThread = empty($scope['is_admin'])
        && strtoupper((string)($scope['reader_role'] ?? '')) === 'PROVIDER'
        && strtoupper((string)($ctx['thread_type'] ?? '')) === 'ITEM'
        && (int)($ctx['item_id'] ?? 0) > 0;
    if ($isProviderItemThread) {
        $commissionLocked = $commissionGateEnabled && !$commissionPaid;
        $freeMessageState['can_send_free_message'] = (!$feeLocked && !$commissionLocked);
        $freeMessageState['stage_allows_free_message'] = true;
        if ($feeLocked) {
            $freeMessageState['blocked_reason'] = 'fee_locked';
            $freeMessageState['notice'] = '';
        } elseif ($commissionLocked) {
            $freeMessageState['blocked_reason'] = 'commission';
            $freeMessageState['notice'] = 'Messaging is locked until the commission is paid. Please contact MedTravel if you need help.';
        } else {
            $freeMessageState['blocked_reason'] = '';
            $freeMessageState['notice'] = '';
        }
    }
    $messages = [];
    $sinceId = (int)($_GET['since_id'] ?? $_POST['since_id'] ?? 0);
    if (inbox_table_exists($conexion, 'inbox_messages')) {
        if ($sinceId > 0) {
            $stmt = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? AND id > ? ORDER BY id ASC");
        } else {
            $stmt = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? ORDER BY id ASC");
        }
        if ($stmt) {
            $threadId = (string)$ctx['thread_id'];
            if ($sinceId > 0) {
                mysqli_stmt_bind_param($stmt, 'si', $threadId, $sinceId);
            } else {
                mysqli_stmt_bind_param($stmt, 's', $threadId);
            }
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $messages[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'sender' => inbox_sender_to_ui($row['sender_role'] ?? ''),
                        'sender_user_id' => (int)($row['sender_user_id'] ?? 0),
                        'actor_user_id' => (int)($row['sender_user_id'] ?? 0),
                        'body' => (string)($row['body'] ?? ''),
                        'time' => (string)($row['created_at'] ?? ''),
                        'thread_type' => (string)$ctx['thread_type'],
                        'thread_item_id' => (int)$ctx['item_id'],
                    ];
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($sinceId <= 0 && empty($messages) && trim((string)($ctx['additional_notes'] ?? '')) !== '') {
        $legacy = inbox_parse_legacy_messages((string)$ctx['additional_notes']);
        $legacy = inbox_filter_legacy_messages($legacy, (string)$ctx['thread_type'], (int)$ctx['item_id']);
        foreach ($legacy as $idx => $m) {
            $messages[] = [
                'id' => 'legacy-' . ($idx + 1),
                'sender' => (string)($m['sender'] ?? 'system'),
                'sender_user_id' => 0,
                'actor_user_id' => 0,
                'body' => (string)($m['body'] ?? ''),
                'time' => (string)($m['time'] ?? ''),
                'thread_type' => (string)$ctx['thread_type'],
                'thread_item_id' => (int)$ctx['item_id'],
            ];
        }
    }

    $documents = [];
    $documentsError = '';
    $threadTypeUpper = strtoupper((string)($ctx['thread_type'] ?? ''));
    $isItemThread = ($threadTypeUpper === 'ITEM') && ((int)($ctx['item_id'] ?? 0) > 0);
    $isCareThread = ($threadTypeUpper === 'CARE');
    if (($isItemThread || $isCareThread) && inbox_table_exists($conexion, 'client_documents')) {
        $docHasRequestId = inbox_table_has_column($conexion, 'client_documents', 'booking_request_id');
        $docHasItemId = inbox_table_has_column($conexion, 'client_documents', 'item_id');
        if (!$docHasRequestId || !$docHasItemId) {
            $documentsError = 'client_documents_scope_missing';
        } else {
            $clientUserId = 0;
            $clientesId = 0;
            $clientEmail = '';
            if (inbox_table_exists($conexion, 'booking_requests') && $bookingRequestId > 0) {
                $hasBrClientUserId = inbox_table_has_column($conexion, 'booking_requests', 'client_user_id');
                $hasBrEmail = inbox_table_has_column($conexion, 'booking_requests', 'email');
                $selectCols = $hasBrClientUserId ? 'client_user_id' : 'NULL AS client_user_id';
                $selectCols .= $hasBrEmail ? ', email' : ", '' AS email";
                $stmtClient = mysqli_prepare($conexion, "SELECT {$selectCols} FROM booking_requests WHERE id = ? LIMIT 1");
                if ($stmtClient) {
                    mysqli_stmt_bind_param($stmtClient, 'i', $bookingRequestId);
                    if (mysqli_stmt_execute($stmtClient)) {
                        $resClient = mysqli_stmt_get_result($stmtClient);
                        $rowClient = $resClient ? mysqli_fetch_assoc($resClient) : null;
                        if ($rowClient) {
                            $clientUserId = (int)($rowClient['client_user_id'] ?? 0);
                            $clientEmail = trim((string)($rowClient['email'] ?? ''));
                        }
                    }
                    mysqli_stmt_close($stmtClient);
                }
            }
            if ($clientUserId <= 0 && $clientEmail !== '' && inbox_table_exists($conexion, 'clientes') && inbox_table_has_column($conexion, 'clientes', 'email')) {
                $hasClientesClientUserId = inbox_table_has_column($conexion, 'clientes', 'client_user_id');
                $hasClientesUserId = inbox_table_has_column($conexion, 'clientes', 'user_id');
                $clientSelect = $hasClientesClientUserId ? 'client_user_id' : ($hasClientesUserId ? 'user_id' : 'id');
                $stmtLookup = mysqli_prepare($conexion, "SELECT {$clientSelect} AS client_user_id, id AS clientes_id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
                if ($stmtLookup) {
                    mysqli_stmt_bind_param($stmtLookup, 's', $clientEmail);
                    if (mysqli_stmt_execute($stmtLookup)) {
                        $resLookup = mysqli_stmt_get_result($stmtLookup);
                        $rowLookup = $resLookup ? mysqli_fetch_assoc($resLookup) : null;
                        if ($rowLookup) {
                            $clientUserId = (int)($rowLookup['client_user_id'] ?? 0);
                            $clientesId = (int)($rowLookup['clientes_id'] ?? 0);
                        }
                    }
                    mysqli_stmt_close($stmtLookup);
                }
            }

            $docHasClientUserId = inbox_table_has_column($conexion, 'client_documents', 'client_user_id');
            $clientesHasClientUserId = inbox_table_has_column($conexion, 'clientes', 'client_user_id');
            $clientesHasUserId = inbox_table_has_column($conexion, 'clientes', 'user_id');
            $clientesMapCol = $clientesHasClientUserId ? 'client_user_id' : ($clientesHasUserId ? 'user_id' : '');

            if ($docHasClientUserId && $clientUserId <= 0 && $clientesMapCol !== '' && $bookingRequestId > 0) {
                // Try to resolve client user id via clientes mapping if booking_requests doesn't have it.
                $stmtResolve = mysqli_prepare($conexion, "SELECT {$clientesMapCol} AS client_user_id, id AS clientes_id FROM clientes WHERE id = ? LIMIT 1");
                if ($stmtResolve) {
                    mysqli_stmt_bind_param($stmtResolve, 'i', $clientesId);
                    if (mysqli_stmt_execute($stmtResolve)) {
                        $resResolve = mysqli_stmt_get_result($stmtResolve);
                        $rowResolve = $resResolve ? mysqli_fetch_assoc($resResolve) : null;
                        if ($rowResolve) {
                            $clientUserId = (int)($rowResolve['client_user_id'] ?? 0);
                            if ($clientesId <= 0) {
                                $clientesId = (int)($rowResolve['clientes_id'] ?? 0);
                            }
                        }
                    }
                    mysqli_stmt_close($stmtResolve);
                }
            }

            if ($bookingRequestId > 0) {
                $selectCols = ['id', 'file_path', 'filename', 'original_filename', 'document_type', 'booking_request_id', 'item_id'];
                if (inbox_table_has_column($conexion, 'client_documents', 'file_size')) {
                    $selectCols[] = 'file_size';
                }
                if (inbox_table_has_column($conexion, 'client_documents', 'mime_type')) {
                    $selectCols[] = 'mime_type';
                }
                if (inbox_table_has_column($conexion, 'client_documents', 'title')) {
                    $selectCols[] = 'title';
                }
                if (inbox_table_has_column($conexion, 'client_documents', 'description')) {
                    $selectCols[] = 'description';
                }
                if (inbox_table_has_column($conexion, 'client_documents', 'uploaded_at')) {
                    $selectCols[] = 'uploaded_at';
                }
                if (inbox_table_has_column($conexion, 'client_documents', 'created_at')) {
                    $selectCols[] = 'created_at';
                }
                $orderByColumn = inbox_table_has_column($conexion, 'client_documents', 'uploaded_at') ? 'uploaded_at' : 'id';
                $docSql = "SELECT " . implode(', ', $selectCols) . " FROM client_documents cd";
                $docTypes = '';
                $docParams = [];

                if ($docHasClientUserId && $clientUserId > 0) {
                    $docSql .= " WHERE (cd.client_user_id = ?";
                    $docTypes .= 'i';
                    $docParams[] = $clientUserId;
                    if ($clientesMapCol !== '') {
                        $docSql .= " OR (cd.client_user_id IS NULL AND EXISTS (SELECT 1 FROM clientes c WHERE c.id = cd.client_id AND c." . $clientesMapCol . " = ?))";
                        $docTypes .= 'i';
                        $docParams[] = $clientUserId;
                    }
                    $docSql .= ")";
                } else {
                    if ($clientesId > 0) {
                        $docSql .= " WHERE cd.client_id = ?";
                        $docTypes .= 'i';
                        $docParams[] = $clientesId;
                    } else {
                        $docSql .= " WHERE 1=1"; // booking_request_id filter below scopes the query
                    }
                }

                if (inbox_table_has_column($conexion, 'client_documents', 'shared_with_provider')) {
                    $docSql .= " AND shared_with_provider = 1";
                }
                $docSql .= " AND booking_request_id = ?";
                $docTypes .= 'i';
                $docParams[] = $bookingRequestId;
                if ($isItemThread && (int)$ctx['item_id'] > 0) {
                    $docSql .= " AND (item_id = ? OR item_id IS NULL)";
                    $docTypes .= 'i';
                    $docParams[] = (int)$ctx['item_id'];
                } elseif ($isCareThread) {
                    $docSql .= " AND (item_id IS NULL OR item_id = 0)";
                }
                $docSql .= " ORDER BY " . $orderByColumn . " DESC";
                $stmtDocs = mysqli_prepare($conexion, $docSql);
                if ($stmtDocs) {
                    if (inbox_bind_stmt_params($stmtDocs, $docTypes, $docParams) && mysqli_stmt_execute($stmtDocs)) {
                        $docRes = mysqli_stmt_get_result($stmtDocs);
                        while ($docRes && ($docRow = mysqli_fetch_assoc($docRes))) {
                            $docRow['download_url'] = '/admin/ajax/download_medical_document.php?doc_id=' . (int)($docRow['id'] ?? 0);
                            $documents[] = $docRow;
                        }
                    }
                    mysqli_stmt_close($stmtDocs);
                }
            }
        }
    }

    admin_inbox_ok([
        'thread_id' => $ctx['thread_id'],
        'thread_type' => $ctx['thread_type'],
        'request_id' => (int)$ctx['request_id'],
        'booking_request_id' => (int)$ctx['request_id'],
        'item_id' => (int)$ctx['item_id'],
        'since_id' => $sinceId,
        'fee_locked' => $feeLocked ? 1 : 0,
        'commission_gate_enabled' => $commissionGateEnabled ? 1 : 0,
        'commission_paid' => $commissionPaid ? 1 : 0,
        'commission_status' => $commissionGateEnabled ? ($commissionPaid ? 'paid' : 'unpaid') : 'disabled',
        'can_send_free_message' => !empty($freeMessageState['can_send_free_message']),
        'free_message_blocked_reason' => (string)($freeMessageState['blocked_reason'] ?? ''),
        'free_message_notice' => (string)($freeMessageState['notice'] ?? ''),
        'documents' => $documents,
        'documents_error' => $documentsError,
        'messages' => $messages,
    ]);
}

if ($action === 'send_message') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'ADMIN_SEND_MESSAGE_ENTER request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
            . ' actor=' . strtoupper((string)($scope['reader_role'] ?? ''))
        );
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        admin_inbox_err('inbox_messages_not_available', 409);
    }
    $bookingRequestId = (int)($ctx['request_id'] ?? 0);
    $feeLocked = (!empty($bookingRequestId) && empty($scope['is_admin']))
        ? is_booking_fee_required($conexion, $bookingRequestId)
        : false;
    $commissionGate = commission_gate_status($conexion, $bookingRequestId, (int)($ctx['item_id'] ?? 0));
    $commissionGateEnabled = !empty($commissionGate['enabled']);
    $commissionPaid = !empty($commissionGate['paid']);
    $freeMessageState = admin_inbox_free_message_state($conexion, $bookingRequestId, $scope, $feeLocked);
    $isProviderItemThread = empty($scope['is_admin'])
        && strtoupper((string)($scope['reader_role'] ?? '')) === 'PROVIDER'
        && strtoupper((string)($ctx['thread_type'] ?? '')) === 'ITEM'
        && (int)($ctx['item_id'] ?? 0) > 0;
    if ($isProviderItemThread) {
        $commissionLocked = $commissionGateEnabled && !$commissionPaid;
        $freeMessageState['can_send_free_message'] = (!$feeLocked && !$commissionLocked);
        $freeMessageState['blocked_reason'] = $feeLocked ? 'fee_locked' : ($commissionLocked ? 'commission' : '');
        $freeMessageState['notice'] = $commissionLocked
            ? 'Messaging is locked until the commission is paid. Please contact MedTravel if you need help.'
            : '';
    }
    $canSendFreeMessage = !empty($freeMessageState['can_send_free_message']);
    if (empty($scope['is_admin']) && strtoupper((string)($scope['reader_role'] ?? '')) === 'PROVIDER' && $commissionGateEnabled && !$commissionPaid) {
        if (function_exists('mt_email_debug_log')) {
            mt_email_debug_log(
                'PROVIDER_BLOCK_SEND_MESSAGE reason=commission'
                . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
                . ' provider_user_id=' . (int)($scope['user_id'] ?? 0)
                . ' request_id=' . (int)($ctx['request_id'] ?? 0)
                . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            );
        }
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'compose_locked',
            'reason' => 'commission',
        ]);
        exit;
    }
    if ($feeLocked) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'code' => 'FEE_REQUIRED',
            'error' => 'Coordination Fee required',
        ]);
        exit;
    }
    if (!$canSendFreeMessage) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'code' => 'FREE_MESSAGE_BLOCKED',
            'error' => 'Messaging is temporarily limited. Please use the options above.',
            'reason' => (string)($freeMessageState['blocked_reason'] ?? ''),
            'notice' => (string)($freeMessageState['notice'] ?? ''),
        ]);
        exit;
    }
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        admin_inbox_err('message_required', 422);
    }
    if (mb_strlen($message) > 2000) {
        admin_inbox_err('message_too_long', 422);
    }

    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    $itemId = (int)$ctx['item_id'];
    $maxBeforeInsert = null;
    $stmtMax = mysqli_prepare($conexion, "SELECT COALESCE(MAX(id), 0) AS max_id FROM inbox_messages WHERE thread_id = ?");
    if ($stmtMax) {
        mysqli_stmt_bind_param($stmtMax, 's', $threadId);
        if (mysqli_stmt_execute($stmtMax)) {
            $resMax = mysqli_stmt_get_result($stmtMax);
            $rowMax = $resMax ? mysqli_fetch_assoc($resMax) : null;
            $maxBeforeInsert = (int)($rowMax['max_id'] ?? 0);
        }
        mysqli_stmt_close($stmtMax);
    }
    $senderRole = (string)$scope['reader_role'];
    $senderUserId = (int)$scope['user_id'];
    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        admin_inbox_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param($stmt, 'ssiisis', $threadId, $threadType, $requestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        admin_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_client_email($conexion, $requestId, $emailSource);
        mt_email_debug_log(
            'ADMIN_NOTIFY_CLIENT_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        mt_email_debug_log(
            'TAG=PROVE_MD5 interaction_email_md5=' . md5_file(__DIR__ . '/../../inc/interaction_email.php')
            . ' interaction_email_path=' . realpath(__DIR__ . '/../../inc/interaction_email.php')
        );
        mt_email_debug_log('TAG=PROVE_HIT reached_before_notify action=' . $action . ' msg_id=' . (int)$messageId);
        file_put_contents(__DIR__ . '/../../storage/logs/email_debug.log',
            date('c') . ' ADMIN_BEFORE_NOTIFY file=' . __FILE__ . ' msg_id=' . $messageId . ' ctx_thread_id=' . $ctx['thread_id'] . "\n",
            FILE_APPEND
        );
        $notifyResult = notify_new_message_to_client(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            $senderRole,
            $message,
            $resolvedEmail,
            $emailSource,
            $messageId,
            $maxBeforeInsert
        );
        mt_email_debug_log('ADMIN_NOTIFY_CLIENT_DONE result=' . json_encode($notifyResult));
    } else {
        mt_email_debug_log(
            'TAG=PROVE_MD5 interaction_email_md5=' . md5_file(__DIR__ . '/../../inc/interaction_email.php')
            . ' interaction_email_path=' . realpath(__DIR__ . '/../../inc/interaction_email.php')
        );
        mt_email_debug_log('TAG=PROVE_HIT reached_before_notify action=' . $action . ' msg_id=' . (int)$messageId);
        file_put_contents(__DIR__ . '/../../storage/logs/email_debug.log',
            date('c') . ' ADMIN_BEFORE_NOTIFY file=' . __FILE__ . ' msg_id=' . $messageId . ' ctx_thread_id=' . $ctx['thread_id'] . "\n",
            FILE_APPEND
        );
        notify_new_message_to_client($conexion, $requestId, $itemId, $threadType, $senderRole, $message, '', '', $messageId, $maxBeforeInsert);
    }

    admin_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_request_id' => $requestId,
        'item_id' => $itemId,
        'message' => [
            'id' => $messageId,
            'sender' => inbox_sender_to_ui($senderRole),
            'body' => $message,
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'upload_documents') {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        admin_inbox_err('method_not_allowed', 405);
    }
    if (!inbox_table_exists($conexion, 'client_documents')) {
        admin_inbox_err('client_documents_not_available', 409);
    }

    $bookingRequestId = (int)($ctx['request_id'] ?? 0);
    $feeLocked = (!empty($bookingRequestId) && empty($scope['is_admin']))
        ? is_booking_fee_required($conexion, $bookingRequestId)
        : false;
    $freeMessageState = admin_inbox_free_message_state($conexion, $bookingRequestId, $scope, $feeLocked);
    $commissionGate = commission_gate_status($conexion, $bookingRequestId, (int)($ctx['item_id'] ?? 0));
    $commissionGateEnabled = !empty($commissionGate['enabled']);
    $commissionPaid = !empty($commissionGate['paid']);
    $isProviderItemThread = empty($scope['is_admin'])
        && strtoupper((string)($scope['reader_role'] ?? '')) === 'PROVIDER'
        && strtoupper((string)($ctx['thread_type'] ?? '')) === 'ITEM'
        && (int)($ctx['item_id'] ?? 0) > 0;
    if ($isProviderItemThread) {
        $commissionLocked = $commissionGateEnabled && !$commissionPaid;
        $freeMessageState['can_send_free_message'] = (!$feeLocked && !$commissionLocked);
    }
    if ($feeLocked) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'code' => 'FEE_REQUIRED',
            'message' => 'La condición de coordinación sigue pendiente.',
        ]);
        exit;
    }
    if (empty($freeMessageState['can_send_free_message'])) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'code' => 'FREE_MESSAGE_BLOCKED',
            'message' => (string)($freeMessageState['notice'] ?? 'La mensajería está limitada temporalmente.'),
        ]);
        exit;
    }

    $files = admin_inbox_normalize_upload_files($_FILES['chat_files'] ?? null);
    if (empty($files)) {
        admin_inbox_err('file_required', 422);
    }

    $owner = admin_inbox_resolve_document_owner($conexion, $bookingRequestId);
    $resolvedClientId = (int)($owner['client_id'] ?? 0);
    $resolvedClientUserId = (int)($owner['client_user_id'] ?? 0);
    if ($resolvedClientId <= 0) {
        admin_inbox_err('client_not_resolved', 422);
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
    $maxFileSize = 10 * 1024 * 1024;
    $documentTitles = $_POST['document_title'] ?? [];
    $documentTypes = $_POST['document_type'] ?? [];
    $documentNotes = $_POST['document_note'] ?? [];

    $uploadRoot = __DIR__ . '/../../uploads/medical_docs/';
    $folderOwner = $resolvedClientUserId > 0 ? $resolvedClientUserId : $resolvedClientId;
    $clientDir = $uploadRoot . 'client_' . $folderOwner . '/';
    if (!is_dir($clientDir) && !mkdir($clientDir, 0755, true)) {
        admin_inbox_err('upload_dir_not_created', 500);
    }

    $hasDocumentType = inbox_table_has_column($conexion, 'client_documents', 'document_type');
    $hasFileSize = inbox_table_has_column($conexion, 'client_documents', 'file_size');
    $hasMimeType = inbox_table_has_column($conexion, 'client_documents', 'mime_type');
    $hasFileExtension = inbox_table_has_column($conexion, 'client_documents', 'file_extension');
    $hasSharedWithProvider = inbox_table_has_column($conexion, 'client_documents', 'shared_with_provider');
    $hasUploadedBy = inbox_table_has_column($conexion, 'client_documents', 'uploaded_by');
    $hasClientUserId = inbox_table_has_column($conexion, 'client_documents', 'client_user_id');
    $hasTitle = inbox_table_has_column($conexion, 'client_documents', 'title');
    $hasDescription = inbox_table_has_column($conexion, 'client_documents', 'description');
    $hasUploadedAt = inbox_table_has_column($conexion, 'client_documents', 'uploaded_at');
    $hasCreatedAt = inbox_table_has_column($conexion, 'client_documents', 'created_at');

    $results = [];
    $uploadedCount = 0;
    foreach ($files as $index => $file) {
        $originalFilename = trim((string)($file['name'] ?? ''));
        $documentTitle = trim(admin_inbox_array_value($documentTitles, $index, ''));
        if ($documentTitle === '') {
            $documentTitle = admin_inbox_document_title_fallback($originalFilename);
        }
        $documentType = admin_inbox_normalize_document_type(admin_inbox_array_value($documentTypes, $index, 'other'));
        $documentNote = trim(admin_inbox_array_value($documentNotes, $index, ''));
        if (function_exists('mb_substr')) {
            $documentTitle = mb_substr($documentTitle, 0, 190);
            $documentNote = mb_substr($documentNote, 0, 500);
        } else {
            $documentTitle = substr($documentTitle, 0, 190);
            $documentNote = substr($documentNote, 0, 500);
        }
        $fileError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fileError === UPLOAD_ERR_NO_FILE) {
            $results[] = ['index' => $index, 'ok' => false, 'message' => 'file_required', 'original_filename' => $originalFilename];
            continue;
        }
        if ($fileError !== UPLOAD_ERR_OK) {
            $results[] = ['index' => $index, 'ok' => false, 'message' => 'upload_error', 'original_filename' => $originalFilename];
            continue;
        }
        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize > $maxFileSize) {
            $results[] = ['index' => $index, 'ok' => false, 'message' => 'file_too_large', 'original_filename' => $originalFilename];
            continue;
        }
        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $results[] = ['index' => $index, 'ok' => false, 'message' => 'invalid_tmp_file', 'original_filename' => $originalFilename];
            continue;
        }
        $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions, true)) {
            $results[] = ['index' => $index, 'ok' => false, 'message' => 'file_extension_not_allowed', 'original_filename' => $originalFilename];
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
            $results[] = ['index' => $index, 'ok' => false, 'message' => 'file_type_not_allowed', 'original_filename' => $originalFilename];
            continue;
        }

        $filename = uniqid('doc_' . $folderOwner . '_') . '.' . $fileExtension;
        $filePath = 'client_' . $folderOwner . '/' . $filename;
        $fullPath = $clientDir . $filename;
        if (!move_uploaded_file($tmpName, $fullPath)) {
            $results[] = ['index' => $index, 'ok' => false, 'message' => 'file_save_failed', 'original_filename' => $originalFilename];
            continue;
        }

        $columns = ['client_id', 'file_path', 'filename', 'original_filename', 'booking_request_id'];
        $placeholders = ['?', '?', '?', '?', '?'];
        $types = 'isssi';
        $params = [$resolvedClientId, $filePath, $filename, $originalFilename, $bookingRequestId];

        if ($hasDocumentType) {
            $columns[] = 'document_type';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $documentType;
        }
        if ($hasTitle) {
            $columns[] = 'title';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $documentTitle;
        }
        if ($hasDescription) {
            $columns[] = 'description';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $documentNote;
        }
        if ($hasClientUserId && $resolvedClientUserId > 0) {
            $columns[] = 'client_user_id';
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = $resolvedClientUserId;
        }
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
            $params[] = (int)($scope['user_id'] ?? 0);
        }

        $columns[] = 'item_id';
        if ((int)($ctx['item_id'] ?? 0) > 0) {
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = (int)($ctx['item_id'] ?? 0);
        } else {
            $placeholders[] = 'NULL';
        }

        $insertSql = "INSERT INTO client_documents (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmtInsert = mysqli_prepare($conexion, $insertSql);
        if (!$stmtInsert || !inbox_bind_stmt_params($stmtInsert, $types, $params) || !mysqli_stmt_execute($stmtInsert)) {
            $err = $stmtInsert ? mysqli_stmt_error($stmtInsert) : 'insert_prepare_failed';
            if ($stmtInsert) {
                mysqli_stmt_close($stmtInsert);
            }
            @unlink($fullPath);
            $results[] = ['index' => $index, 'ok' => false, 'message' => 'insert_failed: ' . $err, 'original_filename' => $originalFilename];
            continue;
        }
        $documentId = (int)mysqli_insert_id($conexion);
        mysqli_stmt_close($stmtInsert);
        $uploadedCount++;
        $results[] = [
            'index' => $index,
            'ok' => true,
            'document_id' => $documentId,
            'file_path' => $filePath,
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'title' => $documentTitle,
            'document_type' => $documentType,
            'description' => $documentNote,
            'document_note' => $documentNote,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'uploaded_at' => ($hasUploadedAt || $hasCreatedAt) ? date('Y-m-d H:i:s') : null,
            'download_url' => '/admin/ajax/download_medical_document.php?doc_id=' . $documentId,
        ];
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

    admin_inbox_ok([
        'uploaded_count' => $uploadedCount,
        'results' => $results,
    ]);
}

if ($action === 'send_quick_reply') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'ADMIN_SEND_QUICK_REPLY_ENTER request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
            . ' actor=' . strtoupper((string)($scope['reader_role'] ?? ''))
        );
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        admin_inbox_err('method_not_allowed', 405);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        admin_inbox_err('inbox_messages_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        admin_inbox_err('invalid_thread_type', 422);
    }

    $key = strtoupper(trim((string)($_POST['reply_key'] ?? '')));
    $quickReplies = [
        'DATES_AVAILABLE' => 'Dates available',
        'DATES_NOT_AVAILABLE' => 'Dates not available',
        'REQUEST_MEDICAL_HISTORY' => 'REQUEST HISTORY',
        'REQUEST_LABS' => 'REQUEST LABS',
        'REQUEST_IMAGING' => 'REQUEST IMAGING',
        'REQUEST_PHOTOS' => 'REQUEST PHOTOS',
        'FINAL_APPROVED' => 'FINAL_APPROVED',
        'FINAL_NOT_ELIGIBLE' => 'FINAL_NOT_ELIGIBLE'
    ];
    if ($key === '' || !isset($quickReplies[$key])) {
        admin_inbox_err('invalid_reply_key', 422);
    }

    $finalStatus = null;
    if ($key === 'FINAL_APPROVED') {
        $finalStatus = 'provider_confirmed';
    } elseif ($key === 'FINAL_NOT_ELIGIBLE') {
        $finalStatus = 'provider_rejected';
    }

    if ($finalStatus !== null) {
        if (!inbox_table_exists($conexion, 'booking_request_items')) {
            admin_inbox_err('booking_items_not_available', 409);
        }
        if (!inbox_table_has_column($conexion, 'booking_request_items', 'item_status')) {
            admin_inbox_err('item_status_not_available', 409);
        }

        $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasItemUpdatedAt = inbox_table_has_column($conexion, 'booking_request_items', 'updated_at');
        $hasProviderResponseAt = inbox_table_has_column($conexion, 'booking_request_items', 'provider_response_at');
        $hasProviderResponseBy = inbox_table_has_column($conexion, 'booking_request_items', 'provider_response_by');

        $setParts = ['bri.item_status = ?'];
        $types = 's';
        $params = [$finalStatus];
        if ($hasItemUpdatedAt) {
            $setParts[] = 'bri.updated_at = NOW()';
        }
        if ($hasProviderResponseAt) {
            $setParts[] = 'bri.provider_response_at = NOW()';
        }
        if ($hasProviderResponseBy) {
            $setParts[] = 'bri.provider_response_by = ?';
            $types .= 'i';
            $params[] = (int)$scope['user_id'];
        }

        $sql = "UPDATE booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                SET " . implode(', ', $setParts) . "
                WHERE bri.id = ?";
        $types .= 'i';
        $params[] = (int)$ctx['item_id'];
        if ($hasItemsSoftDelete) {
            $sql .= ' AND bri.is_deleted = 0';
        }
        $sql .= (string)$scope['scope_where'];
        $sql .= ' LIMIT 1';

        $finalTypes = $types . (string)$scope['scope_types'];
        $finalParams = array_merge($params, (array)$scope['scope_params']);

        $stmtUpdate = mysqli_prepare($conexion, $sql);
        if (!$stmtUpdate) {
            admin_inbox_err('prepare_failed', 500);
        }
        if (!inbox_bind_stmt_params($stmtUpdate, $finalTypes, $finalParams) || !mysqli_stmt_execute($stmtUpdate)) {
            $err = mysqli_stmt_error($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);
            admin_inbox_err('update_failed: ' . $err, 500);
        }
        mysqli_stmt_close($stmtUpdate);
    }

    $message = '[REPLY] ' . $quickReplies[$key];
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    $itemId = (int)$ctx['item_id'];
    $maxBeforeInsert = null;
    $stmtMax = mysqli_prepare($conexion, "SELECT COALESCE(MAX(id), 0) AS max_id FROM inbox_messages WHERE thread_id = ?");
    if ($stmtMax) {
        mysqli_stmt_bind_param($stmtMax, 's', $threadId);
        if (mysqli_stmt_execute($stmtMax)) {
            $resMax = mysqli_stmt_get_result($stmtMax);
            $rowMax = $resMax ? mysqli_fetch_assoc($resMax) : null;
            $maxBeforeInsert = (int)($rowMax['max_id'] ?? 0);
        }
        mysqli_stmt_close($stmtMax);
    }
    $senderRole = (string)$scope['reader_role'];
    $senderUserId = (int)$scope['user_id'];

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        admin_inbox_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param($stmt, 'ssiisis', $threadId, $threadType, $requestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        admin_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_client_email($conexion, $requestId, $emailSource);
        mt_email_debug_log(
            'ADMIN_NOTIFY_CLIENT_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        mt_email_debug_log(
            'TAG=PROVE_MD5 interaction_email_md5=' . md5_file(__DIR__ . '/../../inc/interaction_email.php')
            . ' interaction_email_path=' . realpath(__DIR__ . '/../../inc/interaction_email.php')
        );
        mt_email_debug_log('TAG=PROVE_HIT reached_before_notify action=' . $action . ' msg_id=' . (int)$messageId);
        file_put_contents(__DIR__ . '/../../storage/logs/email_debug.log',
            date('c') . ' ADMIN_BEFORE_NOTIFY file=' . __FILE__ . ' msg_id=' . $messageId . ' ctx_thread_id=' . $ctx['thread_id'] . "\n",
            FILE_APPEND
        );
        $notifyResult = notify_new_message_to_client(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            $senderRole,
            $message,
            $resolvedEmail,
            $emailSource,
            $messageId,
            $maxBeforeInsert
        );
        mt_email_debug_log('ADMIN_NOTIFY_CLIENT_DONE result=' . json_encode($notifyResult));
    } else {
        mt_email_debug_log(
            'TAG=PROVE_MD5 interaction_email_md5=' . md5_file(__DIR__ . '/../../inc/interaction_email.php')
            . ' interaction_email_path=' . realpath(__DIR__ . '/../../inc/interaction_email.php')
        );
        mt_email_debug_log('TAG=PROVE_HIT reached_before_notify action=' . $action . ' msg_id=' . (int)$messageId);
        file_put_contents(__DIR__ . '/../../storage/logs/email_debug.log',
            date('c') . ' ADMIN_BEFORE_NOTIFY file=' . __FILE__ . ' msg_id=' . $messageId . ' ctx_thread_id=' . $ctx['thread_id'] . "\n",
            FILE_APPEND
        );
        notify_new_message_to_client($conexion, $requestId, $itemId, $threadType, $senderRole, $message, '', '', $messageId, $maxBeforeInsert);
    }

    admin_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_request_id' => $requestId,
        'item_id' => $itemId,
        'message' => [
            'id' => $messageId,
            'sender' => inbox_sender_to_ui($senderRole),
            'body' => $message,
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'send_structured_action') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'ADMIN_SEND_STRUCTURED_ENTER request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
            . ' actor=' . strtoupper((string)($scope['reader_role'] ?? ''))
        );
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        admin_inbox_err('method_not_allowed', 405);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        admin_inbox_err('inbox_messages_not_available', 409);
    }
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        admin_inbox_err('booking_items_not_available', 409);
    }
    if (!inbox_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        admin_inbox_err('item_status_not_available', 409);
    }
    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        admin_inbox_err('invalid_thread_type', 422);
    }

    $actionType = strtoupper(trim((string)($_POST['action_type'] ?? '')));
    $allowedActionTypes = [
        'PROPOSE_NEW_DATES',
        'REQUEST_LABS',
        'REQUEST_IMAGING',
        'REQUEST_PHOTOS',
        'REQUEST_HISTORY',
        'REQUEST_ADDITIONAL_INFO',
        'PROPOSE_QUOTE_ADJUSTMENT',
        'FINAL_APPROVED',
        'NOT_ELIGIBLE',
        'DATES_AVAILABLE',
        'DATES_NOT_AVAILABLE',
    ];
    if (!in_array($actionType, $allowedActionTypes, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'invalid_action_type']);
        exit;
    }

    $messagePrefix = '';
    $structuredPayload = [];
    $message = '';
    if ($actionType === 'REQUEST_ADDITIONAL_INFO' || $actionType === 'PROPOSE_QUOTE_ADJUSTMENT') {
        $payload = admin_inbox_decode_payload((string)($_POST['payload_json'] ?? ''));
        if ($payload === null) {
            admin_inbox_err('invalid_payload_json', 422);
        }
    }

    if ($actionType === 'REQUEST_ADDITIONAL_INFO') {
        $allowedTypes = ['labs', 'imaging', 'photos', 'medical_history', 'other'];
        $requiredTypes = [];
        if (isset($payload['required_types']) && is_array($payload['required_types'])) {
            foreach ($payload['required_types'] as $t) {
                $type = strtolower(trim((string)$t));
                if ($type !== '' && in_array($type, $allowedTypes, true) && !in_array($type, $requiredTypes, true)) {
                    $requiredTypes[] = $type;
                }
            }
        }
        if (empty($requiredTypes)) {
            admin_inbox_err('required_types_missing', 422);
        }
        $note = trim(strip_tags((string)($payload['note'] ?? '')));
        if (mb_strlen($note) > 300) {
            $note = function_exists('mb_substr') ? mb_substr($note, 0, 300) : substr($note, 0, 300);
        }

        $messagePrefix = '[REQUEST_INFO] ';
        $structuredPayload = [
            'action_type' => 'REQUEST_ADDITIONAL_INFO',
            'required_types' => $requiredTypes,
            'note' => $note,
        ];
    } elseif ($actionType === 'PROPOSE_QUOTE_ADJUSTMENT') {
        $amountRaw = trim((string)($payload['amount'] ?? ''));
        $amount = is_numeric($amountRaw) ? (float)$amountRaw : 0;
        if ($amount <= 0) {
            admin_inbox_err('invalid_amount', 422);
        }
        $currency = strtoupper(trim((string)($payload['currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }
        if (mb_strlen($currency) > 10) {
            admin_inbox_err('invalid_currency', 422);
        }
        $notes = trim(strip_tags((string)($payload['notes'] ?? '')));
        if (mb_strlen($notes) > 300) {
            $notes = function_exists('mb_substr') ? mb_substr($notes, 0, 300) : substr($notes, 0, 300);
        }

        $messagePrefix = '[PROPOSE_QUOTE] ';
        $structuredPayload = [
            'action_type' => 'PROPOSE_QUOTE_ADJUSTMENT',
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'notes' => $notes,
        ];
    } else {
        if ($actionType === 'PROPOSE_NEW_DATES') {
            $message = '[ACTION] PROPOSE_NEW_DATES';
        } else {
            $replyMap = [
                'REQUEST_LABS' => 'REQUEST LABS',
                'REQUEST_IMAGING' => 'REQUEST IMAGING',
                'REQUEST_PHOTOS' => 'REQUEST PHOTOS',
                'REQUEST_HISTORY' => 'REQUEST HISTORY',
                'DATES_AVAILABLE' => 'DATES AVAILABLE',
                'DATES_NOT_AVAILABLE' => 'DATES NOT AVAILABLE',
                'FINAL_APPROVED' => 'FINAL_APPROVED',
                'NOT_ELIGIBLE' => 'NOT_ELIGIBLE',
            ];
            $message = '[REPLY] ' . ($replyMap[$actionType] ?? $actionType);
        }
    }

    if ($message === '') {
        $jsonPayload = json_encode($structuredPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            admin_inbox_err('payload_encode_failed', 500);
        }
        $message = $messagePrefix . $jsonPayload;
    }
    if (mb_strlen($message) > 2000) {
        admin_inbox_err('message_too_long', 422);
    }

    $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasItemUpdatedAt = inbox_table_has_column($conexion, 'booking_request_items', 'updated_at');
    $hasProviderResponseAt = inbox_table_has_column($conexion, 'booking_request_items', 'provider_response_at');
    $hasProviderResponseBy = inbox_table_has_column($conexion, 'booking_request_items', 'provider_response_by');
    $hasProviderProposedPrice = inbox_table_has_column($conexion, 'booking_request_items', 'provider_proposed_price');
    $hasProviderProposedCurrency = inbox_table_has_column($conexion, 'booking_request_items', 'provider_proposed_currency');
    $hasItemProposedPrice = inbox_table_has_column($conexion, 'booking_request_items', 'proposed_price');
    $hasItemCurrency = inbox_table_has_column($conexion, 'booking_request_items', 'currency');

    $setParts = ['bri.item_status = ?'];
    $types = 's';
    $params = ['awaiting_client'];
    if ($hasItemUpdatedAt) {
        $setParts[] = 'bri.updated_at = NOW()';
    }
    if ($hasProviderResponseAt) {
        $setParts[] = 'bri.provider_response_at = NOW()';
    }
    if ($hasProviderResponseBy) {
        $setParts[] = 'bri.provider_response_by = ?';
        $types .= 'i';
        $params[] = (int)$scope['user_id'];
    }
    if ($actionType === 'PROPOSE_QUOTE_ADJUSTMENT') {
        if ($hasProviderProposedPrice) {
            $setParts[] = 'bri.provider_proposed_price = ?';
            $types .= 'd';
            $params[] = $amount;
        } elseif ($hasItemProposedPrice) {
            $setParts[] = 'bri.proposed_price = ?';
            $types .= 'd';
            $params[] = $amount;
        }
        if ($hasProviderProposedCurrency) {
            $setParts[] = 'bri.provider_proposed_currency = ?';
            $types .= 's';
            $params[] = $currency;
        } elseif ($hasItemCurrency) {
            $setParts[] = 'bri.currency = ?';
            $types .= 's';
            $params[] = $currency;
        }
    }

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET " . implode(', ', $setParts) . "
            WHERE bri.id = ?";
    $types .= 'i';
    $params[] = (int)$ctx['item_id'];
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    $sql .= (string)$scope['scope_where'];
    $sql .= ' LIMIT 1';

    $updateTypes = $types . (string)$scope['scope_types'];
    $updateParams = array_merge($params, (array)$scope['scope_params']);
    $stmtUpdate = mysqli_prepare($conexion, $sql);
    if (!$stmtUpdate) {
        admin_inbox_err('prepare_failed', 500);
    }
    if (!inbox_bind_stmt_params($stmtUpdate, $updateTypes, $updateParams) || !mysqli_stmt_execute($stmtUpdate)) {
        $err = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        admin_inbox_err('update_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmtUpdate);

    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    $itemId = (int)$ctx['item_id'];
    $maxBeforeInsert = null;
    $stmtMax = mysqli_prepare($conexion, "SELECT COALESCE(MAX(id), 0) AS max_id FROM inbox_messages WHERE thread_id = ?");
    if ($stmtMax) {
        mysqli_stmt_bind_param($stmtMax, 's', $threadId);
        if (mysqli_stmt_execute($stmtMax)) {
            $resMax = mysqli_stmt_get_result($stmtMax);
            $rowMax = $resMax ? mysqli_fetch_assoc($resMax) : null;
            $maxBeforeInsert = (int)($rowMax['max_id'] ?? 0);
        }
        mysqli_stmt_close($stmtMax);
    }
    $senderRole = (string)$scope['reader_role'];
    $senderUserId = (int)$scope['user_id'];

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        admin_inbox_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param($stmt, 'ssiisis', $threadId, $threadType, $requestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        admin_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_client_email($conexion, $requestId, $emailSource);
        mt_email_debug_log(
            'ADMIN_NOTIFY_CLIENT_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        mt_email_debug_log(
            'TAG=PROVE_MD5 interaction_email_md5=' . md5_file(__DIR__ . '/../../inc/interaction_email.php')
            . ' interaction_email_path=' . realpath(__DIR__ . '/../../inc/interaction_email.php')
        );
        mt_email_debug_log('TAG=PROVE_HIT reached_before_notify action=' . $action . ' msg_id=' . (int)$messageId);
        file_put_contents(__DIR__ . '/../../storage/logs/email_debug.log',
            date('c') . ' ADMIN_BEFORE_NOTIFY file=' . __FILE__ . ' msg_id=' . $messageId . ' ctx_thread_id=' . $ctx['thread_id'] . "\n",
            FILE_APPEND
        );
        $notifyResult = notify_new_message_to_client(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            $senderRole,
            $message,
            $resolvedEmail,
            $emailSource,
            $messageId,
            $maxBeforeInsert
        );
        mt_email_debug_log('ADMIN_NOTIFY_CLIENT_DONE result=' . json_encode($notifyResult));
    } else {
        mt_email_debug_log(
            'TAG=PROVE_MD5 interaction_email_md5=' . md5_file(__DIR__ . '/../../inc/interaction_email.php')
            . ' interaction_email_path=' . realpath(__DIR__ . '/../../inc/interaction_email.php')
        );
        mt_email_debug_log('TAG=PROVE_HIT reached_before_notify action=' . $action . ' msg_id=' . (int)$messageId);
        file_put_contents(__DIR__ . '/../../storage/logs/email_debug.log',
            date('c') . ' ADMIN_BEFORE_NOTIFY file=' . __FILE__ . ' msg_id=' . $messageId . ' ctx_thread_id=' . $ctx['thread_id'] . "\n",
            FILE_APPEND
        );
        notify_new_message_to_client($conexion, $requestId, $itemId, $threadType, $senderRole, $message, '', '', $messageId, $maxBeforeInsert);
    }

    admin_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_request_id' => $requestId,
        'item_id' => $itemId,
        'item_status' => 'awaiting_client',
        'message' => [
            'id' => $messageId,
            'sender' => inbox_sender_to_ui($senderRole),
            'body' => $message,
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'mark_read') {
    if (!inbox_table_exists($conexion, 'inbox_thread_reads') || !inbox_table_exists($conexion, 'inbox_messages')) {
        admin_inbox_err('inbox_read_state_not_available', 409);
    }

    $threadId = (string)$ctx['thread_id'];
    $maxId = 0;
    $stmtMax = mysqli_prepare($conexion, "SELECT COALESCE(MAX(id), 0) AS max_id FROM inbox_messages WHERE thread_id = ?");
    if ($stmtMax) {
        mysqli_stmt_bind_param($stmtMax, 's', $threadId);
        if (mysqli_stmt_execute($stmtMax)) {
            $resMax = mysqli_stmt_get_result($stmtMax);
            $rowMax = $resMax ? mysqli_fetch_assoc($resMax) : null;
            $maxId = (int)($rowMax['max_id'] ?? 0);
        }
        mysqli_stmt_close($stmtMax);
    }

    $readerRole = (string)$scope['reader_role'];
    $readerUserId = (int)$scope['user_id'];
    $upsert = "INSERT INTO inbox_thread_reads (thread_id, reader_role, reader_user_id, last_read_message_id, last_read_at)
               VALUES (?, ?, ?, ?, NOW())
               ON DUPLICATE KEY UPDATE
                 last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), VALUES(last_read_message_id)),
                 last_read_at = NOW()";
    $stmt = mysqli_prepare($conexion, $upsert);
    if (!$stmt) {
        admin_inbox_err('prepare_failed', 500);
    }
    mysqli_stmt_bind_param($stmt, 'ssii', $threadId, $readerRole, $readerUserId, $maxId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        admin_inbox_err('mark_read_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmt);

    admin_inbox_ok([
        'thread_id' => $threadId,
        'last_read_message_id' => $maxId,
    ]);
}

admin_inbox_err('invalid_action', 400);
