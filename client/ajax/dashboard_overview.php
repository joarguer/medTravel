<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth_client.php';
require_client_auth_ajax();
require_once __DIR__ . '/../../admin/include/conexion.php';
require_once __DIR__ . '/../include/client_notifications.php';

function client_dashboard_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function client_dashboard_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function client_dashboard_build_in_clause($count)
{
    $count = (int)$count;
    if ($count <= 0) {
        return '';
    }
    return implode(',', array_fill(0, $count, '?'));
}

function client_dashboard_bind_stmt_params($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [$stmt, &$types];
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function client_dashboard_normalize_item_status($status)
{
    $status = trim((string)$status);
    if ($status === '' || $status === 'pending_admin' || $status === 'pending_review') {
        return 'pending_provider';
    }
    return $status;
}

function client_dashboard_item_name_expr($conexion)
{
    $hasOffersTable = client_table_exists($conexion, 'provider_service_offers');
    $hasCatalogTable = client_table_exists($conexion, 'service_catalog');
    $hasComplementaryTable = client_table_exists($conexion, 'medtravel_services_catalog');
    $hasItemOfferId = client_table_has_column($conexion, 'booking_request_items', 'offer_id');
    $hasItemServiceId = client_table_has_column($conexion, 'booking_request_items', 'medtravel_service_id');
    $hasItemType = client_table_has_column($conexion, 'booking_request_items', 'item_type');

    $medicalNameExpr = "CONCAT('Offer #', bri.offer_id)";
    if ($hasItemOfferId && $hasOffersTable && $hasCatalogTable) {
        $medicalNameExpr = "COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), CONCAT('Offer #', bri.offer_id))";
    } elseif ($hasItemOfferId && $hasOffersTable) {
        $medicalNameExpr = "COALESCE(NULLIF(o.title, ''), CONCAT('Offer #', bri.offer_id))";
    }

    $complementaryNameExpr = "CONCAT('Service #', bri.medtravel_service_id)";
    if ($hasItemServiceId && $hasComplementaryTable) {
        $complementaryNameExpr = "COALESCE(NULLIF(ms.service_name, ''), CONCAT('Service #', bri.medtravel_service_id))";
    }

    if ($hasItemType && $hasItemOfferId && $hasItemServiceId) {
        return "CASE
            WHEN bri.item_type = 'medical_offer' THEN {$medicalNameExpr}
            WHEN bri.item_type = 'complementary_service' THEN {$complementaryNameExpr}
            ELSE CONCAT('Item #', bri.id)
        END";
    }

    return "CONCAT('Item #', bri.id)";
}

function client_dashboard_parse_request_info_payload($body, $prefix)
{
    $body = trim((string)$body);
    $prefix = trim((string)$prefix);
    if ($body === '' || $prefix === '' || strpos($body, $prefix) !== 0) {
        return null;
    }
    $json = trim(substr($body, strlen($prefix)));
    if ($json === '') {
        return null;
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
}

function client_dashboard_requested_document_label($type)
{
    $type = strtolower(trim((string)$type));
    $map = [
        'labs' => 'lab results',
        'lab_results' => 'lab results',
        'imaging' => 'diagnostic images',
        'diagnostic_imaging' => 'diagnostic images',
        'photos' => 'clinical photos',
        'medical_history' => 'medical history',
        'history' => 'medical history',
        'other' => 'documents',
    ];
    return isset($map[$type]) ? $map[$type] : 'documents';
}

function client_dashboard_event_mode($eventRow)
{
    $googleMeetUrl = trim((string)($eventRow['google_meet_url'] ?? ''));
    $integrationMode = strtolower(trim((string)($eventRow['integration_mode'] ?? '')));
    if ($googleMeetUrl !== '' || $integrationMode === 'calendar_plus_meet') {
        return [
            'key' => 'virtual',
            'label' => 'Virtual appointment',
        ];
    }

    return [
        'key' => '',
        'label' => '',
    ];
}

function client_dashboard_build_actions($booking, $phaseKey)
{
    $requestId = (int)($booking['id'] ?? 0);
    $primaryItemId = (int)($booking['primary_item_id'] ?? 0);
    $actionItemId = (int)($booking['action_item_id'] ?? 0);
    $inboxItemId = $actionItemId > 0 ? $actionItemId : $primaryItemId;

    $requestUrl = '/client/request_detail.php?id=' . $requestId;
    $calendarUrl = '/client/app_calendar.php?request_id=' . $requestId;
    $careInboxUrl = '/client/app_inbox.php?request_id=' . $requestId . '&thread_type=CARE';
    $itemInboxUrl = $inboxItemId > 0
        ? '/client/app_inbox.php?request_id=' . $requestId . '&thread_type=ITEM&item_id=' . $inboxItemId
        : $careInboxUrl;

    $primary = [
        'label' => 'View case',
        'url' => $requestUrl,
    ];
    $secondary = [
        [
            'label' => 'Open messages',
            'url' => $itemInboxUrl,
        ],
    ];

    if ($phaseKey === 'appointment_review') {
        $primary = [
            'label' => 'Review appointment',
            'url' => $calendarUrl,
        ];
    } elseif ($phaseKey === 'appointment_scheduled') {
        $primary = [
            'label' => 'Open appointment details',
            'url' => $calendarUrl,
        ];
    } elseif ($phaseKey === 'docs_requested') {
        $primary = [
            'label' => 'Upload documents',
            'url' => $itemInboxUrl,
        ];
    } elseif ($phaseKey === 'coordinating') {
        $primary = [
            'label' => 'Open messages',
            'url' => $itemInboxUrl,
        ];
    } elseif ($phaseKey === 'reviewing') {
        $primary = [
            'label' => 'View case',
            'url' => $requestUrl,
        ];
    } elseif ($phaseKey === 'closed') {
        $primary = [
            'label' => 'View case',
            'url' => $requestUrl,
        ];
    }

    if (!empty($booking['doc_request']['pending']) && $phaseKey !== 'docs_requested') {
        $secondary[] = [
            'label' => 'Upload documents',
            'url' => $itemInboxUrl,
        ];
    }

    if (($phaseKey === 'appointment_review' || $phaseKey === 'appointment_scheduled') && $calendarUrl !== $primary['url']) {
        $secondary[] = [
            'label' => 'Open calendar',
            'url' => $calendarUrl,
        ];
    }

    return [
        'primary' => $primary,
        'secondary' => $secondary,
    ];
}

function client_dashboard_build_visible_phase(array $booking)
{
    $itemStatuses = array_values(array_unique(array_filter((array)($booking['item_statuses'] ?? []))));
    $requestStatus = strtolower(trim((string)($booking['request_status'] ?? 'pending')));
    $hasPendingProvider = in_array('pending_provider', $itemStatuses, true);
    $hasAwaitingClient = in_array('awaiting_client', $itemStatuses, true);
    $hasProposedChange = in_array('provider_proposed_change', $itemStatuses, true);
    $hasConfirmed = in_array('provider_confirmed', $itemStatuses, true) || in_array('client_accepted', $itemStatuses, true);
    $terminalStatuses = ['provider_rejected', 'client_rejected', 'cancelled'];
    $allTerminal = !empty($itemStatuses) && count(array_diff($itemStatuses, $terminalStatuses)) === 0;
    $nextProposedEvent = $booking['next_proposed_event'] ?? null;
    $nextConfirmedEvent = $booking['next_confirmed_event'] ?? null;
    $docRequest = is_array($booking['doc_request'] ?? null) ? $booking['doc_request'] : ['pending' => false];
    $phaseKey = 'coordinating';
    $headline = 'We are coordinating your next step';
    $description = 'Your MedTravel coordination team is organizing the next step of your care plan.';
    $nextStep = 'No action is needed right now. We will keep you updated here.';
    $requiresAction = false;

    if ($requestStatus === 'cancelled' || $allTerminal) {
        $phaseKey = 'closed';
        $headline = 'This case has been closed';
        $description = 'This request is no longer active in your portal.';
        $nextStep = 'You can review the details of this case if needed.';
    } elseif ($nextProposedEvent || $hasAwaitingClient || $hasProposedChange) {
        $phaseKey = 'appointment_review';
        $headline = 'Please review your appointment';
        $description = 'We have a proposed appointment ready for your review.';
        $nextStep = 'Please confirm the proposed appointment or request a change.';
        $requiresAction = true;
    } elseif (!empty($docRequest['pending'])) {
        $phaseKey = 'docs_requested';
        $headline = 'We are coordinating your next step';
        $description = 'Your care team needs additional information to keep your case moving.';
        $nextStep = (string)($docRequest['summary'] ?? 'Please upload the requested documents.');
        $requiresAction = true;
    } elseif ($nextConfirmedEvent) {
        $phaseKey = 'appointment_scheduled';
        $headline = 'Your appointment is scheduled';
        $description = 'Your next appointment has been confirmed.';
        $nextStep = 'Please review the appointment details and keep them handy.';
    } elseif ($hasPendingProvider) {
        $phaseKey = 'reviewing';
        $headline = 'We are reviewing your case';
        $description = 'Your care team is reviewing your request and provider availability.';
        $nextStep = 'No action is needed right now. We will contact you if we need anything else.';
    } elseif ($hasConfirmed) {
        $phaseKey = 'coordinating';
        $headline = 'We are coordinating your next step';
        $description = 'Your case is active and your next coordination step is in progress.';
        $nextStep = 'No action is needed right now. You can check your messages for updates.';
    }

    $actions = client_dashboard_build_actions($booking, $phaseKey);

    return [
        'key' => $phaseKey,
        'label' => $headline,
        'headline' => $headline,
        'description' => $description,
        'next_step' => $nextStep,
        'requires_action' => $requiresAction ? 1 : 0,
        'primary_cta' => $actions['primary'],
        'secondary_actions' => $actions['secondary'],
        'source' => [
            'request_status' => $requestStatus,
            'item_statuses' => $itemStatuses,
            'has_next_proposed_event' => $nextProposedEvent ? 1 : 0,
            'has_next_confirmed_event' => $nextConfirmedEvent ? 1 : 0,
            'has_pending_docs_request' => !empty($docRequest['pending']) ? 1 : 0,
        ],
    ];
}

function client_dashboard_phase_priority($phaseKey, $requiresAction)
{
    if ((int)$requiresAction === 1) {
        return 100;
    }
    $map = [
        'appointment_scheduled' => 80,
        'reviewing' => 60,
        'coordinating' => 50,
        'closed' => 10,
    ];
    return isset($map[$phaseKey]) ? $map[$phaseKey] : 40;
}

$clientUserId = get_client_user_id();
if (!isset($conexion) || !$conexion || !client_table_exists($conexion, 'booking_requests')) {
    client_dashboard_ok([
        'summary' => [
            'total_requests' => 0,
            'unread_messages' => 0,
            'action_required_count' => 0,
            'scheduled_count' => 0,
            'primary_request' => null,
            'requests' => [],
        ],
    ]);
}

$ownerScope = client_build_booking_owner_scope($conexion, 'br', $clientUserId, client_get_session_email());
if ($ownerScope['sql'] === '1=0') {
    client_dashboard_ok([
        'summary' => [
            'total_requests' => 0,
            'unread_messages' => 0,
            'action_required_count' => 0,
            'scheduled_count' => 0,
            'primary_request' => null,
            'requests' => [],
        ],
    ]);
}

$hasBookingSoftDelete = client_table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasBookingUpdatedAt = client_table_has_column($conexion, 'booking_requests', 'updated_at');
$bookingSql = "SELECT br.id, br.created_at, br.destination";
$bookingSql .= $hasBookingUpdatedAt ? ", br.updated_at" : ", NULL AS updated_at";
$bookingSql .= client_table_has_column($conexion, 'booking_requests', 'status') ? ", br.status" : ", 'pending' AS status";
$bookingSql .= " FROM booking_requests br WHERE " . $ownerScope['sql'];
if ($hasBookingSoftDelete) {
    $bookingSql .= " AND br.is_deleted = 0";
}
$bookingSql .= " ORDER BY COALESCE(br.updated_at, br.created_at) DESC, br.id DESC";

$bookings = [];
$bookingIds = [];
$stmtBookings = mysqli_prepare($conexion, $bookingSql);
if ($stmtBookings) {
    $bookingTypes = $ownerScope['types'];
    $bookingParams = $ownerScope['params'];
    if (client_dashboard_bind_stmt_params($stmtBookings, $bookingTypes, $bookingParams) && mysqli_stmt_execute($stmtBookings)) {
        $res = mysqli_stmt_get_result($stmtBookings);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $bookingId = (int)($row['id'] ?? 0);
            if ($bookingId <= 0) {
                continue;
            }
            $bookingIds[] = $bookingId;
            $createdAt = (string)($row['created_at'] ?? '');
            $updatedAt = trim((string)($row['updated_at'] ?? ''));
            $lastUpdate = $updatedAt !== '' ? $updatedAt : $createdAt;
            $bookings[$bookingId] = [
                'id' => $bookingId,
                'request_status' => client_status_label($row['status'] ?? ''),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'last_update' => $lastUpdate,
                'destination' => (string)($row['destination'] ?? ''),
                'items' => [],
                'item_statuses' => [],
                'primary_item_id' => 0,
                'primary_item_name' => '',
                'action_item_id' => 0,
                'action_item_name' => '',
                'next_confirmed_event' => null,
                'next_proposed_event' => null,
                'latest_confirmed_event' => null,
                'doc_request' => [
                    'pending' => false,
                    'item_id' => 0,
                    'summary' => '',
                    'requested_types' => [],
                ],
            ];
        }
    }
    mysqli_stmt_close($stmtBookings);
}

if (empty($bookings)) {
    $notifPayload = client_fetch_notifications($conexion, $clientUserId, 8);
    client_dashboard_ok([
        'summary' => [
            'total_requests' => 0,
            'unread_messages' => (int)($notifPayload['count'] ?? 0),
            'action_required_count' => 0,
            'scheduled_count' => 0,
            'primary_request' => null,
            'requests' => [],
        ],
    ]);
}

$bookingIds = array_values(array_unique($bookingIds));
$inClause = client_dashboard_build_in_clause(count($bookingIds));

if ($inClause !== '' && client_table_exists($conexion, 'booking_request_items')) {
    $hasItemsSoftDelete = client_table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $hasItemOfferId = client_table_has_column($conexion, 'booking_request_items', 'offer_id');
    $hasItemServiceId = client_table_has_column($conexion, 'booking_request_items', 'medtravel_service_id');
    $hasOffersTable = client_table_exists($conexion, 'provider_service_offers');
    $hasCatalogTable = client_table_exists($conexion, 'service_catalog');
    $hasComplementaryTable = client_table_exists($conexion, 'medtravel_services_catalog');
    $itemStatusExpr = client_table_has_column($conexion, 'booking_request_items', 'item_status')
        ? "CASE
            WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
            ELSE bri.item_status
        END"
        : "'pending_provider'";
    $itemTimeExpr = client_table_has_column($conexion, 'booking_request_items', 'updated_at')
        ? 'COALESCE(bri.updated_at, bri.created_at)'
        : (client_table_has_column($conexion, 'booking_request_items', 'created_at') ? 'bri.created_at' : 'NULL');

    $itemsSql = "SELECT
                    bri.id,
                    bri.booking_request_id,
                    {$itemStatusExpr} AS item_status,
                    {$itemTimeExpr} AS item_event_at,
                    " . client_dashboard_item_name_expr($conexion) . " AS item_name
                 FROM booking_request_items bri";
    if ($hasItemOfferId && $hasOffersTable) {
        $itemsSql .= " LEFT JOIN provider_service_offers o ON o.id = bri.offer_id";
    }
    if ($hasItemOfferId && $hasOffersTable && $hasCatalogTable) {
        $itemsSql .= " LEFT JOIN service_catalog sc ON sc.id = o.service_id";
    }
    if ($hasItemServiceId && $hasComplementaryTable) {
        $itemsSql .= " LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id";
    }
    $itemsSql .= "
                 WHERE bri.booking_request_id IN ({$inClause})";
    if ($hasItemsSoftDelete) {
        $itemsSql .= " AND bri.is_deleted = 0";
    }
    $itemsSql .= " ORDER BY bri.booking_request_id ASC, bri.id ASC";

    $stmtItems = mysqli_prepare($conexion, $itemsSql);
    if ($stmtItems) {
        $itemTypes = str_repeat('i', count($bookingIds));
        $itemParams = $bookingIds;
        if (client_dashboard_bind_stmt_params($stmtItems, $itemTypes, $itemParams) && mysqli_stmt_execute($stmtItems)) {
            $itemsRes = mysqli_stmt_get_result($stmtItems);
            while ($itemsRes && ($itemRow = mysqli_fetch_assoc($itemsRes))) {
                $bookingId = (int)($itemRow['booking_request_id'] ?? 0);
                if (!isset($bookings[$bookingId])) {
                    continue;
                }
                $itemId = (int)($itemRow['id'] ?? 0);
                $itemStatus = client_dashboard_normalize_item_status($itemRow['item_status'] ?? '');
                $itemName = trim((string)($itemRow['item_name'] ?? ''));
                if ($itemName === '') {
                    $itemName = 'Item #' . $itemId;
                }
                $itemEventAt = trim((string)($itemRow['item_event_at'] ?? ''));
                $bookings[$bookingId]['items'][] = [
                    'id' => $itemId,
                    'name' => $itemName,
                    'status' => $itemStatus,
                ];
                $bookings[$bookingId]['item_statuses'][] = $itemStatus;
                if ($bookings[$bookingId]['primary_item_id'] <= 0) {
                    $bookings[$bookingId]['primary_item_id'] = $itemId;
                    $bookings[$bookingId]['primary_item_name'] = $itemName;
                }
                if ($itemEventAt !== '' && strtotime($itemEventAt) > strtotime((string)$bookings[$bookingId]['last_update'])) {
                    $bookings[$bookingId]['last_update'] = $itemEventAt;
                }
            }
        }
        mysqli_stmt_close($stmtItems);
    }
}

if ($inClause !== '' && client_table_exists($conexion, 'calendar_events')) {
    $eventCols = ['ce.request_id', 'ce.item_id', 'ce.status', 'ce.start_at', 'ce.end_at', 'ce.title'];
    if (client_table_has_column($conexion, 'calendar_events', 'integration_mode')) {
        $eventCols[] = 'ce.integration_mode';
    } else {
        $eventCols[] = "'' AS integration_mode";
    }
    if (client_table_has_column($conexion, 'calendar_events', 'google_meet_url')) {
        $eventCols[] = 'ce.google_meet_url';
    } else {
        $eventCols[] = "'' AS google_meet_url";
    }
    $eventsSql = "SELECT " . implode(', ', $eventCols) . "
                  FROM calendar_events ce
                  WHERE ce.request_id IN ({$inClause})
                    AND ce.event_type = 'ITEM'
                  ORDER BY ce.start_at ASC, ce.id ASC";
    $stmtEvents = mysqli_prepare($conexion, $eventsSql);
    if ($stmtEvents) {
        $eventTypes = str_repeat('i', count($bookingIds));
        $eventParams = $bookingIds;
        if (client_dashboard_bind_stmt_params($stmtEvents, $eventTypes, $eventParams) && mysqli_stmt_execute($stmtEvents)) {
            $eventsRes = mysqli_stmt_get_result($stmtEvents);
            $nowTs = time();
            while ($eventsRes && ($eventRow = mysqli_fetch_assoc($eventsRes))) {
                $bookingId = (int)($eventRow['request_id'] ?? 0);
                if (!isset($bookings[$bookingId])) {
                    continue;
                }
                $status = strtolower(trim((string)($eventRow['status'] ?? 'scheduled')));
                if ($status === 'cancelled') {
                    continue;
                }
                $startAt = trim((string)($eventRow['start_at'] ?? ''));
                $eventTs = $startAt !== '' ? strtotime($startAt) : false;
                $eventPayload = [
                    'item_id' => (int)($eventRow['item_id'] ?? 0),
                    'status' => $status,
                    'start_at' => $startAt,
                    'end_at' => (string)($eventRow['end_at'] ?? ''),
                    'title' => (string)($eventRow['title'] ?? ''),
                    'integration_mode' => (string)($eventRow['integration_mode'] ?? ''),
                    'google_meet_url' => (string)($eventRow['google_meet_url'] ?? ''),
                ];
                if ($status === 'confirmed') {
                    if ($bookings[$bookingId]['latest_confirmed_event'] === null) {
                        $bookings[$bookingId]['latest_confirmed_event'] = $eventPayload;
                    } else {
                        $currentTs = strtotime((string)($bookings[$bookingId]['latest_confirmed_event']['start_at'] ?? ''));
                        if ($eventTs !== false && ($currentTs === false || $eventTs > $currentTs)) {
                            $bookings[$bookingId]['latest_confirmed_event'] = $eventPayload;
                        }
                    }
                    if ($eventTs !== false && $eventTs >= $nowTs) {
                        if ($bookings[$bookingId]['next_confirmed_event'] === null) {
                            $bookings[$bookingId]['next_confirmed_event'] = $eventPayload;
                        }
                    }
                } elseif (in_array($status, ['proposed', 'scheduled'], true) && $eventTs !== false && $eventTs >= $nowTs) {
                    if ($bookings[$bookingId]['next_proposed_event'] === null) {
                        $bookings[$bookingId]['next_proposed_event'] = $eventPayload;
                    }
                }
            }
        }
        mysqli_stmt_close($stmtEvents);
    }
}

$docLatestUploads = [];
if (
    $inClause !== ''
    && client_table_exists($conexion, 'client_documents')
    && client_table_has_column($conexion, 'client_documents', 'booking_request_id')
    && client_table_has_column($conexion, 'client_documents', 'item_id')
) {
    $docTimeExpr = client_table_has_column($conexion, 'client_documents', 'uploaded_at')
        ? 'COALESCE(cd.uploaded_at, cd.created_at)'
        : (client_table_has_column($conexion, 'client_documents', 'created_at') ? 'cd.created_at' : 'NULL');
    $docsSql = "SELECT cd.booking_request_id, cd.item_id, MAX({$docTimeExpr}) AS latest_document_at
                FROM client_documents cd
                WHERE cd.booking_request_id IN ({$inClause})
                GROUP BY cd.booking_request_id, cd.item_id";
    $stmtDocs = mysqli_prepare($conexion, $docsSql);
    if ($stmtDocs) {
        $docTypes = str_repeat('i', count($bookingIds));
        $docParams = $bookingIds;
        if (client_dashboard_bind_stmt_params($stmtDocs, $docTypes, $docParams) && mysqli_stmt_execute($stmtDocs)) {
            $docsRes = mysqli_stmt_get_result($stmtDocs);
            while ($docsRes && ($docRow = mysqli_fetch_assoc($docsRes))) {
                $bookingId = (int)($docRow['booking_request_id'] ?? 0);
                $itemId = (int)($docRow['item_id'] ?? 0);
                if (!isset($docLatestUploads[$bookingId])) {
                    $docLatestUploads[$bookingId] = [];
                }
                $docLatestUploads[$bookingId][$itemId] = trim((string)($docRow['latest_document_at'] ?? ''));
            }
        }
        mysqli_stmt_close($stmtDocs);
    }
}

if ($inClause !== '' && client_table_exists($conexion, 'inbox_messages')) {
    $hasMessageCreatedAt = client_table_has_column($conexion, 'inbox_messages', 'created_at');
    $messageTimeExpr = $hasMessageCreatedAt ? 'im.created_at' : 'NULL';
    $messagesSql = "SELECT im.id, im.request_id, im.item_id, im.body, im.sender_role, {$messageTimeExpr} AS created_at
                    FROM inbox_messages im
                    WHERE im.request_id IN ({$inClause})
                      AND im.thread_type = 'ITEM'
                      AND im.item_id > 0
                      AND (
                          im.body LIKE '[REQUEST_INFO] %'
                          OR im.body LIKE '[REPLY] REQUEST LABS%'
                          OR im.body LIKE '[REPLY] REQUEST IMAGING%'
                          OR im.body LIKE '[REPLY] REQUEST PHOTOS%'
                          OR im.body LIKE '[REPLY] REQUEST HISTORY%'
                      )
                    ORDER BY im.id DESC";
    $stmtMessages = mysqli_prepare($conexion, $messagesSql);
    if ($stmtMessages) {
        $messageTypes = str_repeat('i', count($bookingIds));
        $messageParams = $bookingIds;
        if (client_dashboard_bind_stmt_params($stmtMessages, $messageTypes, $messageParams) && mysqli_stmt_execute($stmtMessages)) {
            $messagesRes = mysqli_stmt_get_result($stmtMessages);
            while ($messagesRes && ($messageRow = mysqli_fetch_assoc($messagesRes))) {
                $bookingId = (int)($messageRow['request_id'] ?? 0);
                $itemId = (int)($messageRow['item_id'] ?? 0);
                if (!isset($bookings[$bookingId]) || $itemId <= 0 || !empty($bookings[$bookingId]['doc_request']['pending'])) {
                    continue;
                }

                $createdAt = trim((string)($messageRow['created_at'] ?? ''));
                $latestDocAt = isset($docLatestUploads[$bookingId][$itemId]) ? trim((string)$docLatestUploads[$bookingId][$itemId]) : '';
                if ($createdAt !== '' && $latestDocAt !== '' && strtotime($latestDocAt) >= strtotime($createdAt)) {
                    continue;
                }

                $body = trim((string)($messageRow['body'] ?? ''));
                $summary = 'Please upload the requested documents so we can continue coordinating your case.';
                $requestedTypes = [];
                if (strpos($body, '[REQUEST_INFO] ') === 0) {
                    $payload = client_dashboard_parse_request_info_payload($body, '[REQUEST_INFO]');
                    if (is_array($payload)) {
                        $types = [];
                        foreach ((array)($payload['required_types'] ?? []) as $type) {
                            $label = client_dashboard_requested_document_label($type);
                            if (!in_array($label, $types, true)) {
                                $types[] = $label;
                            }
                        }
                        $requestedTypes = $types;
                        $note = trim((string)($payload['note'] ?? ''));
                        if (!empty($types)) {
                            $summary = 'Please upload ' . implode(', ', $types) . ' to continue with your case.';
                        }
                        if ($note !== '') {
                            $summary .= ' ' . $note;
                        }
                    }
                } else {
                    $simpleMap = [
                        '[REPLY] REQUEST LABS' => 'Please upload your lab results so we can continue with your case.',
                        '[REPLY] REQUEST IMAGING' => 'Please upload your diagnostic images so we can continue with your case.',
                        '[REPLY] REQUEST PHOTOS' => 'Please upload your clinical photos so we can continue with your case.',
                        '[REPLY] REQUEST HISTORY' => 'Please upload your medical history so we can continue with your case.',
                    ];
                    foreach ($simpleMap as $prefix => $labelText) {
                        if (strpos($body, $prefix) === 0) {
                            $summary = $labelText;
                            break;
                        }
                    }
                }

                $bookings[$bookingId]['doc_request'] = [
                    'pending' => true,
                    'item_id' => $itemId,
                    'summary' => $summary,
                    'requested_types' => $requestedTypes,
                ];
                $bookings[$bookingId]['action_item_id'] = $itemId;
                foreach ((array)$bookings[$bookingId]['items'] as $item) {
                    if ((int)($item['id'] ?? 0) === $itemId) {
                        $bookings[$bookingId]['action_item_name'] = (string)($item['name'] ?? '');
                        break;
                    }
                }
            }
        }
        mysqli_stmt_close($stmtMessages);
    }
}

$requests = [];
$actionRequiredCount = 0;
$scheduledCount = 0;
foreach ($bookings as $bookingId => $booking) {
    if ((int)($booking['action_item_id'] ?? 0) <= 0) {
        if (!empty($booking['next_proposed_event']['item_id'])) {
            $booking['action_item_id'] = (int)$booking['next_proposed_event']['item_id'];
        } elseif (!empty($booking['next_confirmed_event']['item_id'])) {
            $booking['action_item_id'] = (int)$booking['next_confirmed_event']['item_id'];
        } else {
            $booking['action_item_id'] = (int)($booking['primary_item_id'] ?? 0);
        }
    }
    if ($booking['action_item_name'] === '' && $booking['action_item_id'] > 0) {
        foreach ((array)$booking['items'] as $item) {
            if ((int)($item['id'] ?? 0) === (int)$booking['action_item_id']) {
                $booking['action_item_name'] = (string)($item['name'] ?? '');
                break;
            }
        }
    }

    $phase = client_dashboard_build_visible_phase($booking);
    $appointment = $booking['next_proposed_event'] ?: $booking['next_confirmed_event'];
    $appointmentPayload = null;
    if ($appointment) {
        $mode = client_dashboard_event_mode($appointment);
        $appointmentPayload = [
            'status' => (string)($appointment['status'] ?? ''),
            'start_at' => (string)($appointment['start_at'] ?? ''),
            'end_at' => (string)($appointment['end_at'] ?? ''),
            'title' => (string)($appointment['title'] ?? ''),
            'mode_key' => (string)($mode['key'] ?? ''),
            'mode_label' => (string)($mode['label'] ?? ''),
        ];
    }

    $serviceTitle = trim((string)($booking['primary_item_name'] ?? ''));
    if ($serviceTitle === '') {
        $serviceTitle = 'MedTravel care journey';
    }
    $phasePriority = client_dashboard_phase_priority((string)($phase['key'] ?? ''), (int)($phase['requires_action'] ?? 0));
    if ((int)($phase['requires_action'] ?? 0) === 1) {
        $actionRequiredCount++;
    }
    if ((string)($phase['key'] ?? '') === 'appointment_scheduled') {
        $scheduledCount++;
    }

    $requests[] = [
        'id' => (int)$booking['id'],
        'service_title' => $serviceTitle,
        'destination' => (string)($booking['destination'] ?? ''),
        'created_at' => (string)($booking['created_at'] ?? ''),
        'last_update' => (string)($booking['last_update'] ?? ''),
        'visible_phase' => $phase,
        'appointment' => $appointmentPayload,
        'items_count' => count((array)($booking['items'] ?? [])),
        'request_status' => (string)($booking['request_status'] ?? ''),
        'doc_request_pending' => !empty($booking['doc_request']['pending']) ? 1 : 0,
        'view_url' => '/client/request_detail.php?id=' . (int)$booking['id'],
        '_priority' => $phasePriority,
    ];
}

usort($requests, function ($a, $b) {
    $priorityDiff = (int)($b['_priority'] ?? 0) - (int)($a['_priority'] ?? 0);
    if ($priorityDiff !== 0) {
        return $priorityDiff;
    }
    $aTs = strtotime((string)($a['last_update'] ?? ''));
    $bTs = strtotime((string)($b['last_update'] ?? ''));
    if ($aTs === $bTs) {
        return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
    }
    return $bTs <=> $aTs;
});

$primaryRequest = null;
if (!empty($requests)) {
    $primaryRequest = $requests[0];
    unset($primaryRequest['_priority']);
}
foreach ($requests as &$requestRow) {
    unset($requestRow['_priority']);
}
unset($requestRow);

$notifPayload = client_fetch_notifications($conexion, $clientUserId, 8);

client_dashboard_ok([
    'summary' => [
        'total_requests' => count($requests),
        'unread_messages' => (int)($notifPayload['count'] ?? 0),
        'action_required_count' => $actionRequiredCount,
        'scheduled_count' => $scheduledCount,
        'primary_request' => $primaryRequest,
        'requests' => $requests,
    ],
]);
