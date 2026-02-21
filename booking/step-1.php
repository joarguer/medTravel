<?php
session_start();
require_once __DIR__ . '/../inc/constants.php';

function booking_step1_client_ip_local()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    }
    if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    }
    if (!empty($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /booking.php');
    exit;
}

$fields = ['name', 'email', 'timeline_from', 'timeline_to', 'destination', 'persons', 'category', 'special_request', 'origin', 'preselected_offer', 'phone', 'terms_accepted'];
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

if (!isset($_POST['terms_accepted']) || (string)$_POST['terms_accepted'] !== '1') {
    $_SESSION['booking_step1_error'] = 'You must accept the Terms to continue.';
    $back = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/booking.php';
    header('Location: ' . $back);
    exit;
}

$termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.0';
$input['terms_accepted'] = '1';
$input['terms_version'] = $termsVersion;
$input['terms_accepted_at'] = date('Y-m-d H:i:s');
$input['terms_ip'] = booking_step1_client_ip_local();
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
