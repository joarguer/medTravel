<?php

function client_table_exists($conexion, $table)
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

function client_table_has_column($conexion, $table, $column)
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

function client_status_label($status)
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

function client_status_is_update($status)
{
    $status = client_status_label($status);
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

function client_fetch_notifications($conexion, $clientUserId, $limit = 10)
{
    $clientUserId = (int)$clientUserId;
    $limit = max(1, min(50, (int)$limit));
    $out = ['count' => 0, 'items' => []];

    if ($clientUserId <= 0 || !client_table_exists($conexion, 'booking_requests') || !client_table_has_column($conexion, 'booking_requests', 'client_user_id')) {
        return $out;
    }

    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasTimeline = client_table_has_column($conexion, 'booking_requests', 'timeline');

    $bookingSql = "SELECT br.id, br.created_at, br.destination, br.status";
    if ($hasTimeline) {
        $bookingSql .= ", br.timeline";
    } else {
        $bookingSql .= ", '' AS timeline";
    }
    $bookingSql .= " FROM booking_requests br WHERE br.client_user_id = ?";
    if ($hasBookingSoftDelete) {
        $bookingSql .= " AND br.is_deleted = 0";
    }
    $bookingSql .= " ORDER BY br.created_at DESC LIMIT " . $limit;

    $stmtBookings = mysqli_prepare($conexion, $bookingSql);
    if ($stmtBookings) {
        mysqli_stmt_bind_param($stmtBookings, 'i', $clientUserId);
        if (mysqli_stmt_execute($stmtBookings)) {
            $res = mysqli_stmt_get_result($stmtBookings);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $bookingId = (int)$row['id'];
                $status = client_status_label($row['status'] ?? '');
                $destination = trim((string)($row['destination'] ?? ''));
                $timeline = trim((string)($row['timeline'] ?? ''));
                $subtitle = '';
                if ($destination !== '') {
                    $subtitle = $destination;
                }
                if ($timeline !== '') {
                    $subtitle = ($subtitle !== '' ? $subtitle . ' · ' : '') . $timeline;
                }
                $out['items'][] = [
                    'type' => 'booking',
                    'title' => 'Request #' . $bookingId . ' (' . $status . ')',
                    'subtitle' => $subtitle,
                    'time' => (string)($row['created_at'] ?? ''),
                    'url' => '/client/request_detail.php?id=' . $bookingId,
                ];
            }
        }
        mysqli_stmt_close($stmtBookings);
    }

    if (client_table_exists($conexion, 'booking_request_items')) {
        $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasItemStatus = client_table_has_column($conexion, 'booking_request_items', 'item_status');
        $hasItemUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');
        $hasItemCreatedAt = client_table_has_column($conexion, 'booking_request_items', 'created_at');
        $hasProviderNotes = client_table_has_column($conexion, 'booking_request_items', 'provider_notes');
        $hasProviderResponseAt = client_table_has_column($conexion, 'booking_request_items', 'provider_response_at');

        if ($hasItemStatus) {
            $eventAtExpr = 'br.created_at';
            if ($hasItemUpdatedAt && $hasProviderResponseAt && $hasItemCreatedAt) {
                $eventAtExpr = 'COALESCE(bri.provider_response_at, bri.updated_at, bri.created_at, br.created_at)';
            } elseif ($hasItemUpdatedAt && $hasItemCreatedAt) {
                $eventAtExpr = 'COALESCE(bri.updated_at, bri.created_at, br.created_at)';
            } elseif ($hasItemCreatedAt) {
                $eventAtExpr = 'COALESCE(bri.created_at, br.created_at)';
            }

            $notesExpr = $hasProviderNotes ? 'bri.provider_notes' : "''";
            $itemsSql = "SELECT bri.booking_request_id, bri.item_status, {$eventAtExpr} AS event_at, {$notesExpr} AS provider_notes
                         FROM booking_request_items bri
                         INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                         WHERE br.client_user_id = ?";
            if ($hasBookingSoftDelete) {
                $itemsSql .= " AND br.is_deleted = 0";
            }
            if ($hasItemsSoftDelete) {
                $itemsSql .= " AND bri.is_deleted = 0";
            }
            $itemsSql .= " ORDER BY event_at DESC, bri.id DESC LIMIT " . $limit;

            $stmtItems = mysqli_prepare($conexion, $itemsSql);
            if ($stmtItems) {
                mysqli_stmt_bind_param($stmtItems, 'i', $clientUserId);
                if (mysqli_stmt_execute($stmtItems)) {
                    $resItems = mysqli_stmt_get_result($stmtItems);
                    while ($resItems && ($itemRow = mysqli_fetch_assoc($resItems))) {
                        $status = client_status_label($itemRow['item_status'] ?? '');
                        if (!client_status_is_update($status) && trim((string)($itemRow['provider_notes'] ?? '')) === '') {
                            continue;
                        }
                        $bookingId = (int)$itemRow['booking_request_id'];
                        $subtitle = '';
                        if (trim((string)($itemRow['provider_notes'] ?? '')) !== '') {
                            $subtitle = trim((string)$itemRow['provider_notes']);
                        }
                        $out['items'][] = [
                            'type' => 'item_status',
                            'title' => 'Service update in request #' . $bookingId . ': ' . $status,
                            'subtitle' => $subtitle,
                            'time' => (string)($itemRow['event_at'] ?? ''),
                            'url' => '/client/request_detail.php?id=' . $bookingId,
                        ];
                    }
                }
                mysqli_stmt_close($stmtItems);
            }
        }
    }

    usort($out['items'], function ($a, $b) {
        $ta = strtotime((string)($a['time'] ?? ''));
        $tb = strtotime((string)($b['time'] ?? ''));
        if ($ta === $tb) {
            return 0;
        }
        return ($ta > $tb) ? -1 : 1;
    });

    if (count($out['items']) > $limit) {
        $out['items'] = array_slice($out['items'], 0, $limit);
    }
    $out['count'] = count($out['items']);

    return $out;
}

