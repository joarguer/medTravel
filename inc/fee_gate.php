<?php

function mt_fee_table_exists($conexion, $table)
{
    static $cache = [];
    $key = (string)$table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $key);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function mt_fee_table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = (string)$table . '.' . (string)$column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, (string)$table);
    $columnEsc = mysqli_real_escape_string($conexion, (string)$column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function mt_fee_bind_stmt_params($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [$types];
    foreach ($params as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function mt_fee_booking_has_provider_confirmed_item($conexion, $bookingRequestId)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0) {
        return false;
    }
    if (!mt_fee_table_exists($conexion, 'booking_request_items')) {
        return false;
    }
    if (!mt_fee_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        return false;
    }

    $hasItemsSoftDelete = mt_fee_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $normalizedStatusExpr = "CASE
        WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
        ELSE bri.item_status
    END";

    $sql = "SELECT 1
            FROM booking_request_items bri
            WHERE bri.booking_request_id = ?
              AND {$normalizedStatusExpr} = 'provider_confirmed'";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return is_array($row) && !empty($row);
}

function mt_fee_get_booking_meta($conexion, $bookingRequestId)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0) {
        return null;
    }
    if (!mt_fee_table_exists($conexion, 'booking_requests')) {
        return null;
    }

    $hasFeeStatus = mt_fee_table_has_column($conexion, 'booking_requests', 'fee_status');
    $hasFeeRequired = mt_fee_table_has_column($conexion, 'booking_requests', 'fee_required');
    $hasRequestsSoftDelete = mt_fee_table_has_column($conexion, 'booking_requests', 'is_deleted');
    if (!$hasFeeStatus && !$hasFeeRequired) {
        return null;
    }

    $sql = "SELECT br.id";
    $sql .= $hasFeeStatus ? ", br.fee_status" : ", 'pending' AS fee_status";
    $sql .= $hasFeeRequired ? ", br.fee_required" : ", 0 AS fee_required";
    $sql .= " FROM booking_requests br WHERE br.id = ?";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }

    $feeStatus = strtolower(trim((string)($row['fee_status'] ?? 'pending')));
    if (!in_array($feeStatus, ['not_required', 'pending', 'paid'], true)) {
        $feeStatus = 'pending';
    }
    return [
        'booking_id' => (int)($row['id'] ?? 0),
        'fee_status' => $feeStatus,
        'fee_required' => (int)($row['fee_required'] ?? 0) === 1,
        'has_fee_status_column' => $hasFeeStatus,
        'has_fee_required_column' => $hasFeeRequired,
    ];
}

function is_booking_fee_paid($conexion, $bookingRequestId)
{
    $meta = mt_fee_get_booking_meta($conexion, $bookingRequestId);
    if (!is_array($meta) || empty($meta['has_fee_status_column'])) {
        return false;
    }
    return ((string)$meta['fee_status'] === 'paid');
}

function is_booking_fee_required($conexion, $bookingRequestId)
{
    $meta = mt_fee_get_booking_meta($conexion, $bookingRequestId);
    if (!is_array($meta) || empty($meta['has_fee_status_column'])) {
        return false;
    }
    if (is_booking_fee_paid($conexion, $bookingRequestId)) {
        return false;
    }
    return mt_fee_booking_has_provider_confirmed_item($conexion, $bookingRequestId);
}

function mt_fee_booking_required_for_owner_scope($conexion, $bookingRequestId, $ownerScope)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0 || !is_array($ownerScope)) {
        return false;
    }
    $scopeSql = trim((string)($ownerScope['sql'] ?? ''));
    if ($scopeSql === '' || $scopeSql === '1=0') {
        return false;
    }
    if (!mt_fee_table_exists($conexion, 'booking_requests')) {
        return false;
    }

    $hasRequestsSoftDelete = mt_fee_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $sql = "SELECT br.id
            FROM booking_requests br
            WHERE br.id = ? AND ({$scopeSql})";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }

    $types = 'i' . (string)($ownerScope['types'] ?? '');
    $params = array_merge([$bookingRequestId], is_array($ownerScope['params'] ?? null) ? $ownerScope['params'] : []);
    if (!mt_fee_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return false;
    }
    return is_booking_fee_required($conexion, $bookingRequestId);
}

function mt_fee_any_required_for_owner_scope($conexion, $ownerScope)
{
    if (!is_array($ownerScope)) {
        return false;
    }
    $scopeSql = trim((string)($ownerScope['sql'] ?? ''));
    if ($scopeSql === '' || $scopeSql === '1=0') {
        return false;
    }
    if (!mt_fee_table_exists($conexion, 'booking_requests') || !mt_fee_table_exists($conexion, 'booking_request_items')) {
        return false;
    }
    if (!mt_fee_table_has_column($conexion, 'booking_requests', 'fee_status')) {
        return false;
    }
    if (!mt_fee_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        return false;
    }

    $hasRequestsSoftDelete = mt_fee_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasItemsSoftDelete = mt_fee_table_has_column($conexion, 'booking_request_items', 'is_deleted');

    $normalizedStatusExpr = "CASE
        WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
        ELSE bri.item_status
    END";

    $sql = "SELECT 1
            FROM booking_requests br
            WHERE ({$scopeSql})";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= " AND LOWER(TRIM(COALESCE(br.fee_status, 'pending'))) <> 'paid'";
    $sql .= " AND EXISTS (
                SELECT 1
                FROM booking_request_items bri
                WHERE bri.booking_request_id = br.id";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    $sql .= " AND {$normalizedStatusExpr} = 'provider_confirmed'
              )
              LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    $types = (string)($ownerScope['types'] ?? '');
    $params = is_array($ownerScope['params'] ?? null) ? $ownerScope['params'] : [];
    if (!mt_fee_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return is_array($row) && !empty($row);
}
