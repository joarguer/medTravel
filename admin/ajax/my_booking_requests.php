<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../include/email_config.php';

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
            $messages[] = [
                'sender' => ($type === 'CLIENT_MESSAGE') ? 'client' : 'provider',
                'type' => strtolower($type),
                'time' => trim((string)$m[2]),
                'actor' => isset($m[3]) ? trim((string)$m[3]) : '',
                'body' => trim((string)$m[4]),
            ];
        }
    }

    return $messages;
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

    $bookingNameExpr = $hasBookingName ? 'br.name' : "''";
    $bookingEmailExpr = $hasBookingEmail ? 'br.email' : "''";
    $bookingPhoneExpr = $hasBookingPhone ? 'br.phone' : "''";
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

    $bookingRequestId = (int)$row['booking_request_id'];
    $row['messages'] = parse_additional_notes_messages((string)($row['additional_notes'] ?? ''));
    sort_messages_by_time($row['messages']);

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
    $itemId = intval($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id');
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }

    $bookingRequestId = (int)$itemRow['booking_request_id'];
    $messages = parse_additional_notes_messages(fetch_booking_additional_notes($conexion, $bookingRequestId, $hasRequestsSoftDelete));

    $timelineSql = "SELECT
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
                    WHERE bri.booking_request_id = ?";

    if ($hasItemsSoftDelete) {
        $timelineSql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $timelineSql .= ' AND br.is_deleted = 0';
    }
    $timelineSql .= $scopeWhere;
    $timelineSql .= ' ORDER BY bri.id ASC';

    $stmtTimeline = mysqli_prepare($conexion, $timelineSql);
    if ($stmtTimeline) {
        $timelineTypes = 'i' . $scopeTypes;
        $timelineParams = array_merge([$bookingRequestId], $scopeParams);
        bind_stmt_params($stmtTimeline, $timelineTypes, $timelineParams);
        if (mysqli_stmt_execute($stmtTimeline)) {
            $timelineRes = mysqli_stmt_get_result($stmtTimeline);
            while ($timelineRes && ($timelineRow = mysqli_fetch_assoc($timelineRes))) {
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
                    ];
                }
            }
        }
        mysqli_stmt_close($stmtTimeline);
    }

    sort_messages_by_time($messages);
    json_ok(['booking_request_id' => $bookingRequestId, 'messages' => $messages]);
}

if ($action === 'send_message') {
    $itemId = intval($_POST['item_id'] ?? 0);
    $messageText = trim((string)($_POST['message'] ?? ''));
    if ($itemId <= 0) {
        json_err('invalid_id');
    }
    if ($messageText === '') {
        json_err('message_required', 422);
    }
    if (!$hasAdditionalNotes) {
        json_err('additional_notes_not_available', 409);
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }

    $bookingRequestId = (int)$itemRow['booking_request_id'];
    $stamp = date('Y-m-d H:i:s');
    $normalizedMessage = normalize_message_text($messageText);
    $actor = '';
    if ($providerId > 0) {
        $actor .= 'provider:' . $providerId;
    }
    if ($serviceProviderId > 0) {
        $actor .= ($actor !== '' ? '|' : '') . 'service_provider:' . $serviceProviderId;
    }
    if ($actor === '') {
        $actor = 'provider';
    }
    $entry = '[PROVIDER_MESSAGE][' . $stamp . '][' . $actor . '] ' . $normalizedMessage;

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
        'message' => [
            'sender' => 'provider',
            'type' => 'provider_message',
            'time' => $stamp,
            'actor' => $actor,
            'body' => $normalizedMessage,
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

            $htmlBody = '<p>Hello ' . safe_html($safeClientName) . ',</p>'
                . '<p>There is a new update on your MedTravel request.</p>'
                . '<p><strong>Request ID:</strong> #' . safe_html((string)$bookingId) . '<br>'
                . '<strong>Service:</strong> ' . safe_html($safeItemName) . '<br>'
                . '<strong>New status:</strong> ' . safe_html($statusLabel) . '</p>'
                . $summaryHtml
                . '<p>You can log in to your client portal to review details.</p>';

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
