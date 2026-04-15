<?php
if (!defined('MT_REALTIME_INTERNAL_SECRET')) {
    define('MT_REALTIME_INTERNAL_SECRET', getenv('MT_REALTIME_INTERNAL_SECRET') ?: '');
}
if (!defined('MT_REALTIME_HMAC_SECRET')) {
    define('MT_REALTIME_HMAC_SECRET', getenv('MT_REALTIME_HMAC_SECRET') ?: '');
}
if (!defined('MT_REALTIME_BASE_URL')) {
    $baseUrl = getenv('MT_REALTIME_BASE_URL') ?: 'https://medtravel.com.co';
    $baseUrl = rtrim(trim((string)$baseUrl), '/');
    define('MT_REALTIME_BASE_URL', $baseUrl);
}
if (!defined('MT_REALTIME_SOCKET_PATH')) {
    $socketPath = getenv('MT_REALTIME_SOCKET_PATH') ?: '/realtime/socket.io';
    define('MT_REALTIME_SOCKET_PATH', trim((string)$socketPath));
}

function mt_realtime_log($message)
{
    $text = 'MT_REALTIME ' . trim((string)$message);
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log($text);
        return;
    }
    error_log($text);
}

if (!function_exists('mt_realtime_make_token')) {
    function mt_realtime_make_token($threadId, $userId, $role)
    {
        $secret = defined('MT_REALTIME_HMAC_SECRET') ? MT_REALTIME_HMAC_SECRET : '';
        if ($secret === '') {
            return '';
        }
        $threadId = trim((string)$threadId);
        $role = strtoupper(trim((string)$role));
        $userId = (int)$userId;
        if ($threadId === '' || $userId <= 0 || $role === '') {
            return '';
        }
        $exp = time() + 600;
        $payload = $threadId . '|' . $userId . '|' . $role . '|' . $exp;
        $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payload, $secret);
        return $b64 . '.' . $sig;
    }
}

if (!function_exists('mt_realtime_make_admin_token')) {
    function mt_realtime_make_admin_token($userId, $role = 'ADMIN')
    {
        $secret = defined('MT_REALTIME_HMAC_SECRET') ? MT_REALTIME_HMAC_SECRET : '';
        if ($secret === '') {
            return '';
        }
        $userId = (int)$userId;
        if ($userId <= 0) {
            return '';
        }
        $role = strtoupper(trim((string)$role));
        if ($role !== 'ADMIN') {
            return '';
        }
        $exp = time() + 600;
        $payload = $userId . '|' . $role . '|' . $exp;
        $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payload, $secret);
        return $b64 . '.' . $sig;
    }
}

function mt_realtime_emit_inbox_message($payload)
{
    if (!is_array($payload)) {
        mt_realtime_log('emit_skipped invalid_payload');
        return false;
    }
    $secret = defined('MT_REALTIME_INTERNAL_SECRET') ? MT_REALTIME_INTERNAL_SECRET : '';
    $diagThread  = (string)($payload['thread_id']  ?? '');
    $diagMsgId   = (int)($payload['message_id']     ?? 0);
    if ($secret === '') {
        $diagBase = defined('MT_REALTIME_BASE_URL') ? MT_REALTIME_BASE_URL : '';
        mt_realtime_log('emit_skipped missing_internal_secret thread=' . $diagThread . ' msg=' . $diagMsgId . ' base_url=' . $diagBase);
        return false;
    }
    $baseUrl = defined('MT_REALTIME_BASE_URL') ? MT_REALTIME_BASE_URL : '';
    if ($baseUrl === '') {
        mt_realtime_log('emit_skipped missing_base_url thread=' . $diagThread . ' msg=' . $diagMsgId);
        return false;
    }
    $url = rtrim($baseUrl, '/') . '/realtime/internal/inbox-message';
    $json = json_encode($payload);
    if ($json === false) {
        mt_realtime_log('emit_skipped json_encode_failed');
        return false;
    }

    $headers = [
        'Content-Type: application/json',
        'X-Internal-Secret: ' . $secret,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        $err   = curl_error($ch);
        $errno = curl_errno($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code < 200 || $code >= 300) {
            $body  = is_string($response) ? substr($response, 0, 200) : '';
            mt_realtime_log('emit_failed http=' . $code . ' curl_errno=' . $errno . ' err=' . $err . ' url=' . $url . ' thread=' . $diagThread . ' msg=' . $diagMsgId . ' body=' . $body);
            return false;
        }
        return true;
    }

    $headerLines = implode("\r\n", $headers);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headerLines,
            'content' => $json,
            'timeout' => 3,
            'ignore_errors' => true,
        ],
    ]);
    $result = @file_get_contents($url, false, $context);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('~^HTTP/\\S+\\s+(\\d+)~', $line, $m)) {
                $code = (int)$m[1];
                break;
            }
        }
    }
    if ($result === false || $code < 200 || $code >= 300) {
        $body = is_string($result) ? substr($result, 0, 200) : '';
        mt_realtime_log('emit_failed http=' . $code . ' stream=1 url=' . $url . ' thread=' . $diagThread . ' msg=' . $diagMsgId . ' body=' . $body);
        return false;
    }
    return true;
}
