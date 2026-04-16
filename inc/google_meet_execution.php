<?php

require_once __DIR__ . '/google_calendar.php';

function google_meet_execution_is_enabled()
{
    $raw = strtolower(trim((string)(getenv('MT_GOOGLE_MEET_EXECUTION_ENABLED') ?: '0')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function google_meet_execution_default_timezone()
{
    return 'America/Bogota';
}

function google_meet_execution_columns($conexion)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [
        'calendar_events' => google_calendar_table_exists($conexion, 'calendar_events'),
        'google_meet_event_log' => google_calendar_table_exists($conexion, 'google_meet_event_log'),
        'google_meet_subscriptions' => google_calendar_table_exists($conexion, 'google_meet_subscriptions'),
        'google_meet_space_code' => google_calendar_table_has_column($conexion, 'calendar_events', 'google_meet_space_code'),
        'google_meet_space_name' => google_calendar_table_has_column($conexion, 'calendar_events', 'google_meet_space_name'),
        'meeting_execution_status' => google_calendar_table_has_column($conexion, 'calendar_events', 'meeting_execution_status'),
        'meeting_started_at' => google_calendar_table_has_column($conexion, 'calendar_events', 'meeting_started_at'),
        'meeting_ended_at' => google_calendar_table_has_column($conexion, 'calendar_events', 'meeting_ended_at'),
        'meeting_duration_seconds' => google_calendar_table_has_column($conexion, 'calendar_events', 'meeting_duration_seconds'),
        'meeting_detected_source' => google_calendar_table_has_column($conexion, 'calendar_events', 'meeting_detected_source'),
        'conference_record_name' => google_calendar_table_has_column($conexion, 'calendar_events', 'conference_record_name'),
        'meeting_last_event_type' => google_calendar_table_has_column($conexion, 'calendar_events', 'meeting_last_event_type'),
        'meeting_last_detected_at' => google_calendar_table_has_column($conexion, 'calendar_events', 'meeting_last_detected_at'),
    ];

    return $cache;
}

function google_meet_execution_extract_space_code_from_url($url)
{
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }

    if (preg_match('~meet\.google\.com/([a-z0-9-]+)~i', $url, $match)) {
        return strtolower(trim((string)$match[1]));
    }

    return '';
}

function google_meet_execution_extract_space_code_from_google_event(array $rawEvent, $meetUrl = '')
{
    $conferenceId = strtolower(trim((string)($rawEvent['conferenceData']['conferenceId'] ?? '')));
    if ($conferenceId !== '') {
        return $conferenceId;
    }

    return google_meet_execution_extract_space_code_from_url($meetUrl);
}

function google_meet_execution_base64url_encode($data)
{
    return rtrim(strtr(base64_encode((string)$data), '+/', '-_'), '=');
}

function google_meet_execution_parse_service_account_json($path)
{
    $path = trim((string)$path);
    if ($path === '' || !is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $json = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
        return null;
    }

    return $json;
}

function google_meet_execution_pubsub_config()
{
    $projectId = trim((string)(getenv('GOOGLE_CLOUD_PROJECT_ID') ?: ''));
    $subscription = trim((string)(getenv('GOOGLE_PUBSUB_SUBSCRIPTION') ?: 'medtravel-meet-events-sub'));
    $serviceAccountPath = trim((string)(getenv('GOOGLE_PUBSUB_SERVICE_ACCOUNT_JSON_PATH') ?: ''));

    if ($subscription !== '' && !str_starts_with($subscription, 'projects/')) {
        if ($projectId !== '') {
            $subscription = 'projects/' . $projectId . '/subscriptions/' . $subscription;
        }
    }

    $config = [
        'enabled' => google_meet_execution_is_enabled(),
        'project_id' => $projectId,
        'subscription' => $subscription,
        'service_account_path' => $serviceAccountPath,
        'service_account' => null,
        'errors' => [],
    ];

    if (!$config['enabled']) {
        return $config;
    }

    if ($projectId === '') {
        $config['errors'][] = 'missing GOOGLE_CLOUD_PROJECT_ID';
    }
    if ($subscription === '' || !str_starts_with($subscription, 'projects/')) {
        $config['errors'][] = 'missing valid GOOGLE_PUBSUB_SUBSCRIPTION';
    }
    if ($serviceAccountPath === '') {
        $config['errors'][] = 'missing GOOGLE_PUBSUB_SERVICE_ACCOUNT_JSON_PATH';
        return $config;
    }

    $serviceAccount = google_meet_execution_parse_service_account_json($serviceAccountPath);
    if (!$serviceAccount) {
        $config['errors'][] = 'invalid service account json';
        return $config;
    }

    foreach (['client_email', 'private_key', 'token_uri'] as $requiredKey) {
        if (trim((string)($serviceAccount[$requiredKey] ?? '')) === '') {
            $config['errors'][] = 'service account missing ' . $requiredKey;
        }
    }

    $config['service_account'] = $serviceAccount;
    return $config;
}

function google_meet_execution_service_account_access_token(array $config)
{
    static $cache = [];

    $cacheKey = sha1(json_encode([
        'client_email' => (string)($config['service_account']['client_email'] ?? ''),
        'token_uri' => (string)($config['service_account']['token_uri'] ?? ''),
    ]));
    $cached = $cache[$cacheKey] ?? null;
    if ($cached && (int)($cached['expires_at'] ?? 0) > (time() + 60)) {
        return ['ok' => true, 'access_token' => (string)$cached['access_token']];
    }

    $serviceAccount = (array)($config['service_account'] ?? []);
    $privateKey = (string)($serviceAccount['private_key'] ?? '');
    $clientEmail = (string)($serviceAccount['client_email'] ?? '');
    $tokenUri = (string)($serviceAccount['token_uri'] ?? '');
    if ($privateKey === '' || $clientEmail === '' || $tokenUri === '') {
        return ['ok' => false, 'error' => 'service_account_incomplete'];
    }

    $issuedAt = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/pubsub',
        'aud' => $tokenUri,
        'iat' => $issuedAt,
        'exp' => $issuedAt + 3600,
    ];

    $unsigned = google_meet_execution_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES))
        . '.'
        . google_meet_execution_base64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));

    $signature = '';
    $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$ok) {
        return ['ok' => false, 'error' => 'service_account_sign_failed'];
    }

    $assertion = $unsigned . '.' . google_meet_execution_base64url_encode($signature);
    $response = google_calendar_http_request('POST', $tokenUri, [
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $assertion,
    ]));

    if (empty($response['ok']) || !is_array($response['json'])) {
        return [
            'ok' => false,
            'error' => trim((string)($response['json']['error_description'] ?? $response['json']['error'] ?? $response['error'] ?? 'service_account_token_failed')),
        ];
    }

    $accessToken = trim((string)($response['json']['access_token'] ?? ''));
    if ($accessToken === '') {
        return ['ok' => false, 'error' => 'empty_access_token'];
    }

    $cache[$cacheKey] = [
        'access_token' => $accessToken,
        'expires_at' => $issuedAt + (int)($response['json']['expires_in'] ?? 3600),
    ];

    return ['ok' => true, 'access_token' => $accessToken];
}

function google_meet_execution_pubsub_pull(array $config, $maxMessages = 10)
{
    $maxMessages = max(1, min(50, (int)$maxMessages));
    $tokenState = google_meet_execution_service_account_access_token($config);
    if (empty($tokenState['ok'])) {
        return $tokenState;
    }

    $url = 'https://pubsub.googleapis.com/v1/' . $config['subscription'] . ':pull';
    $response = google_calendar_http_request('POST', $url, [
        'Authorization: Bearer ' . $tokenState['access_token'],
        'Accept: application/json',
        'Content-Type: application/json',
    ], json_encode(['maxMessages' => $maxMessages], JSON_UNESCAPED_SLASHES));

    if (empty($response['ok']) || !is_array($response['json'])) {
        return [
            'ok' => false,
            'error' => trim((string)($response['json']['error']['message'] ?? $response['error'] ?? 'pubsub_pull_failed')),
        ];
    }

    return [
        'ok' => true,
        'received_messages' => (array)($response['json']['receivedMessages'] ?? []),
    ];
}

function google_meet_execution_pubsub_ack(array $config, array $ackIds)
{
    $ackIds = array_values(array_filter(array_map(static function ($value) {
        return trim((string)$value);
    }, $ackIds), static function ($value) {
        return $value !== '';
    }));

    if (empty($ackIds)) {
        return ['ok' => true, 'ack_count' => 0];
    }

    $tokenState = google_meet_execution_service_account_access_token($config);
    if (empty($tokenState['ok'])) {
        return $tokenState;
    }

    $url = 'https://pubsub.googleapis.com/v1/' . $config['subscription'] . ':acknowledge';
    $response = google_calendar_http_request('POST', $url, [
        'Authorization: Bearer ' . $tokenState['access_token'],
        'Accept: application/json',
        'Content-Type: application/json',
    ], json_encode(['ackIds' => $ackIds], JSON_UNESCAPED_SLASHES));

    if (empty($response['ok'])) {
        return [
            'ok' => false,
            'error' => trim((string)($response['json']['error']['message'] ?? $response['error'] ?? 'pubsub_ack_failed')),
        ];
    }

    return ['ok' => true, 'ack_count' => count($ackIds)];
}

function google_meet_execution_timestamp_to_db($timestamp)
{
    $timestamp = trim((string)$timestamp);
    if ($timestamp === '') {
        return null;
    }

    try {
        $date = new DateTimeImmutable($timestamp);
        $date = $date->setTimezone(new DateTimeZone(google_meet_execution_default_timezone()));
        return $date->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function google_meet_execution_normalize_workspace_subscription_name($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }

    $prefix = '//workspaceevents.googleapis.com/';
    if (str_starts_with($raw, $prefix)) {
        return substr($raw, strlen($prefix));
    }

    return ltrim($raw, '/');
}

function google_meet_execution_normalize_meet_space_name($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }

    $prefix = '//meet.googleapis.com/';
    if (str_starts_with($raw, $prefix)) {
        return substr($raw, strlen($prefix));
    }

    return ltrim($raw, '/');
}

function google_meet_execution_decode_received_message(array $receivedMessage)
{
    $message = (array)($receivedMessage['message'] ?? []);
    $attributes = (array)($message['attributes'] ?? []);
    $dataJson = [];
    $rawData = trim((string)($message['data'] ?? ''));
    if ($rawData !== '') {
        $decodedData = base64_decode($rawData, true);
        if ($decodedData !== false && $decodedData !== '') {
            $tmp = json_decode($decodedData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $dataJson = $tmp;
            }
        }
    }

    $conferenceRecordName = trim((string)($dataJson['conferenceRecord']['name'] ?? ''));
    if ($conferenceRecordName === '') {
        $conferenceRecordName = trim((string)($dataJson['participantSession']['conferenceRecord'] ?? ''));
    }

    return [
        'ack_id' => trim((string)($receivedMessage['ackId'] ?? '')),
        'pubsub_message_id' => trim((string)($message['messageId'] ?? '')),
        'workspace_event_id' => trim((string)($attributes['ce-id'] ?? '')) !== ''
            ? trim((string)$attributes['ce-id'])
            : 'pubsub:' . trim((string)($message['messageId'] ?? '')),
        'workspace_event_type' => trim((string)($attributes['ce-type'] ?? 'unknown')),
        'workspace_subscription_name' => google_meet_execution_normalize_workspace_subscription_name((string)($attributes['ce-source'] ?? '')),
        'google_meet_space_name' => google_meet_execution_normalize_meet_space_name((string)($attributes['ce-subject'] ?? '')),
        'conference_record_name' => $conferenceRecordName,
        'published_at' => google_meet_execution_timestamp_to_db((string)($attributes['ce-time'] ?? $message['publishTime'] ?? '')),
        'payload_json' => json_encode([
            'attributes' => $attributes,
            'data' => $dataJson,
            'messageId' => (string)($message['messageId'] ?? ''),
            'orderingKey' => (string)($message['orderingKey'] ?? ''),
            'publishTime' => (string)($message['publishTime'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
    ];
}

function google_meet_execution_find_calendar_event_context($conexion, array $event)
{
    $columns = google_meet_execution_columns($conexion);

    if ($columns['google_meet_subscriptions']) {
        $subscriptionName = trim((string)($event['workspace_subscription_name'] ?? ''));
        if ($subscriptionName !== '') {
            $sql = "SELECT ce.id AS calendar_event_id, ce.request_id, ce.item_id
                    FROM google_meet_subscriptions gms
                    INNER JOIN calendar_events ce ON ce.id = gms.calendar_event_id
                    WHERE gms.workspace_subscription_name = ?
                    ORDER BY gms.id DESC
                    LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $subscriptionName);
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    $row = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($stmt);
                    if ($row) {
                        return [
                            'calendar_event_id' => (int)$row['calendar_event_id'],
                            'request_id' => (int)($row['request_id'] ?? 0),
                            'item_id' => (int)($row['item_id'] ?? 0),
                        ];
                    }
                } else {
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    if ($columns['conference_record_name']) {
        $conferenceRecordName = trim((string)($event['conference_record_name'] ?? ''));
        if ($conferenceRecordName !== '') {
            $sql = "SELECT id AS calendar_event_id, request_id, item_id
                    FROM calendar_events
                    WHERE conference_record_name = ?
                    ORDER BY id DESC
                    LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $conferenceRecordName);
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    $row = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($stmt);
                    if ($row) {
                        return [
                            'calendar_event_id' => (int)$row['calendar_event_id'],
                            'request_id' => (int)($row['request_id'] ?? 0),
                            'item_id' => (int)($row['item_id'] ?? 0),
                        ];
                    }
                } else {
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    if ($columns['google_meet_space_name']) {
        $spaceName = trim((string)($event['google_meet_space_name'] ?? ''));
        if ($spaceName !== '') {
            $sql = "SELECT id AS calendar_event_id, request_id, item_id
                    FROM calendar_events
                    WHERE google_meet_space_name = ?
                    ORDER BY (status = 'confirmed') DESC, start_at DESC, id DESC
                    LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $spaceName);
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    $row = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($stmt);
                    if ($row) {
                        return [
                            'calendar_event_id' => (int)$row['calendar_event_id'],
                            'request_id' => (int)($row['request_id'] ?? 0),
                            'item_id' => (int)($row['item_id'] ?? 0),
                        ];
                    }
                } else {
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    return [
        'calendar_event_id' => 0,
        'request_id' => 0,
        'item_id' => 0,
    ];
}

function google_meet_execution_insert_event_log($conexion, array $event, array $context)
{
    if (!google_calendar_table_exists($conexion, 'google_meet_event_log')) {
        return ['ok' => false, 'error' => 'google_meet_event_log_missing'];
    }

    $sql = "INSERT INTO google_meet_event_log
                (calendar_event_id, request_id, item_id, workspace_event_id, workspace_event_type,
                 workspace_subscription_name, conference_record_name, google_meet_space_name,
                 payload_json, published_at, received_at, processed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'log_prepare_failed'];
    }

    $calendarEventId = (int)($context['calendar_event_id'] ?? 0);
    $requestId = (int)($context['request_id'] ?? 0);
    $itemId = (int)($context['item_id'] ?? 0);
    $publishedAt = $event['published_at'] ?: null;
    $processedAt = date('Y-m-d H:i:s');
    $workspaceEventId = (string)$event['workspace_event_id'];
    $workspaceEventType = (string)$event['workspace_event_type'];
    $workspaceSubscriptionName = (string)($event['workspace_subscription_name'] ?? '');
    $conferenceRecordName = (string)($event['conference_record_name'] ?? '');
    $spaceName = (string)($event['google_meet_space_name'] ?? '');
    $payloadJson = (string)($event['payload_json'] ?? '{}');

    mysqli_stmt_bind_param(
        $stmt,
        'iiissssssss',
        $calendarEventId,
        $requestId,
        $itemId,
        $workspaceEventId,
        $workspaceEventType,
        $workspaceSubscriptionName,
        $conferenceRecordName,
        $spaceName,
        $payloadJson,
        $publishedAt,
        $processedAt
    );

    $ok = mysqli_stmt_execute($stmt);
    $errorNo = mysqli_stmt_errno($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['ok' => true, 'duplicate' => false];
    }

    if ($errorNo === 1062) {
        return ['ok' => true, 'duplicate' => true];
    }

    return ['ok' => false, 'error' => 'log_insert_failed'];
}

function google_meet_execution_fetch_calendar_snapshot($conexion, $calendarEventId)
{
    $calendarEventId = (int)$calendarEventId;
    if ($calendarEventId <= 0) {
        return null;
    }

    $columns = google_meet_execution_columns($conexion);
    $select = ['id'];
    foreach ([
        'meeting_execution_status',
        'meeting_started_at',
        'meeting_ended_at',
        'meeting_duration_seconds',
        'conference_record_name',
        'google_meet_space_name',
        'meeting_last_event_type',
        'meeting_last_detected_at',
    ] as $column) {
        if (!empty($columns[$column])) {
            $select[] = $column;
        }
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM calendar_events WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $calendarEventId);
    $row = null;
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
    }
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function google_meet_execution_duration_seconds($startedAt, $endedAt)
{
    $startedAt = trim((string)$startedAt);
    $endedAt = trim((string)$endedAt);
    if ($startedAt === '' || $endedAt === '') {
        return null;
    }

    try {
        $start = new DateTimeImmutable($startedAt, new DateTimeZone(google_meet_execution_default_timezone()));
        $end = new DateTimeImmutable($endedAt, new DateTimeZone(google_meet_execution_default_timezone()));
        $diff = $end->getTimestamp() - $start->getTimestamp();
        return $diff >= 0 ? $diff : null;
    } catch (Throwable $e) {
        return null;
    }
}

function google_meet_execution_apply_calendar_projection($conexion, $calendarEventId, array $event)
{
    $calendarEventId = (int)$calendarEventId;
    if ($calendarEventId <= 0) {
        return ['ok' => true, 'updated' => false, 'reason' => 'unresolved_calendar_event'];
    }

    $columns = google_meet_execution_columns($conexion);
    $current = google_meet_execution_fetch_calendar_snapshot($conexion, $calendarEventId);
    if (!$current) {
        return ['ok' => false, 'error' => 'calendar_event_not_found'];
    }

    $detectedAt = $event['published_at'] ?: date('Y-m-d H:i:s');
    $eventType = trim((string)($event['workspace_event_type'] ?? ''));
    $setParts = [];
    $types = '';
    $params = [];

    if ($columns['meeting_detected_source']) {
        $setParts[] = 'meeting_detected_source = ?';
        $types .= 's';
        $params[] = 'workspace_events';
    }
    if ($columns['meeting_last_event_type']) {
        $setParts[] = 'meeting_last_event_type = ?';
        $types .= 's';
        $params[] = $eventType;
    }
    if ($columns['meeting_last_detected_at']) {
        $setParts[] = 'meeting_last_detected_at = ?';
        $types .= 's';
        $params[] = $detectedAt;
    }
    if ($columns['conference_record_name'] && trim((string)($event['conference_record_name'] ?? '')) !== '') {
        $setParts[] = 'conference_record_name = ?';
        $types .= 's';
        $params[] = (string)$event['conference_record_name'];
    }
    if ($columns['google_meet_space_name'] && trim((string)($event['google_meet_space_name'] ?? '')) !== '') {
        $setParts[] = 'google_meet_space_name = ?';
        $types .= 's';
        $params[] = (string)$event['google_meet_space_name'];
    }

    $targetStartedAt = trim((string)($current['meeting_started_at'] ?? ''));
    $targetEndedAt = trim((string)($current['meeting_ended_at'] ?? ''));
    $targetStatus = trim((string)($current['meeting_execution_status'] ?? ''));

    if ($eventType === 'google.workspace.meet.conference.v2.started') {
        if ($targetStartedAt === '' || $detectedAt < $targetStartedAt) {
            $targetStartedAt = $detectedAt;
        }
        if ($targetStatus !== 'ended') {
            $targetStatus = 'started';
        }
    } elseif ($eventType === 'google.workspace.meet.conference.v2.ended') {
        if ($targetEndedAt === '' || $detectedAt > $targetEndedAt) {
            $targetEndedAt = $detectedAt;
        }
        $targetStatus = 'ended';
    } else {
        return ['ok' => true, 'updated' => false, 'reason' => 'event_type_not_projected'];
    }

    if ($columns['meeting_execution_status']) {
        $setParts[] = 'meeting_execution_status = ?';
        $types .= 's';
        $params[] = $targetStatus;
    }
    if ($columns['meeting_started_at'] && $targetStartedAt !== '') {
        $setParts[] = 'meeting_started_at = ?';
        $types .= 's';
        $params[] = $targetStartedAt;
    }
    if ($columns['meeting_ended_at'] && $targetEndedAt !== '') {
        $setParts[] = 'meeting_ended_at = ?';
        $types .= 's';
        $params[] = $targetEndedAt;
    }

    $durationSeconds = google_meet_execution_duration_seconds($targetStartedAt, $targetEndedAt);
    if ($columns['meeting_duration_seconds'] && $durationSeconds !== null) {
        $setParts[] = 'meeting_duration_seconds = ?';
        $types .= 'i';
        $params[] = $durationSeconds;
    }

    if (empty($setParts)) {
        return ['ok' => true, 'updated' => false, 'reason' => 'nothing_to_update'];
    }

    $sql = 'UPDATE calendar_events SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = ? LIMIT 1';
    $types .= 'i';
    $params[] = $calendarEventId;
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'projection_prepare_failed'];
    }

    $bindArgs = [$stmt, $types];
    foreach ($params as $index => $value) {
        $bindArgs[] = &$params[$index];
    }
    $bound = call_user_func_array('mysqli_stmt_bind_param', $bindArgs);
    $executed = $bound && mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$executed) {
        return ['ok' => false, 'error' => 'projection_execute_failed'];
    }

    return ['ok' => true, 'updated' => true];
}

function google_meet_execution_process_received_message($conexion, array $receivedMessage)
{
    $decoded = google_meet_execution_decode_received_message($receivedMessage);
    $context = google_meet_execution_find_calendar_event_context($conexion, $decoded);

    $logState = google_meet_execution_insert_event_log($conexion, $decoded, $context);
    if (empty($logState['ok'])) {
        return [
            'ok' => false,
            'ack_id' => (string)($decoded['ack_id'] ?? ''),
            'workspace_event_id' => (string)($decoded['workspace_event_id'] ?? ''),
            'error' => (string)($logState['error'] ?? 'log_failed'),
        ];
    }

    $projection = google_meet_execution_apply_calendar_projection(
        $conexion,
        (int)($context['calendar_event_id'] ?? 0),
        $decoded
    );

    if (empty($projection['ok'])) {
        return [
            'ok' => false,
            'ack_id' => (string)($decoded['ack_id'] ?? ''),
            'workspace_event_id' => (string)($decoded['workspace_event_id'] ?? ''),
            'calendar_event_id' => (int)($context['calendar_event_id'] ?? 0),
            'error' => (string)($projection['error'] ?? 'projection_failed'),
        ];
    }

    return [
        'ok' => true,
        'ack_id' => (string)($decoded['ack_id'] ?? ''),
        'workspace_event_id' => (string)($decoded['workspace_event_id'] ?? ''),
        'workspace_event_type' => (string)($decoded['workspace_event_type'] ?? ''),
        'calendar_event_id' => (int)($context['calendar_event_id'] ?? 0),
        'duplicate' => !empty($logState['duplicate']),
        'projected' => !empty($projection['updated']),
    ];
}
