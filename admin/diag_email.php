<?php
require_once __DIR__ . '/include/conexion.php';
require_once __DIR__ . '/include/roles.php';
require_once __DIR__ . '/include/email_config.php';
require_once __DIR__ . '/../inc/email_template.php';
require_once __DIR__ . '/../inc/interaction_email.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (!is_role_admin_session()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
    exit;
}

$to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
if ($to !== '' && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $to = '';
}
if ($to === '') {
    $to = interaction_email_resolve_patientcare_email($conexion);
}
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_RECIPIENT']);
    exit;
}

$subject = 'MedTravel diagnostic email';
$contentHtml = '<p>This is a diagnostic email from MedTravel.</p>'
    . '<p>If you received this message, email sending is working for admin notifications.</p>';
$textBody = "This is a diagnostic email from MedTravel.\nIf you received this message, email sending is working for admin notifications.";

$meta = [
    'preheader' => 'Diagnostic email test',
    'event' => 'diag_test',
];

$result = send_interaction_email($to, $subject, $contentHtml, $textBody, $meta, $conexion);
$ok = ($result === true) || (is_array($result) && !empty($result['success']));

$publicResult = $result;
if (is_array($publicResult)) {
    if (array_key_exists('smtp_log', $publicResult)) {
        unset($publicResult['smtp_log']);
    }
    if (array_key_exists('error_info', $publicResult)) {
        unset($publicResult['error_info']);
    }
}

$lastLogLines = function_exists('mt_email_debug_tail') ? mt_email_debug_tail(50) : [];

$response = [
    'ok' => $ok,
    'to' => $to,
    'subject' => $subject,
    'mailer_used' => 'patientcare',
    'result' => $publicResult,
    'error' => $ok ? null : (is_array($result) ? ($result['error'] ?? 'send_failed') : 'send_failed'),
    'last_log_lines' => $lastLogLines,
];

echo json_encode($response);
