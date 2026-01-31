<?php
session_start();
include(__DIR__ . '/../inc/include.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /booking/wizard.php');
    exit;
}

$booking = isset($_SESSION['booking_request']) ? $_SESSION['booking_request'] : [];
if (empty($booking['name']) || empty($booking['email'])) {
    $_SESSION['booking_request_status'] = 'error';
    $_SESSION['booking_request_message'] = 'Please complete the contact data before selecting services.';
    header('Location: /booking/wizard.php');
    exit;
}

$service_categories = isset($_POST['service_categories']) ? array_values(array_filter(array_map('intval', $_POST['service_categories']))) : [];
$medical_services = isset($_POST['medical_services']) ? array_values(array_filter(array_map('intval', $_POST['medical_services']))) : [];
$budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? number_format((float) $_POST['budget'], 2, '.', '') : null;
$timeline = isset($_POST['timeline']) ? trim($_POST['timeline']) : '';
$additional_notes = isset($_POST['additional_notes']) ? trim($_POST['additional_notes']) : '';
$origin = isset($booking['origin']) ? $booking['origin'] : 'wizard';

$insert_sql = "INSERT INTO booking_requests (name,email,origin,booking_datetime,destination,persons,category,special_request,service_categories,medical_services,budget,timeline,additional_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
$stmt = mysqli_prepare($conexion, $insert_sql);
$service_json = json_encode($service_categories);
$medical_json = json_encode($medical_services);
$booking_datetime = isset($booking['datetime']) ? $booking['datetime'] : '';
$destination = isset($booking['destination']) ? $booking['destination'] : '';
$persons = isset($booking['persons']) ? $booking['persons'] : '';
$category = isset($booking['category']) ? $booking['category'] : '';
$special_request = isset($booking['special_request']) ? $booking['special_request'] : '';

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'sssssssssssss',
        $booking['name'],
        $booking['email'],
        $origin,
        $booking_datetime,
        $destination,
        $persons,
        $category,
        $special_request,
        $service_json,
        $medical_json,
        $budget,
        $timeline,
        $additional_notes
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$_SESSION['booking_request_status'] = 'submitted';
$_SESSION['booking_request_message'] = 'Your request was saved. One of our coordinators will reach back soon.';
header('Location: /booking/wizard.php');
exit;
