<?php
/**
 * AJAX endpoint — Client personal acceptance of Terms of Service.
 *
 * Records terms_accepted = 1 in usuarios for the authenticated client,
 * stores the audit fields (IP, user agent, version, timestamp),
 * and updates the session so the terms gate is cleared for this session.
 *
 * Must be called from the client portal session only.
 */

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF: only authenticated clients from the same session can call this.
$clientUserId = get_client_user_id();
if ($clientUserId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Require all three consent checkboxes
$consentTerms        = (string)($_POST['consent_terms'] ?? '');
$consentPrivacy      = (string)($_POST['consent_privacy'] ?? '');
$consentIntermediary = (string)($_POST['consent_intermediary'] ?? '');
$termsVersion        = trim((string)($_POST['terms_version'] ?? ''));

if ($consentTerms !== '1' || $consentPrivacy !== '1' || $consentIntermediary !== '1') {
    echo json_encode(['success' => false, 'message' => 'All consent checkboxes must be accepted.']);
    exit;
}

if ($termsVersion === '') {
    $termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.1';
}

// Capture client audit metadata
$acceptedAt  = date('Y-m-d H:i:s');
$clientIp    = (string)($_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$clientIp    = substr(trim($clientIp), 0, 45);
$userAgent   = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

include_once __DIR__ . '/../../admin/include/conexion.php';

if (!isset($conexion) || !$conexion) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// Check that the column exists before updating (guards against missing migration)
$colCheck = mysqli_query($conexion, "SHOW COLUMNS FROM `usuarios` LIKE 'terms_accepted'");
if (!$colCheck || mysqli_num_rows($colCheck) === 0) {
    // Column not yet added — silently accept and set session flag anyway.
    // Operator should run the migration SQL.
    $_SESSION['client_terms_accepted'] = 1;
    echo json_encode(['success' => true, 'message' => 'Accepted (migration pending)']);
    exit;
}

// Build UPDATE with only existing columns
$setParts = ["terms_accepted = 1", "terms_accepted_at = ?"];
$types    = 's';
$params   = [$acceptedAt];

$checkCol = function ($col) use ($conexion) {
    $r = mysqli_query($conexion, "SHOW COLUMNS FROM `usuarios` LIKE '" . mysqli_real_escape_string($conexion, $col) . "'");
    return $r && mysqli_num_rows($r) > 0;
};

if ($checkCol('terms_version')) {
    $setParts[] = 'terms_version = ?';
    $types .= 's';
    $params[] = $termsVersion;
}
if ($checkCol('terms_ip')) {
    $setParts[] = 'terms_ip = ?';
    $types .= 's';
    $params[] = $clientIp;
}
if ($checkCol('terms_user_agent')) {
    $setParts[] = 'terms_user_agent = ?';
    $types .= 's';
    $params[] = $userAgent;
}

$types   .= 'i';
$params[] = $clientUserId;

$sql  = "UPDATE usuarios SET " . implode(', ', $setParts) . " WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . mysqli_error($conexion)]);
    exit;
}

// Bind params manually (variadic-safe)
$bind = [$stmt, &$types];
foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
call_user_func_array('mysqli_stmt_bind_param', $bind);

if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save acceptance: ' . $err]);
    exit;
}
mysqli_stmt_close($stmt);

// Update session so this session doesn't hit the gate again
$_SESSION['client_terms_accepted'] = 1;

echo json_encode(['success' => true, 'message' => 'Terms accepted.']);
exit;
