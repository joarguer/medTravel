<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../../admin/include/email_config.php';
require_once __DIR__ . '/../include/client_notifications.php';
require_once __DIR__ . '/../../inc/inbox_utils.php';
require_once __DIR__ . '/../../inc/email_template.php';
require_once __DIR__ . '/../../inc/interaction_email.php';
require_once __DIR__ . '/../../inc/fee_gate.php';
require_once __DIR__ . '/../../inc/commission_gate.php';
require_once __DIR__ . '/../../inc/google_calendar.php';

function client_inbox_err($message, $code = 400, $errorCode = '')
{
    http_response_code($code);
    $payload = ['ok' => false, 'message' => $message];
    if ($errorCode !== '') {
        $payload['code'] = $errorCode;
    }
    echo json_encode($payload);
    exit;
}

function client_inbox_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function client_inbox_calendar_event_columns($conexion)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [
        'organizer_admin_user_id' => client_table_has_column($conexion, 'calendar_events', 'organizer_admin_user_id'),
        'integration_mode' => client_table_has_column($conexion, 'calendar_events', 'integration_mode'),
        'google_event_id' => client_table_has_column($conexion, 'calendar_events', 'google_event_id'),
        'google_html_link' => client_table_has_column($conexion, 'calendar_events', 'google_html_link'),
        'google_meet_url' => client_table_has_column($conexion, 'calendar_events', 'google_meet_url'),
        'organizer_email' => client_table_has_column($conexion, 'calendar_events', 'organizer_email'),
    ];

    return $cache;
}

function client_inbox_fetch_latest_proposed_meeting($conexion, $itemId, $ownerScope)
{
    if (!inbox_table_exists($conexion, 'calendar_events')) {
        return null;
    }

    $columns = client_inbox_calendar_event_columns($conexion);
    $organizerAdminExpr = $columns['organizer_admin_user_id'] ? 'ce.organizer_admin_user_id' : 'NULL';
    $integrationModeExpr = $columns['integration_mode'] ? 'ce.integration_mode' : "'calendar_plus_meet'";
    $googleEventExpr = $columns['google_event_id'] ? 'ce.google_event_id' : "''";
    $googleHtmlExpr = $columns['google_html_link'] ? 'ce.google_html_link' : "''";
    $googleMeetExpr = $columns['google_meet_url'] ? 'ce.google_meet_url' : "''";
    $organizerEmailExpr = $columns['organizer_email'] ? 'ce.organizer_email' : "''";
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');

    $sql = "SELECT ce.id, ce.request_id, ce.item_id, ce.title, ce.description, ce.start_at, ce.end_at, ce.status,
                   {$organizerAdminExpr} AS organizer_admin_user_id,
                   {$integrationModeExpr} AS integration_mode,
                   {$googleEventExpr} AS google_event_id,
                   {$googleHtmlExpr} AS google_html_link,
                   {$googleMeetExpr} AS google_meet_url,
                   {$organizerEmailExpr} AS organizer_email,
                   " . (client_table_has_column($conexion, 'booking_requests', 'email') ? 'br.email' : "''") . " AS client_email
            FROM calendar_events ce
            INNER JOIN booking_requests br ON br.id = ce.request_id
            INNER JOIN booking_request_items bri ON bri.id = ce.item_id AND bri.booking_request_id = br.id
            WHERE ce.event_type = 'ITEM'
              AND ce.item_id = ?
              AND ce.status = 'proposed'
              AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasBookingSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= ' ORDER BY ce.start_at DESC, ce.id DESC LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    $types = 'i' . $ownerScope['types'];
    $params = array_merge([$itemId], $ownerScope['params']);
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function client_inbox_fetch_latest_confirmed_meeting($conexion, $itemId, $ownerScope)
{
    if (!inbox_table_exists($conexion, 'calendar_events')) {
        return null;
    }

    $columns = client_inbox_calendar_event_columns($conexion);
    $organizerAdminExpr = $columns['organizer_admin_user_id'] ? 'ce.organizer_admin_user_id' : 'NULL';
    $integrationModeExpr = $columns['integration_mode'] ? 'ce.integration_mode' : "'calendar_plus_meet'";
    $googleEventExpr = $columns['google_event_id'] ? 'ce.google_event_id' : "''";
    $googleHtmlExpr = $columns['google_html_link'] ? 'ce.google_html_link' : "''";
    $googleMeetExpr = $columns['google_meet_url'] ? 'ce.google_meet_url' : "''";
    $organizerEmailExpr = $columns['organizer_email'] ? 'ce.organizer_email' : "''";
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');

    $sql = "SELECT ce.id, ce.request_id, ce.item_id, ce.title, ce.description, ce.start_at, ce.end_at, ce.status,
                   {$organizerAdminExpr} AS organizer_admin_user_id,
                   {$integrationModeExpr} AS integration_mode,
                   {$googleEventExpr} AS google_event_id,
                   {$googleHtmlExpr} AS google_html_link,
                   {$googleMeetExpr} AS google_meet_url,
                   {$organizerEmailExpr} AS organizer_email
            FROM calendar_events ce
            INNER JOIN booking_requests br ON br.id = ce.request_id
            INNER JOIN booking_request_items bri ON bri.id = ce.item_id AND bri.booking_request_id = br.id
            WHERE ce.event_type = 'ITEM'
              AND ce.item_id = ?
              AND ce.status = 'confirmed'
              AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasBookingSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= ' ORDER BY ce.start_at DESC, ce.id DESC LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    $types = 'i' . $ownerScope['types'];
    $params = array_merge([$itemId], $ownerScope['params']);
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function client_inbox_cancel_proposed_meeting($conexion, $eventId)
{
    $eventId = (int)$eventId;
    if ($eventId <= 0 || !inbox_table_exists($conexion, 'calendar_events')) {
        return;
    }

    $stmt = mysqli_prepare($conexion, "UPDATE calendar_events SET status = 'cancelled', updated_at = NOW() WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'i', $eventId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function client_inbox_meeting_has_defined_schedule(array $eventRow)
{
    $startAt = trim((string)($eventRow['start_at'] ?? ''));
    $endAt = trim((string)($eventRow['end_at'] ?? ''));
    return $startAt !== '' && $endAt !== '';
}

function client_inbox_confirm_internal_meeting($conexion, array $eventRow, $forceInternalMode = false)
{
    $eventId = (int)($eventRow['id'] ?? 0);
    if ($eventId <= 0) {
        return ['ok' => false, 'error' => 'invalid_calendar_event'];
    }

    $columns = client_inbox_calendar_event_columns($conexion);
    $integrationMode = trim((string)($eventRow['integration_mode'] ?? 'calendar_plus_meet'));
    if (!in_array($integrationMode, ['internal_only', 'calendar_only', 'calendar_plus_meet'], true)) {
        $integrationMode = 'calendar_plus_meet';
    }
    $resolvedMode = $forceInternalMode ? 'internal_only' : $integrationMode;

    $setParts = ["status = 'confirmed'", 'updated_at = NOW()'];
    $types = '';
    $params = [];

    if ($columns['integration_mode']) {
        $setParts[] = 'integration_mode = ?';
        $types .= 's';
        $params[] = $resolvedMode;
    }
    if ($resolvedMode === 'internal_only') {
        if ($columns['organizer_admin_user_id']) {
            $setParts[] = 'organizer_admin_user_id = NULL';
        }
        if ($columns['google_event_id']) {
            $setParts[] = "google_event_id = ''";
        }
        if ($columns['google_html_link']) {
            $setParts[] = "google_html_link = ''";
        }
        if ($columns['google_meet_url']) {
            $setParts[] = "google_meet_url = ''";
        }
        if ($columns['organizer_email']) {
            $setParts[] = "organizer_email = ''";
        }
    }

    $sql = 'UPDATE calendar_events SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1';
    $types .= 'i';
    $params[] = $eventId;
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'calendar_event_update_prepare_failed'];
    }
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'error' => 'calendar_event_update_failed: ' . $err];
    }
    mysqli_stmt_close($stmt);

    return [
        'ok' => true,
        'calendar_event_id' => $eventId,
        'event_id' => '',
        'html_link' => '',
        'meet_url' => '',
        'organizer_email' => '',
        'integration_mode' => $resolvedMode,
        'start_at' => (string)($eventRow['start_at'] ?? ''),
        'end_at' => (string)($eventRow['end_at'] ?? ''),
        'fallback_to_internal' => $forceInternalMode ? 1 : 0,
    ];
}

function client_inbox_fetch_proposal_sender_user_id($conexion, $requestId, $itemId)
{
    $requestId = (int)$requestId;
    $itemId = (int)$itemId;
    if ($requestId <= 0 || $itemId <= 0 || !inbox_table_exists($conexion, 'inbox_messages')) {
        return 0;
    }

    $threadId = inbox_thread_id('ITEM', $requestId, $itemId);
    $sql = "SELECT sender_user_id
            FROM inbox_messages
            WHERE thread_id = ?
              AND (body LIKE '[REPLY] PROPOSED_DATES%' OR body LIKE '[MEETING_PROPOSAL] %')
              AND COALESCE(sender_user_id, 0) > 0
            ORDER BY id DESC
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 's', $threadId);
    $userId = 0;
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $userId = (int)($row['sender_user_id'] ?? 0);
    }
    mysqli_stmt_close($stmt);
    return $userId;
}

function client_inbox_fetch_assigned_staff_linked_user_id($conexion, $itemId)
{
    $itemId = (int)$itemId;
    if ($itemId <= 0
        || !client_table_has_column($conexion, 'booking_request_items', 'assigned_staff_id')
        || !inbox_table_exists($conexion, 'provider_medical_staff')
        || !client_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')) {
        return 0;
    }

    $sql = "SELECT COALESCE(pms.linked_user_id, 0) AS linked_user_id
            FROM booking_request_items bri
            INNER JOIN provider_medical_staff pms ON pms.id = bri.assigned_staff_id
            WHERE bri.id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'i', $itemId);
    $linkedUserId = 0;
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $linkedUserId = (int)($row['linked_user_id'] ?? 0);
    }
    mysqli_stmt_close($stmt);
    return $linkedUserId;
}

function client_inbox_fetch_user_email($conexion, $userId)
{
    $userId = (int)$userId;
    if ($userId <= 0 || !inbox_table_exists($conexion, 'usuarios')) {
        return '';
    }

    $sql = "SELECT email FROM usuarios WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    $email = '';
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $candidate = strtolower(trim((string)($row['email'] ?? '')));
        if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $email = $candidate;
        }
    }
    mysqli_stmt_close($stmt);
    return $email;
}

function client_inbox_confirm_google_meeting($conexion, array $eventRow, $requestId, $itemId, array $options = [])
{
    $eventId = (int)($eventRow['id'] ?? 0);
    $allowInternalFallback = !empty($options['allow_internal_fallback']);
    $integrationMode = trim((string)($eventRow['integration_mode'] ?? 'calendar_plus_meet'));
    if (!in_array($integrationMode, ['internal_only', 'calendar_only', 'calendar_plus_meet'], true)) {
        $integrationMode = 'calendar_plus_meet';
    }

    if ($integrationMode === 'internal_only') {
        return client_inbox_confirm_internal_meeting($conexion, $eventRow, false);
    }

    $proposalSenderUserId = client_inbox_fetch_proposal_sender_user_id($conexion, $requestId, $itemId);
    $assignedStaffLinkedUserId = client_inbox_fetch_assigned_staff_linked_user_id($conexion, $itemId);

    $organizerAdminUserId = (int)($eventRow['organizer_admin_user_id'] ?? 0);
    if ($organizerAdminUserId > 0 && function_exists('google_calendar_get_connection')) {
        $persistedConnection = google_calendar_get_connection($conexion, $organizerAdminUserId, false);
        if (empty($persistedConnection['is_connected'])) {
            $organizerAdminUserId = 0;
        }
    }
    if ($organizerAdminUserId <= 0 && $proposalSenderUserId > 0) {
        $picked = (int)google_calendar_pick_connected_admin_user_id($conexion, $proposalSenderUserId);
        if ($picked === $proposalSenderUserId) {
            $organizerAdminUserId = $picked;
        }
    }
    if ($organizerAdminUserId <= 0 && $assignedStaffLinkedUserId > 0) {
        $picked = (int)google_calendar_pick_connected_admin_user_id($conexion, $assignedStaffLinkedUserId);
        if ($picked === $assignedStaffLinkedUserId) {
            $organizerAdminUserId = $picked;
        }
    }
    if ($organizerAdminUserId <= 0) {
        $organizerAdminUserId = (int)google_calendar_pick_connected_admin_user_id($conexion, 0);
    }
    if ($organizerAdminUserId <= 0) {
        if ($allowInternalFallback) {
            $fallbackResult = client_inbox_confirm_internal_meeting($conexion, $eventRow, true);
            if (!empty($fallbackResult['ok'])) {
                $fallbackResult['fallback_to_internal'] = 1;
                $fallbackResult['fallback_reason'] = 'no_google_admin_connected';
            }
            return $fallbackResult;
        }
        return ['ok' => false, 'error' => 'no_google_admin_connected'];
    }

    $timezone = 'America/Bogota';
    $attendees = [];
    $clientEmail = trim((string)($eventRow['client_email'] ?? ''));
    $organizerConnectionEmail = '';
    if (function_exists('google_calendar_get_connection') && $organizerAdminUserId > 0) {
        $organizerConnection = google_calendar_get_connection($conexion, $organizerAdminUserId, false);
        $organizerConnectionEmail = strtolower(trim((string)($organizerConnection['google_email'] ?? '')));
    }
    $proposalSenderEmail = client_inbox_fetch_user_email($conexion, $proposalSenderUserId);
    $assignedStaffEmail = client_inbox_fetch_user_email($conexion, $assignedStaffLinkedUserId);
    $providerEmailSource = '';
    $providerEmail = function_exists('interaction_email_fetch_provider_email')
        ? trim((string)interaction_email_fetch_provider_email($conexion, $itemId, $providerEmailSource))
        : '';
    if (filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        $attendees[] = ['email' => $clientEmail];
    }

    $clinicalAttendeeEmails = [];
    foreach ([$proposalSenderEmail, $assignedStaffEmail] as $candidateEmail) {
        $candidateEmail = strtolower(trim((string)$candidateEmail));
        if (!filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if ($organizerConnectionEmail !== '' && strcasecmp($candidateEmail, $organizerConnectionEmail) === 0) {
            continue;
        }
        if (in_array($candidateEmail, $clinicalAttendeeEmails, true)) {
            continue;
        }
        $clinicalAttendeeEmails[] = $candidateEmail;
    }
    foreach ($clinicalAttendeeEmails as $clinicalEmail) {
        $attendees[] = ['email' => $clinicalEmail];
    }
    if (empty($clinicalAttendeeEmails) && filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        $providerEmail = strtolower(trim((string)$providerEmail));
        if ($organizerConnectionEmail === '' || strcasecmp($providerEmail, $organizerConnectionEmail) !== 0) {
            $attendees[] = ['email' => $providerEmail];
        }
    }

    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_MEETING_CONFIRM_ATTENDEES_PREP'
            . ' request_id=' . (int)$requestId
            . ' item_id=' . (int)$itemId
            . ' organizer_admin_user_id=' . (int)$organizerAdminUserId
            . ' organizer_email_existing=' . (string)($eventRow['organizer_email'] ?? '')
            . ' organizer_connection_email=' . $organizerConnectionEmail
            . ' client_email=' . $clientEmail
            . ' proposal_sender_user_id=' . (int)$proposalSenderUserId
            . ' proposal_sender_email=' . $proposalSenderEmail
            . ' assigned_staff_linked_user_id=' . (int)$assignedStaffLinkedUserId
            . ' assigned_staff_email=' . $assignedStaffEmail
            . ' provider_email=' . $providerEmail
            . ' provider_email_source=' . ($providerEmailSource !== '' ? $providerEmailSource : 'unresolved')
            . ' attendees=' . json_encode($attendees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    $startDate = new DateTimeImmutable((string)$eventRow['start_at'], new DateTimeZone($timezone));
    $endDate = new DateTimeImmutable((string)$eventRow['end_at'], new DateTimeZone($timezone));
    $result = google_calendar_create_event($conexion, $organizerAdminUserId, [
        'summary' => (string)($eventRow['title'] ?? ('Videollamada MedTravel - Item #' . $itemId)),
        'description' => (string)($eventRow['description'] ?? ('Solicitud #' . $requestId . ' / Item #' . $itemId)),
        'start_at' => $startDate->format(DateTimeInterface::RFC3339),
        'end_at' => $endDate->format(DateTimeInterface::RFC3339),
        'timezone' => $timezone,
        'attendees' => $attendees,
        'create_meet' => $integrationMode === 'calendar_plus_meet',
    ]);

    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_MEETING_CONFIRM_ATTENDEES_RESULT'
            . ' request_id=' . (int)$requestId
            . ' item_id=' . (int)$itemId
            . ' organizer_email=' . (string)($result['organizer_email'] ?? '')
            . ' provider_email=' . $providerEmail
            . ' client_email=' . $clientEmail
            . ' google_attendees=' . json_encode((array)($result['attendees'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . ' ok=' . (!empty($result['ok']) ? '1' : '0')
        );
    }

    if (empty($result['ok'])) {
        return $result;
    }

    $columns = client_inbox_calendar_event_columns($conexion);
    $setParts = ["status = 'confirmed'", 'updated_at = NOW()'];
    $types = '';
    $params = [];
    if ($columns['organizer_admin_user_id']) {
        $setParts[] = 'organizer_admin_user_id = ?';
        $types .= 'i';
        $params[] = $organizerAdminUserId;
    }
    if ($columns['integration_mode']) {
        $setParts[] = 'integration_mode = ?';
        $types .= 's';
        $params[] = $integrationMode;
    }
    if ($columns['google_event_id']) {
        $setParts[] = 'google_event_id = ?';
        $types .= 's';
        $params[] = (string)($result['event_id'] ?? '');
    }
    if ($columns['google_html_link']) {
        $setParts[] = 'google_html_link = ?';
        $types .= 's';
        $params[] = (string)($result['html_link'] ?? '');
    }
    if ($columns['google_meet_url']) {
        $setParts[] = 'google_meet_url = ?';
        $types .= 's';
        $params[] = (string)($result['meet_url'] ?? '');
    }
    if ($columns['organizer_email']) {
        $setParts[] = 'organizer_email = ?';
        $types .= 's';
        $params[] = (string)($result['organizer_email'] ?? '');
    }

    $sql = 'UPDATE calendar_events SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1';
    $types .= 'i';
    $params[] = $eventId;
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'calendar_event_update_prepare_failed'];
    }
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'error' => 'calendar_event_update_failed: ' . $err];
    }
    mysqli_stmt_close($stmt);

    return [
        'ok' => true,
        'calendar_event_id' => $eventId,
        'event_id' => (string)($result['event_id'] ?? ''),
        'html_link' => (string)($result['html_link'] ?? ''),
        'meet_url' => (string)($result['meet_url'] ?? ''),
        'organizer_email' => (string)($result['organizer_email'] ?? ''),
        'integration_mode' => $integrationMode,
        'start_at' => (string)($eventRow['start_at'] ?? ''),
        'end_at' => (string)($eventRow['end_at'] ?? ''),
    ];
}

function client_inbox_compose_locked($reason, $ctx, $clientUserId)
{
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_BLOCK_SEND_MESSAGE reason=' . (string)$reason
            . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
            . ' user_id=' . (int)$clientUserId
            . ' request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
        );
    }
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'success' => false,
        'error' => 'compose_locked',
        'reason' => (string)$reason,
    ]);
    exit;
}

function client_inbox_notify_message($conexion, $ctx, $message)
{
    if (!function_exists('send_interaction_email')) {
        return;
    }
    $threadType = (string)($ctx['thread_type'] ?? '');
    $requestId = (int)($ctx['request_id'] ?? 0);
    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($requestId <= 0) {
        return;
    }

    $meta = interaction_email_request_meta($conexion, $threadType, $requestId, $itemId);
    $serviceTitle = trim((string)($meta['title'] ?? 'Request #' . $requestId));
    $destination = trim((string)($meta['subtitle'] ?? ''));
    $actorLabel = interaction_email_actor_label('CLIENT');
    $snippet = interaction_email_safe_snippet($message, 120);
    if ($snippet === '') {
        $snippet = 'New message received.';
    }

    $subject = 'MedTravel update - ' . $actorLabel . ' message for Request #' . $requestId;
    $contentHtml = '<p><strong>Actor:</strong> ' . htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Request:</strong> #' . $requestId . '<br>'
        . '<strong>Service:</strong> ' . htmlspecialchars($serviceTitle, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($destination !== '') {
        $contentHtml .= '<p><strong>Destination:</strong> ' . htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $contentHtml .= '<p><strong>Message:</strong> ' . htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') . '</p>';

    $ctaUrl = 'https://medtravel.com.co/admin/app_inbox.php?request_id=' . $requestId
        . '&thread_type=' . urlencode((string)$meta['thread_type'])
        . '&item_id=' . (int)$meta['item_id'];
    $textBody = "Actor: {$actorLabel}\nRequest: #{$requestId}\nService: {$serviceTitle}";
    if ($destination !== '') {
        $textBody .= "\nDestination: {$destination}";
    }
    $textBody .= "\nMessage: {$snippet}\nInbox: {$ctaUrl}";

    $adminEmail = interaction_email_resolve_patientcare_email($conexion);
    $providerEmail = ($threadType === 'ITEM' && $itemId > 0)
        ? interaction_email_fetch_provider_email($conexion, $itemId)
        : '';

    $metaSend = [
        'preheader' => $snippet,
        'cta' => ['text' => 'Open Inbox', 'url' => $ctaUrl],
    ];

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        send_interaction_email($adminEmail, $subject, $contentHtml, $textBody, $metaSend, $conexion);
    }
    if (filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        send_interaction_email($providerEmail, $subject, $contentHtml, $textBody, $metaSend, $conexion);
    }
}

function client_inbox_fee_gate_state($conexion, $bookingRequestId)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0 || !inbox_table_exists($conexion, 'booking_requests')) {
        return [
            'fee_required' => 0,
            'fee_status' => 'pending',
            'fee_locked' => false,
        ];
    }

    $hasFeeRequired = client_table_has_column($conexion, 'booking_requests', 'fee_required');
    $hasFeeStatus = client_table_has_column($conexion, 'booking_requests', 'fee_status');
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');

    $sql = "SELECT " . ($hasFeeRequired ? "fee_required" : "0 AS fee_required") . ", " . ($hasFeeStatus ? "fee_status" : "'pending' AS fee_status") . "\n"
         . "FROM booking_requests WHERE id = ?";
    if ($hasBookingSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [
            'fee_required' => 0,
            'fee_status' => 'pending',
            'fee_locked' => false,
        ];
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [
            'fee_required' => 0,
            'fee_status' => 'pending',
            'fee_locked' => false,
        ];
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    $feeRequired = (int)($row['fee_required'] ?? 0) === 1 ? 1 : 0;
    $feeStatus = strtolower(trim((string)($row['fee_status'] ?? 'pending')));
    if ($feeStatus === '') {
        $feeStatus = 'pending';
    }

    return [
        'fee_required' => $feeRequired,
        'fee_status' => $feeStatus,
        'fee_locked' => ($feeRequired === 1 && $feeStatus !== 'paid'),
    ];
}

function client_inbox_free_message_state($conexion, $bookingRequestId, $feeGate = null)
{
    $bookingRequestId = (int)$bookingRequestId;
    return [
        'booking_status' => '',
        'stage_allows_free_message' => true,
        'can_send_free_message' => true,
        'blocked_reason' => '',
        'notice' => '',
    ];
}

function client_inbox_is_structured_message($message, $quickActions = [])
{
    $text = ltrim((string)$message);
    if ($text === '') {
        return false;
    }
    if (stripos($text, '[ACTION]') === 0) {
        return true;
    }
    if (stripos($text, '[REPLY]') === 0) {
        return true;
    }
    if (!empty($quickActions)) {
        foreach ($quickActions as $actionText) {
            if ($actionText === '') {
                continue;
            }
            if (strcasecmp($text, $actionText) === 0) {
                return true;
            }
            if (stripos($text, '[ACTION] ') === 0 && strcasecmp(trim(substr($text, 9)), $actionText) === 0) {
                return true;
            }
        }
    }
    return false;
}

function client_inbox_resolve_context($conexion, $ownerScope, $threadType, $requestId, $itemId, $threadIdInput)
{
    $threadType = strtoupper(trim((string)$threadType));
    $requestId = (int)$requestId;
    $itemId = (int)$itemId;
    $threadIdInput = trim((string)$threadIdInput);

    if ($threadIdInput !== '') {
        $parsed = inbox_parse_thread_id($threadIdInput);
        if (empty($parsed['ok'])) {
            return ['ok' => false, 'message' => 'invalid_thread_id', 'status' => 422];
        }
        $threadType = (string)$parsed['thread_type'];
        if ($threadType === 'CARE') {
            $requestId = (int)$parsed['request_id'];
            $itemId = 0;
        } else {
            $itemId = (int)$parsed['item_id'];
        }
    }

    if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
        return ['ok' => false, 'message' => 'invalid_thread_type', 'status' => 422];
    }

    if (!inbox_table_exists($conexion, 'booking_requests')) {
        return ['ok' => false, 'message' => 'booking_requests_not_available', 'status' => 409];
    }

    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasAdditionalNotes = client_table_has_column($conexion, 'booking_requests', 'additional_notes');

    if ($threadType === 'CARE') {
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'invalid_request_id', 'status' => 422];
        }
        $sql = "SELECT br.id AS request_id, br.destination";
        $sql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
        $sql .= " FROM booking_requests br WHERE br.id = ? AND (" . $ownerScope['sql'] . ")";
        if ($hasBookingSoftDelete) {
            $sql .= " AND br.is_deleted = 0";
        }
        $sql .= " LIMIT 1";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'prepare_failed', 'status' => 500];
        }
        $types = 'i' . $ownerScope['types'];
        $params = array_merge([$requestId], $ownerScope['params']);
        if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'message' => 'execute_failed', 'status' => 500];
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'message' => 'forbidden_or_not_found', 'status' => 404];
        }
        return [
            'ok' => true,
            'thread_id' => inbox_thread_id('CARE', $requestId, 0),
            'thread_type' => 'CARE',
            'request_id' => $requestId,
            'item_id' => 0,
            'destination' => (string)($row['destination'] ?? ''),
            'additional_notes' => (string)($row['additional_notes'] ?? ''),
        ];
    }

    if ($itemId <= 0) {
        return ['ok' => false, 'message' => 'invalid_item_id', 'status' => 422];
    }
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        return ['ok' => false, 'message' => 'booking_items_not_available', 'status' => 409];
    }
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');

    $sql = "SELECT bri.id AS item_id, bri.booking_request_id AS request_id, br.destination";
    $sql .= $hasAdditionalNotes ? ", br.additional_notes" : ", '' AS additional_notes";
    $sql .= " FROM booking_request_items bri
             INNER JOIN booking_requests br ON br.id = bri.booking_request_id
             WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= " AND bri.is_deleted = 0";
    }
    if ($hasBookingSoftDelete) {
        $sql .= " AND br.is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'message' => 'prepare_failed', 'status' => 500];
    }
    $types = 'i' . $ownerScope['types'];
    $params = array_merge([$itemId], $ownerScope['params']);
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'message' => 'execute_failed', 'status' => 500];
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return ['ok' => false, 'message' => 'forbidden_or_not_found', 'status' => 404];
    }

    $requestId = (int)($row['request_id'] ?? 0);
    return [
        'ok' => true,
        'thread_id' => inbox_thread_id('ITEM', $requestId, $itemId),
        'thread_type' => 'ITEM',
        'request_id' => $requestId,
        'item_id' => $itemId,
        'destination' => (string)($row['destination'] ?? ''),
        'additional_notes' => (string)($row['additional_notes'] ?? ''),
    ];
}

if (defined('INBOX_BOOTSTRAP_ONLY') && INBOX_BOOTSTRAP_ONLY) {
    return;
}

$clientUserId = get_client_user_id();
if (!isset($conexion) || !$conexion) {
    client_inbox_err('db_not_available', 500);
}

$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    client_inbox_err('booking_owner_scope_unavailable', 409);
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list_threads'));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 200);
if ($limit < 1) {
    $limit = 200;
}
if ($limit > 500) {
    $limit = 500;
}

if ($action === 'list_threads') {
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $threads = [];

    $careSql = "SELECT br.id AS request_id,
                       br.destination,
                       br.created_at
                FROM booking_requests br
                WHERE " . $ownerScope['sql'];
    if ($hasBookingSoftDelete) {
        $careSql .= " AND br.is_deleted = 0";
    }
    $careSql .= " ORDER BY br.created_at DESC LIMIT " . (int)$limit;
    $stmtCare = mysqli_prepare($conexion, $careSql);
    if ($stmtCare) {
        $types = $ownerScope['types'];
        $params = $ownerScope['params'];
        if (inbox_bind_stmt_params($stmtCare, $types, $params) && mysqli_stmt_execute($stmtCare)) {
            $res = mysqli_stmt_get_result($stmtCare);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $requestId = (int)($row['request_id'] ?? 0);
                if ($requestId <= 0) {
                    continue;
                }
                $threads[] = [
                    'thread_id' => inbox_thread_id('CARE', $requestId, 0),
                    'thread_key' => inbox_thread_id('CARE', $requestId, 0),
                    'thread_type' => 'CARE',
                    'request_id' => $requestId,
                    'booking_id' => $requestId,
                    'item_id' => 0,
                    'title' => 'General - Request #' . $requestId,
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
        mysqli_stmt_close($stmtCare);
    }

    if (inbox_table_exists($conexion, 'booking_request_items')) {
        $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $itemSql = "SELECT
                        bri.id AS item_id,
                        bri.booking_request_id AS request_id,
                        bri.item_type,
                        COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                        br.destination,
                        br.created_at
                    FROM booking_request_items bri
                    INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                    LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                    LEFT JOIN service_catalog sc ON sc.id = o.service_id
                    LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                    WHERE " . $ownerScope['sql'];
        if ($hasItemsSoftDelete) {
            $itemSql .= " AND bri.is_deleted = 0";
        }
        if ($hasBookingSoftDelete) {
            $itemSql .= " AND br.is_deleted = 0";
        }
        $itemSql .= " ORDER BY br.created_at DESC, bri.id DESC LIMIT " . (int)$limit;

        $stmtItem = mysqli_prepare($conexion, $itemSql);
        if ($stmtItem) {
            $types = $ownerScope['types'];
            $params = $ownerScope['params'];
            if (inbox_bind_stmt_params($stmtItem, $types, $params) && mysqli_stmt_execute($stmtItem)) {
                $res = mysqli_stmt_get_result($stmtItem);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $requestId = (int)($row['request_id'] ?? 0);
                    $itemId = (int)($row['item_id'] ?? 0);
                    if ($requestId <= 0 || $itemId <= 0) {
                        continue;
                    }
                    $itemName = trim((string)($row['item_name'] ?? ''));
                    if ($itemName === '') {
                        $itemName = 'Item #' . $itemId;
                    }
                    $threads[] = [
                        'thread_id' => inbox_thread_id('ITEM', $requestId, $itemId),
                        'thread_key' => inbox_thread_id('ITEM', $requestId, $itemId),
                        'thread_type' => 'ITEM',
                        'item_type' => (string)($row['item_type'] ?? ''),
                        'request_id' => $requestId,
                        'booking_id' => $requestId,
                        'item_id' => $itemId,
                        'title' => $itemName . ' - Request #' . $requestId,
                        'subtitle' => trim((string)($row['destination'] ?? '')),
                        'updated_at' => (string)($row['created_at'] ?? ''),
                    ];
                }
            }
            mysqli_stmt_close($stmtItem);
        }
    }

    $threads = inbox_enrich_threads_with_meta($conexion, $threads, 'CLIENT', $clientUserId);
    $totalUnread = 0;
    foreach ($threads as $t) {
        $totalUnread += (int)($t['unread_count'] ?? 0);
    }

    client_inbox_ok([
        'threads' => $threads,
        'unread_count' => $totalUnread,
    ]);
}

if ($action === 'list_messages' || $action === 'mark_read' || $action === 'send_message' || $action === 'send_quick_action' || $action === 'send_structured_action' || $action === 'accept_dates' || $action === 'reject_dates' || $action === 'cancel_meeting' || $action === 'final_accept_and_pay' || $action === 'final_decline') {
    $threadIdInput = (string)($_GET['thread_id'] ?? $_POST['thread_id'] ?? '');
    $threadType = (string)($_GET['thread_type'] ?? $_POST['thread_type'] ?? '');
    $requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? $_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
    $itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

    $ctx = client_inbox_resolve_context($conexion, $ownerScope, $threadType, $requestId, $itemId, $threadIdInput);
    if (empty($ctx['ok'])) {
        client_inbox_err((string)($ctx['message'] ?? 'invalid_thread'), (int)($ctx['status'] ?? 400));
    }

    $bookingRequestId = (int)($ctx['request_id'] ?? 0);
    $isCareThread = (strtoupper((string)($ctx['thread_type'] ?? '')) === 'CARE');
    $feeGate = client_inbox_fee_gate_state($conexion, $bookingRequestId);
    $feeLocked = !$isCareThread && !empty($feeGate['fee_locked']);
    $feeRequired = (int)($feeGate['fee_required'] ?? 0);
    $feeStatus = (string)($feeGate['fee_status'] ?? 'pending');
    $commissionGate = commission_gate_status($conexion, $bookingRequestId, (int)($ctx['item_id'] ?? 0));
    $commissionGateEnabled = !empty($commissionGate['enabled']);
    $commissionPaid = !empty($commissionGate['paid']);
    $commissionLocked = $commissionGateEnabled && !$commissionPaid;
    $freeMessageState = client_inbox_free_message_state($conexion, $bookingRequestId, $feeGate);
    $canSendFreeMessage = !empty($freeMessageState['can_send_free_message']);
    if ($action === 'send_message') {
        $messageInput = trim((string)($_POST['message'] ?? ''));
        $structuredAllowlist = [
            'Please confirm availability for my dates.',
            'My dates are flexible.',
            'I have uploaded medical documents.',
            "I don't have the requested documents yet."
        ];
        $isStructured = client_inbox_is_structured_message($messageInput, $structuredAllowlist);
        if (!$isCareThread && !$canSendFreeMessage && !$isStructured) {
            client_inbox_compose_locked('review', $ctx, $clientUserId);
        }
    }
}

if ($action === 'list_messages') {
    $messages = [];
    $sinceId = (int)($_GET['since_id'] ?? $_POST['since_id'] ?? 0);
    if (inbox_table_exists($conexion, 'inbox_messages')) {
        if ($sinceId > 0) {
            $stmt = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? AND id > ? ORDER BY id ASC");
        } else {
            $stmt = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? ORDER BY id ASC");
        }
        if ($stmt) {
            $threadId = (string)$ctx['thread_id'];
            if ($sinceId > 0) {
                mysqli_stmt_bind_param($stmt, 'si', $threadId, $sinceId);
            } else {
                mysqli_stmt_bind_param($stmt, 's', $threadId);
            }
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $messages[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'sender' => inbox_sender_to_ui($row['sender_role'] ?? ''),
                        'body' => (string)($row['body'] ?? ''),
                        'time' => (string)($row['created_at'] ?? ''),
                        'thread_type' => (string)$ctx['thread_type'],
                        'thread_item_id' => (int)$ctx['item_id'],
                    ];
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($sinceId <= 0 && empty($messages) && trim((string)($ctx['additional_notes'] ?? '')) !== '') {
        $legacy = inbox_parse_legacy_messages((string)$ctx['additional_notes']);
        $legacy = inbox_filter_legacy_messages($legacy, (string)$ctx['thread_type'], (int)$ctx['item_id']);
        foreach ($legacy as $idx => $m) {
            $messages[] = [
                'id' => 'legacy-' . ($idx + 1),
                'sender' => (string)($m['sender'] ?? 'system'),
                'body' => (string)($m['body'] ?? ''),
                'time' => (string)($m['time'] ?? ''),
                'thread_type' => (string)$ctx['thread_type'],
                'thread_item_id' => (int)$ctx['item_id'],
            ];
        }
    }

    $hasStructuredItemActions = false;
    $structuredItemId = 0;
    if (strtoupper((string)($ctx['thread_type'] ?? '')) === 'CARE'
        && inbox_table_exists($conexion, 'inbox_messages')
        && inbox_table_exists($conexion, 'booking_request_items')) {
        $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
        $structuredSql = "SELECT im.item_id
                          FROM inbox_messages im
                          INNER JOIN booking_request_items bri ON bri.id = im.item_id
                          INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                          WHERE im.request_id = ?
                            AND im.thread_type = 'ITEM'
                            AND im.item_id > 0
                            AND (" . $ownerScope['sql'] . ")
                            AND (
                                im.body LIKE '[REPLY] PROPOSED_DATES%'
                                OR im.body LIKE '[MEETING_PROPOSAL] %'
                                OR im.body LIKE '[REPLY] FINAL_APPROVED%'
                                OR im.body LIKE '[REQUEST_INFO] %'
                                OR im.body LIKE '[PROPOSE_QUOTE] %'
                            )";
        if ($hasItemsSoftDelete) {
            $structuredSql .= " AND bri.is_deleted = 0";
        }
        if ($hasBookingSoftDelete) {
            $structuredSql .= " AND br.is_deleted = 0";
        }
        $structuredSql .= " ORDER BY im.id ASC LIMIT 1";

        $stmtStructured = mysqli_prepare($conexion, $structuredSql);
        if ($stmtStructured) {
            $types = 'i' . $ownerScope['types'];
            $params = array_merge([(int)$ctx['request_id']], $ownerScope['params']);
            if (inbox_bind_stmt_params($stmtStructured, $types, $params) && mysqli_stmt_execute($stmtStructured)) {
                $structuredRes = mysqli_stmt_get_result($stmtStructured);
                $structuredRow = $structuredRes ? mysqli_fetch_assoc($structuredRes) : null;
                if ($structuredRow) {
                    $structuredItemId = (int)($structuredRow['item_id'] ?? 0);
                    $hasStructuredItemActions = $structuredItemId > 0;
                }
            }
            mysqli_stmt_close($stmtStructured);
        }
    }

    $documents = [];
    $threadTypeUpper = strtoupper((string)($ctx['thread_type'] ?? ''));
    $isItemThreadForDocs = ($threadTypeUpper === 'ITEM') && ((int)($ctx['item_id'] ?? 0) > 0);
    $isCareThreadForDocs = ($threadTypeUpper === 'CARE');
    if (($isItemThreadForDocs || $isCareThreadForDocs) && client_table_exists($conexion, 'client_documents')) {
        $docHasRequestId = client_table_has_column($conexion, 'client_documents', 'booking_request_id');
        $docHasItemId = client_table_has_column($conexion, 'client_documents', 'item_id');
        if ($docHasRequestId && $docHasItemId) {
            $docHasClientUserId = client_table_has_column($conexion, 'client_documents', 'client_user_id');
            $clientesHasClientUserId = client_table_has_column($conexion, 'clientes', 'client_user_id');
            $clientesHasUserId = client_table_has_column($conexion, 'clientes', 'user_id');
            $clientesMapCol = $clientesHasClientUserId ? 'client_user_id' : ($clientesHasUserId ? 'user_id' : '');

            $selectCols = ['id', 'file_path', 'filename', 'original_filename', 'document_type', 'booking_request_id', 'item_id'];
            if (client_table_has_column($conexion, 'client_documents', 'file_size')) {
                $selectCols[] = 'file_size';
            }
            if (client_table_has_column($conexion, 'client_documents', 'mime_type')) {
                $selectCols[] = 'mime_type';
            }
            if (client_table_has_column($conexion, 'client_documents', 'title')) {
                $selectCols[] = 'title';
            }
            if (client_table_has_column($conexion, 'client_documents', 'description')) {
                $selectCols[] = 'description';
            }
            if (client_table_has_column($conexion, 'client_documents', 'uploaded_at')) {
                $selectCols[] = 'uploaded_at';
            }
            if (client_table_has_column($conexion, 'client_documents', 'created_at')) {
                $selectCols[] = 'created_at';
            }
            $orderByColumn = client_table_has_column($conexion, 'client_documents', 'uploaded_at') ? 'uploaded_at' : 'id';

            $docSql = "SELECT " . implode(', ', $selectCols) . " FROM client_documents cd";
            $docTypes = '';
            $docParams = [];

            if ($docHasClientUserId && $clientUserId > 0) {
                $docSql .= " WHERE (cd.client_user_id = ?";
                $docTypes .= 'i';
                $docParams[] = $clientUserId;
                if ($clientesMapCol !== '') {
                    $docSql .= " OR (cd.client_user_id IS NULL AND EXISTS (SELECT 1 FROM clientes c WHERE c.id = cd.client_id AND c." . $clientesMapCol . " = ?))";
                    $docTypes .= 'i';
                    $docParams[] = $clientUserId;
                }
                $docSql .= ")";
            } else {
                $clientIdForDocs = 0;
                if ($clientesMapCol !== '') {
                    $stmtClient = mysqli_prepare($conexion, "SELECT id FROM clientes WHERE " . $clientesMapCol . " = ? ORDER BY id DESC LIMIT 1");
                    if ($stmtClient) {
                        mysqli_stmt_bind_param($stmtClient, 'i', $clientUserId);
                        if (mysqli_stmt_execute($stmtClient)) {
                            $resClient = mysqli_stmt_get_result($stmtClient);
                            $rowClient = $resClient ? mysqli_fetch_assoc($resClient) : null;
                            if ($rowClient) {
                                $clientIdForDocs = (int)($rowClient['id'] ?? 0);
                            }
                        }
                        mysqli_stmt_close($stmtClient);
                    }
                }
                if ($clientIdForDocs > 0) {
                    $docSql .= " WHERE cd.client_id = ?";
                    $docTypes .= 'i';
                    $docParams[] = $clientIdForDocs;
                } else {
                    $docSql .= " WHERE 1=1"; // booking_request_id filter below scopes the query
                }
            }

            $docSql .= " AND cd.booking_request_id = ?";
            $docTypes .= 'i';
            $docParams[] = (int)$ctx['request_id'];

            if ($isItemThreadForDocs && (int)($ctx['item_id'] ?? 0) > 0) {
                $docSql .= " AND (cd.item_id = ? OR cd.item_id IS NULL)";
                $docTypes .= 'i';
                $docParams[] = (int)$ctx['item_id'];
            } elseif ($isCareThreadForDocs) {
                $docSql .= " AND (cd.item_id IS NULL OR cd.item_id = 0)";
            }

            $docSql .= " ORDER BY " . $orderByColumn . " DESC";

            $stmtDocs = mysqli_prepare($conexion, $docSql);
            if ($stmtDocs) {
                if (inbox_bind_stmt_params($stmtDocs, $docTypes, $docParams) && mysqli_stmt_execute($stmtDocs)) {
                    $docRes = mysqli_stmt_get_result($stmtDocs);
                    while ($docRes && ($docRow = mysqli_fetch_assoc($docRes))) {
                        $filePath = preg_replace('/\\\\+/', '/', (string)($docRow['file_path'] ?? ''));
                        $filePath = ltrim($filePath, '/');
                        if (stripos($filePath, 'uploads/medical_docs/') === 0) {
                            $filePath = substr($filePath, strlen('uploads/medical_docs/'));
                        }
                        if (stripos($filePath, 'medical_docs/') === 0) {
                            $filePath = substr($filePath, strlen('medical_docs/'));
                        }
                        $filePath = ltrim((string)$filePath, '/');
                        $docRow['download_url'] = $filePath !== '' ? '/uploads/medical_docs/' . $filePath : '';
                        $documents[] = $docRow;
                    }
                }
                mysqli_stmt_close($stmtDocs);
            }
        }
    }

    client_inbox_ok([
        'thread_id' => $ctx['thread_id'],
        'thread_type' => $ctx['thread_type'],
        'request_id' => (int)$ctx['request_id'],
        'booking_id' => (int)$ctx['request_id'],
        'item_id' => (int)$ctx['item_id'],
        'since_id' => $sinceId,
        'has_structured_item_actions' => $hasStructuredItemActions,
        'structured_item_id' => $structuredItemId,
        'fee_required' => $feeRequired,
        'fee_status' => $feeStatus,
        'fee_locked' => !empty($feeLocked),
        'fee_message' => !empty($feeLocked)
            ? 'Messaging remains available here. The coordination fee may still unlock additional downstream steps.'
            : '',
        'commission_gate_enabled' => $commissionGateEnabled ? 1 : 0,
        'commission_paid' => $commissionPaid ? 1 : 0,
        'commission_message' => $commissionLocked
            ? 'Messaging remains available in Inbox. The commission payment may still unlock provider details or other downstream steps.'
            : '',
        'can_send_free_message' => !empty($freeMessageState['can_send_free_message']),
        'free_message_blocked_reason' => (string)($freeMessageState['blocked_reason'] ?? ''),
        'free_message_notice' => (string)($freeMessageState['notice'] ?? ''),
        'documents' => $documents,
        'messages' => $messages,
    ]);
}

if ($action === 'send_message') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_SEND_MESSAGE_ENTER endpoint=client/ajax/inbox.php action=send_message'
            . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
            . ' request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
        );
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        client_inbox_err('message_required', 422);
    }
    if (mb_strlen($message) > 2000) {
        client_inbox_err('message_too_long', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmt) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    $itemId = (int)$ctx['item_id'];
    mysqli_stmt_bind_param($stmt, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_provider_email($conexion, $itemId, $emailSource);
        mt_email_debug_log(
            'CLIENT_NOTIFY_PROVIDER_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        $notifyResult = notify_new_message_to_provider(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            'CLIENT',
            $message,
            $resolvedEmail,
            $emailSource
        );
        mt_email_debug_log('CLIENT_NOTIFY_PROVIDER_DONE result=' . json_encode($notifyResult));
    } else {
        notify_new_message_to_provider($conexion, $requestId, $itemId, $threadType, 'CLIENT', $message);
    }

    client_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_id' => $requestId,
        'item_id' => $itemId,
        'message' => [
            'id' => $messageId,
            'sender' => 'client',
            'body' => $message,
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'send_quick_action') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_SEND_QUICK_ACTION_ENTER endpoint=client/ajax/inbox.php action=send_quick_action'
            . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
            . ' request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
        );
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }

    $actionKey = strtoupper(trim((string)($_POST['action_key'] ?? '')));
    $quickActions = [
        'REQUEST_AVAILABILITY' => 'Please confirm availability for my dates.',
        'DATES_FLEXIBLE' => 'My dates are flexible.',
        'DOCS_UPLOADED' => 'I have uploaded medical documents.',
        'DOCS_NOT_AVAILABLE' => 'I don\'t have the requested documents yet.'
    ];
    if ($actionKey === '' || !isset($quickActions[$actionKey])) {
        client_inbox_err('invalid_action_key', 422);
    }

    $message = '[ACTION] ' . $quickActions[$actionKey];
    if (mb_strlen($message) > 2000) {
        client_inbox_err('message_too_long', 422);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmt) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    $itemId = (int)$ctx['item_id'];
    mysqli_stmt_bind_param($stmt, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_provider_email($conexion, $itemId, $emailSource);
        mt_email_debug_log(
            'CLIENT_NOTIFY_PROVIDER_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        $notifyResult = notify_new_message_to_provider(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            'CLIENT',
            $message,
            $resolvedEmail,
            $emailSource
        );
        mt_email_debug_log('CLIENT_NOTIFY_PROVIDER_DONE result=' . json_encode($notifyResult));
    } else {
        notify_new_message_to_provider($conexion, $requestId, $itemId, $threadType, 'CLIENT', $message);
    }

    client_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_id' => $requestId,
        'item_id' => $itemId,
        'message' => [
            'id' => $messageId,
            'sender' => 'client',
            'body' => $message,
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'send_structured_action') {
    if (function_exists('mt_email_debug_log')) {
        mt_email_debug_log(
            'CLIENT_SEND_STRUCTURED_ENTER endpoint=client/ajax/inbox.php action=send_structured_action'
            . ' thread_id=' . (string)($ctx['thread_id'] ?? '')
            . ' request_id=' . (int)($ctx['request_id'] ?? 0)
            . ' item_id=' . (int)($ctx['item_id'] ?? 0)
            . ' thread_type=' . (string)($ctx['thread_type'] ?? '')
        );
    }
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        client_inbox_err('booking_items_not_available', 409);
    }
    if (!client_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        client_inbox_err('item_status_not_available', 409);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        client_inbox_err('invalid_thread_type', 422);
    }

    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($itemId <= 0) {
        client_inbox_err('invalid_item_id', 422);
    }

    $actionType = strtoupper(trim((string)($_POST['action_type'] ?? '')));
    $allowedTypes = ['ACCEPT_PROPOSAL', 'REQUEST_CHANGES', 'REJECT_PROPOSAL', 'DOCS_NOT_AVAILABLE'];
    if (!in_array($actionType, $allowedTypes, true)) {
        client_inbox_err('invalid_action_type', 422);
    }

    $notes = trim((string)($_POST['notes'] ?? ''));
    if (mb_strlen($notes) > 500) {
        client_inbox_err('notes_too_long', 422);
    }

    $targetStatus = '';
    $shouldUpdateStatus = ($actionType !== 'DOCS_NOT_AVAILABLE');
    if ($actionType === 'ACCEPT_PROPOSAL') {
        $targetStatus = 'client_accepted';
    } elseif ($actionType === 'REQUEST_CHANGES') {
        $targetStatus = 'provider_proposed_change';
    } elseif ($actionType === 'REJECT_PROPOSAL') {
        $targetStatus = 'client_rejected';
    }

    if ($shouldUpdateStatus) {
        $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
        $hasItemUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');

        $sql = "UPDATE booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                SET bri.item_status = ?";
        if ($hasItemUpdatedAt) {
            $sql .= ', bri.updated_at = NOW()';
        }
        $sql .= " WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
        if ($hasItemsSoftDelete) {
            $sql .= ' AND bri.is_deleted = 0';
        }
        if ($hasBookingSoftDelete) {
            $sql .= ' AND br.is_deleted = 0';
        }
        $sql .= ' LIMIT 1';

        $types = 'si' . $ownerScope['types'];
        $params = array_merge([$targetStatus, $itemId], $ownerScope['params']);

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            client_inbox_err('prepare_failed', 500);
        }
        if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            client_inbox_err('update_failed: ' . $err, 500);
        }
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($affected <= 0) {
            client_inbox_err('not_found_or_no_change', 404);
        }
    }

    $payload = [
        'action_type' => $actionType,
        'notes' => $notes,
    ];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        client_inbox_err('payload_encode_failed', 500);
    }
    $message = '[PROPOSAL_RESPONSE] ' . $payloadJson;

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmtMsg) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    mysqli_stmt_bind_param($stmtMsg, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);
    $createdAt = date('Y-m-d H:i:s');

    if (function_exists('mt_email_debug_log')) {
        $emailSource = '';
        $resolvedEmail = interaction_email_fetch_provider_email($conexion, $itemId, $emailSource);
        mt_email_debug_log(
            'CLIENT_NOTIFY_PROVIDER_START resolved_email=' . (string)$resolvedEmail
            . ' source=' . (string)$emailSource
        );
        $notifyResult = notify_new_message_to_provider(
            $conexion,
            $requestId,
            $itemId,
            $threadType,
            'CLIENT',
            $message,
            $resolvedEmail,
            $emailSource
        );
        mt_email_debug_log('CLIENT_NOTIFY_PROVIDER_DONE result=' . json_encode($notifyResult));
    } else {
        notify_new_message_to_provider($conexion, $requestId, $itemId, $threadType, 'CLIENT', $message);
    }

    $responsePayload = [
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_id' => $requestId,
        'item_id' => $itemId,
        'message' => [
            'id' => $messageId,
            'sender' => 'client',
            'body' => $message,
            'time' => $createdAt,
        ],
    ];
    if ($shouldUpdateStatus) {
        $responsePayload['item_status'] = $targetStatus;
    }

    client_inbox_ok($responsePayload);
}

if ($action === 'accept_dates' || $action === 'reject_dates') {
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        client_inbox_err('booking_items_not_available', 409);
    }
    if (!client_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        client_inbox_err('item_status_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        client_inbox_err('invalid_thread_type', 422);
    }

    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($itemId <= 0) {
        client_inbox_err('invalid_item_id', 422);
    }

    $meetingRow = client_inbox_fetch_latest_proposed_meeting($conexion, $itemId, $ownerScope);
    if (!$meetingRow) {
        client_inbox_err('meeting_proposal_not_found', 409);
    }

    mysqli_begin_transaction($conexion);

    $confirmedMeeting = null;
    $syncedItemStatus = '';
    if ($action === 'accept_dates') {
        $confirmedMeeting = client_inbox_confirm_google_meeting($conexion, $meetingRow, (int)$ctx['request_id'], $itemId);
        if (empty($confirmedMeeting['ok'])) {
            mysqli_rollback($conexion);
            client_inbox_err((string)($confirmedMeeting['error'] ?? 'meeting_google_create_failed'), 409);
        }

        $syncResult = google_calendar_sync_item_status_for_transition($conexion, $itemId, 'appointment_confirmed');
        if (empty($syncResult['ok'])) {
            mysqli_rollback($conexion);
            client_inbox_err((string)($syncResult['error'] ?? 'item_status_sync_failed'), 409);
        }
        $syncedItemStatus = (string)($syncResult['item_status'] ?? 'provider_confirmed');
    } else {
        client_inbox_cancel_proposed_meeting($conexion, (int)($meetingRow['id'] ?? 0));

        $syncResult = google_calendar_sync_item_status_for_transition($conexion, $itemId, 'appointment_requested_change');
        if (empty($syncResult['ok'])) {
            mysqli_rollback($conexion);
            client_inbox_err((string)($syncResult['error'] ?? 'item_status_sync_failed'), 409);
        }
        $syncedItemStatus = (string)($syncResult['item_status'] ?? 'provider_proposed_change');
    }

    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        mysqli_rollback($conexion);
        client_inbox_err('inbox_messages_not_available', 409);
    }

    $message = ($action === 'accept_dates')
        ? '[MEETING_CONFIRMED] ' . json_encode([
            'calendar_event_id' => (int)($confirmedMeeting['calendar_event_id'] ?? 0),
            'start_at' => (string)($confirmedMeeting['start_at'] ?? ''),
            'end_at' => (string)($confirmedMeeting['end_at'] ?? ''),
            'integration_mode' => (string)($confirmedMeeting['integration_mode'] ?? ''),
            'event_id' => (string)($confirmedMeeting['event_id'] ?? ''),
            'html_link' => (string)($confirmedMeeting['html_link'] ?? ''),
            'meet_url' => (string)($confirmedMeeting['meet_url'] ?? ''),
            'organizer_email' => (string)($confirmedMeeting['organizer_email'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '[ACTION] Client rejected proposed dates';

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmtMsg) {
        mysqli_rollback($conexion);
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    mysqli_stmt_bind_param($stmtMsg, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        mysqli_rollback($conexion);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);
    $createdAt = date('Y-m-d H:i:s');

    mysqli_commit($conexion);

    client_inbox_ok([
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_id' => $requestId,
        'item_id' => $itemId,
        'item_status' => $syncedItemStatus,
        'meeting' => $confirmedMeeting,
        'message' => [
            'id' => $messageId,
            'sender' => 'client',
            'body' => $message,
            'time' => $createdAt,
        ],
    ]);
}

if ($action === 'cancel_meeting') {
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        client_inbox_err('booking_items_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        client_inbox_err('invalid_thread_type', 422);
    }

    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($itemId <= 0) {
        client_inbox_err('invalid_item_id', 422);
    }

    $meetingRow = client_inbox_fetch_latest_confirmed_meeting($conexion, $itemId, $ownerScope);
    if (!$meetingRow) {
        client_inbox_err('confirmed_meeting_not_found', 409);
    }

    $cancelResult = google_calendar_cancel_item_meeting($conexion, $itemId, [
        'role' => 'CLIENT',
        'user_id' => $clientUserId,
    ]);
    if (empty($cancelResult['ok'])) {
        client_inbox_err((string)($cancelResult['error'] ?? 'meeting_cancel_failed'), 409);
    }

    client_inbox_ok([
        'thread_id' => (string)($ctx['thread_id'] ?? ''),
        'thread_type' => (string)($ctx['thread_type'] ?? 'ITEM'),
        'request_id' => (int)($ctx['request_id'] ?? 0),
        'booking_id' => (int)($ctx['request_id'] ?? 0),
        'item_id' => $itemId,
        'meeting' => [
            'status' => 'cancelled',
            'calendar_event_id' => (int)($cancelResult['calendar_event_id'] ?? 0),
            'event_id' => (string)($cancelResult['google_event_id'] ?? ''),
            'start_at' => (string)($cancelResult['start_at'] ?? ''),
            'end_at' => (string)($cancelResult['end_at'] ?? ''),
            'integration_mode' => (string)($cancelResult['integration_mode'] ?? ''),
            'cancelled_by_role' => (string)($cancelResult['cancelled_by_role'] ?? 'CLIENT'),
        ],
        'message' => [
            'id' => (int)($cancelResult['message_id'] ?? 0),
            'sender' => 'client',
            'body' => (string)($cancelResult['message_body'] ?? ''),
            'time' => date('Y-m-d H:i:s'),
        ],
    ]);
}

if ($action === 'final_accept_and_pay' || $action === 'final_decline') {
    if (!inbox_table_exists($conexion, 'booking_request_items')) {
        client_inbox_err('booking_items_not_available', 409);
    }
    if (!client_table_has_column($conexion, 'booking_request_items', 'item_status')) {
        client_inbox_err('item_status_not_available', 409);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_messages_not_available', 409);
    }

    if (strtoupper((string)($ctx['thread_type'] ?? '')) !== 'ITEM') {
        client_inbox_err('invalid_thread_type', 422);
    }

    $itemId = (int)($ctx['item_id'] ?? 0);
    if ($itemId <= 0) {
        client_inbox_err('invalid_item_id', 422);
    }

    if ($action === 'final_accept_and_pay') {
        $threadId = (string)$ctx['thread_id'];
        $stmtCheck = mysqli_prepare($conexion, "SELECT id FROM inbox_messages WHERE thread_id = ? AND body LIKE '[REPLY] FINAL_APPROVED%' LIMIT 1");
        if (!$stmtCheck) {
            client_inbox_err('prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmtCheck, 's', $threadId);
        if (!mysqli_stmt_execute($stmtCheck)) {
            $err = mysqli_stmt_error($stmtCheck);
            mysqli_stmt_close($stmtCheck);
            client_inbox_err('check_failed: ' . $err, 500);
        }
        $resCheck = mysqli_stmt_get_result($stmtCheck);
        $hasApproved = $resCheck && mysqli_fetch_assoc($resCheck);
        mysqli_stmt_close($stmtCheck);
        if (!$hasApproved) {
            client_inbox_err('final_approval_required', 409);
        }
    }

    $targetStatus = ($action === 'final_accept_and_pay') ? 'client_accepted' : 'client_rejected';
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
    $hasItemUpdatedAt = client_table_has_column($conexion, 'booking_request_items', 'updated_at');

    mysqli_begin_transaction($conexion);

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET bri.item_status = ?";
    if ($hasItemUpdatedAt) {
        $sql .= ', bri.updated_at = NOW()';
    }
    $sql .= " WHERE bri.id = ? AND (" . $ownerScope['sql'] . ")";
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasBookingSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $types = 'si' . $ownerScope['types'];
    $params = array_merge([$targetStatus, $itemId], $ownerScope['params']);

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        mysqli_rollback($conexion);
        client_inbox_err('prepare_failed', 500);
    }
    if (!inbox_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_rollback($conexion);
        client_inbox_err('update_failed: ' . $err, 500);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected <= 0) {
        mysqli_rollback($conexion);
        client_inbox_err('not_found_or_no_change', 404);
    }

    $meetingPayload = null;
    if ($action === 'final_accept_and_pay') {
        $proposedMeeting = client_inbox_fetch_latest_proposed_meeting($conexion, $itemId, $ownerScope);
        if ($proposedMeeting && client_inbox_meeting_has_defined_schedule($proposedMeeting)) {
            $meetingPayload = client_inbox_confirm_google_meeting($conexion, $proposedMeeting, (int)$ctx['request_id'], $itemId, [
                'allow_internal_fallback' => true,
            ]);
            if (empty($meetingPayload['ok'])) {
                mysqli_rollback($conexion);
                client_inbox_err((string)($meetingPayload['error'] ?? 'meeting_confirm_failed'), 409);
            }
        } else {
            $confirmedMeeting = client_inbox_fetch_latest_confirmed_meeting($conexion, $itemId, $ownerScope);
            if ($confirmedMeeting && client_inbox_meeting_has_defined_schedule($confirmedMeeting)) {
                $meetingPayload = [
                    'ok' => true,
                    'calendar_event_id' => (int)($confirmedMeeting['id'] ?? 0),
                    'event_id' => (string)($confirmedMeeting['google_event_id'] ?? ''),
                    'html_link' => (string)($confirmedMeeting['google_html_link'] ?? ''),
                    'meet_url' => (string)($confirmedMeeting['google_meet_url'] ?? ''),
                    'organizer_email' => (string)($confirmedMeeting['organizer_email'] ?? ''),
                    'integration_mode' => (string)($confirmedMeeting['integration_mode'] ?? 'internal_only'),
                    'start_at' => (string)($confirmedMeeting['start_at'] ?? ''),
                    'end_at' => (string)($confirmedMeeting['end_at'] ?? ''),
                ];
            }
        }
    }

    $message = ($action === 'final_accept_and_pay')
        ? '[ACTION] FINAL_ACCEPT_AND_PAY'
        : '[ACTION] FINAL_DECLINE';

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, ?, ?, ?, 'CLIENT', ?, ?)"
    );
    if (!$stmtMsg) {
        mysqli_rollback($conexion);
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    $threadType = (string)$ctx['thread_type'];
    $requestId = (int)$ctx['request_id'];
    mysqli_stmt_bind_param($stmtMsg, 'ssiiis', $threadId, $threadType, $requestId, $itemId, $clientUserId, $message);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        mysqli_rollback($conexion);
        client_inbox_err('insert_failed: ' . $err, 500);
    }
    $messageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);
    $createdAt = date('Y-m-d H:i:s');

    mysqli_commit($conexion);

    $response = [
        'thread_id' => $threadId,
        'thread_type' => $threadType,
        'request_id' => $requestId,
        'booking_id' => $requestId,
        'item_id' => $itemId,
        'item_status' => $targetStatus,
        'message' => [
            'id' => $messageId,
            'sender' => 'client',
            'body' => $message,
            'time' => $createdAt,
        ],
    ];
    if ($meetingPayload && !empty($meetingPayload['ok'])) {
        $response['meeting'] = $meetingPayload;
    }

    client_inbox_ok($response);
}

if ($action === 'mark_read') {
    if (!inbox_table_exists($conexion, 'inbox_thread_reads') || !inbox_table_exists($conexion, 'inbox_messages')) {
        client_inbox_err('inbox_read_state_not_available', 409);
    }

    $maxId = 0;
    $stmtMax = mysqli_prepare($conexion, "SELECT COALESCE(MAX(id), 0) AS max_id FROM inbox_messages WHERE thread_id = ?");
    if ($stmtMax) {
        $threadId = (string)$ctx['thread_id'];
        mysqli_stmt_bind_param($stmtMax, 's', $threadId);
        if (mysqli_stmt_execute($stmtMax)) {
            $resMax = mysqli_stmt_get_result($stmtMax);
            $rowMax = $resMax ? mysqli_fetch_assoc($resMax) : null;
            $maxId = (int)($rowMax['max_id'] ?? 0);
        }
        mysqli_stmt_close($stmtMax);
    }

    $upsert = "INSERT INTO inbox_thread_reads (thread_id, reader_role, reader_user_id, last_read_message_id, last_read_at)
               VALUES (?, 'CLIENT', ?, ?, NOW())
               ON DUPLICATE KEY UPDATE
                 last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), VALUES(last_read_message_id)),
                 last_read_at = NOW()";
    $stmtUpsert = mysqli_prepare($conexion, $upsert);
    if (!$stmtUpsert) {
        client_inbox_err('prepare_failed', 500);
    }
    $threadId = (string)$ctx['thread_id'];
    mysqli_stmt_bind_param($stmtUpsert, 'sii', $threadId, $clientUserId, $maxId);
    if (!mysqli_stmt_execute($stmtUpsert)) {
        $err = mysqli_stmt_error($stmtUpsert);
        mysqli_stmt_close($stmtUpsert);
        client_inbox_err('mark_read_failed: ' . $err, 500);
    }
    mysqli_stmt_close($stmtUpsert);

    client_inbox_ok([
        'thread_id' => $threadId,
        'last_read_message_id' => $maxId,
    ]);
}

client_inbox_err('invalid_action', 400);
