<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../include/email_config.php';
require_once __DIR__ . '/../../inc/email_template.php';
require_once __DIR__ . '/../../inc/inbox_utils.php';
require_once __DIR__ . '/../../inc/fee_gate.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

function json_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function json_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

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

function bind_stmt_params($stmt, $types, &$values)
{
    if ($types === '' || empty($values)) {
        return true;
    }
    $bind = [$types];
    foreach ($values as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function normalize_legacy_item_status($status)
{
    $status = trim((string)$status);
    if ($status === '' || $status === 'pending_admin' || $status === 'pending_review') {
        return 'pending_provider';
    }
    return $status;
}

function is_valid_date_ymd($value)
{
    if ($value === '' || $value === null) {
        return true;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $parts = explode('-', $value);
    if (count($parts) !== 3) {
        return false;
    }
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

function normalize_message_text($text)
{
    return trim((string)preg_replace('/\s+/', ' ', (string)$text));
}

function safe_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function resolve_patientcare_admin_email($conexion)
{
    if (!function_exists('loadEmailAccountsFromDB')) {
        return '';
    }
    $accounts = loadEmailAccountsFromDB($conexion);
    if (!is_array($accounts) || empty($accounts['patientcare']) || !is_array($accounts['patientcare'])) {
        return '';
    }
    $email = trim((string)($accounts['patientcare']['reply_to'] ?? ''));
    if ($email === '') {
        $email = trim((string)($accounts['patientcare']['from_email'] ?? ''));
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function provider_status_label($status)
{
    $map = [
        'provider_confirmed' => 'Confirmed by provider',
        'provider_rejected' => 'Rejected by provider',
        'provider_proposed_change' => 'Provider proposed changes',
    ];
    $key = trim((string)$status);
    return isset($map[$key]) ? $map[$key] : $key;
}

function parse_additional_notes_messages($additionalNotes)
{
    $messages = [];
    $notes = trim((string)$additionalNotes);
    if ($notes === '') {
        return $messages;
    }

    $lines = preg_split('/\R+/', $notes);
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^\[(CLIENT_MESSAGE|PROVIDER_MESSAGE)\]\[(.*?)\](?:\[(.*?)\])?\s*(.*)$/', $line, $m)) {
            $type = strtoupper((string)$m[1]);
            $actorRaw = isset($m[3]) ? trim((string)$m[3]) : '';
            $threadType = 'CARE';
            $threadItemId = 0;
            $actor = $actorRaw;
            if ($actorRaw !== '') {
                if (preg_match('/(?:^|\|)THREAD:ITEM:(\d+)/i', $actorRaw, $scopeMatch)) {
                    $threadType = 'ITEM';
                    $threadItemId = (int)$scopeMatch[1];
                } elseif (preg_match('/(?:^|\|)THREAD:CARE(?:\||$)/i', $actorRaw)) {
                    $threadType = 'CARE';
                }
                $actorParts = explode('|', $actorRaw);
                $actorClean = [];
                foreach ($actorParts as $part) {
                    $part = trim((string)$part);
                    if ($part === '' || stripos($part, 'THREAD:') === 0) {
                        continue;
                    }
                    $actorClean[] = $part;
                }
                $actor = implode('|', $actorClean);
            }
            $messages[] = [
                'sender' => ($type === 'CLIENT_MESSAGE') ? 'client' : 'provider',
                'type' => strtolower($type),
                'time' => trim((string)$m[2]),
                'actor' => $actor,
                'body' => trim((string)$m[4]),
                'thread_type' => $threadType,
                'thread_item_id' => $threadItemId,
            ];
        }
    }

    return $messages;
}

function build_thread_actor($threadType, $threadItemId, $actor)
{
    $threadType = strtoupper(trim((string)$threadType));
    $actor = trim((string)$actor);
    if ($threadType === 'ITEM' && (int)$threadItemId > 0) {
        return 'THREAD:ITEM:' . (int)$threadItemId . ($actor !== '' ? ('|' . $actor) : '');
    }
    return 'THREAD:CARE' . ($actor !== '' ? ('|' . $actor) : '');
}

function sort_messages_by_time(&$messages)
{
    usort($messages, function ($a, $b) {
        $ta = strtotime((string)($a['time'] ?? ''));
        $tb = strtotime((string)($b['time'] ?? ''));
        if ($ta === $tb) {
            return 0;
        }
        return ($ta < $tb) ? -1 : 1;
    });
}

function strip_medtravel_services_requested_block($additionalNotes)
{
    $notes = trim((string)$additionalNotes);
    if ($notes === '') {
        return '';
    }

    $cleaned = preg_replace('/(?:\R|^)\s*MedTravel Services Requested:\s*(?:\R\s*-\s.*)*/i', '', $notes);
    if (!is_string($cleaned)) {
        return $notes;
    }
    $cleaned = preg_replace('/\R{3,}/', "\n\n", $cleaned);
    return trim((string)$cleaned);
}

function find_explicit_provider_complementary_relation($conexion)
{
    $candidates = [
        ['table' => 'provider_complementary_services', 'provider_col' => 'provider_id', 'service_col' => 'medtravel_service_id'],
        ['table' => 'provider_medtravel_services', 'provider_col' => 'provider_id', 'service_col' => 'medtravel_service_id'],
        ['table' => 'provider_complementary_service_map', 'provider_col' => 'provider_id', 'service_col' => 'medtravel_service_id'],
        ['table' => 'medical_provider_complementary_services', 'provider_col' => 'provider_id', 'service_col' => 'medtravel_service_id'],
        ['table' => 'provider_complementary_services', 'provider_col' => 'provider_id', 'service_col' => 'complementary_service_id'],
        ['table' => 'provider_medtravel_services', 'provider_col' => 'provider_id', 'service_col' => 'complementary_service_id'],
    ];

    foreach ($candidates as $candidate) {
        $table = $candidate['table'];
        $providerCol = $candidate['provider_col'];
        $serviceCol = $candidate['service_col'];
        if (!table_exists($conexion, $table)) {
            continue;
        }
        if (!table_has_column($conexion, $table, $providerCol) || !table_has_column($conexion, $table, $serviceCol)) {
            continue;
        }
        return $candidate;
    }

    return null;
}

function build_medical_provider_scope($conexion, $providerId)
{
    $providerId = (int)$providerId;
    $medicalClause = "(bri.item_type = 'medical_offer' AND EXISTS (
        SELECT 1
        FROM provider_service_offers o_scope
        WHERE o_scope.id = bri.offer_id
          AND o_scope.provider_id = ?
    ))";

    $relation = find_explicit_provider_complementary_relation($conexion);
    if (is_array($relation)) {
        $table = $relation['table'];
        $providerCol = $relation['provider_col'];
        $serviceCol = $relation['service_col'];
        $complementaryClause = "(bri.item_type = 'complementary_service' AND EXISTS (
            SELECT 1
            FROM `{$table}` rel
            WHERE rel.`{$providerCol}` = ?
              AND rel.`{$serviceCol}` = bri.medtravel_service_id
        ))";
        return [
            'where' => ' AND (' . $medicalClause . ' OR ' . $complementaryClause . ')',
            'types' => 'ii',
            'params' => [$providerId, $providerId],
        ];
    }

    return [
        'where' => ' AND ' . $medicalClause,
        'types' => 'i',
        'params' => [$providerId],
    ];
}

function fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete)
{
    $sql = "SELECT
                bri.id,
                bri.booking_request_id,
                CASE
                    WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                    ELSE bri.item_status
                END AS current_status,
                COALESCE(NULLIF(bri.currency, ''), NULLIF(o.currency, ''), NULLIF(ms.currency, ''), 'USD') AS base_currency
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
            WHERE bri.id = ?";

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    $types = 'i' . $scopeTypes;
    $params = array_merge([$itemId], $scopeParams);
    bind_stmt_params($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_booking_additional_notes($conexion, $bookingRequestId, $hasRequestsSoftDelete)
{
    if (!table_has_column($conexion, 'booking_requests', 'additional_notes')) {
        return '';
    }

    $sql = "SELECT additional_notes FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return '';
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (string)($row['additional_notes'] ?? '');
}

function sync_booking_fee_gate_state($conexion, $bookingRequestId, $hasRequestsSoftDelete)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0) {
        return;
    }
    if (!table_exists($conexion, 'booking_requests') || !table_exists($conexion, 'booking_request_items')) {
        return;
    }

    $hasFeeStatus = table_has_column($conexion, 'booking_requests', 'fee_status');
    $hasFeeRequired = table_has_column($conexion, 'booking_requests', 'fee_required');
    if (!$hasFeeStatus && !$hasFeeRequired) {
        return;
    }
    if (!table_has_column($conexion, 'booking_request_items', 'item_status')) {
        return;
    }

    $hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $normalizedStatusExpr = "CASE
        WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
        ELSE bri.item_status
    END";

    $statsSql = "SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN {$normalizedStatusExpr} = 'provider_confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
                    SUM(CASE WHEN {$normalizedStatusExpr} IN ('provider_rejected', 'cancelled') THEN 1 ELSE 0 END) AS terminal_count
                 FROM booking_request_items bri
                 WHERE bri.booking_request_id = ?";
    if ($hasItemsSoftDelete) {
        $statsSql .= " AND bri.is_deleted = 0";
    }
    $statsSql .= " LIMIT 1";

    $stmtStats = mysqli_prepare($conexion, $statsSql);
    if (!$stmtStats) {
        return;
    }
    mysqli_stmt_bind_param($stmtStats, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmtStats)) {
        mysqli_stmt_close($stmtStats);
        return;
    }
    $statsRes = mysqli_stmt_get_result($stmtStats);
    $statsRow = $statsRes ? mysqli_fetch_assoc($statsRes) : null;
    mysqli_stmt_close($stmtStats);
    if (!$statsRow) {
        return;
    }

    $totalCount = (int)($statsRow['total_count'] ?? 0);
    $confirmedCount = (int)($statsRow['confirmed_count'] ?? 0);
    $terminalCount = (int)($statsRow['terminal_count'] ?? 0);

    $targetFeeRequired = 0;
    $targetFeeStatus = 'pending';
    if ($confirmedCount > 0) {
        $targetFeeRequired = 1;
        $targetFeeStatus = 'pending';
    } elseif ($totalCount > 0 && $terminalCount >= $totalCount) {
        $targetFeeRequired = 0;
        $targetFeeStatus = 'not_required';
    }

    $setParts = [];
    $types = '';
    $params = [];
    if ($hasFeeRequired) {
        $setParts[] = 'fee_required = ?';
        $types .= 'i';
        $params[] = $targetFeeRequired;
    }
    if ($hasFeeStatus) {
        $setParts[] = "fee_status = CASE
            WHEN LOWER(TRIM(COALESCE(fee_status, 'pending'))) = 'paid' THEN 'paid'
            ELSE ?
        END";
        $types .= 's';
        $params[] = $targetFeeStatus;
    }
    if (empty($setParts)) {
        return;
    }

    $updateSql = "UPDATE booking_requests
                  SET " . implode(', ', $setParts) . "
                  WHERE id = ?";
    $types .= 'i';
    $params[] = $bookingRequestId;
    if ($hasRequestsSoftDelete) {
        $updateSql .= " AND is_deleted = 0";
    }
    $updateSql .= " LIMIT 1";

    $stmtUpdate = mysqli_prepare($conexion, $updateSql);
    if (!$stmtUpdate) {
        return;
    }
    bind_stmt_params($stmtUpdate, $types, $params);
    mysqli_stmt_execute($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
}

function rollup_booking_status($conexion, $bookingRequestId, $hasRequestsSoftDelete)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0) {
        return;
    }
    if (!table_exists($conexion, 'booking_requests') || !table_exists($conexion, 'booking_request_items')) {
        return;
    }
    if (!table_has_column($conexion, 'booking_requests', 'status')) {
        return;
    }
    if (!table_has_column($conexion, 'booking_request_items', 'item_status')) {
        return;
    }

    $hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $normalizedStatusExpr = "CASE
        WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
        ELSE bri.item_status
    END";

    $statsSql = "SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN {$normalizedStatusExpr} = 'provider_confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
                    SUM(CASE WHEN {$normalizedStatusExpr} IN ('provider_rejected', 'cancelled') THEN 1 ELSE 0 END) AS terminal_count
                 FROM booking_request_items bri
                 WHERE bri.booking_request_id = ?";
    if ($hasItemsSoftDelete) {
        $statsSql .= " AND bri.is_deleted = 0";
    }
    $statsSql .= " LIMIT 1";

    $stmtStats = mysqli_prepare($conexion, $statsSql);
    if (!$stmtStats) {
        return;
    }
    mysqli_stmt_bind_param($stmtStats, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmtStats)) {
        mysqli_stmt_close($stmtStats);
        return;
    }
    $statsRes = mysqli_stmt_get_result($stmtStats);
    $statsRow = $statsRes ? mysqli_fetch_assoc($statsRes) : null;
    mysqli_stmt_close($stmtStats);
    if (!$statsRow) {
        return;
    }

    $totalCount = (int)($statsRow['total_count'] ?? 0);
    $confirmedCount = (int)($statsRow['confirmed_count'] ?? 0);
    $terminalCount = (int)($statsRow['terminal_count'] ?? 0);

    $targetStatus = 'pending';
    if ($confirmedCount > 0) {
        $targetStatus = 'confirmed';
    } elseif ($totalCount > 0 && $terminalCount >= $totalCount) {
        $targetStatus = 'cancelled';
    }

    $currentSql = "SELECT status FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $currentSql .= " AND is_deleted = 0";
    }
    $currentSql .= " LIMIT 1";
    $stmtCurrent = mysqli_prepare($conexion, $currentSql);
    if (!$stmtCurrent) {
        return;
    }
    mysqli_stmt_bind_param($stmtCurrent, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmtCurrent)) {
        mysqli_stmt_close($stmtCurrent);
        return;
    }
    $currentRes = mysqli_stmt_get_result($stmtCurrent);
    $currentRow = $currentRes ? mysqli_fetch_assoc($currentRes) : null;
    mysqli_stmt_close($stmtCurrent);
    if (!$currentRow) {
        return;
    }
    $currentStatus = strtolower(trim((string)($currentRow['status'] ?? '')));

    if ($targetStatus === 'pending' && $currentStatus !== 'pending') {
        return;
    }
    if ($currentStatus === $targetStatus) {
        return;
    }

    $setParts = ['status = ?'];
    $types = 's';
    $params = [$targetStatus];
    if (table_has_column($conexion, 'booking_requests', 'updated_at')) {
        $setParts[] = 'updated_at = NOW()';
    }

    $updateSql = "UPDATE booking_requests
                  SET " . implode(', ', $setParts) . "
                  WHERE id = ?";
    $types .= 'i';
    $params[] = $bookingRequestId;
    if ($hasRequestsSoftDelete) {
        $updateSql .= " AND is_deleted = 0";
    }
    $updateSql .= " LIMIT 1";

    $stmtUpdate = mysqli_prepare($conexion, $updateSql);
    if (!$stmtUpdate) {
        return;
    }
    bind_stmt_params($stmtUpdate, $types, $params);
    mysqli_stmt_execute($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
}

if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
    json_err('forbidden', 403);
}

if (!table_exists($conexion, 'booking_request_items')) {
    json_err('booking_request_items_not_available', 409);
}

$providerId = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
$serviceProviderId = isset($_SESSION['service_provider_id']) ? intval($_SESSION['service_provider_id']) : 0;
$isAdminSession = is_role_admin_session();
$isComplementarySession = is_complementary_user_session();
$sessionRoleText = strtolower(trim((string)($_SESSION['rol'] ?? '')));
$sessionRoleId = current_role_id();
$hasComplementaryRoleHint = strpos($sessionRoleText, 'complement') !== false || strpos($sessionRoleText, 'partner') !== false;
$isLikelyMedicalProviderRole = in_array((int)$sessionRoleId, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true)
    || strpos($sessionRoleText, 'prestador') !== false
    || (!$hasComplementaryRoleHint && strpos($sessionRoleText, 'provider') !== false);
$isMedicalProviderSession = !$isAdminSession && ($isLikelyMedicalProviderRole || ($providerId > 0 && !$isComplementarySession));

if ($isMedicalProviderSession && $providerId <= 0) {
    json_err('provider_id_required', 401);
}

if (!$isAdminSession && !$isMedicalProviderSession && $serviceProviderId <= 0) {
    json_err('forbidden', 403);
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : 'list');
$canonicalItemStatuses = [
    'pending_provider',
    'provider_confirmed',
    'provider_rejected',
    'provider_proposed_change',
    'awaiting_client',
    'client_accepted',
    'client_rejected',
    'cancelled',
];
$providerAllowedTargets = [
    'provider_confirmed',
    'provider_rejected',
    'provider_proposed_change',
];

$hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
$hasRequestsSoftDelete = table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasItemsProviderId = table_has_column($conexion, 'booking_request_items', 'provider_id');
$hasItemsServiceProviderId = table_has_column($conexion, 'booking_request_items', 'service_provider_id');
$hasItemStatus = table_has_column($conexion, 'booking_request_items', 'item_status');
$hasItemCreatedAt = table_has_column($conexion, 'booking_request_items', 'created_at');
$hasItemUpdatedAt = table_has_column($conexion, 'booking_request_items', 'updated_at');
$hasItemCurrency = table_has_column($conexion, 'booking_request_items', 'currency');
$hasItemNotes = table_has_column($conexion, 'booking_request_items', 'notes');
$hasItemProposedPrice = table_has_column($conexion, 'booking_request_items', 'proposed_price');

$hasProviderResponseAt = table_has_column($conexion, 'booking_request_items', 'provider_response_at');
$hasProviderResponseBy = table_has_column($conexion, 'booking_request_items', 'provider_response_by');
$hasProviderRejectReason = table_has_column($conexion, 'booking_request_items', 'provider_reject_reason');
$hasProviderProposedDateFrom = table_has_column($conexion, 'booking_request_items', 'provider_proposed_date_from');
$hasProviderProposedDateTo = table_has_column($conexion, 'booking_request_items', 'provider_proposed_date_to');
$hasProviderProposedPrice = table_has_column($conexion, 'booking_request_items', 'provider_proposed_price');
$hasProviderProposedCurrency = table_has_column($conexion, 'booking_request_items', 'provider_proposed_currency');
$hasProviderNotes = table_has_column($conexion, 'booking_request_items', 'provider_notes');

$hasTimelineFrom = table_has_column($conexion, 'booking_requests', 'timeline_from');
$hasTimelineTo = table_has_column($conexion, 'booking_requests', 'timeline_to');
$hasSpecialRequest = table_has_column($conexion, 'booking_requests', 'special_request');
$hasAdditionalNotes = table_has_column($conexion, 'booking_requests', 'additional_notes');
$hasBookingName = table_has_column($conexion, 'booking_requests', 'name');
$hasBookingEmail = table_has_column($conexion, 'booking_requests', 'email');
$hasBookingPhone = table_has_column($conexion, 'booking_requests', 'phone');
$hasBookingFeeStatus = table_has_column($conexion, 'booking_requests', 'fee_status');
$hasBookingClientUserId = table_has_column($conexion, 'booking_requests', 'client_user_id');
$hasBookingOrigin = table_has_column($conexion, 'booking_requests', 'origin');
$hasBookingPersons = table_has_column($conexion, 'booking_requests', 'persons');
$hasBookingCategory = table_has_column($conexion, 'booking_requests', 'category');
$hasBookingServiceCategories = table_has_column($conexion, 'booking_requests', 'service_categories');
$hasBookingMedicalServices = table_has_column($conexion, 'booking_requests', 'medical_services');
$hasBookingBudget = table_has_column($conexion, 'booking_requests', 'budget');
$hasBookingStatus = table_has_column($conexion, 'booking_requests', 'status');
$hasBookingDatetime = table_has_column($conexion, 'booking_requests', 'booking_datetime');
$hasBookingSelectedOffers = table_has_column($conexion, 'booking_requests', 'selected_offers');
$hasBookingCreatedAt = table_has_column($conexion, 'booking_requests', 'created_at');
$hasBookingUpdatedAt = table_has_column($conexion, 'booking_requests', 'updated_at');

if (!$hasItemStatus) {
    json_err('item_status_not_available', 409);
}

$scopeWhere = '';
$scopeTypes = '';
$scopeParams = [];
if ($isAdminSession) {
    $scopeWhere = '';
    $scopeTypes = '';
    $scopeParams = [];
} elseif ($isMedicalProviderSession) {
    if ($hasItemsProviderId) {
        $scopeWhere = " AND bri.provider_id = ? AND bri.item_type = 'medical_offer'";
        $scopeTypes = 'i';
        $scopeParams = [$providerId];
    } else {
        $medicalScope = build_medical_provider_scope($conexion, $providerId);
        $scopeWhere = (string)$medicalScope['where'];
        $scopeTypes = (string)$medicalScope['types'];
        $scopeParams = is_array($medicalScope['params']) ? $medicalScope['params'] : [];
    }
} else {
    if (!$hasItemsServiceProviderId) {
        json_err('service_provider_id_not_available', 409);
    }
    $scopeWhere = " AND bri.service_provider_id = ? AND bri.item_type = 'complementary_service'";
    $scopeTypes = 'i';
    $scopeParams = [$serviceProviderId];
}

$timelineFromExpr = $hasTimelineFrom ? 'br.timeline_from' : 'NULL';
$timelineToExpr = $hasTimelineTo ? 'br.timeline_to' : 'NULL';
$specialRequestExpr = $hasSpecialRequest ? 'br.special_request' : 'NULL';
$additionalNotesExpr = $hasAdditionalNotes ? 'br.additional_notes' : 'NULL';
$responseAtExpr = $hasProviderResponseAt ? 'bri.provider_response_at' : 'NULL';
$rejectReasonExpr = $hasProviderRejectReason ? 'bri.provider_reject_reason' : 'NULL';
$providerNotesExpr = $hasProviderNotes ? 'bri.provider_notes' : ($hasItemNotes ? 'bri.notes' : 'NULL');
$proposedDateFromExpr = $hasProviderProposedDateFrom ? 'bri.provider_proposed_date_from' : 'NULL';
$proposedDateToExpr = $hasProviderProposedDateTo ? 'bri.provider_proposed_date_to' : 'NULL';
$proposedPriceExpr = $hasProviderProposedPrice ? 'bri.provider_proposed_price' : ($hasItemProposedPrice ? 'bri.proposed_price' : 'NULL');
$proposedCurrencyExpr = $hasProviderProposedCurrency ? 'bri.provider_proposed_currency' : ($hasItemCurrency ? 'bri.currency' : 'NULL');

if ($action === 'list_threads') {
    $threads = [];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 200);
    if ($limit < 1) {
        $limit = 200;
    }
    if ($limit > 500) {
        $limit = 500;
    }

    if ($isAdminSession) {
        $careSql = "SELECT br.id AS booking_request_id,
                           br.created_at,
                           br.destination,
                           " . ($hasBookingUpdatedAt ? "COALESCE(br.updated_at, br.created_at)" : "br.created_at") . " AS thread_updated_at
                    FROM booking_requests br
                    WHERE 1=1";
        if ($hasRequestsSoftDelete) {
            $careSql .= " AND br.is_deleted = 0";
        }
        $careSql .= " ORDER BY thread_updated_at DESC LIMIT " . (int)$limit;
        $careRes = mysqli_query($conexion, $careSql);
        if ($careRes) {
            while ($row = mysqli_fetch_assoc($careRes)) {
                $bookingId = (int)($row['booking_request_id'] ?? 0);
                if ($bookingId <= 0) {
                    continue;
                }
                $threads[] = [
                    'thread_key' => 'CARE:' . $bookingId,
                    'thread_type' => 'CARE',
                    'booking_request_id' => $bookingId,
                    'item_id' => 0,
                    'title' => 'General - Request #' . $bookingId,
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['thread_updated_at'] ?? $row['created_at'] ?? ''),
                ];
            }
        }
    }

    $itemSql = "SELECT
                    bri.id AS item_id,
                    bri.booking_request_id,
                    COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                    br.destination,
                    " . ($hasProviderResponseAt && $hasItemUpdatedAt && $hasItemCreatedAt
                        ? "COALESCE(bri.provider_response_at, bri.updated_at, bri.created_at, br.created_at)"
                        : ($hasItemUpdatedAt && $hasItemCreatedAt
                            ? "COALESCE(bri.updated_at, bri.created_at, br.created_at)"
                            : ($hasItemCreatedAt ? "COALESCE(bri.created_at, br.created_at)" : "br.created_at"))) . " AS thread_updated_at
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                LEFT JOIN service_catalog sc ON sc.id = o.service_id
                LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                WHERE 1=1";
    if ($hasItemsSoftDelete) {
        $itemSql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $itemSql .= " AND br.is_deleted = 0";
    }
    $itemSql .= $scopeWhere;
    $itemSql .= " ORDER BY thread_updated_at DESC, bri.id DESC LIMIT " . (int)$limit;

    $stmtThreads = mysqli_prepare($conexion, $itemSql);
    if ($stmtThreads) {
        if ($scopeTypes !== '') {
            bind_stmt_params($stmtThreads, $scopeTypes, $scopeParams);
        }
        if (mysqli_stmt_execute($stmtThreads)) {
            $threadsRes = mysqli_stmt_get_result($stmtThreads);
            while ($threadsRes && ($row = mysqli_fetch_assoc($threadsRes))) {
                $itemId = (int)($row['item_id'] ?? 0);
                $bookingId = (int)($row['booking_request_id'] ?? 0);
                if ($itemId <= 0 || $bookingId <= 0) {
                    continue;
                }
                $title = trim((string)($row['item_name'] ?? ''));
                if ($title === '') {
                    $title = 'Item #' . $itemId;
                }
                $threads[] = [
                    'thread_key' => 'ITEM:' . $itemId,
                    'thread_type' => 'ITEM',
                    'booking_request_id' => $bookingId,
                    'item_id' => $itemId,
                    'title' => $title . ' - Request #' . $bookingId,
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['thread_updated_at'] ?? ''),
                ];
            }
        }
        mysqli_stmt_close($stmtThreads);
    }

    usort($threads, function ($a, $b) {
        $ta = strtotime((string)($a['updated_at'] ?? ''));
        $tb = strtotime((string)($b['updated_at'] ?? ''));
        if ($ta === $tb) {
            return strcmp((string)($a['thread_key'] ?? ''), (string)($b['thread_key'] ?? ''));
        }
        return ($ta > $tb) ? -1 : 1;
    });

    json_ok(['threads' => $threads]);
}

if ($action === 'list') {
    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id,
                bri.item_type,
                bri.offer_id,
                bri.medtravel_service_id,
                CASE
                    WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                    ELSE bri.item_status
                END AS item_status,
                bri.created_at AS item_created_at,
                br.created_at AS booking_created_at,
                br.destination,
                {$timelineFromExpr} AS timeline_from,
                {$timelineToExpr} AS timeline_to,
                br.timeline,
                {$specialRequestExpr} AS special_request,
                {$additionalNotesExpr} AS additional_notes,
                {$responseAtExpr} AS provider_response_at,
                COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                COALESCE(NULLIF(ms.currency, ''), NULLIF(o.currency, ''), NULLIF(bri.currency, ''), 'USD') AS item_currency
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            LEFT JOIN service_catalog sc ON sc.id = o.service_id
            LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
            WHERE 1=1";

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' ORDER BY br.created_at DESC, bri.id DESC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }
    if ($scopeTypes !== '') {
        bind_stmt_params($stmt, $scopeTypes, $scopeParams);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if ($isMedicalProviderSession && isset($row['additional_notes'])) {
            $row['additional_notes'] = strip_medtravel_services_requested_block((string)$row['additional_notes']);
        }
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    json_ok(['data' => $rows]);
}

if ($action === 'get_detail') {
    $itemId = intval($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id');
    }

    $bookingNameExpr = $hasBookingName ? 'br.name' : "''";
    $bookingEmailExpr = $hasBookingEmail ? 'br.email' : "''";
    $bookingPhoneExpr = $hasBookingPhone ? 'br.phone' : "''";
    $bookingFeeStatusExpr = $hasBookingFeeStatus ? 'br.fee_status' : "'pending'";
    $bookingClientUserExpr = $hasBookingClientUserId ? 'br.client_user_id' : 'NULL';
    $bookingOriginExpr = $hasBookingOrigin ? 'br.origin' : "''";
    $bookingPersonsExpr = $hasBookingPersons ? 'br.persons' : "''";
    $bookingCategoryExpr = $hasBookingCategory ? 'br.category' : "''";
    $bookingServiceCategoriesExpr = $hasBookingServiceCategories ? 'br.service_categories' : "''";
    $bookingMedicalServicesExpr = $hasBookingMedicalServices ? 'br.medical_services' : "''";
    $bookingBudgetExpr = $hasBookingBudget ? 'br.budget' : "NULL";
    $bookingStatusExpr = $hasBookingStatus ? 'br.status' : "'pending'";
    $bookingDatetimeExpr = $hasBookingDatetime ? 'br.booking_datetime' : "''";
    $bookingSelectedOffersExpr = $hasBookingSelectedOffers ? 'br.selected_offers' : "''";
    $bookingCreatedAtExpr = $hasBookingCreatedAt ? 'br.created_at' : "NULL";
    $bookingUpdatedAtExpr = $hasBookingUpdatedAt ? 'br.updated_at' : "NULL";

    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id,
                bri.item_type,
                CASE
                    WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                    ELSE bri.item_status
                END AS item_status,
                {$bookingNameExpr} AS client_name,
                {$bookingEmailExpr} AS client_email,
                {$bookingPhoneExpr} AS client_phone,
                {$bookingFeeStatusExpr} AS fee_status,
                {$bookingClientUserExpr} AS client_user_id,
                {$bookingOriginExpr} AS origin,
                br.destination,
                {$bookingPersonsExpr} AS persons,
                {$bookingCategoryExpr} AS category,
                {$bookingServiceCategoriesExpr} AS service_categories,
                {$bookingMedicalServicesExpr} AS medical_services,
                {$bookingBudgetExpr} AS budget,
                {$bookingStatusExpr} AS booking_status,
                {$bookingDatetimeExpr} AS booking_datetime,
                {$bookingSelectedOffersExpr} AS selected_offers,
                {$bookingCreatedAtExpr} AS booking_created_at,
                {$bookingUpdatedAtExpr} AS booking_updated_at,
                {$timelineFromExpr} AS timeline_from,
                {$timelineToExpr} AS timeline_to,
                br.timeline,
                {$specialRequestExpr} AS special_request,
                {$additionalNotesExpr} AS additional_notes,
                {$responseAtExpr} AS provider_response_at,
                {$rejectReasonExpr} AS provider_reject_reason,
                {$providerNotesExpr} AS provider_notes,
                {$proposedDateFromExpr} AS provider_proposed_date_from,
                {$proposedDateToExpr} AS provider_proposed_date_to,
                {$proposedPriceExpr} AS provider_proposed_price,
                {$proposedCurrencyExpr} AS provider_proposed_currency,
                COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                COALESCE(NULLIF(ms.currency, ''), NULLIF(o.currency, ''), NULLIF(bri.currency, ''), 'USD') AS item_currency
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            LEFT JOIN service_catalog sc ON sc.id = o.service_id
            LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
            WHERE bri.id = ?";

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    $types = 'i' . $scopeTypes;
    $params = array_merge([$itemId], $scopeParams);
    bind_stmt_params($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        json_err('not_found', 404);
    }

    if (!$isAdminSession) {
        $feeStatus = strtolower(trim((string)($row['fee_status'] ?? '')));
        $itemStatus = strtolower(trim((string)($row['item_status'] ?? '')));
        $allowedItemStatuses = ['provider_confirmed', 'client_accepted'];
        $canShowContact = ($feeStatus === 'paid');
        if ($canShowContact && $itemStatus !== '' && !in_array($itemStatus, $allowedItemStatuses, true)) {
            $canShowContact = false;
        }
        if (!$canShowContact) {
            $row['client_email'] = 'Locked until Coordination Fee is paid';
            $row['client_phone'] = 'Locked until Coordination Fee is paid';
        }
    }

    $bookingRequestId = (int)$row['booking_request_id'];
    $feeLocked = ($bookingRequestId > 0 && is_booking_fee_required($conexion, $bookingRequestId));
    $row['fee_locked'] = $feeLocked ? 1 : 0;
    $rawAdditionalNotes = (string)($row['additional_notes'] ?? '');
    $row['messages'] = parse_additional_notes_messages($rawAdditionalNotes);
    if ($isMedicalProviderSession) {
        $row['additional_notes'] = strip_medtravel_services_requested_block($rawAdditionalNotes);
    }
    sort_messages_by_time($row['messages']);

    $documents = [];
    $documentsError = '';
    $clientId = (int)($row['client_user_id'] ?? 0);
    if ($clientId <= 0 && $hasBookingEmail) {
        $clientEmail = trim((string)($row['client_email'] ?? ''));
        if ($clientEmail !== '' && table_exists($conexion, 'clientes') && table_has_column($conexion, 'clientes', 'email')) {
            $clientLookupSql = "SELECT id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1";
            $stmtClient = mysqli_prepare($conexion, $clientLookupSql);
            if ($stmtClient) {
                mysqli_stmt_bind_param($stmtClient, 's', $clientEmail);
                if (mysqli_stmt_execute($stmtClient)) {
                    $clientRes = mysqli_stmt_get_result($stmtClient);
                    $clientRow = $clientRes ? mysqli_fetch_assoc($clientRes) : null;
                    if ($clientRow) {
                        $clientId = (int)($clientRow['id'] ?? 0);
                    }
                }
                mysqli_stmt_close($stmtClient);
            }
        }
    }

    if ($clientId > 0 && table_exists($conexion, 'client_documents')) {
        $docHasShared = table_has_column($conexion, 'client_documents', 'shared_with_provider');
        $docHasRequestId = table_has_column($conexion, 'client_documents', 'booking_request_id');
        $docHasItemId = table_has_column($conexion, 'client_documents', 'item_id');

        if (!$docHasRequestId || !$docHasItemId) {
            $documentsError = 'client_documents_scope_missing';
        } else {
            $docSql = "SELECT id, document_type, file_path, filename, original_filename, file_size, mime_type, title, uploaded_at, booking_request_id, item_id
                       FROM client_documents WHERE client_id = ?";

            $docTypes = 'i';
            $docParams = [$clientId];
            if ($docHasShared) {
                $docSql .= " AND shared_with_provider = 1";
            }
            $docSql .= " AND booking_request_id = ?";
            $docTypes .= 'i';
            $docParams[] = $bookingRequestId;
            if ($itemId > 0) {
                $docSql .= " AND (item_id = ? OR item_id IS NULL)";
                $docTypes .= 'i';
                $docParams[] = $itemId;
            }
            $docSql .= " ORDER BY uploaded_at DESC";

            $stmtDocs = mysqli_prepare($conexion, $docSql);
            if ($stmtDocs) {
                bind_stmt_params($stmtDocs, $docTypes, $docParams);
                if (mysqli_stmt_execute($stmtDocs)) {
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
    $row['documents'] = $documents;
    if ($documentsError !== '') {
        $row['documents_error'] = $documentsError;
    }

    $history = [];
    $historySql = "SELECT
                    bri.id AS item_id,
                    bri.item_type,
                    CASE
                        WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                        ELSE bri.item_status
                    END AS item_status,
                    " . ($hasItemCreatedAt ? "bri.created_at" : "NULL") . " AS item_created_at,
                    " . ($hasItemUpdatedAt ? "bri.updated_at" : "NULL") . " AS item_updated_at,
                    {$responseAtExpr} AS provider_response_at,
                    {$rejectReasonExpr} AS provider_reject_reason,
                    {$providerNotesExpr} AS provider_notes,
                    {$proposedDateFromExpr} AS provider_proposed_date_from,
                    {$proposedDateToExpr} AS provider_proposed_date_to,
                    {$proposedPriceExpr} AS provider_proposed_price,
                    {$proposedCurrencyExpr} AS provider_proposed_currency,
                    COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                    COALESCE(NULLIF(ms.currency, ''), NULLIF(o.currency, ''), NULLIF(bri.currency, ''), 'USD') AS item_currency
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                LEFT JOIN service_catalog sc ON sc.id = o.service_id
                LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                WHERE bri.booking_request_id = ?";

    if ($hasItemsSoftDelete) {
        $historySql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $historySql .= ' AND br.is_deleted = 0';
    }
    $historySql .= $scopeWhere;
    $historySql .= ' ORDER BY bri.id ASC';

    $stmtHistory = mysqli_prepare($conexion, $historySql);
    if ($stmtHistory) {
        $historyTypes = 'i' . $scopeTypes;
        $historyParams = array_merge([$bookingRequestId], $scopeParams);
        bind_stmt_params($stmtHistory, $historyTypes, $historyParams);
        if (mysqli_stmt_execute($stmtHistory)) {
            $historyRes = mysqli_stmt_get_result($stmtHistory);
            while ($historyRes && ($historyRow = mysqli_fetch_assoc($historyRes))) {
                $history[] = $historyRow;
            }
        }
        mysqli_stmt_close($stmtHistory);
    }

    json_ok(['data' => $row, 'items_history' => $history]);
}

if ($action === 'list_messages') {
    $threadTypeRaw = trim((string)($_POST['thread_type'] ?? $_GET['thread_type'] ?? ''));
    $legacyMode = ($threadTypeRaw === '');
    $threadType = strtoupper($threadTypeRaw);
    $itemId = intval($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
    $bookingRequestId = intval($_POST['booking_request_id'] ?? $_GET['booking_request_id'] ?? $_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);

    if ($threadType === '') {
        $threadType = 'ITEM';
    }
    if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
        json_err('invalid_thread_type', 422);
    }
    if ($threadType === 'CARE' && !$isAdminSession) {
        json_err('forbidden', 403);
    }

    if ($threadType === 'ITEM') {
        if ($itemId <= 0) {
            json_err('invalid_id');
        }
        $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
        if (!$itemRow) {
            json_err('not_found', 404);
        }
        $bookingRequestId = (int)$itemRow['booking_request_id'];
    } else {
        if ($bookingRequestId <= 0) {
            json_err('invalid_booking_id', 422);
        }
        $bookingSql = "SELECT id FROM booking_requests WHERE id = ?";
        if ($hasRequestsSoftDelete) {
            $bookingSql .= " AND is_deleted = 0";
        }
        $bookingSql .= " LIMIT 1";
        $bookingStmt = mysqli_prepare($conexion, $bookingSql);
        if (!$bookingStmt) {
            json_err('db_prepare_error', 500);
        }
        mysqli_stmt_bind_param($bookingStmt, 'i', $bookingRequestId);
        if (!mysqli_stmt_execute($bookingStmt)) {
            $err = mysqli_stmt_error($bookingStmt);
            mysqli_stmt_close($bookingStmt);
            json_err('db_error: ' . $err, 500);
        }
        $bookingRes = mysqli_stmt_get_result($bookingStmt);
        $bookingRow = $bookingRes ? mysqli_fetch_assoc($bookingRes) : null;
        mysqli_stmt_close($bookingStmt);
        if (!$bookingRow) {
            json_err('not_found', 404);
        }
    }

    $feeLocked = ($bookingRequestId > 0 && is_booking_fee_required($conexion, $bookingRequestId));

    $parsedMessages = parse_additional_notes_messages(fetch_booking_additional_notes($conexion, $bookingRequestId, $hasRequestsSoftDelete));
    $messages = [];
    foreach ($parsedMessages as $m) {
        if ($legacyMode) {
            $messages[] = $m;
            continue;
        }
        $mThreadType = strtoupper((string)($m['thread_type'] ?? 'CARE'));
        $mThreadItemId = (int)($m['thread_item_id'] ?? 0);
        if ($threadType === 'CARE') {
            if ($mThreadType !== 'ITEM') {
                $messages[] = $m;
            }
        } else {
            if ($mThreadType === 'ITEM' && $mThreadItemId === $itemId) {
                $messages[] = $m;
            }
        }
    }

    if ($threadType === 'ITEM') {
        $timelineSql = "SELECT
                            bri.id AS row_item_id,
                            CASE
                                WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                                ELSE bri.item_status
                            END AS item_status,
                            {$providerNotesExpr} AS provider_notes,
                            {$rejectReasonExpr} AS provider_reject_reason,
                            {$responseAtExpr} AS provider_response_at,
                            " . ($hasItemUpdatedAt ? "bri.updated_at" : "NULL") . " AS item_updated_at,
                            " . ($hasItemCreatedAt ? "bri.created_at" : "NULL") . " AS item_created_at
                        FROM booking_request_items bri
                        INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                        WHERE " . ($legacyMode ? "bri.booking_request_id = ?" : "bri.id = ?");

        if ($hasItemsSoftDelete) {
            $timelineSql .= ' AND bri.is_deleted = 0';
        }
        if ($hasRequestsSoftDelete) {
            $timelineSql .= ' AND br.is_deleted = 0';
        }
        $timelineSql .= $scopeWhere;
        if ($legacyMode) {
            $timelineSql .= ' ORDER BY bri.id ASC';
        } else {
            $timelineSql .= ' LIMIT 1';
        }

        $stmtTimeline = mysqli_prepare($conexion, $timelineSql);
        if ($stmtTimeline) {
            $timelineTypes = 'i' . $scopeTypes;
            $timelineParams = array_merge([$legacyMode ? $bookingRequestId : $itemId], $scopeParams);
            bind_stmt_params($stmtTimeline, $timelineTypes, $timelineParams);
            if (mysqli_stmt_execute($stmtTimeline)) {
                $timelineRes = mysqli_stmt_get_result($stmtTimeline);
                while ($timelineRes && ($timelineRow = mysqli_fetch_assoc($timelineRes))) {
                    $rowItemId = (int)($timelineRow['row_item_id'] ?? $itemId);
                    $eventTime = trim((string)($timelineRow['provider_response_at'] ?? ''));
                    if ($eventTime === '') {
                        $eventTime = trim((string)($timelineRow['item_updated_at'] ?? ''));
                    }
                    if ($eventTime === '') {
                        $eventTime = trim((string)($timelineRow['item_created_at'] ?? ''));
                    }

                    $providerNotes = trim((string)($timelineRow['provider_notes'] ?? ''));
                    if ($providerNotes !== '') {
                        $messages[] = [
                            'sender' => 'provider',
                            'type' => 'provider_note',
                            'time' => $eventTime,
                            'actor' => '',
                            'body' => $providerNotes,
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }

                    $rejectReason = trim((string)($timelineRow['provider_reject_reason'] ?? ''));
                    if ($rejectReason !== '') {
                        $messages[] = [
                            'sender' => 'provider',
                            'type' => 'provider_reject_reason',
                            'time' => $eventTime,
                            'actor' => '',
                            'body' => 'Rejection reason: ' . $rejectReason,
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }

                    $status = normalize_legacy_item_status($timelineRow['item_status'] ?? '');
                    if ($status !== '') {
                        $messages[] = [
                            'sender' => 'system',
                            'type' => 'status_update',
                            'time' => $eventTime,
                            'actor' => '',
                            'body' => 'Service status updated to: ' . $status,
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }
                }
            }
            mysqli_stmt_close($stmtTimeline);
        }
    }

    if (inbox_table_exists($conexion, 'inbox_messages')) {
        $threadId = inbox_thread_id($threadType, $bookingRequestId, $itemId);
        $stmtInbox = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? ORDER BY id ASC");
        if ($stmtInbox) {
            mysqli_stmt_bind_param($stmtInbox, 's', $threadId);
            if (mysqli_stmt_execute($stmtInbox)) {
                $resInbox = mysqli_stmt_get_result($stmtInbox);
                while ($resInbox && ($rowInbox = mysqli_fetch_assoc($resInbox))) {
                    $body = (string)($rowInbox['body'] ?? '');
                    $type = 'inbox_message';
                    if (stripos($body, '[ACTION]') === 0) {
                        $type = 'quick_action';
                    } elseif (stripos($body, '[REPLY]') === 0) {
                        $type = 'quick_reply';
                    }
                    $messages[] = [
                        'sender' => inbox_sender_to_ui($rowInbox['sender_role'] ?? ''),
                        'type' => $type,
                        'time' => (string)($rowInbox['created_at'] ?? ''),
                        'actor' => '',
                        'body' => $body,
                        'thread_type' => $threadType,
                        'thread_item_id' => $threadType === 'ITEM' ? $itemId : 0,
                    ];
                }
            }
            mysqli_stmt_close($stmtInbox);
        }
    }

    sort_messages_by_time($messages);
    json_ok([
        'booking_request_id' => $bookingRequestId,
        'thread_type' => $threadType,
        'item_id' => $threadType === 'ITEM' ? $itemId : 0,
        'fee_locked' => $feeLocked ? 1 : 0,
        'messages' => $messages
    ]);
}

if ($action === 'send_message') {
    $messageText = trim((string)($_POST['message'] ?? ''));
    if ($messageText === '') {
        json_err('message_required', 422);
    }
    if (!$hasAdditionalNotes) {
        json_err('additional_notes_not_available', 409);
    }

    $threadType = strtoupper(trim((string)($_POST['thread_type'] ?? '')));
    $itemId = intval($_POST['item_id'] ?? 0);
    $bookingRequestId = intval($_POST['booking_request_id'] ?? $_POST['booking_id'] ?? 0);
    if ($threadType === '') {
        $threadType = 'ITEM';
    }
    if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
        json_err('invalid_thread_type', 422);
    }
    if ($threadType === 'CARE' && !$isAdminSession) {
        json_err('forbidden', 403);
    }

    if ($threadType === 'ITEM') {
        if ($itemId <= 0) {
            json_err('invalid_id', 422);
        }
        $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
        if (!$itemRow) {
            json_err('not_found', 404);
        }
        $bookingRequestId = (int)$itemRow['booking_request_id'];
    } else {
        if ($bookingRequestId <= 0) {
            json_err('invalid_booking_id', 422);
        }
        $bookingSql = "SELECT id FROM booking_requests WHERE id = ?";
        if ($hasRequestsSoftDelete) {
            $bookingSql .= " AND is_deleted = 0";
        }
        $bookingSql .= " LIMIT 1";
        $stmtBooking = mysqli_prepare($conexion, $bookingSql);
        if (!$stmtBooking) {
            json_err('db_prepare_error', 500);
        }
        mysqli_stmt_bind_param($stmtBooking, 'i', $bookingRequestId);
        if (!mysqli_stmt_execute($stmtBooking)) {
            $err = mysqli_stmt_error($stmtBooking);
            mysqli_stmt_close($stmtBooking);
            json_err('db_error: ' . $err, 500);
        }
        $bookingRes = mysqli_stmt_get_result($stmtBooking);
        $bookingRow = $bookingRes ? mysqli_fetch_assoc($bookingRes) : null;
        mysqli_stmt_close($stmtBooking);
        if (!$bookingRow) {
            json_err('not_found', 404);
        }
    }

    $feeLocked = ($bookingRequestId > 0 && is_booking_fee_required($conexion, $bookingRequestId));
    if ($feeLocked && !$isAdminSession) {
        json_err('coordination_fee_required', 403);
    }

    $stamp = date('Y-m-d H:i:s');
    $normalizedMessage = normalize_message_text($messageText);
    $actor = 'provider';
    if ($providerId > 0) {
        $actor = 'provider:' . $providerId;
    }
    if ($serviceProviderId > 0) {
        $actor .= '|service_provider:' . $serviceProviderId;
    } elseif ($isAdminSession) {
        $adminId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
        if ($adminId > 0) {
            $actor = 'admin:' . $adminId;
        } else {
            $actor = 'admin';
        }
    }
    $threadActor = build_thread_actor($threadType, $itemId, $actor);
    $entry = '[PROVIDER_MESSAGE][' . $stamp . '][' . $threadActor . '] ' . $normalizedMessage;

    $currentNotes = fetch_booking_additional_notes($conexion, $bookingRequestId, $hasRequestsSoftDelete);
    $newNotes = trim($currentNotes) !== '' ? (rtrim($currentNotes) . "\n" . $entry) : $entry;

    $updateSql = 'UPDATE booking_requests SET additional_notes = ?';
    if ($hasBookingUpdatedAt) {
        $updateSql .= ', updated_at = NOW()';
    }
    $updateSql .= ' WHERE id = ?';
    if ($hasRequestsSoftDelete) {
        $updateSql .= ' AND is_deleted = 0';
    }
    $updateSql .= ' LIMIT 1';

    $stmtUpdate = mysqli_prepare($conexion, $updateSql);
    if (!$stmtUpdate) {
        json_err('db_prepare_error', 500);
    }
    mysqli_stmt_bind_param($stmtUpdate, 'si', $newNotes, $bookingRequestId);
    if (!mysqli_stmt_execute($stmtUpdate)) {
        $err = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        json_err('db_error: ' . $err, 500);
    }
    mysqli_stmt_close($stmtUpdate);

    json_ok([
        'booking_request_id' => $bookingRequestId,
        'thread_type' => $threadType,
        'item_id' => $threadType === 'ITEM' ? $itemId : 0,
        'message' => [
            'sender' => 'provider',
            'type' => 'provider_message',
            'time' => $stamp,
            'actor' => $actor,
            'body' => $normalizedMessage,
            'thread_type' => $threadType,
            'thread_item_id' => $threadType === 'ITEM' ? $itemId : 0,
        ],
    ]);
}

if ($action === 'send_quick_reply') {
    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id', 422);
    }

    $replyKey = strtoupper(trim((string)($_POST['reply_key'] ?? '')));
    $quickReplies = [
        'DATES_OK' => 'Dates available',
        'DATES_AVAILABLE' => 'Dates available',
        'DATES_NOT_AVAILABLE' => 'Dates not available',
        'REQUEST_MEDICAL_HISTORY' => 'REQUEST HISTORY',
        'REQUEST_LABS' => 'REQUEST LABS',
        'REQUEST_IMAGING' => 'REQUEST IMAGING',
        'REQUEST_PHOTOS' => 'REQUEST PHOTOS'
    ];
    if ($replyKey === '' || !isset($quickReplies[$replyKey])) {
        json_err('invalid_reply_key', 422);
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }
    $bookingRequestId = (int)$itemRow['booking_request_id'];
    if ($bookingRequestId <= 0) {
        json_err('invalid_booking_id', 422);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        json_err('inbox_messages_not_available', 409);
    }

    $message = '[REPLY] ' . $quickReplies[$replyKey];
    $threadId = inbox_thread_id('ITEM', $bookingRequestId, $itemId);
    $senderRole = $isAdminSession ? 'ADMIN' : 'PROVIDER';
    $senderUserId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, 'ITEM', ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }
    mysqli_stmt_bind_param($stmt, 'siisis', $threadId, $bookingRequestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }
    mysqli_stmt_close($stmt);

    json_ok([
        'booking_request_id' => $bookingRequestId,
        'thread_type' => 'ITEM',
        'item_id' => $itemId,
        'message' => [
            'sender' => $isAdminSession ? 'admin' : 'provider',
            'type' => 'quick_reply',
            'time' => date('Y-m-d H:i:s'),
            'actor' => '',
            'body' => $message,
            'thread_type' => 'ITEM',
            'thread_item_id' => $itemId,
        ],
    ]);
}

if (in_array($action, ['provider_confirm', 'provider_reject', 'provider_propose_change', 'update_item_status'], true)) {
    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id');
    }

    $targetStatus = '';
    if ($action === 'provider_confirm') {
        $targetStatus = 'provider_confirmed';
    } elseif ($action === 'provider_reject') {
        $targetStatus = 'provider_rejected';
    } elseif ($action === 'provider_propose_change') {
        $targetStatus = 'provider_proposed_change';
    } else {
        $targetStatus = trim((string)($_POST['status'] ?? ''));
    }

    if (!in_array($targetStatus, $canonicalItemStatuses, true) || !in_array($targetStatus, $providerAllowedTargets, true)) {
        json_err('transition_not_allowed', 403);
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }

    $currentStatus = normalize_legacy_item_status($itemRow['current_status'] ?? '');
    if (!in_array($currentStatus, $canonicalItemStatuses, true)) {
        json_err('invalid_current_status', 409);
    }
    if ($currentStatus !== 'pending_provider') {
        json_err('transition_not_allowed_from_' . $currentStatus, 409);
    }

    $providerResponseBy = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
    if ($providerResponseBy <= 0) {
        $providerResponseBy = null;
    }

    $setParts = ['bri.item_status = ?'];
    $types = 's';
    $params = [$targetStatus];

    if ($hasItemUpdatedAt) {
        $setParts[] = 'bri.updated_at = NOW()';
    }
    if ($hasProviderResponseAt) {
        $setParts[] = 'bri.provider_response_at = NOW()';
    }
    if ($hasProviderResponseBy && $providerResponseBy !== null) {
        $setParts[] = 'bri.provider_response_by = ?';
        $types .= 'i';
        $params[] = $providerResponseBy;
    }

    if ($targetStatus === 'provider_rejected') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            json_err('reject_reason_required', 422);
        }
        $reason = substr($reason, 0, 255);

        if ($hasProviderRejectReason) {
            $setParts[] = 'bri.provider_reject_reason = ?';
            $types .= 's';
            $params[] = $reason;
        }
        if ($hasProviderNotes) {
            $setParts[] = 'bri.provider_notes = ?';
            $types .= 's';
            $params[] = $reason;
        } elseif ($hasItemNotes) {
            $setParts[] = 'bri.notes = ?';
            $types .= 's';
            $params[] = $reason;
        }
    }

    if ($targetStatus === 'provider_proposed_change') {
        $providerNotes = trim((string)($_POST['provider_notes'] ?? ''));
        if ($providerNotes === '') {
            json_err('provider_notes_required', 422);
        }

        $dateFrom = trim((string)($_POST['proposed_date_from'] ?? ''));
        $dateTo = trim((string)($_POST['proposed_date_to'] ?? ''));
        $priceRaw = trim((string)($_POST['proposed_price'] ?? ''));
        $currency = strtoupper(trim((string)($_POST['proposed_currency'] ?? '')));

        if (!is_valid_date_ymd($dateFrom) || !is_valid_date_ymd($dateTo)) {
            json_err('invalid_proposed_dates', 422);
        }
        if ($dateFrom !== '' && $dateTo !== '' && strcmp($dateFrom, $dateTo) > 0) {
            json_err('invalid_date_range', 422);
        }

        $proposedPrice = null;
        if ($priceRaw !== '') {
            if (!is_numeric($priceRaw)) {
                json_err('invalid_proposed_price', 422);
            }
            $proposedPrice = round((float)$priceRaw, 2);
            if ($proposedPrice < 0) {
                json_err('invalid_proposed_price', 422);
            }
        }

        $baseCurrency = strtoupper(trim((string)($itemRow['base_currency'] ?? 'USD')));
        if ($baseCurrency === '') {
            $baseCurrency = 'USD';
        }
        if ($currency === '') {
            $currency = $baseCurrency;
        }
        if (!in_array($currency, ['USD', 'COP'], true)) {
            json_err('invalid_proposed_currency', 422);
        }

        if ($hasProviderNotes) {
            $setParts[] = 'bri.provider_notes = ?';
            $types .= 's';
            $params[] = $providerNotes;
        } elseif ($hasItemNotes) {
            $setParts[] = 'bri.notes = ?';
            $types .= 's';
            $params[] = $providerNotes;
        }

        if ($hasProviderProposedDateFrom) {
            if ($dateFrom !== '') {
                $setParts[] = 'bri.provider_proposed_date_from = ?';
                $types .= 's';
                $params[] = $dateFrom;
            } else {
                $setParts[] = 'bri.provider_proposed_date_from = NULL';
            }
        }
        if ($hasProviderProposedDateTo) {
            if ($dateTo !== '') {
                $setParts[] = 'bri.provider_proposed_date_to = ?';
                $types .= 's';
                $params[] = $dateTo;
            } else {
                $setParts[] = 'bri.provider_proposed_date_to = NULL';
            }
        }

        if ($hasProviderProposedPrice) {
            if ($proposedPrice !== null) {
                $setParts[] = 'bri.provider_proposed_price = ?';
                $types .= 'd';
                $params[] = $proposedPrice;
            } else {
                $setParts[] = 'bri.provider_proposed_price = NULL';
            }
        } elseif ($hasItemProposedPrice && $proposedPrice !== null) {
            $setParts[] = 'bri.proposed_price = ?';
            $types .= 'd';
            $params[] = $proposedPrice;
        } elseif ($hasItemProposedPrice) {
            $setParts[] = 'bri.proposed_price = NULL';
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
    $params[] = $itemId;

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' LIMIT 1';

    $finalTypes = $types . $scopeTypes;
    $finalParams = array_merge($params, $scopeParams);

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    bind_stmt_params($stmt, $finalTypes, $finalParams);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        json_err('not_found_or_no_change', 404);
    }

    $bookingRequestId = (int)($itemRow['booking_request_id'] ?? 0);
    if ($bookingRequestId > 0) {
        sync_booking_fee_gate_state($conexion, $bookingRequestId, $hasRequestsSoftDelete);
        rollup_booking_status($conexion, $bookingRequestId, $hasRequestsSoftDelete);
    }

    try {
        $notifySql = "SELECT
                        br.id AS booking_id,
                        br.name AS client_name,
                        br.email AS client_email,
                        CASE
                            WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                            ELSE bri.item_status
                        END AS item_status,
                        {$providerNotesExpr} AS provider_notes,
                        {$rejectReasonExpr} AS provider_reject_reason,
                        {$proposedDateFromExpr} AS provider_proposed_date_from,
                        {$proposedDateToExpr} AS provider_proposed_date_to,
                        {$proposedPriceExpr} AS provider_proposed_price,
                        {$proposedCurrencyExpr} AS provider_proposed_currency,
                        COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                        COALESCE(NULLIF(ms.currency, ''), NULLIF(o.currency, ''), NULLIF(bri.currency, ''), 'USD') AS item_currency
                    FROM booking_request_items bri
                    INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                    LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                    LEFT JOIN service_catalog sc ON sc.id = o.service_id
                    LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                    WHERE bri.id = ?";
        if ($hasItemsSoftDelete) {
            $notifySql .= ' AND bri.is_deleted = 0';
        }
        if ($hasRequestsSoftDelete) {
            $notifySql .= ' AND br.is_deleted = 0';
        }
        $notifySql .= $scopeWhere . ' LIMIT 1';

        $notifyStmt = mysqli_prepare($conexion, $notifySql);
        $notifyRow = null;
        if ($notifyStmt) {
            $notifyTypes = 'i' . $scopeTypes;
            $notifyParams = array_merge([$itemId], $scopeParams);
            bind_stmt_params($notifyStmt, $notifyTypes, $notifyParams);
            if (mysqli_stmt_execute($notifyStmt)) {
                $notifyRes = mysqli_stmt_get_result($notifyStmt);
                $notifyRow = $notifyRes ? mysqli_fetch_assoc($notifyRes) : null;
            }
            mysqli_stmt_close($notifyStmt);
        }

        if (is_array($notifyRow) && !empty($notifyRow)) {
            $bookingId = (int)($notifyRow['booking_id'] ?? 0);
            $clientName = trim((string)($notifyRow['client_name'] ?? ''));
            $clientEmail = trim((string)($notifyRow['client_email'] ?? ''));
            $itemName = trim((string)($notifyRow['item_name'] ?? ''));
            $statusNow = trim((string)($notifyRow['item_status'] ?? $targetStatus));
            $providerNotes = trim((string)($notifyRow['provider_notes'] ?? ''));
            $rejectReason = trim((string)($notifyRow['provider_reject_reason'] ?? ''));
            $propFrom = trim((string)($notifyRow['provider_proposed_date_from'] ?? ''));
            $propTo = trim((string)($notifyRow['provider_proposed_date_to'] ?? ''));
            $propPriceRaw = $notifyRow['provider_proposed_price'] ?? null;
            $propCurrency = strtoupper(trim((string)($notifyRow['provider_proposed_currency'] ?? $notifyRow['item_currency'] ?? 'USD')));
            if ($propCurrency === '') {
                $propCurrency = 'USD';
            }

            $summaryHtml = '';
            $summaryText = '';
            if ($providerNotes !== '') {
                $summaryHtml .= '<p><strong>Provider notes:</strong> ' . safe_html($providerNotes) . '</p>';
                $summaryText .= "Provider notes: " . $providerNotes . "\n";
            }
            if ($rejectReason !== '') {
                $summaryHtml .= '<p><strong>Reject reason:</strong> ' . safe_html($rejectReason) . '</p>';
                $summaryText .= "Reject reason: " . $rejectReason . "\n";
            }
            if ($targetStatus === 'provider_proposed_change') {
                $summaryHtml .= '<p><strong>Proposed changes:</strong></p><ul>';
                $summaryText .= "Proposed changes:\n";
                if ($propFrom !== '' || $propTo !== '') {
                    $dateRange = trim(($propFrom !== '' ? $propFrom : '?') . ' to ' . ($propTo !== '' ? $propTo : '?'));
                    $summaryHtml .= '<li>Dates: ' . safe_html($dateRange) . '</li>';
                    $summaryText .= "- Dates: " . $dateRange . "\n";
                }
                if ($propPriceRaw !== null && $propPriceRaw !== '') {
                    $propPrice = number_format((float)$propPriceRaw, 2);
                    $summaryHtml .= '<li>Price: ' . safe_html($propCurrency . ' ' . $propPrice) . '</li>';
                    $summaryText .= "- Price: " . $propCurrency . ' ' . $propPrice . "\n";
                }
                if ($providerNotes === '' && $rejectReason === '' && $propFrom === '' && $propTo === '' && ($propPriceRaw === null || $propPriceRaw === '')) {
                    $summaryHtml .= '<li>Provider submitted a change proposal.</li>';
                    $summaryText .= "- Provider submitted a change proposal.\n";
                }
                $summaryHtml .= '</ul>';
            }

            $statusLabel = provider_status_label($statusNow);
            $subject = 'Update on your MedTravel request #' . $bookingId;
            $safeClientName = $clientName !== '' ? $clientName : 'Patient';
            $safeItemName = $itemName !== '' ? $itemName : ('Item #' . $itemId);
            $loginUrl = 'https://medtravel.com.co/login.php';

            $contentHtml = '<p>Hello ' . safe_html($safeClientName) . ',</p>'
                . '<p>There is a new update on your MedTravel request.</p>'
                . '<p><strong>Request ID:</strong> #' . safe_html((string)$bookingId) . '<br>'
                . '<strong>Service:</strong> ' . safe_html($safeItemName) . '<br>'
                . '<strong>New status:</strong> ' . safe_html($statusLabel) . '</p>'
                . $summaryHtml
                . '<p>You can log in to your client portal to review details.</p>';

            $htmlBody = $contentHtml;
            if (function_exists('renderMedTravelEmail')) {
                $htmlBody = renderMedTravelEmail(
                    'Request update',
                    'There is a new update on your MedTravel request.',
                    $contentHtml,
                    'This is an automated message.',
                    [
                        'text' => 'Log in to your client portal',
                        'url' => $loginUrl,
                    ]
                );
            }

            $altBody = "Hello {$safeClientName},\n\n"
                . "There is a new update on your MedTravel request.\n"
                . "Request ID: #{$bookingId}\n"
                . "Service: {$safeItemName}\n"
                . "New status: {$statusLabel}\n";
            if ($summaryText !== '') {
                $altBody .= "\n" . trim($summaryText) . "\n";
            }
            $altBody .= "\nYou can log in to your client portal to review details.\n";

            if (filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    sendEmail($clientEmail, $subject, $htmlBody, 'patientcare', ['alt_body' => $altBody], $conexion);
                } catch (Throwable $emailEx) {
                    error_log('provider_action_email_client_error item_id=' . $itemId . ' action=' . $action . ' msg=' . $emailEx->getMessage());
                }
            }

            $adminEmail = resolve_patientcare_admin_email($conexion);
            if ($adminEmail !== '') {
                $adminSubject = '[ADMIN] ' . $subject;
                $adminHtml = '<p>Provider action received.</p>'
                    . '<p><strong>Request ID:</strong> #' . safe_html((string)$bookingId) . '<br>'
                    . '<strong>Item ID:</strong> #' . safe_html((string)$itemId) . '<br>'
                    . '<strong>Status:</strong> ' . safe_html($statusLabel) . '<br>'
                    . '<strong>Client:</strong> ' . safe_html($safeClientName) . ' (' . safe_html($clientEmail) . ')</p>'
                    . $summaryHtml;
                $adminAlt = "Provider action received.\n"
                    . "Request ID: #{$bookingId}\n"
                    . "Item ID: #{$itemId}\n"
                    . "Status: {$statusLabel}\n"
                    . "Client: {$safeClientName} ({$clientEmail})\n";
                if ($summaryText !== '') {
                    $adminAlt .= "\n" . trim($summaryText) . "\n";
                }

                try {
                    sendEmail($adminEmail, $adminSubject, $adminHtml, 'patientcare', ['alt_body' => $adminAlt], $conexion);
                } catch (Throwable $emailEx) {
                    error_log('provider_action_email_admin_error item_id=' . $itemId . ' action=' . $action . ' msg=' . $emailEx->getMessage());
                }
            }
        }
    } catch (Throwable $e) {
        error_log('provider_action_email_error item_id=' . $itemId . ' action=' . $action . ' msg=' . $e->getMessage());
    }

    json_ok(['message' => 'Respuesta guardada', 'status' => $targetStatus]);
}

json_err('invalid_action');
