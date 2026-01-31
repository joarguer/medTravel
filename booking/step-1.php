<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /booking.php');
    exit;
}

$fields = ['name', 'email', 'datetime', 'destination', 'persons', 'category', 'special_request', 'origin', 'preselected_offer', 'phone'];
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
