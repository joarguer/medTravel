<?php

function inbox_table_exists($conexion, $table)
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

function inbox_table_has_column($conexion, $table, $column)
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

function inbox_bind_stmt_params($stmt, $types, &$params)
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

function inbox_thread_id($threadType, $requestId, $itemId = 0)
{
    $threadType = strtoupper(trim((string)$threadType));
    if ($threadType === 'ITEM') {
        return 'ITEM:' . (int)$itemId;
    }
    return 'CARE:' . (int)$requestId;
}

function inbox_parse_thread_id($threadId)
{
    $threadId = strtoupper(trim((string)$threadId));
    if (preg_match('/^CARE:(\d+)$/', $threadId, $m)) {
        return [
            'ok' => true,
            'thread_id' => 'CARE:' . (int)$m[1],
            'thread_type' => 'CARE',
            'request_id' => (int)$m[1],
            'item_id' => 0,
        ];
    }
    if (preg_match('/^ITEM:(\d+)$/', $threadId, $m)) {
        return [
            'ok' => true,
            'thread_id' => 'ITEM:' . (int)$m[1],
            'thread_type' => 'ITEM',
            'request_id' => 0,
            'item_id' => (int)$m[1],
        ];
    }
    return ['ok' => false];
}

function inbox_sender_to_ui($senderRole)
{
    $senderRole = strtoupper(trim((string)$senderRole));
    if ($senderRole === 'CLIENT') {
        return 'client';
    }
    if ($senderRole === 'PROVIDER') {
        return 'provider';
    }
    if ($senderRole === 'PATIENTCARE') {
        return 'patientcare';
    }
    if ($senderRole === 'ADMIN') {
        return 'admin';
    }
    return 'system';
}

function inbox_parse_legacy_messages($additionalNotes)
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

        if (!preg_match('/^\[(CLIENT_MESSAGE|PROVIDER_MESSAGE)\]\[(.*?)\](?:\[(.*?)\])?\s*(.*)$/', $line, $m)) {
            continue;
        }

        $senderTag = strtoupper((string)$m[1]);
        $actorRaw = isset($m[3]) ? trim((string)$m[3]) : '';
        $threadType = 'CARE';
        $threadItemId = 0;

        if ($actorRaw !== '') {
            if (preg_match('/(?:^|\|)THREAD:ITEM:(\d+)/i', $actorRaw, $scopeMatch)) {
                $threadType = 'ITEM';
                $threadItemId = (int)$scopeMatch[1];
            } elseif (preg_match('/(?:^|\|)THREAD:CARE(?:\||$)/i', $actorRaw)) {
                $threadType = 'CARE';
            }
        }

        $messages[] = [
            'id' => null,
            'sender' => ($senderTag === 'PROVIDER_MESSAGE') ? 'provider' : 'client',
            'body' => trim((string)$m[4]),
            'time' => trim((string)$m[2]),
            'thread_type' => $threadType,
            'thread_item_id' => $threadItemId,
        ];
    }

    usort($messages, function ($a, $b) {
        $ta = strtotime((string)($a['time'] ?? ''));
        $tb = strtotime((string)($b['time'] ?? ''));
        if ($ta === $tb) {
            return 0;
        }
        return ($ta < $tb) ? -1 : 1;
    });

    return $messages;
}

function inbox_filter_legacy_messages($messages, $threadType, $itemId = 0)
{
    $threadType = strtoupper(trim((string)$threadType));
    $itemId = (int)$itemId;
    $filtered = [];
    foreach ($messages as $m) {
        $mType = strtoupper((string)($m['thread_type'] ?? 'CARE'));
        $mItemId = (int)($m['thread_item_id'] ?? 0);
        if ($threadType === 'CARE') {
            if ($mType !== 'ITEM') {
                $filtered[] = $m;
            }
            continue;
        }
        if ($mType === 'ITEM' && $mItemId === $itemId) {
            $filtered[] = $m;
        }
    }
    return $filtered;
}

function inbox_enrich_threads_with_meta($conexion, $threads, $readerRole, $readerUserId)
{
    $readerRole = strtoupper(trim((string)$readerRole));
    $readerUserId = (int)$readerUserId;
    if (empty($threads)) {
        return [];
    }

    $enriched = [];
    $threadIds = [];
    foreach ($threads as $t) {
        $threadId = (string)($t['thread_id'] ?? '');
        if ($threadId === '') {
            continue;
        }
        $t['unread_count'] = 0;
        $t['last_message_id'] = 0;
        $t['last_message_at'] = '';
        $t['last_message_preview'] = '';
        $enriched[$threadId] = $t;
        $threadIds[] = $threadId;
    }

    if (empty($threadIds) || !inbox_table_exists($conexion, 'inbox_messages')) {
        return array_values($enriched);
    }

    $readsMap = [];
    if (inbox_table_exists($conexion, 'inbox_thread_reads')) {
        $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
        $readsSql = "SELECT thread_id, COALESCE(last_read_message_id, 0) AS last_read_message_id
                     FROM inbox_thread_reads
                     WHERE reader_role = ? AND reader_user_id = ? AND thread_id IN ({$placeholders})";
        $stmtReads = mysqli_prepare($conexion, $readsSql);
        if ($stmtReads) {
            $types = 'si' . str_repeat('s', count($threadIds));
            $params = array_merge([$readerRole, $readerUserId], $threadIds);
            if (inbox_bind_stmt_params($stmtReads, $types, $params) && mysqli_stmt_execute($stmtReads)) {
                $resReads = mysqli_stmt_get_result($stmtReads);
                while ($resReads && ($row = mysqli_fetch_assoc($resReads))) {
                    $readsMap[(string)$row['thread_id']] = (int)$row['last_read_message_id'];
                }
            }
            mysqli_stmt_close($stmtReads);
        }
    }

    $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
    $msgSql = "SELECT id, thread_id, sender_role, sender_user_id, body, created_at
               FROM inbox_messages
               WHERE thread_id IN ({$placeholders})
               ORDER BY id DESC";
    $stmtMsg = mysqli_prepare($conexion, $msgSql);
    if ($stmtMsg) {
        $types = str_repeat('s', count($threadIds));
        $params = $threadIds;
        if (inbox_bind_stmt_params($stmtMsg, $types, $params) && mysqli_stmt_execute($stmtMsg)) {
            $resMsg = mysqli_stmt_get_result($stmtMsg);
            while ($resMsg && ($row = mysqli_fetch_assoc($resMsg))) {
                $threadId = (string)($row['thread_id'] ?? '');
                if (!isset($enriched[$threadId])) {
                    continue;
                }

                $msgId = (int)($row['id'] ?? 0);
                $readId = isset($readsMap[$threadId]) ? (int)$readsMap[$threadId] : 0;
                $senderRole = strtoupper((string)($row['sender_role'] ?? ''));
                $senderUserId = (int)($row['sender_user_id'] ?? 0);

                if ((int)$enriched[$threadId]['last_message_id'] === 0) {
                    $preview = trim((string)($row['body'] ?? ''));
                    if (strlen($preview) > 140) {
                        $preview = substr($preview, 0, 140) . '...';
                    }
                    $enriched[$threadId]['last_message_id'] = $msgId;
                    $enriched[$threadId]['last_message_at'] = (string)($row['created_at'] ?? '');
                    $enriched[$threadId]['last_message_preview'] = $preview;
                }

                $isOwnMessage = ($senderRole === $readerRole && $senderUserId > 0 && $senderUserId === $readerUserId);
                if ($msgId > $readId && !$isOwnMessage) {
                    $enriched[$threadId]['unread_count'] = (int)$enriched[$threadId]['unread_count'] + 1;
                }
            }
        }
        mysqli_stmt_close($stmtMsg);
    }

    $rows = array_values($enriched);
    usort($rows, function ($a, $b) {
        $ta = strtotime((string)($a['last_message_at'] !== '' ? $a['last_message_at'] : ($a['updated_at'] ?? '')));
        $tb = strtotime((string)($b['last_message_at'] !== '' ? $b['last_message_at'] : ($b['updated_at'] ?? '')));
        if ($ta === $tb) {
            return strcmp((string)($a['thread_id'] ?? ''), (string)($b['thread_id'] ?? ''));
        }
        return ($ta > $tb) ? -1 : 1;
    });

    return $rows;
}

function inbox_build_notifications_payload($threads, $baseUrl, $limit = 12)
{
    $limit = max(1, min(50, (int)$limit));
    $count = 0;
    $unreadThreads = [];

    foreach ($threads as $t) {
        $unread = (int)($t['unread_count'] ?? 0);
        if ($unread > 0) {
            $count += $unread;
            $unreadThreads[] = $t;
        }
    }

    usort($unreadThreads, function ($a, $b) {
        $ta = strtotime((string)($a['last_message_at'] ?? $a['updated_at'] ?? ''));
        $tb = strtotime((string)($b['last_message_at'] ?? $b['updated_at'] ?? ''));
        if ($ta === $tb) {
            return strcmp((string)($a['thread_id'] ?? ''), (string)($b['thread_id'] ?? ''));
        }
        return ($ta > $tb) ? -1 : 1;
    });

    $items = [];
    foreach ($unreadThreads as $t) {
        if (count($items) >= $limit) {
            break;
        }
        $threadId = (string)($t['thread_id'] ?? '');
        if ($threadId === '') {
            continue;
        }
        $items[] = [
            'thread_id' => $threadId,
            'label' => (string)($t['title'] ?? 'Inbox update'),
            'preview' => (string)($t['last_message_preview'] ?? ''),
            'created_at' => (string)($t['last_message_at'] ?? $t['updated_at'] ?? ''),
            'unread_count' => (int)($t['unread_count'] ?? 0),
            'url' => rtrim((string)$baseUrl, '?') . '?thread_id=' . rawurlencode($threadId),
        ];
    }

    return [
        'count' => $count,
        'items' => $items,
    ];
}
