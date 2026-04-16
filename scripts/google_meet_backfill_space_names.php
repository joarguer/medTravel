#!/usr/bin/env php
<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../admin/include/conexion.php';
require_once __DIR__ . '/../inc/google_meet_execution.php';

$options = getopt('', ['limit::', 'dry-run']);
$limit = isset($options['limit']) ? (int)$options['limit'] : 50;
$limit = max(1, min(500, $limit));
$dryRun = array_key_exists('dry-run', $options);

if (
    !google_calendar_table_exists($conexion, 'calendar_events')
    || !google_calendar_table_has_column($conexion, 'calendar_events', 'organizer_admin_user_id')
    || !google_calendar_table_has_column($conexion, 'calendar_events', 'google_meet_space_code')
    || !google_calendar_table_has_column($conexion, 'calendar_events', 'google_meet_space_name')
) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => 'calendar_events_columns_missing',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

$sql = "SELECT id, organizer_admin_user_id, google_meet_space_code, google_meet_space_name, google_meet_url, status
        FROM calendar_events
        WHERE COALESCE(google_meet_space_code, '') <> ''
          AND COALESCE(google_meet_space_name, '') = ''
          AND COALESCE(google_meet_url, '') <> ''
          AND status = 'confirmed'
        ORDER BY start_at DESC, id DESC
        LIMIT ?";
$stmt = mysqli_prepare($conexion, $sql);
if (!$stmt) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => 'select_prepare_failed',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

mysqli_stmt_bind_param($stmt, 'i', $limit);
$rows = [];
if (mysqli_stmt_execute($stmt)) {
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
}
mysqli_stmt_close($stmt);

$summary = [
    'ok' => true,
    'dry_run' => $dryRun,
    'scanned' => count($rows),
    'resolved' => 0,
    'updated' => 0,
    'unresolved' => 0,
    'results' => [],
];

foreach ($rows as $row) {
    $calendarEventId = (int)($row['id'] ?? 0);
    $organizerAdminUserId = (int)($row['organizer_admin_user_id'] ?? 0);
    $spaceCode = trim((string)($row['google_meet_space_code'] ?? ''));
    $resolution = google_meet_execution_fetch_space_name($conexion, $organizerAdminUserId, $spaceCode);
    $spaceName = trim((string)($resolution['space_name'] ?? ''));

    $item = [
        'calendar_event_id' => $calendarEventId,
        'organizer_admin_user_id' => $organizerAdminUserId,
        'space_code' => $spaceCode,
        'space_name' => $spaceName,
        'reason' => (string)($resolution['reason'] ?? ''),
        'updated' => false,
    ];

    if ($spaceName === '') {
        $summary['unresolved']++;
        $summary['results'][] = $item;
        continue;
    }

    $summary['resolved']++;
    if (!$dryRun) {
        $update = google_meet_execution_update_calendar_space_name($conexion, $calendarEventId, $spaceName);
        $item['updated'] = !empty($update['ok']);
    }

    if ($item['updated']) {
        $summary['updated']++;
    }
    $summary['results'][] = $item;
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);
