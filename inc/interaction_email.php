<?php
require_once __DIR__ . '/inbox_utils.php';

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
    function interaction_email_fetch_provider_email($conexion, $itemId)
    {
        $itemId = (int)$itemId;
        if ($itemId <= 0) {
            return '';
        }
        if (!inbox_table_exists($conexion, 'booking_request_items') || !inbox_table_exists($conexion, 'usuarios')) {
            return '';
        }
        $hasUsersDeleted = inbox_table_has_column($conexion, 'usuarios', 'is_deleted');
        $hasUsersActive = inbox_table_has_column($conexion, 'usuarios', 'activo');

        $sql = "SELECT u.email
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
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('interaction_email_fetch_client_email')) {
    function interaction_email_fetch_client_email($conexion, $requestId)
    {
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
        if (!function_exists('sendEmail')) {
            return ['success' => false, 'error' => 'sendEmail_unavailable'];
        }
        $preheader = (string)($meta['preheader'] ?? $subject);
        $cta = isset($meta['cta']) ? $meta['cta'] : null;
        $footerNote = (string)($meta['footer_note'] ?? '');
        $htmlBody = function_exists('renderMedTravelEmail')
            ? renderMedTravelEmail($subject, $preheader, $contentHtml, $footerNote !== '' ? $footerNote : null, $cta)
            : $contentHtml;

        try {
            $result = sendEmail($to, $subject, $htmlBody, 'patientcare', ['alt_body' => $textBody], $conexion);
        } catch (Throwable $e) {
            error_log('interaction_email_send_failed to=' . $to . ' err=' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (is_array($result) && empty($result['success'])) {
            error_log('interaction_email_send_failed to=' . $to . ' err=' . ($result['error'] ?? 'unknown'));
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

// ── 1. New message → Client ──────────────────────────────────────────────────
if (!function_exists('notify_new_message_to_client')) {
    /**
     * Notify the client that a provider or coordinator has sent a new message.
     *
     * @param  object $conexion   Active MySQLi connection.
     * @param  int    $requestId  booking_requests.id
     * @param  int    $itemId     booking_request_items.id (0 for CARE thread)
     * @param  string $threadType 'ITEM' | 'CARE'
     * @param  string $senderRole 'PROVIDER' | 'ADMIN' | 'PATIENTCARE'
     * @param  string $snippet    Raw message body preview (will be sanitised).
     * @return array              Result from send_interaction_email().
     */
    function notify_new_message_to_client($conexion, $requestId, $itemId, $threadType, $senderRole, $snippet = '')
    {
        $requestId  = (int)$requestId;
        $itemId     = (int)$itemId;
        $threadType = strtoupper(trim((string)$threadType));
        $to         = interaction_email_fetch_client_email($conexion, $requestId);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'client_email_not_found'];
        }

        $meta        = interaction_email_request_meta($conexion, $threadType, $requestId, $itemId);
        $safeTitle   = htmlspecialchars((string)$meta['title'], ENT_QUOTES, 'UTF-8');
        $actorLabel  = interaction_email_actor_label($senderRole);
        $safeSnippet = interaction_email_safe_snippet($snippet, 120);
        $ctaUrl      = _interaction_inbox_url('client', $requestId, $itemId, $threadType);

        // Subject — same dash style as booking: "MedTravel – … (Request #N)"
        $subject = 'MedTravel – New message from your coordinator (Request #' . $requestId . ')';

        // HTML content (inner block passed to renderMedTravelEmail)
        $contentHtml =
            '<p>You have received a new message regarding your case.</p>'
            . '<p>'
            .   '<strong>Case:</strong> ' . $safeTitle . '<br>'
            .   '<strong>From:</strong> ' . htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8')
            . '</p>'
            . ($safeSnippet !== ''
                ? '<p style="background:#f3f7fc; border-left:3px solid #0b4ea2; padding:10px 14px; margin:0 0 16px 0; color:#334155;">'
                  . htmlspecialchars($safeSnippet, ENT_QUOTES, 'UTF-8')
                  . '</p>'
                : '')
            . '<p>Please log in to your portal to read and reply. All communication should remain within the MedTravel platform for your safety and record-keeping.</p>';

        // Plain-text alternative
        $textBody = "You have received a new message regarding your case.\n\n"
            . "Case: {$meta['title']}\n"
            . "From: {$actorLabel}\n"
            . ($safeSnippet !== '' ? "\n\"{$safeSnippet}\"\n" : '')
            . "\nLog in to read and reply:\n{$ctaUrl}\n\n"
            . "All communication should remain within the MedTravel platform.";

        return send_interaction_email(
            $to,
            $subject,
            $contentHtml,
            $textBody,
            [
                'preheader'   => $actorLabel . ' sent you a new message on your MedTravel case.',
                'cta'         => ['text' => 'Open in MedTravel', 'url' => $ctaUrl],
                'footer_note' => 'This is an automated message. Do not share personal contact details outside the platform.',
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
    function notify_new_message_to_provider($conexion, $requestId, $itemId, $threadType, $senderRole, $snippet = '')
    {
        $requestId  = (int)$requestId;
        $itemId     = (int)$itemId;
        $threadType = strtoupper(trim((string)$threadType));
        $to         = interaction_email_fetch_provider_email($conexion, $itemId);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'provider_email_not_found'];
        }

        $meta        = interaction_email_request_meta($conexion, $threadType, $requestId, $itemId);
        $safeTitle   = htmlspecialchars((string)$meta['title'], ENT_QUOTES, 'UTF-8');
        $actorLabel  = interaction_email_actor_label($senderRole);
        $safeSnippet = interaction_email_safe_snippet($snippet, 120);
        $ctaUrl      = _interaction_inbox_url('admin', $requestId, $itemId, $threadType);

        $subject = 'MedTravel – New message on case #' . $requestId . ' – action required';

        $contentHtml =
            '<p>A new message has been sent on one of your active cases.</p>'
            . '<p>'
            .   '<strong>Case:</strong> ' . $safeTitle . '<br>'
            .   '<strong>From:</strong> ' . htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8')
            . '</p>'
            . ($safeSnippet !== ''
                ? '<p style="background:#f3f7fc; border-left:3px solid #0b4ea2; padding:10px 14px; margin:0 0 16px 0; color:#334155;">'
                  . htmlspecialchars($safeSnippet, ENT_QUOTES, 'UTF-8')
                  . '</p>'
                : '')
            . '<p>Your timely response helps move the case forward. Please reply through your MedTravel provider portal. Do not contact the patient outside the platform.</p>';

        $textBody = "A new message has been sent on one of your active cases.\n\n"
            . "Case: {$meta['title']}\n"
            . "From: {$actorLabel}\n"
            . ($safeSnippet !== '' ? "\n\"{$safeSnippet}\"\n" : '')
            . "\nOpen your MedTravel inbox to reply:\n{$ctaUrl}\n\n"
            . "Do not contact the patient outside the platform.";

        return send_interaction_email(
            $to,
            $subject,
            $contentHtml,
            $textBody,
            [
                'preheader'   => 'A patient has sent a new message on case #' . $requestId . '.',
                'cta'         => ['text' => 'Open in MedTravel', 'url' => $ctaUrl],
                'footer_note' => 'This is an automated message. Do not contact patients outside the MedTravel platform.',
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

        $subject = 'MedTravel – New medical document received (Request #' . $requestId . ')';

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
            . '<p>Log in to your MedTravel provider portal to view, download, and action this document. Do not request files through external channels.</p>';

        $textBody = "Your patient has uploaded a new medical document.\n\n"
            . "Case: {$meta['title']}\n"
            . "File: " . strip_tags($docFilename) . "\n"
            . "Type: " . strip_tags($docTypeLabel) . "\n"
            . ($uploadedDate !== '' ? "Uploaded: {$uploadedDate}\n" : '')
            . "\nLog in to view and download the document:\n{$ctaUrl}\n\n"
            . "Do not request files through external channels.";

        return send_interaction_email(
            $to,
            $subject,
            $contentHtml,
            $textBody,
            [
                'preheader'   => 'A patient uploaded a medical document on case #' . $requestId . '.',
                'cta'         => ['text' => 'Review document in MedTravel', 'url' => $ctaUrl],
                'footer_note' => 'All documents are securely stored in MedTravel. Never share files outside the platform.',
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

        $subject = 'MedTravel – Your case update (Request #' . $requestId . ')';

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
            . '<p>Log in to respond or upload any requested documents. Your coordinator is here to help — all within the platform.</p>';

        $textBody = "Here is a quick update on your MedTravel case.\n\n"
            . "Case:             {$meta['title']}\n"
            . "Status:           " . strip_tags($safeStatus) . "\n"
            . "Pending action:   " . strip_tags($safePending) . "\n"
            . "Unread messages:  {$unreadCount}\n"
            . "\nLog in to respond or upload any requested documents:\n{$ctaUrl}\n\n"
            . "Your coordinator is here to help — all within the platform.";

        return send_interaction_email(
            $to,
            $subject,
            $contentHtml,
            $textBody,
            [
                'preheader'   => 'Case #' . $requestId . ' — ' . strip_tags($safeStatus) . '. ' . $unreadCount . ' unread message' . ($unreadCount === 1 ? '' : 's') . '.',
                'cta'         => ['text' => 'Open my case in MedTravel', 'url' => $ctaUrl],
                'footer_note' => 'You received this summary because you have an active case on MedTravel.',
            ],
            $conexion
        );
    }
}
