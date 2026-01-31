<?php
session_start();
include(__DIR__ . '/../inc/include.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: wizard.php');
    exit;
}

$booking = isset($_SESSION['booking_request']) ? $_SESSION['booking_request'] : [];
if (empty($booking['name']) || empty($booking['email'])) {
    $_SESSION['booking_request_status'] = 'error';
    $_SESSION['booking_request_message'] = 'Please complete the contact data before selecting services.';
    header('Location: wizard.php');
    exit;
}

// Capturar ofertas seleccionadas (nuevo sistema)
$selected_offers = isset($_POST['selected_offers']) ? array_values(array_filter(array_map('intval', $_POST['selected_offers']))) : [];

// Capturar servicios de medtravel seleccionados
$medtravel_services = isset($_POST['medtravel_services']) ? array_values(array_filter(array_map('intval', $_POST['medtravel_services']))) : [];

$budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? number_format((float) $_POST['budget'], 2, '.', '') : null;

// Construir timeline desde date range
$timeline_from = isset($_POST['timeline_from']) ? trim($_POST['timeline_from']) : '';
$timeline_to = isset($_POST['timeline_to']) ? trim($_POST['timeline_to']) : '';
$timeline = '';
if ($timeline_from && $timeline_to) {
    $timeline = $timeline_from . ' to ' . $timeline_to;
} elseif ($timeline_from) {
    $timeline = 'From ' . $timeline_from;
} elseif ($timeline_to) {
    $timeline = 'Until ' . $timeline_to;
}

$additional_notes = isset($_POST['additional_notes']) ? trim($_POST['additional_notes']) : '';

// Agregar servicios de medtravel a las notas si hay alguno seleccionado
if (!empty($medtravel_services)) {
    $medtravel_names_query = mysqli_query($conexion, "SELECT name FROM coordination_services WHERE id IN (" . implode(',', $medtravel_services) . ")");
    $medtravel_names = [];
    while ($row = mysqli_fetch_assoc($medtravel_names_query)) {
        $medtravel_names[] = $row['name'];
    }
    if (!empty($medtravel_names)) {
        $additional_notes .= "\n\nMedTravel Services Requested:\n- " . implode("\n- ", $medtravel_names);
    }
}

$origin = isset($booking['origin']) ? $booking['origin'] : 'wizard';

// Preparar datos para inserción
$selected_offers_json = json_encode($selected_offers);
$booking_datetime = isset($booking['datetime']) ? $booking['datetime'] : '';
$destination = isset($booking['destination']) ? $booking['destination'] : '';
$persons = isset($booking['persons']) ? $booking['persons'] : '';
$category = isset($booking['category']) ? $booking['category'] : '';
$special_request = isset($booking['special_request']) ? $booking['special_request'] : '';

// Insertar en booking_requests
$insert_sql = "INSERT INTO booking_requests 
               (name, email, origin, booking_datetime, destination, persons, category, 
                special_request, selected_offers, budget, timeline, additional_notes) 
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
$stmt = mysqli_prepare($conexion, $insert_sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ssssssssssss',
        $booking['name'],
        $booking['email'],
        $origin,
        $booking_datetime,
        $destination,
        $persons,
        $category,
        $special_request,
        $selected_offers_json,
        $budget,
        $timeline,
        $additional_notes
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$_SESSION['booking_request_status'] = 'submitted';
$offers_count = count($selected_offers);
$_SESSION['booking_request_message'] = "Your request with {$offers_count} selected service(s) was saved. One of our coordinators will reach back soon.";
header('Location: wizard.php');
exit;
