<?php
// Simple API to log data deletion requests and notify support
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Basic rate limit (per session): 1 request every 60 seconds
if (isset($_SESSION['dd_last']) && (time() - $_SESSION['dd_last'] < 60)) {
    echo json_encode(['ok' => false, 'error' => 'Please wait before sending another request.']);
    exit;
}

function sanitize($v){ return trim(filter_var($v, FILTER_SANITIZE_STRING)); }

$phone   = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
$email   = isset($_POST['email']) ? trim($_POST['email']) : '';
$name    = isset($_POST['name']) ? sanitize($_POST['name']) : '';
$message = isset($_POST['message']) ? sanitize($_POST['message']) : '';

if ($phone === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Phone and valid email are required.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$request_id = date('Ymd-His') . '-' . random_int(1000,9999);
$timestamp = date('c');

$payload = [
    'request_id' => $request_id,
    'timestamp' => $timestamp,
    'ip' => $ip,
    'user_agent' => $ua,
    'phone' => $phone,
    'email' => $email,
    'name' => $name,
    'message' => $message
];

// Log JSON line
$logDir = __DIR__ . '/../admin/logs';
if (!file_exists($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/data_deletion.log';
file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

// Try to email support using existing mailer config
$mail_sent = false;
$mail_error = null;
try {
    require_once __DIR__ . '/../admin/include/email_config.php';
    $mail = getMailer('patientcare');
    $mail->addAddress('info@medtravel.com', 'Data Deletion Support');
    $mail->Subject = 'Data Deletion Request ' . $request_id;
    $body  = "<p>New data deletion request.</p>";
    $body .= "<ul>";
    $body .= "<li><strong>Request ID:</strong> {$request_id}</li>";
    $body .= "<li><strong>Phone:</strong> " . htmlspecialchars($phone) . "</li>";
    $body .= "<li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>";
    $body .= "<li><strong>Name:</strong> " . htmlspecialchars($name) . "</li>";
    $body .= "<li><strong>Message:</strong> " . nl2br(htmlspecialchars($message)) . "</li>";
    $body .= "<li><strong>IP:</strong> {$ip}</li>";
    $body .= "<li><strong>User-Agent:</strong> " . htmlspecialchars($ua) . "</li>";
    $body .= "<li><strong>Timestamp:</strong> {$timestamp}</li>";
    $body .= "</ul>";
    $mail->Body = $body;
    $mail->AltBody = "Request ID: {$request_id}\nPhone: {$phone}\nEmail: {$email}\nName: {$name}\nMessage: {$message}\nIP: {$ip}\nUA: {$ua}\nTimestamp: {$timestamp}";
    $mail->send();
    $mail_sent = true;
} catch (Exception $e) {
    $mail_error = $e->getMessage();
}

$_SESSION['dd_last'] = time();

if ($mail_error) {
    echo json_encode(['ok' => true, 'request_id' => $request_id, 'warning' => 'Logged but email not sent: '.$mail_error]);
} else {
    echo json_encode(['ok' => true, 'request_id' => $request_id]);
}
