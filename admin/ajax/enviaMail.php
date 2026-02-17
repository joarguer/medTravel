<?php
header('Content-Type: application/json; charset=utf-8');

require_once('../include/conexion.php');
require_once('../include/roles.php');
require_once('../include/email_config.php');

require_login_ajax();

function json_response($ok, $statusCode = 200, $payload = array()) {
    http_response_code($statusCode);
    echo json_encode(array_merge(array(
        'ok' => $ok,
        'status' => $ok
    ), $payload));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 405, array('error' => 'method_not_allowed'));
}

if (!user_can(PERM_USERS_MANAGE) && !user_can('users.create')) {
    json_response(false, 403, array('error' => 'forbidden'));
}

$to = isset($_POST['to']) ? trim((string)$_POST['to']) : trim((string)($_POST['email'] ?? ''));
$name = isset($_POST['name']) ? trim((string)$_POST['name']) : trim((string)($_POST['nombre'] ?? ''));
$username = isset($_POST['username']) ? trim((string)$_POST['username']) : trim((string)($_POST['usuario'] ?? $to));
$tempPassword = isset($_POST['temp_password']) ? trim((string)$_POST['temp_password']) : trim((string)($_POST['password'] ?? ''));
$subject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : trim((string)($_POST['asunto'] ?? 'Creación Cuenta Administrativa'));
$addCcRaw = trim((string)($_POST['addCC'] ?? ''));
$addBccRaw = trim((string)($_POST['sBCC'] ?? ''));

if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 422, array('error' => 'invalid_to_email'));
}

if ($subject === '') {
    $subject = 'Creación Cuenta Administrativa';
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '' ? $_SERVER['HTTP_HOST'] : 'medtravel.com.co';
$adminUrl = $scheme . '://' . $host . '/admin/';

$safeName = htmlspecialchars($name !== '' ? $name : 'usuario', ENT_QUOTES, 'UTF-8');
$safeUser = htmlspecialchars($username !== '' ? $username : $to, ENT_QUOTES, 'UTF-8');
$safeAdminUrl = htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8');
$safePassword = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');

$passwordBlock = '';
$passwordText = '';
if ($tempPassword !== '') {
    $passwordBlock = '<li><strong>Contraseña temporal:</strong> ' . $safePassword . '</li>';
    $passwordText = "\nContraseña temporal: " . $tempPassword;
}

$body = '<html><body style="font-family: Arial, Helvetica, sans-serif; color: #333;">'
    . '<h2 style="color:#2980d9;">Bienvenido(a) a MedTravel</h2>'
    . '<p>Hola ' . $safeName . ',</p>'
    . '<p>Tu cuenta administrativa fue creada exitosamente.</p>'
    . '<p><strong>Datos de acceso:</strong></p>'
    . '<ul>'
    . '<li><strong>Usuario:</strong> ' . $safeUser . '</li>'
    . $passwordBlock
    . '</ul>'
    . '<p>Para ingresar al sistema, usa el siguiente enlace:</p>'
    . '<p><a href="' . $safeAdminUrl . '" style="display:inline-block;padding:10px 16px;background:#2980d9;color:#fff;text-decoration:none;border-radius:4px;">Ir al panel administrativo</a></p>'
    . '<p>Si no solicitaste esta cuenta, por favor contacta al administrador.</p>'
    . '</body></html>';

$altBody = "Bienvenido(a) a MedTravel\n"
    . "Usuario: " . ($username !== '' ? $username : $to)
    . $passwordText
    . "\nIngreso: " . $adminUrl;

$options = array('alt_body' => $altBody);
if ($addCcRaw !== '') {
    $ccList = array_values(array_filter(array_map('trim', explode(',', $addCcRaw))));
    if (!empty($ccList)) {
        $options['cc'] = $ccList;
    }
}
if ($addBccRaw !== '') {
    $bccList = array_values(array_filter(array_map('trim', explode(',', $addBccRaw))));
    if (!empty($bccList)) {
        $options['bcc'] = $bccList;
    }
}

try {
    $sent = sendEmail($to, $subject, $body, 'patientcare', $options, $conexion);
    if ($sent === true) {
        json_response(true, 200, array('message' => 'Correo enviado'));
    }

    $error = is_array($sent) && isset($sent['error']) ? $sent['error'] : 'email_send_failed';
    json_response(false, 502, array('error' => $error));
} catch (Exception $e) {
    json_response(false, 500, array('error' => $e->getMessage()));
}

