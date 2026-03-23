<?php
require_once __DIR__ . '/include/conexion.php';
require_once __DIR__ . '/include/roles.php';
require_once __DIR__ . '/include/valida_session.php';
require_once __DIR__ . '/../inc/google_calendar.php';

if (!google_calendar_admin_can_manage()) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$adminUserId = current_admin_user_id();
$config = google_calendar_get_config();
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === 'create_test_event') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Método no permitido';
        exit;
    }

    if (!$config['enabled']) {
        google_calendar_set_flash('danger', 'La configuración backend de Google no está completa todavía.');
        header('Location: google_calendar_settings.php');
        exit;
    }

    $connection = google_calendar_get_connection($conexion, $adminUserId, false);
    if (empty($connection['is_connected'])) {
        google_calendar_set_flash('danger', 'El admin autenticado no tiene una conexión Google activa.');
        header('Location: google_calendar_settings.php');
        exit;
    }

    $startAt = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
    $startAt = $startAt->modify('+30 minutes');
    $endAt = $startAt->modify('+30 minutes');

    $result = google_calendar_create_event_with_meet($conexion, $adminUserId, [
        'summary' => 'MedTravel Test Meeting',
        'description' => 'Evento técnico de prueba creado manualmente desde google_calendar_settings.php para validar Calendar + Meet.',
        'start_at' => $startAt->format(DateTimeInterface::RFC3339),
        'end_at' => $endAt->format(DateTimeInterface::RFC3339),
        'timezone' => 'America/Bogota',
    ]);

    if (!$result['ok']) {
        google_calendar_set_flash('danger', (string)($result['error'] ?? 'No fue posible crear el evento de prueba en Google Calendar.'), [
            'test_event' => [
                'ok' => false,
                'event_id' => (string)($result['event_id'] ?? ''),
                'html_link' => (string)($result['html_link'] ?? ''),
                'meet_url' => (string)($result['meet_url'] ?? ''),
            ],
        ]);
        header('Location: google_calendar_settings.php');
        exit;
    }

    google_calendar_set_flash('success', 'Evento de prueba creado correctamente en Google Calendar del admin conectado.', [
        'test_event' => [
            'ok' => true,
            'event_id' => (string)($result['event_id'] ?? ''),
            'html_link' => (string)($result['html_link'] ?? ''),
            'meet_url' => (string)($result['meet_url'] ?? ''),
        ],
    ]);
    header('Location: google_calendar_settings.php');
    exit;
}

if ($action === 'start') {
    if (!$config['enabled']) {
        google_calendar_set_flash('danger', 'La configuración backend de Google no está completa todavía.');
        header('Location: google_calendar_settings.php');
        exit;
    }

    $state = google_calendar_issue_oauth_state($adminUserId);
    header('Location: ' . google_calendar_build_authorize_url($config, $state));
    exit;
}

if ($action === 'callback') {
    if (!$config['enabled']) {
        google_calendar_set_flash('danger', 'La configuración backend de Google no está completa todavía.');
        header('Location: google_calendar_settings.php');
        exit;
    }

    $error = trim((string)($_GET['error'] ?? ''));
    if ($error !== '') {
        google_calendar_set_flash('danger', 'Google devolvió un error OAuth: ' . $error);
        header('Location: google_calendar_settings.php');
        exit;
    }

    $stateCheck = google_calendar_validate_oauth_state((string)($_GET['state'] ?? ''), $adminUserId);
    if (!$stateCheck['ok']) {
        google_calendar_set_flash('danger', (string)$stateCheck['error']);
        header('Location: google_calendar_settings.php');
        exit;
    }

    $code = trim((string)($_GET['code'] ?? ''));
    if ($code === '') {
        google_calendar_set_flash('danger', 'Google no devolvió el código de autorización.');
        header('Location: google_calendar_settings.php');
        exit;
    }

    $tokenResponse = google_calendar_exchange_code_for_tokens($config, $code);
    if (!$tokenResponse['ok'] || !is_array($tokenResponse['json'])) {
        $errorText = 'No fue posible intercambiar el código OAuth por tokens.';
        if (!empty($tokenResponse['json']['error_description'])) {
            $errorText = (string)$tokenResponse['json']['error_description'];
        } elseif (!empty($tokenResponse['error'])) {
            $errorText = (string)$tokenResponse['error'];
        }
        google_calendar_set_flash('danger', $errorText);
        header('Location: google_calendar_settings.php');
        exit;
    }

    $tokenPayload = $tokenResponse['json'];
    $accessToken = trim((string)($tokenPayload['access_token'] ?? ''));
    if ($accessToken === '') {
        google_calendar_set_flash('danger', 'Google no devolvió un access token utilizable.');
        header('Location: google_calendar_settings.php');
        exit;
    }

    $profile = [];
    $userinfoResponse = google_calendar_fetch_userinfo($config, $accessToken);
    if ($userinfoResponse['ok'] && is_array($userinfoResponse['json'])) {
        $profile = $userinfoResponse['json'];
    }

    $save = google_calendar_upsert_connection($conexion, $adminUserId, $tokenPayload, $profile);
    if (!$save['ok']) {
        google_calendar_set_flash('danger', (string)$save['error']);
        header('Location: google_calendar_settings.php');
        exit;
    }

    $connectedEmail = trim((string)($save['connection']['google_email'] ?? $profile['email'] ?? ''));
    $message = 'Conexión Google guardada correctamente para este admin.';
    if ($connectedEmail !== '') {
        $message .= ' Cuenta vinculada: ' . $connectedEmail . '.';
    }
    google_calendar_set_flash('success', $message);
    header('Location: google_calendar_settings.php');
    exit;
}

if ($action === 'disconnect') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Método no permitido';
        exit;
    }

    $disconnect = google_calendar_disconnect_connection($conexion, $adminUserId);
    if ($disconnect['ok']) {
        google_calendar_set_flash('success', 'La conexión Google del admin fue desconectada y los tokens locales quedaron eliminados.');
    } else {
        google_calendar_set_flash('danger', (string)$disconnect['error']);
    }
    header('Location: google_calendar_settings.php');
    exit;
}

http_response_code(400);
echo 'Acción inválida';