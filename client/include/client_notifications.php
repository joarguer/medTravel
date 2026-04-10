<?php
require_once __DIR__ . '/../../inc/inbox_utils.php';

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
    if ($status === 'appointment_confirmed') {
        return 'provider_confirmed';
    }
    if ($status === 'appointment_requested_change') {
        return 'provider_proposed_change';
    }
    if ($status === 'appointment_cancelled') {
        return 'cancelled';
    }
    if ($status === 'appointment_proposed') {
        return 'awaiting_client';
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

function client_get_session_email()
{
    $email = trim((string)($_SESSION['usuario_email'] ?? ''));
    if ($email === '') {
        $email = trim((string)($_SESSION['usuario'] ?? ''));
    }
    return strtolower($email);
}

function client_bind_params($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [];
    $bind[] = $stmt;
    $bind[] = &$types;
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function client_add_unique_email(&$emails, $email)
{
    $normalized = strtolower(trim((string)$email));
    if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    if (!in_array($normalized, $emails, true)) {
        $emails[] = $normalized;
    }
}

function client_collect_identity_emails($conexion, $clientUserId, $clientEmail)
{
    static $cache = [];

    $clientUserId = (int)$clientUserId;
    $clientEmail = strtolower(trim((string)$clientEmail));
    $cacheKey = $clientUserId . '|' . $clientEmail;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $emails = [];
    client_add_unique_email($emails, $clientEmail);

    if ($clientUserId > 0 && client_table_exists($conexion, 'usuarios')) {
        $userIdCol = client_table_has_column($conexion, 'usuarios', 'id')
            ? 'id'
            : (client_table_has_column($conexion, 'usuarios', 'id_usuario') ? 'id_usuario' : '');
        if ($userIdCol !== '' && client_table_has_column($conexion, 'usuarios', 'email')) {
            $stmtUser = mysqli_prepare($conexion, "SELECT email FROM usuarios WHERE {$userIdCol} = ? LIMIT 1");
            if ($stmtUser) {
                mysqli_stmt_bind_param($stmtUser, 'i', $clientUserId);
                if (mysqli_stmt_execute($stmtUser)) {
                    $resUser = mysqli_stmt_get_result($stmtUser);
                    $rowUser = $resUser ? mysqli_fetch_assoc($resUser) : null;
                    client_add_unique_email($emails, $rowUser['email'] ?? '');
                }
                mysqli_stmt_close($stmtUser);
            }
        }
    }

    if ($clientUserId > 0 && client_table_exists($conexion, 'clientes') && client_table_has_column($conexion, 'clientes', 'email')) {
        $clientesMapCol = client_table_has_column($conexion, 'clientes', 'client_user_id')
            ? 'client_user_id'
            : (client_table_has_column($conexion, 'clientes', 'user_id') ? 'user_id' : '');
        if ($clientesMapCol !== '') {
            $stmtClient = mysqli_prepare(
                $conexion,
                "SELECT email
                 FROM clientes
                 WHERE {$clientesMapCol} = ?
                   AND TRIM(COALESCE(email, '')) <> ''
                 ORDER BY id DESC
                 LIMIT 10"
            );
            if ($stmtClient) {
                mysqli_stmt_bind_param($stmtClient, 'i', $clientUserId);
                if (mysqli_stmt_execute($stmtClient)) {
                    $resClient = mysqli_stmt_get_result($stmtClient);
                    while ($resClient && ($rowClient = mysqli_fetch_assoc($resClient))) {
                        client_add_unique_email($emails, $rowClient['email'] ?? '');
                    }
                }
                mysqli_stmt_close($stmtClient);
            }
        }
    }

    $cache[$cacheKey] = $emails;
    return $emails;
}

function client_build_booking_owner_scope($conexion, $tableAlias, $clientUserId, $clientEmail)
{
    $alias = trim((string)$tableAlias);
    if ($alias === '') {
        $alias = 'br';
    }

    $clientUserId = (int)$clientUserId;
    $clientEmail = strtolower(trim((string)$clientEmail));
    $hasClientUserId = client_table_has_column($conexion, 'booking_requests', 'client_user_id');
    $hasLegacyUserId = client_table_has_column($conexion, 'booking_requests', 'user_id');
    $hasEmail = client_table_has_column($conexion, 'booking_requests', 'email');

    $conditions = [];
    $types = '';
    $params = [];

    if ($clientUserId > 0 && $hasClientUserId) {
        $conditions[] = "{$alias}.client_user_id = ?";
        $types .= 'i';
        $params[] = $clientUserId;
    }

    if ($clientUserId > 0 && $hasLegacyUserId) {
        $conditions[] = "{$alias}.user_id = ?";
        $types .= 'i';
        $params[] = $clientUserId;
    }

    $identityEmails = $hasEmail ? client_collect_identity_emails($conexion, $clientUserId, $clientEmail) : [];
    if (!empty($identityEmails)) {
        $emailConditions = [];
        foreach ($identityEmails as $email) {
            $emailConditions[] = "LOWER(TRIM({$alias}.email)) = LOWER(TRIM(?))";
            $types .= 's';
            $params[] = $email;
        }

        $emailSql = '(' . implode(' OR ', $emailConditions) . ')';
        $unownedConditions = [];
        if ($hasClientUserId) {
            $unownedConditions[] = "COALESCE({$alias}.client_user_id, 0) = 0";
        }
        if ($hasLegacyUserId) {
            $unownedConditions[] = "COALESCE({$alias}.user_id, 0) = 0";
        }

        if (!empty($unownedConditions)) {
            $conditions[] = '((' . implode(' AND ', $unownedConditions) . ') AND ' . $emailSql . ')';
        } else {
            $conditions[] = $emailSql;
        }
    }

    if (!empty($conditions)) {
        return [
            'sql' => '(' . implode(' OR ', $conditions) . ')',
            'types' => $types,
            'params' => $params,
        ];
    }

    return [
        'sql' => '1=0',
        'types' => '',
        'params' => [],
    ];
}

function client_fetch_notifications($conexion, $clientUserId, $limit = 10)
{
    $clientUserId = (int)$clientUserId;
    $limit = max(1, min(50, (int)$limit));
    $out = ['count' => 0, 'items' => []];

    if (inbox_table_exists($conexion, 'inbox_messages') && inbox_table_exists($conexion, 'inbox_thread_reads')) {
        $ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
        if ($ownerScope['sql'] !== '1=0' && client_table_exists($conexion, 'booking_requests')) {
            $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
            $threads = [];

            $careSql = "SELECT br.id AS request_id, br.destination, br.created_at
                        FROM booking_requests br
                        WHERE " . $ownerScope['sql'];
            if ($hasBookingSoftDelete) {
                $careSql .= " AND br.is_deleted = 0";
            }
            $careSql .= " ORDER BY br.created_at DESC LIMIT " . (int)$limit;
            $stmtCare = mysqli_prepare($conexion, $careSql);
            if ($stmtCare) {
                $types = $ownerScope['types'];
                $params = $ownerScope['params'];
                if (inbox_bind_stmt_params($stmtCare, $types, $params) && mysqli_stmt_execute($stmtCare)) {
                    $res = mysqli_stmt_get_result($stmtCare);
                    while ($res && ($row = mysqli_fetch_assoc($res))) {
                        $requestId = (int)($row['request_id'] ?? 0);
                        if ($requestId <= 0) {
                            continue;
                        }
                        $threads[] = [
                            'thread_id' => inbox_thread_id('CARE', $requestId, 0),
                            'thread_type' => 'CARE',
                            'request_id' => $requestId,
                            'item_id' => 0,
                            'title' => 'General - Request #' . $requestId,
                            'subtitle' => trim((string)($row['destination'] ?? '')),
                            'updated_at' => (string)($row['created_at'] ?? ''),
                        ];
                    }
                }
                mysqli_stmt_close($stmtCare);
            }

            if (client_table_exists($conexion, 'booking_request_items')) {
                $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
                $itemSql = "SELECT
                                bri.id AS item_id,
                                bri.booking_request_id AS request_id,
                                COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                                br.destination,
                                br.created_at
                            FROM booking_request_items bri
                            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                            LEFT JOIN service_catalog sc ON sc.id = o.service_id
                            LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                            WHERE " . $ownerScope['sql'];
                if ($hasItemsSoftDelete) {
                    $itemSql .= " AND bri.is_deleted = 0";
                }
                if ($hasBookingSoftDelete) {
                    $itemSql .= " AND br.is_deleted = 0";
                }
                $itemSql .= " ORDER BY br.created_at DESC, bri.id DESC LIMIT " . (int)$limit;
                $stmtItem = mysqli_prepare($conexion, $itemSql);
                if ($stmtItem) {
                    $types = $ownerScope['types'];
                    $params = $ownerScope['params'];
                    if (inbox_bind_stmt_params($stmtItem, $types, $params) && mysqli_stmt_execute($stmtItem)) {
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
                                'thread_type' => 'ITEM',
                                'request_id' => $requestId,
                                'item_id' => $itemId,
                                'title' => $itemName . ' - Request #' . $requestId,
                                'subtitle' => trim((string)($row['destination'] ?? '')),
                                'updated_at' => (string)($row['created_at'] ?? ''),
                            ];
                        }
                    }
                    mysqli_stmt_close($stmtItem);
                }
            }

            $threads = inbox_enrich_threads_with_meta($conexion, $threads, 'CLIENT', $clientUserId);
            return inbox_build_notifications_payload($threads, '/client/app_inbox.php', $limit);
        }
    }

    if (!client_table_exists($conexion, 'booking_requests')) {
        return $out;
    }
    $ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
    if ($ownerScope['sql'] === '1=0') {
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
    $bookingSql .= " FROM booking_requests br WHERE " . $ownerScope['sql'];
    if ($hasBookingSoftDelete) {
        $bookingSql .= " AND br.is_deleted = 0";
    }
    $bookingSql .= " ORDER BY br.created_at DESC LIMIT " . $limit;

    $stmtBookings = mysqli_prepare($conexion, $bookingSql);
    if ($stmtBookings) {
        $bookingTypes = $ownerScope['types'];
        $bookingParams = $ownerScope['params'];
        if (client_bind_params($stmtBookings, $bookingTypes, $bookingParams) && mysqli_stmt_execute($stmtBookings)) {
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
                         WHERE " . $ownerScope['sql'];
            if ($hasBookingSoftDelete) {
                $itemsSql .= " AND br.is_deleted = 0";
            }
            if ($hasItemsSoftDelete) {
                $itemsSql .= " AND bri.is_deleted = 0";
            }
            $itemsSql .= " ORDER BY event_at DESC, bri.id DESC LIMIT " . $limit;

            $stmtItems = mysqli_prepare($conexion, $itemsSql);
            if ($stmtItems) {
                $itemTypes = $ownerScope['types'];
                $itemParams = $ownerScope['params'];
                if (client_bind_params($stmtItems, $itemTypes, $itemParams) && mysqli_stmt_execute($stmtItems)) {
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
