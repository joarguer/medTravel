<?php
include '../include/conexion.php';
require_once '../include/roles.php';

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

if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
    json_err('forbidden', 403);
}

if (!table_exists($conexion, 'booking_request_items')) {
    json_err('booking_request_items_not_available', 409);
}

$providerId = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
$serviceProviderId = isset($_SESSION['service_provider_id']) ? intval($_SESSION['service_provider_id']) : 0;

if ($providerId <= 0 && $serviceProviderId <= 0) {
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
$hasItemStatus = table_has_column($conexion, 'booking_request_items', 'item_status');
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

if (!$hasItemStatus) {
    json_err('item_status_not_available', 409);
}

$scopeWhere = '';
$scopeTypes = '';
$scopeParams = [];
if ($providerId > 0 && $serviceProviderId > 0) {
    $scopeWhere = ' AND (bri.provider_id = ? OR bri.service_provider_id = ?)';
    $scopeTypes = 'ii';
    $scopeParams = [$providerId, $serviceProviderId];
} elseif ($providerId > 0) {
    $scopeWhere = ' AND bri.provider_id = ?';
    $scopeTypes = 'i';
    $scopeParams = [$providerId];
} else {
    $scopeWhere = ' AND bri.service_provider_id = ?';
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

    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id,
                bri.item_type,
                CASE
                    WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                    ELSE bri.item_status
                END AS item_status,
                br.destination,
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

    json_ok(['data' => $row]);
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

    json_ok(['message' => 'Respuesta guardada', 'status' => $targetStatus]);
}

json_err('invalid_action');
