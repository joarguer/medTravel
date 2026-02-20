<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

if (isset($_SESSION['dd_last']) && (time() - (int)$_SESSION['dd_last'] < 60)) {
    echo json_encode(['ok' => false, 'error' => 'rate_limited']);
    exit;
}

function dd_public_sanitize_text($value, $maxLen = 0)
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    if ($value === null) {
        $value = '';
    }
    if ($maxLen > 0 && strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}

$phone = dd_public_sanitize_text($_POST['phone'] ?? '', 80);
$email = dd_public_sanitize_text($_POST['email'] ?? '', 255);
$name = dd_public_sanitize_text($_POST['name'] ?? '', 255);
$message = dd_public_sanitize_text($_POST['message'] ?? '', 5000);

if ($phone === '' && $email === '') {
    echo json_encode(['ok' => false, 'error' => 'email_or_phone_required']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_email']);
    exit;
}

require_once __DIR__ . '/../admin/include/conexion.php';
require_once __DIR__ . '/../admin/include/data_deletion_service.php';

try {
    $requestId = dd_create_request($conexion, [
        'phone' => $phone,
        'email' => $email,
        'name' => $name,
        'message' => $message,
        'ip' => dd_public_sanitize_text($_SERVER['REMOTE_ADDR'] ?? '', 64),
        'user_agent' => dd_public_sanitize_text($_SERVER['HTTP_USER_AGENT'] ?? '', 512),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'request_persist_failed']);
    exit;
}

$mailError = false;
try {
    require_once __DIR__ . '/../admin/include/email_config.php';
    $mail = getMailer('patientcare');
    $mail->addAddress('info@medtravel.com', 'Data Deletion Support');
    $mail->Subject = 'Data Deletion Request ' . $requestId;
    $body  = "<p>New data deletion request.</p>";
    $body .= "<ul>";
    $body .= "<li><strong>Request ID:</strong> " . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') . "</li>";
    if ($phone !== '') {
        $body .= "<li><strong>Phone:</strong> " . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . "</li>";
    }
    if ($email !== '') {
        $body .= "<li><strong>Email:</strong> " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</li>";
    }
    if ($name !== '') {
        $body .= "<li><strong>Name:</strong> " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</li>";
    }
    if ($message !== '') {
        $body .= "<li><strong>Message:</strong> " . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</li>";
    }
    $body .= "</ul>";
    $mail->Body = $body;
    $altLines = ["Request ID: {$requestId}"];
    if ($phone !== '') {
        $altLines[] = "Phone: {$phone}";
    }
    if ($email !== '') {
        $altLines[] = "Email: {$email}";
    }
    if ($name !== '') {
        $altLines[] = "Name: {$name}";
    }
    if ($message !== '') {
        $altLines[] = "Message: {$message}";
    }
    $mail->AltBody = implode("\n", $altLines);
    $mail->send();
} catch (Throwable $e) {
    $mailError = true;
}

$_SESSION['dd_last'] = time();

if ($mailError) {
    echo json_encode(['ok' => true, 'request_id' => $requestId, 'warning' => 'support_email_not_sent']);
} else {
    echo json_encode(['ok' => true, 'request_id' => $requestId]);
}
