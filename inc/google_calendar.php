<?php

function google_calendar_table_exists($conexion, $table)
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$table];
}

function google_calendar_admin_can_manage()
{
    return function_exists('user_can')
        && function_exists('is_role_admin_session')
        && user_can(PERM_SETTINGS_MANAGE)
        && is_role_admin_session();
}

function google_calendar_required_scopes()
{
    return [
        'openid',
        'email',
        'profile',
        'https://www.googleapis.com/auth/calendar.events',
    ];
}

function google_calendar_get_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $clientId = trim((string)(getenv('GOOGLE_OAUTH_CLIENT_ID') ?: getenv('GOOGLE_CALENDAR_CLIENT_ID') ?: ''));
    $clientSecret = trim((string)(getenv('GOOGLE_OAUTH_CLIENT_SECRET') ?: getenv('GOOGLE_CALENDAR_CLIENT_SECRET') ?: ''));
    $redirectUri = trim((string)(getenv('GOOGLE_OAUTH_REDIRECT_URI') ?: getenv('GOOGLE_CALENDAR_REDIRECT_URI') ?: ''));
    $encryptionKey = trim((string)(getenv('GOOGLE_OAUTH_ENCRYPTION_KEY') ?: getenv('GOOGLE_CALENDAR_ENCRYPTION_KEY') ?: getenv('APP_SECRET') ?: getenv('APP_KEY') ?: ''));
    $missing = [];

    if ($clientId === '') {
        $missing[] = 'GOOGLE_OAUTH_CLIENT_ID';
    }
    if ($clientSecret === '') {
        $missing[] = 'GOOGLE_OAUTH_CLIENT_SECRET';
    }
    if ($redirectUri === '') {
        $missing[] = 'GOOGLE_OAUTH_REDIRECT_URI';
    }
    if ($encryptionKey === '') {
        $missing[] = 'GOOGLE_OAUTH_ENCRYPTION_KEY';
    }
    if (!function_exists('curl_init')) {
        $missing[] = 'PHP cURL extension';
    }
    if (!function_exists('openssl_encrypt')) {
        $missing[] = 'PHP OpenSSL extension';
    }

    $config = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'encryption_key' => $encryptionKey,
        'scopes' => google_calendar_required_scopes(),
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'revoke_url' => 'https://oauth2.googleapis.com/revoke',
        'calendar_base_url' => 'https://www.googleapis.com/calendar/v3',
        'missing' => $missing,
        'enabled' => empty($missing),
    ];

    return $config;
}

function google_calendar_mask_value($value, $visible = 4)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $length = strlen($value);
    if ($length <= $visible) {
        return str_repeat('*', $length);
    }
    return str_repeat('*', $length - $visible) . substr($value, -$visible);
}

function google_calendar_crypto_key($encryptionKey)
{
    return hash('sha256', (string)$encryptionKey, true);
}

function google_calendar_encrypt_secret($plainText, $encryptionKey)
{
    $plainText = (string)$plainText;
    if ($plainText === '') {
        return '';
    }

    $key = google_calendar_crypto_key($encryptionKey);
    $iv = random_bytes(16);
    $cipherText = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipherText === false) {
        return '';
    }
    $hmac = hash_hmac('sha256', $iv . $cipherText, $key, true);
    return base64_encode($iv . $hmac . $cipherText);
}

function google_calendar_decrypt_secret($encodedValue, $encryptionKey)
{
    $encodedValue = trim((string)$encodedValue);
    if ($encodedValue === '') {
        return '';
    }

    $decoded = base64_decode($encodedValue, true);
    if ($decoded === false || strlen($decoded) < 49) {
        return '';
    }

    $iv = substr($decoded, 0, 16);
    $hmac = substr($decoded, 16, 32);
    $cipherText = substr($decoded, 48);
    $key = google_calendar_crypto_key($encryptionKey);
    $expectedHmac = hash_hmac('sha256', $iv . $cipherText, $key, true);
    if (!hash_equals($expectedHmac, $hmac)) {
        return '';
    }

    $plainText = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plainText === false ? '' : (string)$plainText;
}

function google_calendar_http_request($method, $url, $headers = [], $body = null)
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 0,
            'error' => 'cURL no está disponible en PHP.',
            'body' => '',
            'json' => null,
        ];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper((string)$method));

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $rawBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = null;
    if (is_string($rawBody) && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $json = $decoded;
        }
    }

    return [
        'ok' => ($curlError === '' && $status >= 200 && $status < 300),
        'status' => $status,
        'error' => $curlError,
        'body' => is_string($rawBody) ? $rawBody : '',
        'json' => $json,
    ];
}

function google_calendar_set_flash($type, $message)
{
    $_SESSION['google_calendar_flash'] = [
        'type' => trim((string)$type),
        'message' => trim((string)$message),
    ];
}

function google_calendar_pop_flash()
{
    $flash = isset($_SESSION['google_calendar_flash']) && is_array($_SESSION['google_calendar_flash'])
        ? $_SESSION['google_calendar_flash']
        : null;
    unset($_SESSION['google_calendar_flash']);
    return $flash;
}

function google_calendar_issue_oauth_state($adminUserId)
{
    $state = bin2hex(random_bytes(24));
    $_SESSION['google_calendar_oauth_state'] = [
        'value' => $state,
        'admin_user_id' => (int)$adminUserId,
        'expires_at' => time() + 600,
        'user_agent' => sha1((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
    ];
    return $state;
}

function google_calendar_validate_oauth_state($state, $adminUserId)
{
    $stored = isset($_SESSION['google_calendar_oauth_state']) && is_array($_SESSION['google_calendar_oauth_state'])
        ? $_SESSION['google_calendar_oauth_state']
        : null;
    unset($_SESSION['google_calendar_oauth_state']);

    if (!$stored) {
        return ['ok' => false, 'error' => 'No existe estado OAuth activo.'];
    }
    if ((int)($stored['admin_user_id'] ?? 0) !== (int)$adminUserId) {
        return ['ok' => false, 'error' => 'El estado OAuth no pertenece al admin autenticado.'];
    }
    if ((int)($stored['expires_at'] ?? 0) < time()) {
        return ['ok' => false, 'error' => 'El estado OAuth expiró.'];
    }
    if (!hash_equals((string)($stored['user_agent'] ?? ''), sha1((string)($_SERVER['HTTP_USER_AGENT'] ?? '')))) {
        return ['ok' => false, 'error' => 'El navegador cambió durante el flujo OAuth.'];
    }
    if (!hash_equals((string)($stored['value'] ?? ''), (string)$state)) {
        return ['ok' => false, 'error' => 'El estado OAuth recibido no es válido.'];
    }

    return ['ok' => true];
}

function google_calendar_build_authorize_url($config, $state)
{
    $query = http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => implode(' ', $config['scopes']),
        'access_type' => 'offline',
        'include_granted_scopes' => 'true',
        'prompt' => 'consent',
        'state' => $state,
    ]);

    return $config['authorize_url'] . '?' . $query;
}

function google_calendar_exchange_code_for_tokens($config, $code)
{
    $postFields = http_build_query([
        'code' => (string)$code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);

    return google_calendar_http_request('POST', $config['token_url'], [
        'Content-Type: application/x-www-form-urlencoded',
    ], $postFields);
}

function google_calendar_refresh_access_token($config, $refreshToken)
{
    $postFields = http_build_query([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'refresh_token' => (string)$refreshToken,
        'grant_type' => 'refresh_token',
    ]);

    return google_calendar_http_request('POST', $config['token_url'], [
        'Content-Type: application/x-www-form-urlencoded',
    ], $postFields);
}

function google_calendar_fetch_userinfo($config, $accessToken)
{
    return google_calendar_http_request('GET', $config['userinfo_url'], [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ]);
}

function google_calendar_update_last_error($conexion, $adminUserId, $message)
{
    if (
        !$conexion ||
        (int)$adminUserId <= 0 ||
        !google_calendar_table_exists($conexion, 'admin_google_calendar_connections')
    ) {
        return;
    }

    $sql = "UPDATE admin_google_calendar_connections
            SET last_error = ?, updated_at = NOW()
            WHERE admin_user_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return;
    }

    $lastError = trim((string)$message);
    $adminUserId = (int)$adminUserId;
    mysqli_stmt_bind_param($stmt, 'si', $lastError, $adminUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function google_calendar_get_connection($conexion, $adminUserId, $includeSecrets = false)
{
    if (
        !$conexion ||
        (int)$adminUserId <= 0 ||
        !google_calendar_table_exists($conexion, 'admin_google_calendar_connections')
    ) {
        return null;
    }

    $sql = "SELECT id, admin_user_id, google_email, google_subject, scope_text, token_type,
                   token_expires_at, access_token_encrypted, refresh_token_encrypted,
                   connected_at, updated_at, disconnected_at, last_error
            FROM admin_google_calendar_connections
            WHERE admin_user_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    $adminUserId = (int)$adminUserId;
    mysqli_stmt_bind_param($stmt, 'i', $adminUserId);
    $row = null;
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            $row = mysqli_fetch_assoc($res) ?: null;
        }
    }
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    $config = google_calendar_get_config();
    $row['is_connected'] = (
        trim((string)($row['refresh_token_encrypted'] ?? '')) !== '' &&
        trim((string)($row['disconnected_at'] ?? '')) === ''
    );

    if ($includeSecrets && $config['enabled']) {
        $row['access_token'] = google_calendar_decrypt_secret((string)($row['access_token_encrypted'] ?? ''), $config['encryption_key']);
        $row['refresh_token'] = google_calendar_decrypt_secret((string)($row['refresh_token_encrypted'] ?? ''), $config['encryption_key']);
    }

    return $row;
}

function google_calendar_upsert_connection($conexion, $adminUserId, array $tokenPayload, array $profile)
{
    $config = google_calendar_get_config();
    if (!$config['enabled']) {
        return ['ok' => false, 'error' => 'La configuración backend de Google no está completa.'];
    }
    if (!$conexion || (int)$adminUserId <= 0) {
        return ['ok' => false, 'error' => 'No se pudo resolver el admin autenticado.'];
    }
    if (!google_calendar_table_exists($conexion, 'admin_google_calendar_connections')) {
        return ['ok' => false, 'error' => 'La tabla admin_google_calendar_connections no existe todavía.'];
    }

    $existing = google_calendar_get_connection($conexion, $adminUserId, true);
    $accessToken = trim((string)($tokenPayload['access_token'] ?? ''));
    $refreshToken = trim((string)($tokenPayload['refresh_token'] ?? ''));
    if ($refreshToken === '' && !empty($existing['refresh_token'])) {
        $refreshToken = (string)$existing['refresh_token'];
    }
    if ($accessToken === '' && !empty($existing['access_token'])) {
        $accessToken = (string)$existing['access_token'];
    }

    if ($refreshToken === '') {
        return ['ok' => false, 'error' => 'Google no devolvió refresh token utilizable para esta conexión.'];
    }

    $scopeText = trim((string)($tokenPayload['scope'] ?? ''));
    if ($scopeText === '') {
        $scopeText = implode(' ', $config['scopes']);
    }
    $tokenType = trim((string)($tokenPayload['token_type'] ?? 'Bearer'));
    $expiresIn = max(0, (int)($tokenPayload['expires_in'] ?? 0));
    $expiresAt = $expiresIn > 0 ? gmdate('Y-m-d H:i:s', time() + $expiresIn) : null;

    $googleEmail = trim((string)($profile['email'] ?? $existing['google_email'] ?? ''));
    $googleSubject = trim((string)($profile['sub'] ?? $existing['google_subject'] ?? ''));
    $accessTokenEncrypted = google_calendar_encrypt_secret($accessToken, $config['encryption_key']);
    $refreshTokenEncrypted = google_calendar_encrypt_secret($refreshToken, $config['encryption_key']);

    if ($accessTokenEncrypted === '' || $refreshTokenEncrypted === '') {
        return ['ok' => false, 'error' => 'No fue posible cifrar los tokens OAuth.'];
    }

    if ($existing) {
        $sql = "UPDATE admin_google_calendar_connections
                SET google_email = ?,
                    google_subject = ?,
                    scope_text = ?,
                    token_type = ?,
                    token_expires_at = ?,
                    access_token_encrypted = ?,
                    refresh_token_encrypted = ?,
                    connected_at = COALESCE(connected_at, NOW()),
                    disconnected_at = NULL,
                    last_error = NULL,
                    updated_at = NOW()
                WHERE admin_user_id = ?
                LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'No fue posible actualizar la conexión Google.'];
        }

        mysqli_stmt_bind_param(
            $stmt,
            'sssssssi',
            $googleEmail,
            $googleSubject,
            $scopeText,
            $tokenType,
            $expiresAt,
            $accessTokenEncrypted,
            $refreshTokenEncrypted,
            $adminUserId
        );
    } else {
        $sql = "INSERT INTO admin_google_calendar_connections (
                    admin_user_id, google_email, google_subject, scope_text, token_type,
                    token_expires_at, access_token_encrypted, refresh_token_encrypted,
                    connected_at, updated_at, disconnected_at, last_error
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NULL, NULL)";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'No fue posible guardar la conexión Google.'];
        }

        mysqli_stmt_bind_param(
            $stmt,
            'isssssss',
            $adminUserId,
            $googleEmail,
            $googleSubject,
            $scopeText,
            $tokenType,
            $expiresAt,
            $accessTokenEncrypted,
            $refreshTokenEncrypted
        );
    }

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Falló la persistencia de la conexión Google.'];
    }

    return ['ok' => true, 'connection' => google_calendar_get_connection($conexion, $adminUserId, false)];
}

function google_calendar_disconnect_connection($conexion, $adminUserId)
{
    if (
        !$conexion ||
        (int)$adminUserId <= 0 ||
        !google_calendar_table_exists($conexion, 'admin_google_calendar_connections')
    ) {
        return ['ok' => false, 'error' => 'No existe conexión Google registrada para este admin.'];
    }

    $connection = google_calendar_get_connection($conexion, $adminUserId, true);
    if (!$connection) {
        return ['ok' => false, 'error' => 'No existe conexión Google registrada para este admin.'];
    }

    $config = google_calendar_get_config();
    $revokeToken = trim((string)($connection['refresh_token'] ?? ''));
    if ($config['enabled'] && $revokeToken !== '') {
        google_calendar_http_request(
            'POST',
            $config['revoke_url'],
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['token' => $revokeToken])
        );
    }

    $sql = "UPDATE admin_google_calendar_connections
            SET access_token_encrypted = NULL,
                refresh_token_encrypted = NULL,
                token_expires_at = NULL,
                disconnected_at = NOW(),
                last_error = NULL,
                updated_at = NOW()
            WHERE admin_user_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No fue posible desconectar la cuenta Google.'];
    }

    $adminUserId = (int)$adminUserId;
    mysqli_stmt_bind_param($stmt, 'i', $adminUserId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok
        ? ['ok' => true]
        : ['ok' => false, 'error' => 'No fue posible desconectar la cuenta Google.'];
}

function google_calendar_ensure_valid_access_token($conexion, $adminUserId)
{
    $config = google_calendar_get_config();
    if (!$config['enabled']) {
        return ['ok' => false, 'error' => 'La configuración backend de Google no está completa.'];
    }

    $connection = google_calendar_get_connection($conexion, $adminUserId, true);
    if (!$connection || empty($connection['refresh_token'])) {
        return ['ok' => false, 'error' => 'El admin no tiene una conexión Google activa.'];
    }

    $accessToken = trim((string)($connection['access_token'] ?? ''));
    $expiresAt = trim((string)($connection['token_expires_at'] ?? ''));
    $expiresTs = $expiresAt !== '' ? strtotime($expiresAt . ' UTC') : false;
    if ($accessToken !== '' && $expiresTs !== false && $expiresTs > (time() + 120)) {
        return ['ok' => true, 'access_token' => $accessToken, 'connection' => $connection];
    }

    $refreshResponse = google_calendar_refresh_access_token($config, (string)$connection['refresh_token']);
    if (!$refreshResponse['ok'] || !is_array($refreshResponse['json'])) {
        $errorText = 'No fue posible refrescar el access token de Google.';
        if (!empty($refreshResponse['json']['error_description'])) {
            $errorText = (string)$refreshResponse['json']['error_description'];
        } elseif (!empty($refreshResponse['error'])) {
            $errorText = (string)$refreshResponse['error'];
        }
        google_calendar_update_last_error($conexion, $adminUserId, $errorText);
        return ['ok' => false, 'error' => $errorText];
    }

    $tokenPayload = $refreshResponse['json'];
    if (empty($tokenPayload['refresh_token'])) {
        $tokenPayload['refresh_token'] = (string)$connection['refresh_token'];
    }
    if (empty($tokenPayload['scope'])) {
        $tokenPayload['scope'] = (string)($connection['scope_text'] ?? implode(' ', $config['scopes']));
    }

    $save = google_calendar_upsert_connection($conexion, $adminUserId, $tokenPayload, [
        'email' => (string)($connection['google_email'] ?? ''),
        'sub' => (string)($connection['google_subject'] ?? ''),
    ]);
    if (!$save['ok']) {
        google_calendar_update_last_error($conexion, $adminUserId, (string)$save['error']);
        return $save;
    }

    $updated = google_calendar_get_connection($conexion, $adminUserId, true);
    return [
        'ok' => true,
        'access_token' => (string)($updated['access_token'] ?? ''),
        'connection' => $updated,
    ];
}

function google_calendar_normalize_attendees($attendees)
{
    $normalized = [];
    if (!is_array($attendees)) {
        return $normalized;
    }

    foreach ($attendees as $attendee) {
        if (!is_array($attendee)) {
            continue;
        }
        $email = trim((string)($attendee['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $entry = ['email' => $email];
        $displayName = trim((string)($attendee['displayName'] ?? $attendee['name'] ?? ''));
        if ($displayName !== '') {
            $entry['displayName'] = $displayName;
        }
        $normalized[] = $entry;
    }

    return $normalized;
}

function google_calendar_extract_meet_url($event)
{
    if (!empty($event['hangoutLink'])) {
        return (string)$event['hangoutLink'];
    }
    if (!empty($event['conferenceData']['entryPoints']) && is_array($event['conferenceData']['entryPoints'])) {
        foreach ($event['conferenceData']['entryPoints'] as $entryPoint) {
            if (strtolower((string)($entryPoint['entryPointType'] ?? '')) === 'video' && !empty($entryPoint['uri'])) {
                return (string)$entryPoint['uri'];
            }
        }
    }
    return '';
}

function google_calendar_create_event_with_meet($conexion, $adminUserId, array $payload)
{
    $tokenState = google_calendar_ensure_valid_access_token($conexion, $adminUserId);
    if (!$tokenState['ok']) {
        return $tokenState;
    }

    $summary = trim((string)($payload['summary'] ?? $payload['title'] ?? ''));
    $startAt = trim((string)($payload['start_at'] ?? ''));
    $endAt = trim((string)($payload['end_at'] ?? ''));
    if ($summary === '' || $startAt === '' || $endAt === '') {
        return ['ok' => false, 'error' => 'summary, start_at y end_at son obligatorios para crear el evento.'];
    }

    $timezone = trim((string)($payload['timezone'] ?? 'America/Bogota'));
    $calendarId = trim((string)($payload['calendar_id'] ?? 'primary'));
    $conferenceRequestId = bin2hex(random_bytes(12));
    $eventBody = [
        'summary' => $summary,
        'description' => trim((string)($payload['description'] ?? '')),
        'start' => [
            'dateTime' => $startAt,
            'timeZone' => $timezone,
        ],
        'end' => [
            'dateTime' => $endAt,
            'timeZone' => $timezone,
        ],
        'conferenceData' => [
            'createRequest' => [
                'requestId' => $conferenceRequestId,
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ],
        ],
    ];

    $location = trim((string)($payload['location'] ?? ''));
    if ($location !== '') {
        $eventBody['location'] = $location;
    }

    $attendees = google_calendar_normalize_attendees($payload['attendees'] ?? []);
    if (!empty($attendees)) {
        $eventBody['attendees'] = $attendees;
    }

    $config = google_calendar_get_config();
    $eventUrl = $config['calendar_base_url']
        . '/calendars/' . rawurlencode($calendarId)
        . '/events?conferenceDataVersion=1&sendUpdates=all';
    $response = google_calendar_http_request('POST', $eventUrl, [
        'Authorization: Bearer ' . $tokenState['access_token'],
        'Accept: application/json',
        'Content-Type: application/json',
    ], json_encode($eventBody));

    if (!$response['ok'] || !is_array($response['json'])) {
        $errorText = 'No fue posible crear el evento de Google Calendar.';
        if (!empty($response['json']['error']['message'])) {
            $errorText = (string)$response['json']['error']['message'];
        } elseif (!empty($response['error'])) {
            $errorText = (string)$response['error'];
        }
        google_calendar_update_last_error($conexion, $adminUserId, $errorText);
        return ['ok' => false, 'error' => $errorText, 'response' => $response['json']];
    }

    $event = $response['json'];
    google_calendar_update_last_error($conexion, $adminUserId, '');
    return [
        'ok' => true,
        'event_id' => (string)($event['id'] ?? ''),
        'html_link' => (string)($event['htmlLink'] ?? ''),
        'meet_url' => google_calendar_extract_meet_url($event),
        'organizer_email' => (string)($event['organizer']['email'] ?? $tokenState['connection']['google_email'] ?? ''),
        'start' => (array)($event['start'] ?? []),
        'end' => (array)($event['end'] ?? []),
        'attendees' => (array)($event['attendees'] ?? []),
        'raw_event' => $event,
    ];
}