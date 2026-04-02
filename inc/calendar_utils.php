<?php

function calendar_table_exists($conexion, $table)
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

function calendar_table_has_column($conexion, $table, $column)
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

function calendar_bind_stmt_params($stmt, $types, &$params)
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

function calendar_normalize_event_type($value)
{
    $type = strtoupper(trim((string)$value));
    return in_array($type, ['CARE', 'ITEM'], true) ? $type : '';
}

function calendar_normalize_status($value)
{
    $status = strtolower(trim((string)$value));
    $allowed = ['scheduled', 'proposed', 'confirmed', 'cancelled'];
    return in_array($status, $allowed, true) ? $status : 'scheduled';
}

function calendar_normalize_appointment_mode($value)
{
    $mode = strtolower(trim((string)$value));
    $allowed = ['virtual', 'in_person', 'travel'];
    return in_array($mode, $allowed, true) ? $mode : '';
}

function calendar_looks_like_travel_context($text)
{
    $text = strtolower(trim((string)$text));
    if ($text === '') {
        return false;
    }
    return (bool)preg_match('/\b(travel|trip|viaje|traslado|flight|vuelo|hotel|airport|aeropuerto)\b/u', $text);
}

function calendar_infer_appointment_mode(array $row)
{
    $explicit = calendar_normalize_appointment_mode($row['appointment_mode'] ?? '');
    if ($explicit !== '') {
        return $explicit;
    }

    $googleMeetUrl = trim((string)($row['google_meet_url'] ?? ''));
    $integrationMode = strtolower(trim((string)($row['integration_mode'] ?? '')));
    if ($googleMeetUrl !== '' || $integrationMode === 'calendar_plus_meet') {
        return 'virtual';
    }

    $title = trim((string)($row['title'] ?? ''));
    $description = trim((string)($row['description'] ?? ''));
    if (calendar_looks_like_travel_context($title . ' ' . $description)) {
        return 'travel';
    }

    return 'in_person';
}

function calendar_appointment_mode_label_es($mode)
{
    $mode = calendar_normalize_appointment_mode($mode);
    if ($mode === 'virtual') {
        return 'Cita virtual';
    }
    if ($mode === 'travel') {
        return 'Cita asociada a viaje';
    }
    return 'Cita presencial';
}

function calendar_appointment_mode_label_en($mode)
{
    $mode = calendar_normalize_appointment_mode($mode);
    if ($mode === 'virtual') {
        return 'Virtual appointment';
    }
    if ($mode === 'travel') {
        return 'Travel-related appointment';
    }
    return 'In-person appointment';
}

function calendar_parse_datetime_input($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

function calendar_build_thread_id($eventType, $requestId, $itemId)
{
    $eventType = calendar_normalize_event_type($eventType);
    if ($eventType === 'ITEM' && (int)$itemId > 0) {
        return 'ITEM:' . (int)$itemId;
    }
    if ((int)$requestId > 0) {
        return 'CARE:' . (int)$requestId;
    }
    return null;
}

function calendar_json_event_row($row)
{
    $eventType = calendar_normalize_event_type($row['event_type'] ?? '');
    $requestId = (int)($row['request_id'] ?? 0);
    $itemId = (int)($row['item_id'] ?? 0);
    $integrationMode = strtolower(trim((string)($row['integration_mode'] ?? '')));
    $googleEventId = trim((string)($row['google_event_id'] ?? ''));
    $isGoogleSynced = ($googleEventId !== '') || ($integrationMode !== '' && $integrationMode !== 'internal_only');
    $threadId = trim((string)($row['thread_id'] ?? ''));
    if ($threadId === '') {
        $threadId = calendar_build_thread_id($eventType, $requestId, $itemId);
    }
    $appointmentMode = calendar_infer_appointment_mode((array)$row);

    return [
        'id' => (int)($row['id'] ?? 0),
        'title' => (string)($row['title'] ?? ''),
        'start' => (string)($row['start_at'] ?? ''),
        'end' => (string)($row['end_at'] ?? ''),
        'allDay' => ((int)($row['all_day'] ?? 0) === 1),
        'description' => (string)($row['description'] ?? ''),
        'status' => (string)($row['status'] ?? 'scheduled'),
        'event_type' => $eventType,
        'request_id' => $requestId,
        'item_id' => $itemId,
        'thread_id' => $threadId ?: null,
        'integration_mode' => $integrationMode,
        'google_event_id' => $googleEventId,
        'google_html_link' => (string)($row['google_html_link'] ?? ''),
        'google_meet_url' => (string)($row['google_meet_url'] ?? ''),
        'organizer_email' => (string)($row['organizer_email'] ?? ''),
        'appointment_mode' => $appointmentMode,
        'appointment_mode_label_es' => calendar_appointment_mode_label_es($appointmentMode),
        'appointment_mode_label_en' => calendar_appointment_mode_label_en($appointmentMode),
        'is_google_synced' => $isGoogleSynced,
        'extendedProps' => [
            'event_type' => $eventType,
            'request_id' => $requestId,
            'item_id' => $itemId,
            'status' => (string)($row['status'] ?? 'scheduled'),
            'description' => (string)($row['description'] ?? ''),
            'thread_id' => $threadId ?: null,
            'integration_mode' => $integrationMode,
            'google_event_id' => $googleEventId,
            'google_html_link' => (string)($row['google_html_link'] ?? ''),
            'google_meet_url' => (string)($row['google_meet_url'] ?? ''),
            'organizer_email' => (string)($row['organizer_email'] ?? ''),
            'appointment_mode' => $appointmentMode,
            'appointment_mode_label_es' => calendar_appointment_mode_label_es($appointmentMode),
            'appointment_mode_label_en' => calendar_appointment_mode_label_en($appointmentMode),
            'is_google_synced' => $isGoogleSynced,
        ],
    ];
}
