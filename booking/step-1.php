<?php
session_start();
require_once __DIR__ . '/../inc/constants.php';

function booking_step1_client_ip_local()
{
    // REMOTE_ADDR is the only value that cannot be spoofed by the client.
    // HTTP_CLIENT_IP and HTTP_X_FORWARDED_FOR can be freely set by any client
    // and must not be trusted unless a controlled trusted-proxy list is configured.
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /booking.php');
    exit;
}

$fields = ['name', 'email', 'timeline_from', 'timeline_to', 'destination', 'persons', 'category', 'special_request', 'origin', 'preselected_offer', 'phone', 'consent_terms', 'consent_privacy', 'consent_insurance'];
$input = [];
foreach ($fields as $field) {
    if (isset($_POST[$field])) {
        $input[$field] = trim($_POST[$field]);
    } else {
        $input[$field] = '';
    }
}

$required = ['name', 'email'];
$missing = [];
foreach ($required as $field) {
    if ($input[$field] === '') {
        $missing[] = $field;
    }
}

$consent_keys = ['consent_terms', 'consent_privacy', 'consent_insurance'];
foreach ($consent_keys as $consent_key) {
    if (!isset($_POST[$consent_key]) || (string)$_POST[$consent_key] !== '1') {
        $_SESSION['booking_step1_error'] = 'You must accept all three consent items to continue.';
        $back = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/booking.php';
        header('Location: ' . $back);
        exit;
    }
}

$termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.1';
// Record each consent separately for audit; derive backward-compatible terms_accepted.
$input['consent_terms']    = '1';
$input['consent_privacy']  = '1';
$input['consent_insurance'] = '1';
$input['terms_accepted']   = '1'; // kept for backward compat with submit.php
$input['terms_version']    = $termsVersion;
$input['terms_accepted_at'] = date('Y-m-d H:i:s');
$input['terms_ip']         = booking_step1_client_ip_local();
$input['terms_user_agent'] = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$_SESSION['booking_request'] = $input;

if (!empty($missing)) {
    $_SESSION['booking_step1_error'] = 'Please provide your name and email before continuing.';
    $back = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/booking.php';
    header('Location: ' . $back);
    exit;
}

unset($_SESSION['booking_step1_error']);
header('Location: /booking/wizard.php');
exit;
