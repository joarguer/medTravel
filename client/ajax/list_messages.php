<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';

function client_messages_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

$clientUserId = get_client_user_id();
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0);
if ($bookingId <= 0) {
    client_messages_error('invalid_booking_id');
}

if (!isset($conexion) || !$conexion || !client_table_exists($conexion, 'booking_requests') || !client_table_has_column($conexion, 'booking_requests', 'client_user_id')) {
    client_messages_error('booking_requests_not_available', 409);
}

$hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasAdditionalNotes = client_table_has_column($conexion, 'booking_requests', 'additional_notes');
$hasSpecialRequest = client_table_has_column($conexion, 'booking_requests', 'special_request');

$bookingSql = "SELECT br.id";
$bookingSql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
$bookingSql .= $hasSpecialRequest ? ", br.special_request" : ", '' AS special_request";
$bookingSql .= " FROM booking_requests br WHERE br.id = ? AND br.client_user_id = ?";
if ($hasBookingSoftDelete) {
    $bookingSql .= " AND br.is_deleted = 0";
}
$bookingSql .= " LIMIT 1";

$stmtBooking = mysqli_prepare($conexion, $bookingSql);
if (!$stmtBooking) {
    client_messages_error('prepare_failed', 500);
}
mysqli_stmt_bind_param($stmtBooking, 'ii', $bookingId, $clientUserId);
if (!mysqli_stmt_execute($stmtBooking)) {
    mysqli_stmt_close($stmtBooking);
    client_messages_error('execute_failed', 500);
}
$bookingRes = mysqli_stmt_get_result($stmtBooking);
$booking = $bookingRes ? mysqli_fetch_assoc($bookingRes) : null;
mysqli_stmt_close($stmtBooking);

if (!$booking) {
    client_messages_error('request_not_found', 404);
}

$messages = [];
$notes = trim((string)($booking['additional_notes'] ?? ''));
$specialRequest = trim((string)($booking['special_request'] ?? ''));

if ($specialRequest !== '') {
    $messages[] = [
        'sender' => 'client',
        'type' => 'initial_request',
        'body' => $specialRequest,
        'time' => '',
    ];
}

if ($notes !== '') {
    $lines = preg_split('/\R+/', $notes);
    $hasStructuredClientMessages = false;
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^\[CLIENT_MESSAGE\]\[(.*?)\]\s*(.*)$/', $line, $m)) {
            $hasStructuredClientMessages = true;
            $messages[] = [
                'sender' => 'client',
                'type' => 'client_message',
                'body' => trim((string)$m[2]),
                'time' => trim((string)$m[1]),
            ];
        }
    }
    if (!$hasStructuredClientMessages) {
        $messages[] = [
            'sender' => 'client',
            'type' => 'additional_notes',
            'body' => $notes,
            'time' => '',
        ];
    }
}

if (client_table_exists($conexion, 'booking_request_items')) {
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasProviderNotes = client_table_has_column($conexion, 'booking_request_items', 'provider_notes');
    $hasRejectReason = client_table_has_column($conexion, 'booking_request_items', 'provider_reject_reason');
    $hasItemStatus = client_table_has_column($conexion, 'booking_request_items', 'item_status');
    $hasResponseAt = client_table_has_column($conexion, 'booking_request_items', 'provider_response_at');
    $hasUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');
    $hasCreatedAt = client_table_has_column($conexion, 'booking_request_items', 'created_at');

    if ($hasProviderNotes || $hasRejectReason || $hasItemStatus) {
        $eventExpr = "''";
        if ($hasResponseAt && $hasUpdatedAt && $hasCreatedAt) {
            $eventExpr = 'COALESCE(provider_response_at, updated_at, created_at)';
        } elseif ($hasUpdatedAt && $hasCreatedAt) {
            $eventExpr = 'COALESCE(updated_at, created_at)';
        } elseif ($hasCreatedAt) {
            $eventExpr = 'created_at';
        }

        $sql = "SELECT id";
        $sql .= $hasProviderNotes ? ", provider_notes" : ", '' AS provider_notes";
        $sql .= $hasRejectReason ? ", provider_reject_reason" : ", '' AS provider_reject_reason";
        $sql .= $hasItemStatus ? ", item_status" : ", '' AS item_status";
        $sql .= ", {$eventExpr} AS event_at";
        $sql .= " FROM booking_request_items WHERE booking_request_id = ?";
        if ($hasItemsSoftDelete) {
            $sql .= " AND is_deleted = 0";
        }
        $sql .= " ORDER BY id ASC";

        $stmtItems = mysqli_prepare($conexion, $sql);
        if ($stmtItems) {
            mysqli_stmt_bind_param($stmtItems, 'i', $bookingId);
            if (mysqli_stmt_execute($stmtItems)) {
                $resItems = mysqli_stmt_get_result($stmtItems);
                while ($resItems && ($row = mysqli_fetch_assoc($resItems))) {
                    $providerNotes = trim((string)($row['provider_notes'] ?? ''));
                    $rejectReason = trim((string)($row['provider_reject_reason'] ?? ''));
                    $status = client_status_label($row['item_status'] ?? '');
                    $eventAt = trim((string)($row['event_at'] ?? ''));

                    if ($providerNotes !== '') {
                        $messages[] = [
                            'sender' => 'provider',
                            'type' => 'provider_note',
                            'body' => $providerNotes,
                            'time' => $eventAt,
                        ];
                    }
                    if ($rejectReason !== '') {
                        $messages[] = [
                            'sender' => 'provider',
                            'type' => 'provider_reject_reason',
                            'body' => 'Rejection reason: ' . $rejectReason,
                            'time' => $eventAt,
                        ];
                    }
                    if ($status !== '' && client_status_is_update($status)) {
                        $messages[] = [
                            'sender' => 'system',
                            'type' => 'status_update',
                            'body' => 'Service status updated to: ' . $status,
                            'time' => $eventAt,
                        ];
                    }
                }
            }
            mysqli_stmt_close($stmtItems);
        }
    }
}

usort($messages, function ($a, $b) {
    $ta = strtotime((string)($a['time'] ?? ''));
    $tb = strtotime((string)($b['time'] ?? ''));
    if ($ta === $tb) {
        return 0;
    }
    return ($ta < $tb) ? -1 : 1;
});

echo json_encode([
    'ok' => true,
    'booking_id' => $bookingId,
    'messages' => $messages,
]);

