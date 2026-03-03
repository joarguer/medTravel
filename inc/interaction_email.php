<?php
require_once __DIR__ . '/inbox_utils.php';

if (!function_exists('mt_email_debug_log')) {
    function mt_email_debug_log($line)
    {
        $baseDir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }
        $file = $baseDir . '/email_debug.log';
        $line = date('c') . ' ' . trim((string)$line) . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('mt_email_debug_tail')) {
    function mt_email_debug_tail($lines = 50)
    {
        $lines = (int)$lines;
        if ($lines <= 0) {
            $lines = 50;
        }
        $file = dirname(__DIR__) . '/storage/logs/email_debug.log';
        if (!is_file($file)) {
            return [];
        }
        $contents = file($file, FILE_IGNORE_NEW_LINES);
        if ($contents === false) {
            return [];
        }
        return array_slice($contents, -$lines);
    }
}

// ── Email dedupe helpers (in-file JSON cache, TTL 60 s) ───────────────────────
if (!function_exists('_interaction_email_dedupe_file')) {
    function _interaction_email_dedupe_file()
    {
        $dir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/email_dedupe.json';
    }
}

if (!function_exists('interaction_email_dedupe_check')) {
    /**
     * Returns true if $key was recorded within $ttl seconds.
     * Non-blocking: silently returns false on any I/O or parse error.
     */
    function interaction_email_dedupe_check($key, $ttl = 60)
    {
        $file = _interaction_email_dedupe_file();
        if (!is_file($file)) {
            return false;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return false;
        }
        $map = @json_decode($raw, true);
        if (!is_array($map) || !isset($map[$key])) {
            return false;
        }
        return (time() - (int)$map[$key]) < (int)$ttl;
    }
}

if (!function_exists('interaction_email_dedupe_mark')) {
    /**
     * Record that $key was just sent. Prunes entries older than $pruneAge seconds.
     * Non-blocking: silently ignores write errors.
     */
    function interaction_email_dedupe_mark($key, $pruneAge = 300)
    {
        $file = _interaction_email_dedupe_file();
        $raw  = is_file($file) ? @file_get_contents($file) : '';
        $map  = ($raw !== false && $raw !== '') ? @json_decode($raw, true) : [];
        if (!is_array($map)) {
            $map = [];
        }
        $now = time();
        foreach ($map as $k => $ts) {
            if ($now - (int)$ts >= $pruneAge) {
                unset($map[$k]);
            }
        }
        $map[$key] = $now;
        @file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

if (!function_exists('interaction_email_safe_snippet')) {
    function interaction_email_safe_snippet($text, $maxLen = 120)
    {
        $value = strip_tags((string)$text);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted email]', $value);
        $value = preg_replace('/\+?\d[\d\s().-]{6,}\d/', '[redacted phone]', $value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $maxLen = (int)$maxLen;
        if ($maxLen > 0 && (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > $maxLen) {
            $value = function_exists('mb_substr') ? mb_substr($value, 0, $maxLen) : substr($value, 0, $maxLen);
            $value = rtrim($value) . '…';
        }
        return $value;
    }
}

if (!function_exists('interaction_email_actor_label')) {
    function interaction_email_actor_label($role)
    {
        $role = strtoupper(trim((string)$role));
        if ($role === 'CLIENT') return 'Client';
        if ($role === 'PROVIDER') return 'Provider';
        if ($role === 'PATIENTCARE' || $role === 'ADMIN') return 'Coordination';
        return 'Staff';
    }
}

if (!function_exists('interaction_email_resolve_patientcare_email')) {
    function interaction_email_resolve_patientcare_email($conexion)
    {
        if (!function_exists('loadEmailAccountsFromDB')) {
            return '';
        }
        $accounts = loadEmailAccountsFromDB($conexion);
        if (!is_array($accounts) || empty($accounts['patientcare']) || !is_array($accounts['patientcare'])) {
            return '';
        }
        $email = trim((string)($accounts['patientcare']['reply_to'] ?? ''));
        if ($email === '') {
            $email = trim((string)($accounts['patientcare']['from_email'] ?? ''));
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('interaction_email_fetch_provider_email')) {
    function interaction_email_fetch_provider_email($conexion, $itemId, &$source = null)
    {
        if ($source !== null) {
            $source = '';
        }
        $itemId = (int)$itemId;
        if ($itemId <= 0) {
            return '';
        }
        if (!inbox_table_exists($conexion, 'booking_request_items') || !inbox_table_exists($conexion, 'usuarios')) {
            return '';
        }
        $hasUsersDeleted = inbox_table_has_column($conexion, 'usuarios', 'is_deleted');
        $hasUsersActive = inbox_table_has_column($conexion, 'usuarios', 'activo');

        $sql = "SELECT u.email, bri.provider_id, bri.service_provider_id
                FROM booking_request_items bri
                INNER JOIN usuarios u ON (
                    (bri.provider_id IS NOT NULL AND bri.provider_id > 0 AND u.provider_id = bri.provider_id)
                    OR
                    (bri.service_provider_id IS NOT NULL AND bri.service_provider_id > 0 AND u.service_provider_id = bri.service_provider_id)
                )
                WHERE bri.id = ?";
        if ($hasUsersDeleted) {
            $sql .= " AND u.is_deleted = 0";
        }
        if ($hasUsersActive) {
            $sql .= " AND u.activo = 1";
        }
        $sql .= " AND u.email IS NOT NULL AND u.email <> '' ORDER BY u.id ASC LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'i', $itemId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return '';
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        $email = trim((string)($row['email'] ?? ''));
        if ($source !== null && $email !== '') {
            $providerId = (int)($row['provider_id'] ?? 0);
            $serviceProviderId = (int)($row['service_provider_id'] ?? 0);
            if ($providerId > 0) {
                $source = 'usuarios.provider_id via booking_request_items.provider_id';
            } elseif ($serviceProviderId > 0) {
                $source = 'usuarios.service_provider_id via booking_request_items.service_provider_id';
            } else {
                $source = 'usuarios.email';
            }
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('interaction_email_fetch_client_email')) {
    function interaction_email_fetch_client_email($conexion, $requestId, &$source = null)
    {
        if ($source !== null) {
            $source = '';
        }
        $requestId = (int)$requestId;
        if ($requestId <= 0 || !inbox_table_exists($conexion, 'booking_requests')) {
            return '';
        }
        $hasEmail = inbox_table_has_column($conexion, 'booking_requests', 'email');
        $hasClientUserId = inbox_table_has_column($conexion, 'booking_requests', 'client_user_id');
        $select = $hasEmail ? 'email' : "'' AS email";
        $select .= $hasClientUserId ? ', client_user_id' : ', 0 AS client_user_id';
        $stmt = mysqli_prepare($conexion, "SELECT {$select} FROM booking_requests WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'i', $requestId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return '';
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return '';
        }
        $email = trim((string)($row['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($source !== null) {
                $source = 'booking_requests.email';
            }
            return $email;
        }
        $clientUserId = (int)($row['client_user_id'] ?? 0);
        if ($clientUserId <= 0 || !inbox_table_exists($conexion, 'usuarios')) {
            return '';
        }
        $userIdCol = inbox_table_has_column($conexion, 'usuarios', 'id') ? 'id' : (inbox_table_has_column($conexion, 'usuarios', 'id_usuario') ? 'id_usuario' : '');
        if ($userIdCol === '') {
            return '';
        }
        $stmtUser = mysqli_prepare($conexion, "SELECT email FROM usuarios WHERE {$userIdCol} = ? LIMIT 1");
        if (!$stmtUser) {
            return '';
        }
        mysqli_stmt_bind_param($stmtUser, 'i', $clientUserId);
        if (!mysqli_stmt_execute($stmtUser)) {
            mysqli_stmt_close($stmtUser);
            return '';
        }
        $resUser = mysqli_stmt_get_result($stmtUser);
        $rowUser = $resUser ? mysqli_fetch_assoc($resUser) : null;
        mysqli_stmt_close($stmtUser);
        $email = trim((string)($rowUser['email'] ?? ''));
        if ($source !== null && $email !== '') {
            $source = 'usuarios.' . $userIdCol . ' via booking_requests.client_user_id';
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('interaction_email_request_meta')) {
    function interaction_email_request_meta($conexion, $threadType, $requestId, $itemId = 0)
    {
        $threadType = strtoupper(trim((string)$threadType));
        $requestId = (int)$requestId;
        $itemId = (int)$itemId;
        $meta = [
            'thread_type' => $threadType,
            'request_id' => $requestId,
            'item_id' => $itemId,
            'title' => $requestId > 0 ? ('Request #' . $requestId) : 'Request',
            'subtitle' => '',
        ];

        if (!inbox_table_exists($conexion, 'booking_requests')) {
            return $meta;
        }

        $hasRequestsSoftDelete = inbox_table_has_column($conexion, 'booking_requests', 'is_deleted');
        if ($threadType === 'ITEM' && $itemId > 0 && inbox_table_exists($conexion, 'booking_request_items')) {
            $hasItemsSoftDelete = inbox_table_has_column($conexion, 'booking_request_items', 'is_deleted');
            $sql = "SELECT
                        bri.booking_request_id AS request_id,
                        COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                        br.destination
                    FROM booking_request_items bri
                    INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                    LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                    LEFT JOIN service_catalog sc ON sc.id = o.service_id
                    LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                    WHERE bri.id = ?";
            if ($hasItemsSoftDelete) {
                $sql .= " AND bri.is_deleted = 0";
            }
            if ($hasRequestsSoftDelete) {
                $sql .= " AND br.is_deleted = 0";
            }
            $sql .= " LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $itemId);
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    $row = $res ? mysqli_fetch_assoc($res) : null;
                    if ($row) {
                        $requestId = (int)($row['request_id'] ?? $requestId);
                        $itemName = trim((string)($row['item_name'] ?? ''));
                        if ($itemName === '') {
                            $itemName = 'Item #' . $itemId;
                        }
                        $meta['title'] = $itemName . ' - Request #' . $requestId;
                        $meta['subtitle'] = trim((string)($row['destination'] ?? ''));
                        $meta['request_id'] = $requestId;
                    }
                }
                mysqli_stmt_close($stmt);
            }
            return $meta;
        }

        if ($requestId > 0) {
            $sql = "SELECT destination FROM booking_requests WHERE id = ?";
            if ($hasRequestsSoftDelete) {
                $sql .= " AND is_deleted = 0";
            }
            $sql .= " LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $requestId);
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    $row = $res ? mysqli_fetch_assoc($res) : null;
                    if ($row) {
                        $meta['title'] = 'General - Request #' . $requestId;
                        $meta['subtitle'] = trim((string)($row['destination'] ?? ''));
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }

        return $meta;
    }
}

if (!function_exists('send_interaction_email')) {
    function send_interaction_email($to, $subject, $contentHtml, $textBody, $meta = [], $conexion = null)
    {
        $event = strtoupper(trim((string)($meta['event'] ?? $meta['event_type'] ?? 'interaction')));
        mt_email_debug_log('CALLED event=' . $event);
        mt_email_debug_log('event=' . $event . ' recipient=' . (string)$to);
        mt_email_debug_log('event=' . $event . ' subject=' . (string)$subject);
        if (!function_exists('sendEmail')) {
            mt_email_debug_log('event=' . $event . ' error=sendEmail_unavailable');
            return ['success' => false, 'error' => 'sendEmail_unavailable'];
        }

        $textBody = trim((string)$textBody);
        if ($textBody === '') {
            $textBody = trim(html_entity_decode(strip_tags((string)$contentHtml), ENT_QUOTES, 'UTF-8'));
        }
        if ($textBody === '') {
            $textBody = (string)$subject;
        }

        // ── Lightweight dedupe (60 s TTL) ────────────────────────────────────
        // Key covers recipient + subject (contains type+requestId) + content preview
        $dedupeKey = sha1((string)$to . '||' . (string)$subject . '||' . substr((string)$textBody, 0, 200));
        if (interaction_email_dedupe_check($dedupeKey, 60)) {
            mt_email_debug_log('event=' . $event . ' DEDUPED key=' . $dedupeKey);
            return ['success' => false, 'error' => 'deduped', 'deduped' => true];
        }

        $preheader   = (string)($meta['preheader'] ?? $subject);
        $cta         = isset($meta['cta']) ? $meta['cta'] : null;
        $footerNote  = (string)($meta['footer_note'] ?? '');
        $senderLabel = trim((string)($meta['sender_label'] ?? ''));
        $htmlBody    = function_exists('renderMedTravelEmail')
            ? renderMedTravelEmail(
                $subject,
                $preheader,
                $contentHtml,
                $footerNote !== '' ? $footerNote : null,
                $cta,
                $senderLabel !== '' ? $senderLabel : 'MedTravel Patient Care'
              )
            : $contentHtml;

        try {
            $result = sendEmail(
                $to,
                $subject,
                $htmlBody,
                'patientcare',
                [
                    'alt_body' => $textBody,
                    'from_email' => 'patientcare@medtravel.com.co',
                    'from_name' => 'MedTravel Patient Care',
                    'reply_to' => 'patientcare@medtravel.com.co',
                ],
                $conexion
            );
        } catch (Throwable $e) {
            mt_email_debug_log('event=' . $event . ' exception=' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Mark dedupe only on success so hard failures remain retryable
        if (is_array($result) && !empty($result['success'])) {
            interaction_email_dedupe_mark($dedupeKey);
        }

        mt_email_debug_log('event=' . $event . ' result=' . json_encode($result));
        if (is_array($result) && empty($result['success'])) {
            mt_email_debug_log('event=' . $event . ' send_failed=' . ($result['error'] ?? 'unknown'));
        }
        return $result;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Notification helpers — aligned with booking email style (renderMedTravelEmail)
// Subject pattern : "MedTravel – {Title} (Request #{id})"
// CTA button      : background:#0b4ea2  (same as booking)
// Footer          : rendered by renderMedTravelEmail — NOT duplicated here
// Language        : English (same as booking emails)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('_interaction_inbox_url')) {
    /** Build absolute inbox URL for client or admin portal. */
    function _interaction_inbox_url($portal, $requestId, $itemId = 0, $threadType = 'CARE')
    {
        $base = 'https://medtravel.com.co';
        $requestId = (int)$requestId;
        $itemId    = (int)$itemId;
        $threadType = strtoupper(trim((string)$threadType));
        if ($portal === 'client') {
            $url = $base . '/client/app_inbox.php?request_id=' . $requestId;
            if ($threadType === 'ITEM' && $itemId > 0) {
                $url .= '&thread_type=ITEM&item_id=' . $itemId;
            }
            return $url;
        }
        // admin / provider portal
        $url = $base . '/admin/app_inbox.php?request_id=' . $requestId;
        if ($threadType === 'ITEM' && $itemId > 0) {
            $url .= '&thread_type=ITEM&item_id=' . $itemId;
        } else {
            $url .= '&thread_type=CARE';
        }
        return $url;
    }
}

// ───────────────────────────────────────────────────────────────────────────── 
// Structured-token → human-readable summary mapper
// Converts inbox tokens (quick replies + structured prefixes) into safe,
// clear sentences suitable for email bodies. Returns '' when unrecognised.
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('interaction_email_map_token')) {
    /**
     * Convert a raw inbox message body (token or structured JSON) to a
     * human-readable summary sentence safe for email.
     *
     * @param  string $body      Raw message body (may be a token, [PREFIX] JSON, or free text).
     * @param  string $audience  'client' | 'provider'  — influences wording.
     * @return string            Human text, or '' if body should be shown as-is.
     */
    function interaction_email_map_token($body, $audience = 'client')
    {
        // ── 1. Normalise: trim + collapse whitespace ─────────────────────────
        $raw  = (string)$body;
        $text = preg_replace('/\s+/', ' ', trim($raw));
        if ($text === '') {
            return '';
        }
        $forClient = strtolower((string)$audience) !== 'provider';

        // ── 2. Strip wrapping single/double quotes ───────────────────────────
        if (strlen($text) >= 2) {
            $first = $text[0]; $last = $text[strlen($text) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $text = substr($text, 1, -1);
            }
        }

        // ── 3. Strip non-structured decorative tags [REPLY] [ACTION] [INFO] ─
        // Structured tags ([REQUEST_INFO] etc.) are preserved for stage 7
        static $structPfx = ['[REQUEST_INFO]', '[PROPOSE_QUOTE]', '[PROPOSAL_RESPONSE]'];
        $hasStructured = false;
        foreach ($structPfx as $sp) {
            if (strncmp($text, $sp, strlen($sp)) === 0) { $hasStructured = true; break; }
        }
        if (!$hasStructured) {
            $text = preg_replace('/^\[[A-Z_]{1,20}\]\s*/i', '', $text);
        }
        $text = trim($text);

        // ── 4. Build uppercase canonical key (non-alnum-underscore → _) ─────
        $canonicalKey = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $text));
        $canonicalKey = trim(preg_replace('/_+/', '_', $canonicalKey), '_');

        // ── 5. Human-variant alias map → canonical key ───────────────────────
        // Covers both canonical tokens and their UI display label equivalents
        $aliasMap = [
            'DATES_AVAILABLE'         => 'DATES_AVAILABLE',
            'REQUEST_AVAILABLE'       => 'DATES_AVAILABLE',
            'AVAILABLE'               => 'DATES_AVAILABLE',
            'DATES_NOT_AVAILABLE'     => 'DATES_NOT_AVAILABLE',
            'NOT_AVAILABLE'           => 'DATES_NOT_AVAILABLE',
            'REQUEST_MEDICAL_HISTORY' => 'REQUEST_MEDICAL_HISTORY',
            'REQUEST_HISTORY'         => 'REQUEST_MEDICAL_HISTORY',
            'HISTORY'                 => 'REQUEST_MEDICAL_HISTORY',
            'REQUEST_LABS'            => 'REQUEST_LABS',
            'LABS'                    => 'REQUEST_LABS',
            'REQUEST_IMAGING'         => 'REQUEST_IMAGING',
            'IMAGING'                 => 'REQUEST_IMAGING',
            'REQUEST_PHOTOS'          => 'REQUEST_PHOTOS',
            'PHOTOS'                  => 'REQUEST_PHOTOS',
            'FINAL_APPROVED'          => 'FINAL_APPROVED',
            'APPROVED'                => 'FINAL_APPROVED',
            'ELIGIBLE'                => 'FINAL_APPROVED',
            'FINAL_NOT_ELIGIBLE'      => 'FINAL_NOT_ELIGIBLE',
            'NOT_ELIGIBLE'            => 'FINAL_NOT_ELIGIBLE',
        ];
        $lookupKey = $aliasMap[$canonicalKey] ?? $canonicalKey;

        // ── 6. Quick-reply table ─────────────────────────────────────────────
        $quickMap = [
            'DATES_AVAILABLE'         => [
                'client'   => 'Your provider confirmed availability for your requested dates.',
                'provider' => "You confirmed availability for the patient's requested dates.",
            ],
            'DATES_NOT_AVAILABLE'     => [
                'client'   => 'Your provider is not available for the requested dates. Please check your portal for alternatives.',
                'provider' => "You indicated unavailability for the patient's requested dates.",
            ],
            'REQUEST_MEDICAL_HISTORY' => [
                'client'   => 'Your care team has requested your medical history. Please upload it through your portal.',
                'provider' => "A request for the patient's medical history was sent.",
            ],
            'REQUEST_LABS'            => [
                'client'   => 'Your care team has requested your recent lab results. Please upload them through your portal.',
                'provider' => "A request for the patient's lab results was sent.",
            ],
            'REQUEST_IMAGING'         => [
                'client'   => 'Your care team has requested imaging studies (X-ray, MRI, CT scan, etc.). Please upload them through your portal.',
                'provider' => "A request for the patient's imaging studies was sent.",
            ],
            'REQUEST_PHOTOS'          => [
                'client'   => 'Your care team has requested clinical photos. Please upload them through your portal.',
                'provider' => "A request for the patient's clinical photos was sent.",
            ],
            'FINAL_APPROVED'          => [
                'client'   => "Great news \xe2\x80\x94 your provider has reviewed your case and given approval. Your coordinator will follow up with the next steps shortly.",
                'provider' => 'You approved this case. The coordination team will communicate next steps to the patient.',
            ],
            'FINAL_NOT_ELIGIBLE'      => [
                'client'   => 'After reviewing your case, the provider has determined that this service is not the right fit at this time. Your MedTravel coordinator will be in touch to explore other options.',
                'provider' => 'You marked this case as not eligible. The coordination team has been notified.',
            ],
        ];
        if (isset($quickMap[$lookupKey])) {
            return $forClient ? $quickMap[$lookupKey]['client'] : $quickMap[$lookupKey]['provider'];
        }

        // ── 7. Structured prefixes → parse JSON payload ────────────────────
        if (strncmp($text, '[REQUEST_INFO]', 14) === 0) {
            $json    = trim(substr($text, 14));
            $payload = ($json !== '') ? @json_decode($json, true) : null;
            if (!is_array($payload)) {
                // JSON parse failed: generic human sentence
                return $forClient
                    ? 'Your care team has requested additional information. Please check your portal and upload any requested documents.'
                    : 'An additional-information request was sent to the patient.';
            }
            $types = !empty($payload['required_types'])
                ? implode(', ', array_map('ucfirst', (array)$payload['required_types']))
                : '';
            $note  = trim((string)($payload['note'] ?? ''));
            if ($forClient) {
                $msg = 'Your care team has requested additional information';
                $msg .= $types !== '' ? ' (' . $types . ')' : '';
                $msg .= $note !== '' ? ': ' . interaction_email_safe_snippet($note, 80) : '.';
                return $msg . ' Please upload the requested documents through your portal.';
            }
            return 'An additional-information request was sent to the patient'
                . ($types !== '' ? ' for: ' . $types : '') . '.';
        }

        if (strncmp($text, '[PROPOSE_QUOTE]', 15) === 0) {
            $json    = trim(substr($text, 15));
            $payload = ($json !== '') ? @json_decode($json, true) : null;
            if (!is_array($payload)) {
                return $forClient
                    ? 'Your provider has submitted an updated quote. Please review it and respond at your earliest convenience.'
                    : 'A revised quote was proposed to the patient.';
            }
            $amount   = trim((string)($payload['amount']   ?? ''));
            $currency = strtoupper(trim((string)($payload['currency'] ?? 'USD')));
            $notes    = trim((string)($payload['notes']    ?? ''));
            $priceStr = $amount !== '' ? $amount . ' ' . $currency : '';
            if ($forClient) {
                $msg  = 'Your provider has submitted an updated quote';
                $msg .= $priceStr !== '' ? ' of ' . $priceStr : '';
                $msg .= '. Please review it and respond at your earliest convenience.';
                return $msg;
            }
            return 'A quote adjustment of ' . ($priceStr ?: 'a revised amount')
                . ' was proposed to the patient'
                . ($notes !== '' ? ': ' . interaction_email_safe_snippet($notes, 80) : '') . '.';
        }

        if (strncmp($text, '[PROPOSAL_RESPONSE]', 19) === 0) {
            $json    = trim(substr($text, 19));
            $payload = ($json !== '') ? @json_decode($json, true) : null;
            if (!is_array($payload)) {
                return $forClient
                    ? 'Your response has been received. Your coordinator will follow up shortly.'
                    : 'A proposal response was received from the patient.';
            }
            $action  = strtoupper(trim((string)($payload['action_type'] ?? '')));
            $pnotes  = trim((string)($payload['notes'] ?? ''));
            $responseMap = [
                'ACCEPT_PROPOSAL'    => [
                    'client'   => "You accepted the provider's proposal. Your coordinator will follow up with the next steps.",
                    'provider' => 'The patient accepted your proposal.',
                ],
                'REQUEST_CHANGES'    => [
                    'client'   => "You requested changes to the provider's proposal. Your coordinator has been notified.",
                    'provider' => 'The patient requested changes to your proposal.',
                ],
                'REJECT_PROPOSAL'    => [
                    'client'   => "You declined the provider's proposal. Your coordinator will be in touch to explore alternatives.",
                    'provider' => 'The patient declined your proposal.',
                ],
                'DOCS_NOT_AVAILABLE' => [
                    'client'   => 'You indicated the requested documents are not available at this time. Your coordinator has been notified.',
                    'provider' => 'The patient indicated the requested documents are not currently available.',
                ],
            ];
            if (isset($responseMap[$action])) {
                $base = $forClient ? $responseMap[$action]['client'] : $responseMap[$action]['provider'];
                return $base . ($pnotes !== '' ? ' Note: ' . interaction_email_safe_snippet($pnotes, 80) : '');
            }
            // Unknown action_type — graceful generic
            return $forClient
                ? 'Your response has been received. Your coordinator will follow up shortly.'
                : 'A proposal response was received from the patient.';
        }

        // ── 8. Free text — sanitise and return; NEVER expose raw tokens ────
        return interaction_email_safe_snippet($text, 140);
    }
}

// ── Helper: resolve canonical token key (steps 1-5 of map_token, no lookup) ─
if (!function_exists('interaction_email_resolve_lookup_key')) {
    /**
     * Normalise $body by the same pipeline as interaction_email_map_token() steps 1-5
     * and return the resolved $lookupKey. Used for subject-framing decisions.
     */
    function interaction_email_resolve_lookup_key($body)
    {
        $text = preg_replace('/\s+/', ' ', trim((string)$body));
        if ($text === '') { return ''; }
        if (strlen($text) >= 2) {
            $f = $text[0]; $l = $text[strlen($text) - 1];
            if (($f === '"' && $l === '"') || ($f === "'" && $l === "'")) {
                $text = substr($text, 1, -1);
            }
        }
        $structPfx = ['[REQUEST_INFO]', '[PROPOSE_QUOTE]', '[PROPOSAL_RESPONSE]'];
        $hasStructured = false;
        foreach ($structPfx as $sp) {
            if (strncmp($text, $sp, strlen($sp)) === 0) { $hasStructured = true; break; }
        }
        if (!$hasStructured) {
            $text = trim(preg_replace('/^\[[A-Z_]{1,20}\]\s*/i', '', $text));
        }
        $canonicalKey = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $text));
        $canonicalKey = trim(preg_replace('/_+/', '_', $canonicalKey), '_');
        $aliasMap = [
            'DATES_AVAILABLE'         => 'DATES_AVAILABLE',
            'REQUEST_AVAILABLE'       => 'DATES_AVAILABLE',
            'AVAILABLE'               => 'DATES_AVAILABLE',
            'DATES_NOT_AVAILABLE'     => 'DATES_NOT_AVAILABLE',
            'NOT_AVAILABLE'           => 'DATES_NOT_AVAILABLE',
            'REQUEST_MEDICAL_HISTORY' => 'REQUEST_MEDICAL_HISTORY',
            'REQUEST_HISTORY'         => 'REQUEST_MEDICAL_HISTORY',
            'HISTORY'                 => 'REQUEST_MEDICAL_HISTORY',
            'REQUEST_LABS'            => 'REQUEST_LABS',
            'LABS'                    => 'REQUEST_LABS',
            'REQUEST_IMAGING'         => 'REQUEST_IMAGING',
            'IMAGING'                 => 'REQUEST_IMAGING',
            'REQUEST_PHOTOS'          => 'REQUEST_PHOTOS',
            'PHOTOS'                  => 'REQUEST_PHOTOS',
            'FINAL_APPROVED'          => 'FINAL_APPROVED',
            'APPROVED'                => 'FINAL_APPROVED',
            'ELIGIBLE'                => 'FINAL_APPROVED',
            'FINAL_NOT_ELIGIBLE'      => 'FINAL_NOT_ELIGIBLE',
            'NOT_ELIGIBLE'            => 'FINAL_NOT_ELIGIBLE',
        ];
        return $aliasMap[$canonicalKey] ?? $canonicalKey;
    }
}

// ── 1. New message → Client ──────────────────────────────────────────────────
if (!function_exists('notify_new_message_to_client')) {
    /**
     * Notify the client that a provider or coordinator has sent a new message.
     *
     * Subject framing (auto-detected from $snippet token):
     *   REQUEST_* tokens ([REQUEST_INFO], REQUEST_PHOTOS, REQUEST_LABS, …)
     *       → "MedTravel – Action required on your case (Request #N)"
     *   DATES_AVAILABLE / actor messages
     *       → "MedTravel – A message from your provider (Request #N)"
     *   Free text / coordinator message
     *       → "MedTravel – A message from your coordinator (Request #N)"
     *
     * @example  REQUEST_PHOTOS  (senderRole = PATIENTCARE)
     *   subject : "MedTravel – Action required on your case (Request #42)"
     *   body    : "Your care team has requested clinical photos. Please upload them through your portal."
     *
     * @example  DATES_AVAILABLE  (senderRole = PROVIDER)
     *   subject : "MedTravel – A message from your provider (Request #42)"
     *   body    : "Your provider confirmed availability for your requested dates."
     *
     * @example  Free text  (senderRole = ADMIN)
     *   subject : "MedTravel – A message from your coordinator (Request #42)"
     *   body    : sanitised snippet (≤140 chars), shown as italic quote block
     *
     * @param  object $conexion   Active MySQLi connection.
     * @param  int    $requestId  booking_requests.id
     * @param  int    $itemId     booking_request_items.id (0 for CARE thread)
     * @param  string $threadType 'ITEM' | 'CARE'
     * @param  string $senderRole 'PROVIDER' | 'ADMIN' | 'PATIENTCARE'
     * @param  string $snippet    Raw message body preview (will be sanitised).
     * @return array              Result from send_interaction_email().
     */
    function notify_new_message_to_client($conexion, $requestId, $itemId, $threadType, $senderRole, $snippet = '', $resolvedEmail = '', $emailSource = '')
    {
        $requestId  = (int)$requestId;
        $itemId     = (int)$itemId;
        $threadType = strtoupper(trim((string)$threadType));
        $to         = trim((string)$resolvedEmail);
        $source     = (string)$emailSource;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $to = interaction_email_fetch_client_email($conexion, $requestId, $source);
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'client_email_not_found'];
        }

        $meta        = interaction_email_request_meta($conexion, $threadType, $requestId, $itemId);
        $safeTitle   = htmlspecialchars((string)$meta['title'], ENT_QUOTES, 'UTF-8');
        $actorLabel  = interaction_email_actor_label($senderRole);
        $ctaUrl      = _interaction_inbox_url('client', $requestId, $itemId, $threadType);

        $roleUpper = strtoupper(trim((string)$senderRole));

        // Detect REQUEST_* token type to choose action-oriented framing
        $resolvedTokenKey = interaction_email_resolve_lookup_key($snippet);
        $isActionRequired = (strncmp(ltrim((string)$snippet), '[REQUEST_INFO]', 14) === 0)
            || (strncmp($resolvedTokenKey, 'REQUEST_', 8) === 0);

        // Map the snippet to a human summary; fall back to sanitised preview
        $mapped      = interaction_email_map_token($snippet, 'client');
        $safeSnippet = $mapped !== '' ? $mapped : interaction_email_safe_snippet($snippet, 140);
        $isMapped    = ($mapped !== '');   // true → plain block; false → italic quote block

        // Subject + body framing: action-required vs. actor-message
        if ($isActionRequired) {
            $subject       = "Case #{$requestId}: your care team needs something from you";
            $preamble      = 'Your care team has a request on your case.';
            $preheaderText = "Your care team is waiting on an item for case #{$requestId} \xe2\x80\x94 log in to see what's needed.";
            $fromLine      = 'Your MedTravel Care Team';
        } else {
            $actorPhrasing = ($roleUpper === 'PROVIDER') ? 'your provider' : 'your coordinator';
            $subject       = ($roleUpper === 'PROVIDER')
                ? "Update on case #{$requestId} from your provider"
                : "Case #{$requestId}: message from your MedTravel coordinator";
            $preamble      = ucfirst($actorPhrasing) . ' left you a message on your case.';
            $preheaderText = ucfirst($actorPhrasing) . " left you a message on case #{$requestId}.";
            $fromLine      = htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8');
        }

        // HTML content (inner block passed to renderMedTravelEmail)
        $contentHtml =
            '<p>' . htmlspecialchars($preamble, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="margin:0 0 6px 0;"><strong>Case:</strong> ' . $safeTitle . '</p>'
            . '<p style="margin:0 0 16px 0;"><strong>From:</strong> ' . $fromLine . '</p>'
            . ($safeSnippet !== ''
                ? ($isMapped
                    ? '<p style="background:#f3f7fc; border-left:3px solid #0b4ea2; padding:10px 14px; margin:0 0 16px 0; color:#334155;">'
                       . htmlspecialchars($safeSnippet, ENT_QUOTES, 'UTF-8') . '</p>'
                    : '<p style="background:#f3f7fc; border-left:3px solid #0b4ea2; padding:10px 14px; margin:0 0 16px 0; color:#334155; font-style:italic;">'
                       . '&ldquo;' . htmlspecialchars($safeSnippet, ENT_QUOTES, 'UTF-8') . '&rdquo;</p>'
                  )
                : '')
            . '<p>Log in to read and reply \xe2\x80\x94 your conversation stays safe and on track within MedTravel.</p>';

        // Plain-text alternative
        $textBody = $preamble . "\n\n"
            . "Case: {$meta['title']}\n"
            . "From: {$fromLine}\n"
            . ($safeSnippet !== '' ? "\n" . ($isMapped ? $safeSnippet : '"' . $safeSnippet . '"') . "\n" : '')
            . "\nLog in to reply:\n{$ctaUrl}";

        return send_interaction_email(
            $to,
            $subject,
            $contentHtml,
            $textBody,
            [
                'preheader'   => $preheaderText,
                'cta'         => ['text' => 'Open in MedTravel', 'url' => $ctaUrl],
                'footer_note' => 'Your conversation is private and secure within MedTravel.',
            ],
            $conexion
        );
    }
}

// ── 2. New message → Provider ────────────────────────────────────────────────
if (!function_exists('notify_new_message_to_provider')) {
    /**
     * Notify the provider that the client (or coordinator) has sent a new message.
     *
     * @param  object $conexion   Active MySQLi connection.
     * @param  int    $requestId  booking_requests.id
     * @param  int    $itemId     booking_request_items.id
     * @param  string $threadType 'ITEM' | 'CARE'
     * @param  string $senderRole 'CLIENT' | 'ADMIN' | 'PATIENTCARE'
     * @param  string $snippet    Raw message body preview (will be sanitised).
     * @return array              Result from send_interaction_email().
     */
    function notify_new_message_to_provider($conexion, $requestId, $itemId, $threadType, $senderRole, $snippet = '', $resolvedEmail = '', $emailSource = '')
    {
        $requestId  = (int)$requestId;
        $itemId     = (int)$itemId;
        $threadType = strtoupper(trim((string)$threadType));
        $to         = trim((string)$resolvedEmail);
        $source     = (string)$emailSource;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $to = interaction_email_fetch_provider_email($conexion, $itemId, $source);
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'provider_email_not_found'];
        }

        $meta        = interaction_email_request_meta($conexion, $threadType, $requestId, $itemId);
        $safeTitle   = htmlspecialchars((string)$meta['title'], ENT_QUOTES, 'UTF-8');
        $actorLabel  = interaction_email_actor_label($senderRole);
        $ctaUrl      = _interaction_inbox_url('admin', $requestId, $itemId, $threadType);

        // Actor-aware subject
        $roleUpper     = strtoupper(trim((string)$senderRole));
        $actorPhrasing = ($roleUpper === 'CLIENT') ? 'your patient' : 'the coordination team';
        $subject = "Case #{$requestId}: " . ucfirst($actorPhrasing) . ' sent you a message';

        // Try to map the snippet to a human summary; fall back to sanitised preview
        $mapped      = interaction_email_map_token($snippet, 'provider');
        $safeSnippet = $mapped !== '' ? $mapped : interaction_email_safe_snippet($snippet, 140);
        $isQuote     = $mapped !== '';

        $contentHtml =
            '<p>' . htmlspecialchars(ucfirst($actorPhrasing), ENT_QUOTES, 'UTF-8') . ' has sent a message on one of your active cases.</p>'
            . '<p style="margin:0 0 6px 0;"><strong>Case:</strong> ' . $safeTitle . '</p>'
            . '<p style="margin:0 0 16px 0;"><strong>From:</strong> ' . htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8') . '</p>'
            . ($safeSnippet !== ''
                ? ($isQuote
                    ? '<p style="background:#f3f7fc; border-left:3px solid #0b4ea2; padding:10px 14px; margin:0 0 16px 0; color:#334155;">'
                       . htmlspecialchars($safeSnippet, ENT_QUOTES, 'UTF-8') . '</p>'
                    : '<p style="background:#f3f7fc; border-left:3px solid #0b4ea2; padding:10px 14px; margin:0 0 16px 0; color:#334155; font-style:italic;">'
                       . '&ldquo;' . htmlspecialchars($safeSnippet, ENT_QUOTES, 'UTF-8') . '&rdquo;</p>'
                  )
                : '')
            . '<p>Log in to read and reply at your earliest convenience.</p>';

        $textBody = ucfirst($actorPhrasing) . " has sent a message on one of your active cases.\n\n"
            . "Case: {$meta['title']}\n"
            . "From: {$actorLabel}\n"
            . ($safeSnippet !== '' ? "\n" . ($isQuote ? $safeSnippet : "\"" . $safeSnippet . "\"") . "\n" : '')
            . "\nLog in to reply:\n{$ctaUrl}";

        return send_interaction_email(
            $to,
            $subject,
            $contentHtml,
            $textBody,
            [
                'preheader'    => ucfirst($actorPhrasing) . " left a message on case #{$requestId} \xe2\x80\x94 see what they said.",
                'cta'          => ['text' => 'Open in MedTravel', 'url' => $ctaUrl],
                'footer_note'  => 'Please reply through your portal so we can keep your case on track.',
                'sender_label' => 'MedTravel Coordination Team',
            ],
            $conexion
        );
    }
}

// ── 3. Medical document uploaded → Provider ──────────────────────────────────
if (!function_exists('notify_document_uploaded_to_provider')) {
    /**
     * Notify the provider that the client has uploaded a new medical document.
     *
     * @param  object $conexion  Active MySQLi connection.
     * @param  int    $requestId booking_requests.id
     * @param  int    $itemId    booking_request_items.id
     * @param  array  $doc       Document row: original_filename, document_type,
     *                           uploaded_at, mime_type (all optional but helpful).
     * @return array             Result from send_interaction_email().
     */
    function notify_document_uploaded_to_provider($conexion, $requestId, $itemId, array $doc = [])
    {
        $requestId = (int)$requestId;
        $itemId    = (int)$itemId;
        $to        = interaction_email_fetch_provider_email($conexion, $itemId);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'provider_email_not_found'];
        }

        $meta        = interaction_email_request_meta($conexion, 'ITEM', $requestId, $itemId);
        $safeTitle   = htmlspecialchars((string)$meta['title'], ENT_QUOTES, 'UTF-8');
        $ctaUrl      = _interaction_inbox_url('admin', $requestId, $itemId, 'ITEM');

        // Document details — graceful fallbacks
        $docFilename = htmlspecialchars(
            trim((string)($doc['original_filename'] ?? $doc['filename'] ?? ('Document #' . ($doc['id'] ?? '?')))),
            ENT_QUOTES, 'UTF-8'
        );
        $docTypeRaw  = strtolower(trim((string)($doc['document_type'] ?? 'other')));
        $typeLabels  = ['labs' => 'Labs', 'imaging' => 'Imaging', 'photos' => 'Photos',
                        'medical_history' => 'Medical History', 'other' => 'Other'];
        $docTypeLabel = htmlspecialchars($typeLabels[$docTypeRaw] ?? ucfirst($docTypeRaw), ENT_QUOTES, 'UTF-8');

        $uploadedRaw  = trim((string)($doc['uploaded_at'] ?? $doc['created_at'] ?? ''));
        $uploadedDate = '';
        if ($uploadedRaw !== '') {
            $d = date_create($uploadedRaw);
            if ($d) {
                $uploadedDate = date_format($d, 'd/m/Y');
            }
        }

        $subject = 'New document ready for review \xe2\x80\x94 case #' . $requestId;

        $contentHtml =
            '<p>Your patient has uploaded a new medical document for review.</p>'
            . '<p><strong>Case:</strong> ' . $safeTitle . '</p>'
            . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0"'
            .   ' style="background:#f3f7fc; border:1px solid #d9e4f1; border-radius:4px; margin-bottom:16px;">'
            .   '<tr><td style="padding:12px 16px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;">'
            .     '<p style="margin:0 0 4px 0; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#0b4ea2; font-weight:700;">Document received</p>'
            .     '<p style="margin:0 0 2px 0; font-weight:700; color:#13357b;">' . $docFilename . '</p>'
            .     '<p style="margin:0; font-size:12px; color:#64748b;">'
            .       'Type: <strong>' . $docTypeLabel . '</strong>'
            .       ($uploadedDate !== '' ? ' &nbsp;·&nbsp; Uploaded: ' . $uploadedDate : '')
            .     '</p>'
            .   '</td></tr>'
            . '</table>'
            . '<p>Log in to review, download and action the file.</p>';

        $textBody = "Your patient has uploaded a new medical document.\n\n"
            . "Case: {$meta['title']}\n"
            . "File: " . strip_tags($docFilename) . "\n"
            . "Type: " . strip_tags($docTypeLabel) . "\n"
            . ($uploadedDate !== '' ? "Uploaded: {$uploadedDate}\n" : '')
            . "\nLog in to review and download:\n{$ctaUrl}";

        return send_interaction_email(
            $to,
            $subject,
            $contentHtml,
            $textBody,
            [
                'preheader'    => "Your patient uploaded a file on case #{$requestId} \xe2\x80\x94 it's in your portal.",
                'cta'          => ['text' => 'Review document in MedTravel', 'url' => $ctaUrl],
                'footer_note'  => 'Documents are stored and shared securely inside MedTravel.',
                'sender_label' => 'MedTravel Coordination Team',
            ],
            $conexion
        );
    }
}

// ── 4. Coordination summary notice → Client (optional) ───────────────────────
if (!function_exists('notify_coordination_summary_to_client')) {
    /**
     * Send a periodic coordination summary to the client.
     *
     * @param  object $conexion        Active MySQLi connection.
     * @param  int    $requestId       booking_requests.id
     * @param  int    $unreadCount     Number of unread messages.
     * @param  string $pendingAction   Human-readable pending action (e.g. "Upload requested documents").
     * @param  string $statusLabel     Human-readable case status (e.g. "Under Review").
     * @return array                   Result from send_interaction_email().
     */
    function notify_coordination_summary_to_client($conexion, $requestId, $unreadCount = 0, $pendingAction = '', $statusLabel = '')
    {
        $requestId   = (int)$requestId;
        $unreadCount = max(0, (int)$unreadCount);
        $to          = interaction_email_fetch_client_email($conexion, $requestId);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'client_email_not_found'];
        }

        $meta          = interaction_email_request_meta($conexion, 'CARE', $requestId, 0);
        $safeTitle     = htmlspecialchars((string)$meta['title'], ENT_QUOTES, 'UTF-8');
        $safeStatus    = htmlspecialchars(trim((string)$statusLabel) ?: 'In progress', ENT_QUOTES, 'UTF-8');
        $safePending   = htmlspecialchars(trim((string)$pendingAction) ?: 'No action required', ENT_QUOTES, 'UTF-8');
        $ctaUrl        = _interaction_inbox_url('client', $requestId, 0, 'CARE');

        $subject = "Your MedTravel case #{$requestId} \xe2\x80\x94 here\xe2\x80\x99s what\xe2\x80\x99s new";

        // Status table — same structural pattern as booking content HTML
        $rowStyle    = 'font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#334155;';
        $labelStyle  = 'padding:9px 14px; font-weight:700; width:42%; border-bottom:1px solid #d9e4f1; ' . $rowStyle;
        $valueStyle  = 'padding:9px 14px; border-bottom:1px solid #d9e4f1; ' . $rowStyle;
        $altRowStyle = 'background:#f3f7fc;';

        $contentHtml =
            '<p>Here is a quick update on your MedTravel case.</p>'
            . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0"'
            .   ' style="border:1px solid #d9e4f1; border-radius:4px; border-collapse:collapse; margin-bottom:16px;">'
            .   '<tr style="' . $altRowStyle . '">'
            .     '<td style="' . $labelStyle . '">Case</td>'
            .     '<td style="' . $valueStyle . '">' . $safeTitle . '</td>'
            .   '</tr>'
            .   '<tr>'
            .     '<td style="' . $labelStyle . '">Status</td>'
            .     '<td style="' . $valueStyle . '">' . $safeStatus . '</td>'
            .   '</tr>'
            .   '<tr style="' . $altRowStyle . '">'
            .     '<td style="' . $labelStyle . '">Pending action</td>'
            .     '<td style="' . $valueStyle . '">' . $safePending . '</td>'
            .   '</tr>'
            .   '<tr>'
            .     '<td style="' . $labelStyle . ' border-bottom:none;">Unread messages</td>'
            .     '<td style="' . $valueStyle . ' border-bottom:none;">' . $unreadCount . '</td>'
            .   '</tr>'
            . '</table>'
            . '<p>Log in to respond or upload any requested documents. Your coordinator is here if you have questions.</p>';

        $textBody = "Here is a quick update on your MedTravel case.\n\n"
            . "Case:             {$meta['title']}\n"
            . "Status:           " . strip_tags($safeStatus) . "\n"
            . "Pending action:   " . strip_tags($safePending) . "\n"
            . "Unread messages:  {$unreadCount}\n"
            . "\nLog in to respond or upload any requested documents:\n{$ctaUrl}\n\n"
            . "Your coordinator is here if you have questions.";

        return send_interaction_email(
            $to,
            $subject,
            $contentHtml,
            $textBody,
            [
                'preheader'   => strip_tags($safeStatus) . " on case #{$requestId}" . ($unreadCount > 0 ? " \xe2\x80\x94 {$unreadCount} unread " . ($unreadCount === 1 ? 'message' : 'messages') : '') . '.',
                'cta'         => ['text' => 'Open my case in MedTravel', 'url' => $ctaUrl],
                'footer_note' => 'Questions? Reach your coordinator directly through your MedTravel portal.'
            ],
            $conexion
        );
    }
}
