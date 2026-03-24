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

function google_calendar_table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
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

function google_calendar_set_flash($type, $message, array $details = [])
{
    $_SESSION['google_calendar_flash'] = [
        'type' => trim((string)$type),
        'message' => trim((string)$message),
        'details' => $details,
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

function google_calendar_pick_connected_admin_user_id($conexion, $preferredAdminUserId = 0)
{
    $preferredAdminUserId = (int)$preferredAdminUserId;
    if ($preferredAdminUserId > 0) {
        $preferred = google_calendar_get_connection($conexion, $preferredAdminUserId, false);
        if (!empty($preferred['is_connected'])) {
            return $preferredAdminUserId;
        }
    }

    if (!$conexion || !google_calendar_table_exists($conexion, 'admin_google_calendar_connections')) {
        return 0;
    }

    $sql = "SELECT admin_user_id
            FROM admin_google_calendar_connections
            WHERE COALESCE(refresh_token_encrypted, '') <> ''
              AND (disconnected_at IS NULL OR disconnected_at = '0000-00-00 00:00:00')
            ORDER BY COALESCE(updated_at, connected_at) DESC, id DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return 0;
    }

    $row = mysqli_fetch_assoc($res) ?: null;
    return $row ? (int)($row['admin_user_id'] ?? 0) : 0;
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
    $seen = [];
    if (!is_array($attendees)) {
        return $normalized;
    }

    foreach ($attendees as $attendee) {
        if (!is_array($attendee)) {
            continue;
        }
        $email = strtolower(trim((string)($attendee['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if (isset($seen[$email])) {
            continue;
        }
        $seen[$email] = true;
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

function google_calendar_create_event($conexion, $adminUserId, array $payload)
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
    $createMeet = !isset($payload['create_meet']) || !empty($payload['create_meet']);
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
    ];

    if ($createMeet) {
        $conferenceRequestId = bin2hex(random_bytes(12));
        $eventBody['conferenceData'] = [
            'createRequest' => [
                'requestId' => $conferenceRequestId,
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ],
        ];
    }

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
        . '/events?sendUpdates=all';
    if ($createMeet) {
        $eventUrl .= '&conferenceDataVersion=1';
    }
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
        'meet_url' => $createMeet ? google_calendar_extract_meet_url($event) : '',
        'organizer_email' => (string)($event['organizer']['email'] ?? $tokenState['connection']['google_email'] ?? ''),
        'start' => (array)($event['start'] ?? []),
        'end' => (array)($event['end'] ?? []),
        'attendees' => (array)($event['attendees'] ?? []),
        'raw_event' => $event,
    ];
}

function google_calendar_create_event_with_meet($conexion, $adminUserId, array $payload)
{
    $payload['create_meet'] = true;
    return google_calendar_create_event($conexion, $adminUserId, $payload);
}

function google_calendar_delete_event($conexion, $adminUserId, $eventId, array $options = [])
{
    $adminUserId = (int)$adminUserId;
    $eventId = trim((string)$eventId);
    if ($adminUserId <= 0) {
        return ['ok' => false, 'error' => 'No se pudo resolver el organizer_admin_user_id para cancelar el evento.'];
    }
    if ($eventId === '') {
        return ['ok' => false, 'error' => 'google_event_id es obligatorio para cancelar el evento.'];
    }

    $tokenState = google_calendar_ensure_valid_access_token($conexion, $adminUserId);
    if (!$tokenState['ok']) {
        return $tokenState;
    }

    $calendarId = trim((string)($options['calendar_id'] ?? 'primary'));
    if ($calendarId === '') {
        $calendarId = 'primary';
    }

    $config = google_calendar_get_config();
    $eventUrl = $config['calendar_base_url']
        . '/calendars/' . rawurlencode($calendarId)
        . '/events/' . rawurlencode($eventId)
        . '?sendUpdates=all';

    $response = google_calendar_http_request('DELETE', $eventUrl, [
        'Authorization: Bearer ' . $tokenState['access_token'],
        'Accept: application/json',
    ]);

    if (!$response['ok']) {
        $errorText = 'No fue posible cancelar el evento de Google Calendar.';
        if (!empty($response['json']['error']['message'])) {
            $errorText = (string)$response['json']['error']['message'];
        } elseif ((int)($response['status'] ?? 0) === 404) {
            $errorText = 'Google Calendar indicó que el evento ya no existe o no es accesible para esta cuenta.';
        } elseif (!empty($response['error'])) {
            $errorText = (string)$response['error'];
        }
        google_calendar_update_last_error($conexion, $adminUserId, $errorText);
        return [
            'ok' => false,
            'error' => $errorText,
            'status' => (int)($response['status'] ?? 0),
            'response' => $response['json'],
        ];
    }

    google_calendar_update_last_error($conexion, $adminUserId, '');
    return [
        'ok' => true,
        'status' => (int)($response['status'] ?? 0),
        'event_id' => $eventId,
        'calendar_id' => $calendarId,
    ];
}

function google_calendar_event_columns($conexion)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [
        'organizer_admin_user_id' => google_calendar_table_has_column($conexion, 'calendar_events', 'organizer_admin_user_id'),
        'integration_mode' => google_calendar_table_has_column($conexion, 'calendar_events', 'integration_mode'),
        'google_event_id' => google_calendar_table_has_column($conexion, 'calendar_events', 'google_event_id'),
        'google_html_link' => google_calendar_table_has_column($conexion, 'calendar_events', 'google_html_link'),
        'google_meet_url' => google_calendar_table_has_column($conexion, 'calendar_events', 'google_meet_url'),
        'organizer_email' => google_calendar_table_has_column($conexion, 'calendar_events', 'organizer_email'),
    ];

    return $cache;
}

function google_calendar_fetch_latest_confirmed_item_event($conexion, $itemId)
{
    $itemId = (int)$itemId;
    if ($itemId <= 0 || !google_calendar_table_exists($conexion, 'calendar_events')) {
        return null;
    }

    $columns = google_calendar_event_columns($conexion);
    $organizerAdminExpr = $columns['organizer_admin_user_id'] ? 'ce.organizer_admin_user_id' : 'NULL';
    $integrationModeExpr = $columns['integration_mode'] ? 'ce.integration_mode' : "'calendar_plus_meet'";
    $googleEventExpr = $columns['google_event_id'] ? 'ce.google_event_id' : "''";
    $googleHtmlExpr = $columns['google_html_link'] ? 'ce.google_html_link' : "''";
    $googleMeetExpr = $columns['google_meet_url'] ? 'ce.google_meet_url' : "''";
    $organizerEmailExpr = $columns['organizer_email'] ? 'ce.organizer_email' : "''";

    $sql = "SELECT ce.id, ce.request_id, ce.item_id, ce.thread_id, ce.title, ce.description, ce.start_at, ce.end_at, ce.status,
                   {$organizerAdminExpr} AS organizer_admin_user_id,
                   {$integrationModeExpr} AS integration_mode,
                   {$googleEventExpr} AS google_event_id,
                   {$googleHtmlExpr} AS google_html_link,
                   {$googleMeetExpr} AS google_meet_url,
                   {$organizerEmailExpr} AS organizer_email
            FROM calendar_events ce
            WHERE ce.event_type = 'ITEM'
              AND ce.item_id = ?
              AND ce.status = 'confirmed'
            ORDER BY ce.start_at DESC, ce.id DESC
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $itemId);
    $row = null;
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            $row = mysqli_fetch_assoc($res) ?: null;
        }
    }
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function google_calendar_normalize_integration_mode_value($value)
{
    $integrationMode = strtolower(trim((string)$value));
    if (!in_array($integrationMode, ['internal_only', 'calendar_only', 'calendar_plus_meet'], true)) {
        return 'internal_only';
    }
    return $integrationMode;
}

function google_calendar_normalize_thread_type_value($value)
{
    $threadType = strtoupper(trim((string)$value));
    return in_array($threadType, ['ITEM', 'CARE'], true) ? $threadType : '';
}

function google_calendar_insert_cancelled_message($conexion, array $eventRow, $integrationMode, $googleEventId, array $actor = [])
{
    if (!google_calendar_table_exists($conexion, 'inbox_messages')) {
        return [
            'message_id' => 0,
            'message_body' => '',
            'message_payload' => [],
        ];
    }

    $eventId = (int)($eventRow['id'] ?? 0);
    $requestId = (int)($eventRow['request_id'] ?? 0);
    $itemId = (int)($eventRow['item_id'] ?? 0);
    $threadType = google_calendar_normalize_thread_type_value($eventRow['event_type'] ?? '');
    if ($threadType === '' || $requestId <= 0) {
        return [
            'message_id' => 0,
            'message_body' => '',
            'message_payload' => [],
        ];
    }

    $threadId = trim((string)($eventRow['thread_id'] ?? ''));
    if ($threadId === '' && function_exists('inbox_thread_id')) {
        $threadId = (string)inbox_thread_id($threadType, $requestId, $threadType === 'ITEM' ? $itemId : 0);
    }
    if ($threadId === '') {
        $threadId = ($threadType === 'ITEM' && $itemId > 0)
            ? ('ITEM:' . $requestId . ':' . $itemId)
            : ('CARE:' . $requestId);
    }

    $actorRole = strtoupper(trim((string)($actor['role'] ?? 'SYSTEM')));
    if ($actorRole === '') {
        $actorRole = 'SYSTEM';
    }
    $actorUserId = (int)($actor['user_id'] ?? 0);
    $cancelReason = trim((string)($actor['reason'] ?? ''));
    if (strlen($cancelReason) > 255) {
        $cancelReason = substr($cancelReason, 0, 255);
    }

    $payload = [
        'event_id' => (string)$googleEventId,
        'calendar_event_id' => $eventId,
        'start_at' => (string)($eventRow['start_at'] ?? ''),
        'end_at' => (string)($eventRow['end_at'] ?? ''),
        'integration_mode' => (string)$integrationMode,
        'cancelled_by_role' => $actorRole,
        'cancelled_by_user_id' => $actorUserId,
        'reason' => $cancelReason,
    ];
    $body = '[MEETING_CANCELLED] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmtMsg) {
        return [
            'message_id' => 0,
            'message_body' => '',
            'message_payload' => [],
            'error' => 'inbox_message_prepare_failed',
        ];
    }

    $senderUserId = $actorUserId > 0 ? $actorUserId : null;
    mysqli_stmt_bind_param($stmtMsg, 'ssiisis', $threadId, $threadType, $requestId, $itemId, $actorRole, $senderUserId, $body);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        return [
            'message_id' => 0,
            'message_body' => '',
            'message_payload' => [],
            'error' => 'inbox_message_insert_failed: ' . $err,
        ];
    }

    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);

    return [
        'message_id' => $messageId,
        'message_body' => $body,
        'message_payload' => $payload,
    ];
}

function google_calendar_cancel_calendar_event($conexion, array $eventRow, array $actor = [])
{
    $eventId = (int)($eventRow['id'] ?? 0);
    if ($eventId <= 0) {
        return ['ok' => false, 'error' => 'invalid_event_id'];
    }
    if (!google_calendar_table_exists($conexion, 'calendar_events')) {
        return ['ok' => false, 'error' => 'calendar_events_not_available'];
    }

    $status = strtolower(trim((string)($eventRow['status'] ?? 'scheduled')));
    if ($status === 'cancelled') {
        return ['ok' => false, 'error' => 'calendar_event_already_cancelled_or_missing'];
    }

    $eventType = google_calendar_normalize_thread_type_value($eventRow['event_type'] ?? '');
    $itemId = (int)($eventRow['item_id'] ?? 0);
    if ($eventType === 'ITEM' && $itemId > 0 && $status === 'confirmed') {
        return google_calendar_cancel_item_meeting($conexion, $itemId, $actor);
    }

    $googleEventId = trim((string)($eventRow['google_event_id'] ?? ''));
    $integrationMode = google_calendar_normalize_integration_mode_value($eventRow['integration_mode'] ?? '');
    if ($integrationMode === 'internal_only' && $googleEventId !== '') {
        $integrationMode = trim((string)($eventRow['google_meet_url'] ?? '')) !== '' ? 'calendar_plus_meet' : 'calendar_only';
    }
    $googleDeleteResult = [
        'ok' => true,
        'status' => 0,
        'event_id' => $googleEventId,
        'calendar_id' => 'primary',
    ];

    if ($integrationMode !== 'internal_only' && $googleEventId !== '') {
        $organizerAdminUserId = (int)($eventRow['organizer_admin_user_id'] ?? 0);
        if ($organizerAdminUserId <= 0) {
            return ['ok' => false, 'error' => 'missing_organizer_admin_user_id'];
        }
        $googleDeleteResult = google_calendar_delete_event($conexion, $organizerAdminUserId, $googleEventId, [
            'calendar_id' => 'primary',
        ]);
        if (empty($googleDeleteResult['ok'])) {
            return $googleDeleteResult;
        }
    }

    mysqli_begin_transaction($conexion);

    $stmtUpdate = mysqli_prepare(
        $conexion,
        "UPDATE calendar_events
         SET status = 'cancelled', updated_at = NOW()
         WHERE id = ?
           AND COALESCE(status, 'scheduled') <> 'cancelled'
         LIMIT 1"
    );
    if (!$stmtUpdate) {
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => 'calendar_event_cancel_prepare_failed'];
    }
    mysqli_stmt_bind_param($stmtUpdate, 'i', $eventId);
    if (!mysqli_stmt_execute($stmtUpdate)) {
        $err = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => 'calendar_event_cancel_failed: ' . $err];
    }
    $affected = mysqli_stmt_affected_rows($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
    if ($affected <= 0) {
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => 'calendar_event_already_cancelled_or_missing'];
    }

    $messageResult = google_calendar_insert_cancelled_message($conexion, $eventRow, $integrationMode, $googleEventId, $actor);
    if (!empty($messageResult['error'])) {
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => (string)$messageResult['error']];
    }

    mysqli_commit($conexion);

    return [
        'ok' => true,
        'calendar_event_id' => $eventId,
        'google_event_id' => $googleEventId,
        'request_id' => (int)($eventRow['request_id'] ?? 0),
        'item_id' => $itemId,
        'start_at' => (string)($eventRow['start_at'] ?? ''),
        'end_at' => (string)($eventRow['end_at'] ?? ''),
        'integration_mode' => $integrationMode,
        'google_result' => $googleDeleteResult,
        'message_id' => (int)($messageResult['message_id'] ?? 0),
        'message_body' => (string)($messageResult['message_body'] ?? ''),
        'message_payload' => (array)($messageResult['message_payload'] ?? []),
        'cancelled_by_role' => strtoupper(trim((string)($actor['role'] ?? 'SYSTEM'))),
    ];
}

function google_calendar_cancel_item_meeting($conexion, $itemId, array $actor = [])
{
    $itemId = (int)$itemId;
    if ($itemId <= 0) {
        return ['ok' => false, 'error' => 'invalid_item_id'];
    }
    if (!google_calendar_table_exists($conexion, 'calendar_events')) {
        return ['ok' => false, 'error' => 'calendar_events_not_available'];
    }

    $eventRow = google_calendar_fetch_latest_confirmed_item_event($conexion, $itemId);
    if (!$eventRow) {
        return ['ok' => false, 'error' => 'confirmed_meeting_not_found'];
    }

    $integrationMode = trim((string)($eventRow['integration_mode'] ?? 'calendar_plus_meet'));
    if (!in_array($integrationMode, ['internal_only', 'calendar_only', 'calendar_plus_meet'], true)) {
        $integrationMode = 'calendar_plus_meet';
    }

    $organizerAdminUserId = (int)($eventRow['organizer_admin_user_id'] ?? 0);
    $googleEventId = trim((string)($eventRow['google_event_id'] ?? ''));
    $googleDeleteResult = [
        'ok' => true,
        'status' => 0,
        'event_id' => $googleEventId,
        'calendar_id' => 'primary',
    ];

    if ($integrationMode !== 'internal_only') {
        if ($organizerAdminUserId <= 0) {
            return ['ok' => false, 'error' => 'missing_organizer_admin_user_id'];
        }
        if ($googleEventId === '') {
            return ['ok' => false, 'error' => 'missing_google_event_id'];
        }
        $googleDeleteResult = google_calendar_delete_event($conexion, $organizerAdminUserId, $googleEventId, [
            'calendar_id' => 'primary',
        ]);
        if (empty($googleDeleteResult['ok'])) {
            return $googleDeleteResult;
        }
    }

    $actorRole = strtoupper(trim((string)($actor['role'] ?? 'SYSTEM')));
    if ($actorRole === '') {
        $actorRole = 'SYSTEM';
    }
    $actorUserId = (int)($actor['user_id'] ?? 0);
    $cancelReason = trim((string)($actor['reason'] ?? ''));
    if (strlen($cancelReason) > 255) {
        $cancelReason = substr($cancelReason, 0, 255);
    }

    mysqli_begin_transaction($conexion);

    $eventId = (int)($eventRow['id'] ?? 0);
    $stmtUpdate = mysqli_prepare($conexion, "UPDATE calendar_events SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'confirmed' LIMIT 1");
    if (!$stmtUpdate) {
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => 'calendar_event_cancel_prepare_failed'];
    }
    mysqli_stmt_bind_param($stmtUpdate, 'i', $eventId);
    if (!mysqli_stmt_execute($stmtUpdate)) {
        $err = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => 'calendar_event_cancel_failed: ' . $err];
    }
    $affected = mysqli_stmt_affected_rows($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
    if ($affected <= 0) {
        mysqli_rollback($conexion);
        return ['ok' => false, 'error' => 'calendar_event_already_cancelled_or_missing'];
    }

    $messageId = 0;
    $messageBody = '';
    $messagePayload = [];
    if (google_calendar_table_exists($conexion, 'inbox_messages')) {
        $threadId = trim((string)($eventRow['thread_id'] ?? ''));
        if ($threadId === '' && function_exists('inbox_thread_id')) {
            $threadId = (string)inbox_thread_id('ITEM', (int)($eventRow['request_id'] ?? 0), $itemId);
        }
        if ($threadId === '') {
            $threadId = 'ITEM:' . (int)($eventRow['request_id'] ?? 0) . ':' . $itemId;
        }

        $payload = [
            'event_id' => $googleEventId,
            'calendar_event_id' => $eventId,
            'start_at' => (string)($eventRow['start_at'] ?? ''),
            'end_at' => (string)($eventRow['end_at'] ?? ''),
            'integration_mode' => $integrationMode,
            'cancelled_by_role' => $actorRole,
            'cancelled_by_user_id' => $actorUserId,
            'reason' => $cancelReason,
        ];
        $body = '[MEETING_CANCELLED] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $messagePayload = $payload;
        $messageBody = $body;
        $stmtMsg = mysqli_prepare(
            $conexion,
            "INSERT INTO inbox_messages
                (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
             VALUES (?, 'ITEM', ?, ?, ?, ?, ?)"
        );
        if (!$stmtMsg) {
            mysqli_rollback($conexion);
            return ['ok' => false, 'error' => 'inbox_message_prepare_failed'];
        }
        $requestId = (int)($eventRow['request_id'] ?? 0);
        $senderUserId = $actorUserId > 0 ? $actorUserId : null;
        mysqli_stmt_bind_param($stmtMsg, 'siisis', $threadId, $requestId, $itemId, $actorRole, $senderUserId, $body);
        if (!mysqli_stmt_execute($stmtMsg)) {
            $err = mysqli_stmt_error($stmtMsg);
            mysqli_stmt_close($stmtMsg);
            mysqli_rollback($conexion);
            return ['ok' => false, 'error' => 'inbox_message_insert_failed: ' . $err];
        }
        $messageId = (int)mysqli_insert_id($conexion);
        mysqli_stmt_close($stmtMsg);
    }

    mysqli_commit($conexion);

    return [
        'ok' => true,
        'calendar_event_id' => $eventId,
        'google_event_id' => $googleEventId,
        'request_id' => (int)($eventRow['request_id'] ?? 0),
        'item_id' => $itemId,
        'start_at' => (string)($eventRow['start_at'] ?? ''),
        'end_at' => (string)($eventRow['end_at'] ?? ''),
        'integration_mode' => $integrationMode,
        'google_result' => $googleDeleteResult,
        'message_id' => $messageId,
        'message_body' => $messageBody,
        'message_payload' => $messagePayload,
        'cancelled_by_role' => $actorRole,
    ];
}