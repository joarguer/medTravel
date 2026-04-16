#!/usr/bin/env php
<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../inc/google_meet_execution.php';

$options = getopt('', ['limit::']);
$limit = isset($options['limit']) ? (int)$options['limit'] : 10;
$limit = max(1, min(50, $limit));

$config = google_meet_execution_pubsub_config();
if (!$config['enabled']) {
    echo json_encode([
        'ok' => true,
        'noop' => true,
        'reason' => 'MT_GOOGLE_MEET_EXECUTION_ENABLED=0',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

if (!empty($config['errors'])) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => 'meet_execution_config_invalid',
        'details' => $config['errors'],
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

require_once __DIR__ . '/../admin/include/conexion.php';

$pullState = google_meet_execution_pubsub_pull($config, $limit);
if (empty($pullState['ok'])) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => (string)($pullState['error'] ?? 'pubsub_pull_failed'),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

$receivedMessages = (array)($pullState['received_messages'] ?? []);
$summary = [
    'ok' => true,
    'pulled' => count($receivedMessages),
    'acked' => 0,
    'processed' => 0,
    'duplicates' => 0,
    'projected' => 0,
    'errors' => [],
    'results' => [],
];

$ackIds = [];
foreach ($receivedMessages as $receivedMessage) {
    $result = google_meet_execution_process_received_message($conexion, (array)$receivedMessage);
    $summary['results'][] = $result;

    if (!empty($result['ok'])) {
        $summary['processed']++;
        if (!empty($result['duplicate'])) {
            $summary['duplicates']++;
        }
        if (!empty($result['projected'])) {
            $summary['projected']++;
        }
        if (!empty($result['ack_id'])) {
            $ackIds[] = (string)$result['ack_id'];
        }
        continue;
    }

    $summary['ok'] = false;
    $summary['errors'][] = [
        'workspace_event_id' => (string)($result['workspace_event_id'] ?? ''),
        'error' => (string)($result['error'] ?? 'unknown'),
    ];
}

$ackState = google_meet_execution_pubsub_ack($config, $ackIds);
if (empty($ackState['ok'])) {
    $summary['ok'] = false;
    $summary['errors'][] = ['error' => (string)($ackState['error'] ?? 'pubsub_ack_failed')];
} else {
    $summary['acked'] = (int)($ackState['ack_count'] ?? 0);
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($summary['ok'] ? 0 : 1);
