<?php
include '../include/conexion.php';
require_once '../include/roles.php';
require_once '../include/provider_medical_staff_helpers.php';
require_once '../include/email_config.php';
require_once __DIR__ . '/../../inc/email_template.php';
require_once __DIR__ . '/../../inc/inbox_utils.php';
require_once __DIR__ . '/../../inc/fee_gate.php';
require_once __DIR__ . '/../../inc/google_calendar.php';
require_once __DIR__ . '/../../inc/realtime.php';
require_once __DIR__ . '/../../inc/commission_gate.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

$GLOBALS['my_booking_requests_debug_action'] = 'bootstrap';
$GLOBALS['my_booking_requests_debug_branch'] = 'bootstrap';

function my_booking_requests_debug_log($label, $context = [])
{
    $path = __DIR__ . '/../../storage/logs/my_booking_requests_debug.log';
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $payload = [
        'time' => date('Y-m-d H:i:s'),
        'label' => (string)$label,
        'action' => (string)($GLOBALS['my_booking_requests_debug_action'] ?? ''),
        'branch' => (string)($GLOBALS['my_booking_requests_debug_branch'] ?? ''),
        'context' => is_array($context) ? $context : ['value' => $context],
    ];
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function my_booking_requests_trace($label, $context = [])
{
    $payload = is_array($context) ? $context : ['value' => $context];
    my_booking_requests_debug_log($label, $payload);
    error_log(
        'MT_PROPOSAL_TRACE ' . json_encode([
            'label' => (string)$label,
            'action' => (string)($GLOBALS['my_booking_requests_debug_action'] ?? ''),
            'branch' => (string)($GLOBALS['my_booking_requests_debug_branch'] ?? ''),
            'context' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function my_booking_emit_realtime_inbox_message($payload, $sourceLabel = '')
{
    $threadId = is_array($payload) ? (string)($payload['thread_id'] ?? '') : '';
    $messageId = is_array($payload) ? (int)($payload['message_id'] ?? 0) : 0;
    $traceContext = [
        'source' => (string)$sourceLabel,
        'thread_id' => $threadId,
        'message_id' => $messageId,
        'payload_keys' => is_array($payload) ? array_keys($payload) : [],
        'realtime_fn_available' => function_exists('mt_realtime_emit_inbox_message') ? 1 : 0,
    ];

    my_booking_requests_trace('realtime_emit_attempt', $traceContext);

    if (!function_exists('mt_realtime_emit_inbox_message')) {
        my_booking_requests_trace('realtime_emit_unavailable', $traceContext);
        return false;
    }

    $emitOk = mt_realtime_emit_inbox_message($payload);
    $traceContext['emit_ok'] = $emitOk ? 1 : 0;
    my_booking_requests_trace($emitOk ? 'realtime_emit_ok' : 'realtime_emit_failed', $traceContext);

    return $emitOk;
}

function my_booking_requests_set_debug_branch($branch)
{
    $GLOBALS['my_booking_requests_debug_branch'] = (string)$branch;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)$error['type'], $fatalTypes, true)) {
        return;
    }
    my_booking_requests_debug_log('fatal', $error);
});

set_exception_handler(function ($exception) {
    $message = is_object($exception) ? $exception->getMessage() : 'unknown';
    $file = is_object($exception) ? $exception->getFile() : '';
    $line = is_object($exception) ? $exception->getLine() : 0;
    my_booking_requests_debug_log('uncaught_exception', [
        'type' => is_object($exception) ? get_class($exception) : 'unknown',
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $payload = ['ok' => false, 'message' => 'server_exception'];
    $appEnv = strtolower((string)(getenv('APP_ENV') ?: getenv('APPLICATION_ENV') ?: ''));
    if (in_array($appEnv, ['local', 'dev', 'development', 'test', 'testing'], true)) {
        $payload['debug_detail'] = $message;
        $payload['debug_file'] = basename((string)$file);
        $payload['debug_line'] = (int)$line;
    }
    echo json_encode($payload);
    exit;
});

function json_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function json_err($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function table_exists($conexion, $table)
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

function table_has_column($conexion, $table, $column)
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

function bind_stmt_params($stmt, $types, &$values)
{
    if ($types === '' || empty($values)) {
        return true;
    }
    $bind = [$types];
    foreach ($values as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function normalize_legacy_item_status($status)
{
    $status = trim((string)$status);
    if ($status === '' || $status === 'pending_admin' || $status === 'pending_review') {
        return 'pending_provider';
    }
    if ($status === 'completed') {
        return 'treatment_completed';
    }
    if ($status === 'appointment_confirmed') {
        return 'provider_confirmed';
    }
    if ($status === 'appointment_requested_change') {
        return 'provider_proposed_change';
    }
    if ($status === 'appointment_cancelled') {
        return 'cancelled';
    }
    if ($status === 'appointment_proposed') {
        return 'awaiting_client';
    }
    return $status;
}

function apply_operational_owner_meta($row, $isLinkedMedicalStaffSession, $currentLinkedStaffId = 0)
{
    $row = is_array($row) ? $row : [];
    $currentLinkedStaffId = (int)$currentLinkedStaffId;
    $assignedStaffId = (int)($row['assigned_staff_id'] ?? 0);
    $currentStatus = normalize_legacy_item_status($row['current_status'] ?? $row['item_status'] ?? '');
    $assignedStaff = isset($row['assigned_staff']) && is_array($row['assigned_staff']) ? $row['assigned_staff'] : [];
    $assignedDoctor = trim((string)($row['assigned_doctor'] ?? $assignedStaff['full_name'] ?? ''));
    $providerOwnerLabel = 'Administración del prestador / sin asignar';
    $providerOwnerShortLabel = 'Administración del prestador';
    $isOperationalOwner = false;

    if ($assignedStaffId > 0) {
        if ($assignedDoctor === '') {
            $assignedDoctor = 'Staff asignado';
        }
        $row['operational_owner_type'] = 'assigned_staff';
        $row['operational_owner_label'] = $assignedDoctor;
        $row['operational_owner_short_label'] = $assignedDoctor;
        $row['operational_owner_role_label_es'] = 'Staff asignado';
        $row['operational_owner_note_es'] = 'El ciclo operativo de este item queda a cargo del staff asignado.';
        $isOperationalOwner = $isLinkedMedicalStaffSession && $currentLinkedStaffId > 0 && $assignedStaffId === $currentLinkedStaffId;
    } else {
        $row['operational_owner_type'] = 'provider_admin';
        $row['operational_owner_label'] = $providerOwnerLabel;
        $row['operational_owner_short_label'] = $providerOwnerShortLabel;
        $row['operational_owner_role_label_es'] = 'Administración del prestador';
        $row['operational_owner_note_es'] = 'Mientras no haya staff asignado, la administración del prestador conserva la responsabilidad operativa.';
    }

    $row['has_operational_owner_staff'] = $assignedStaffId > 0 ? 1 : 0;
    $row['current_user_is_operational_owner'] = $isOperationalOwner ? 1 : 0;
    $row['supervisor_override_required'] = (!$isLinkedMedicalStaffSession && $assignedStaffId > 0) ? 1 : 0;
    $row['supervisor_override_message'] = (!$isLinkedMedicalStaffSession && $assignedStaffId > 0)
        ? 'Este item está asignado a ' . $assignedDoctor . '. Como administración del prestador actuarás en modo supervisión si continúas.'
        : '';
    $row['linked_staff_auto_claim_available'] = ($isLinkedMedicalStaffSession && $assignedStaffId <= 0 && $currentStatus === 'pending_provider') ? 1 : 0;
    $row['linked_staff_auto_claim_message'] = ($isLinkedMedicalStaffSession && $assignedStaffId <= 0 && $currentStatus === 'pending_provider')
        ? 'Este item aún no tiene staff asignado. Si continúas con una acción operativa, quedarás como responsable operativo.'
        : '';
    $row['ownership_mode_label_es'] = $row['supervisor_override_required']
        ? 'Supervisión'
        : ($isOperationalOwner ? 'Responsable actual' : ($assignedStaffId > 0 ? 'Seguimiento del staff' : 'Sin asignación clínica'));

    if ($isLinkedMedicalStaffSession && $assignedStaffId > 0 && !$isOperationalOwner) {
        $row['operational_owner_note_es'] = 'Este item está asignado a otro integrante del staff.';
    } elseif ($isLinkedMedicalStaffSession && $assignedStaffId <= 0) {
        $row['operational_owner_note_es'] = 'Aún no hay staff asignado. La administración del prestador conserva la responsabilidad hasta que alguien asuma el item.';
    }

    return $row;
}

function is_valid_date_ymd($value)
{
    if ($value === '' || $value === null) {
        return true;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $parts = explode('-', $value);
    if (count($parts) !== 3) {
        return false;
    }
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

function normalize_datetime_local_to_mysql($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'];
    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    return '';
}

function my_booking_calendar_event_columns($conexion)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [
        'organizer_admin_user_id' => table_has_column($conexion, 'calendar_events', 'organizer_admin_user_id'),
        'integration_mode' => table_has_column($conexion, 'calendar_events', 'integration_mode'),
        'appointment_mode' => table_has_column($conexion, 'calendar_events', 'appointment_mode'),
        'google_event_id' => table_has_column($conexion, 'calendar_events', 'google_event_id'),
        'google_html_link' => table_has_column($conexion, 'calendar_events', 'google_html_link'),
        'google_meet_url' => table_has_column($conexion, 'calendar_events', 'google_meet_url'),
        'organizer_email' => table_has_column($conexion, 'calendar_events', 'organizer_email'),
    ];

    return $cache;
}

function my_booking_pick_operational_admin_user_id($conexion, $preferredAdminUserId = 0)
{
    return function_exists('google_calendar_pick_connected_admin_user_id')
        ? (int)google_calendar_pick_connected_admin_user_id($conexion, (int)$preferredAdminUserId)
        : 0;
}

function my_booking_cancel_pending_meeting_events($conexion, $itemId)
{
    if (!table_exists($conexion, 'calendar_events')) {
        return;
    }

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE calendar_events
         SET status = 'cancelled', updated_at = NOW()
         WHERE event_type = 'ITEM'
           AND item_id = ?
           AND status IN ('proposed', 'scheduled')"
    );
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, 'i', $itemId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function my_booking_create_proposed_meeting_event($conexion, array $itemRow, array $meetingData, $preferredAdminUserId = 0)
{
    if (!table_exists($conexion, 'calendar_events')) {
        return ['ok' => false, 'error' => 'calendar_events_not_available'];
    }

    $itemId = (int)($itemRow['id'] ?? 0);
    $bookingRequestId = (int)($itemRow['booking_request_id'] ?? 0);
    $providerId = (int)($itemRow['provider_id'] ?? 0);
    $clientUserId = (int)($itemRow['client_user_id'] ?? 0);
    $itemName = trim((string)($itemRow['item_name'] ?? ''));
    $providerNotes = trim((string)($meetingData['provider_notes'] ?? ''));
    $startAt = trim((string)($meetingData['start_at'] ?? ''));
    $endAt = trim((string)($meetingData['end_at'] ?? ''));
    $timezone = trim((string)($meetingData['timezone'] ?? 'America/Bogota'));
    $integrationMode = trim((string)($meetingData['integration_mode'] ?? 'calendar_plus_meet'));
    $appointmentMode = function_exists('calendar_normalize_appointment_mode')
        ? calendar_normalize_appointment_mode($meetingData['appointment_mode'] ?? '')
        : '';

    if (!in_array($integrationMode, ['internal_only', 'calendar_only', 'calendar_plus_meet'], true)) {
        $integrationMode = 'calendar_plus_meet';
    }

    if ($itemId <= 0 || $bookingRequestId <= 0 || $clientUserId <= 0 || $startAt === '' || $endAt === '') {
        return ['ok' => false, 'error' => 'invalid_meeting_context'];
    }

    $organizerAdminUserId = 0;
    $organizerEmail = '';
    if ($integrationMode !== 'internal_only') {
        $organizerAdminUserId = my_booking_pick_operational_admin_user_id($conexion, $preferredAdminUserId);
        if ($organizerAdminUserId > 0 && function_exists('google_calendar_get_connection')) {
            $adminConn = google_calendar_get_connection($conexion, $organizerAdminUserId, false);
            $organizerEmail = trim((string)($adminConn['google_email'] ?? ''));
        }
    }
    if ($integrationMode !== 'internal_only' && $organizerAdminUserId <= 0) {
        return ['ok' => false, 'error' => 'no_google_admin_connected'];
    }

    $columns = my_booking_calendar_event_columns($conexion);
    my_booking_cancel_pending_meeting_events($conexion, $itemId);

    $title = $itemName !== '' ? ('Reunión MedTravel - ' . $itemName) : ('Reunión MedTravel - Item #' . $itemId);
    $integrationSummaryMap = [
        'internal_only' => 'Tipo: propuesta interna MedTravel.',
        'calendar_only' => 'Tipo: propuesta con evento de Google Calendar.',
        'calendar_plus_meet' => 'Tipo: propuesta con Google Calendar y Google Meet.',
    ];
    $description = 'Solicitud #' . $bookingRequestId . "\n"
        . 'Item #' . $itemId . "\n"
        . 'Reunión propuesta desde Mis Solicitudes.'
        . "\n" . ($integrationSummaryMap[$integrationMode] ?? $integrationSummaryMap['calendar_plus_meet'])
        . ($providerNotes !== '' ? "\nNotas: " . $providerNotes : '')
        . "\nZona horaria: " . $timezone;
    $threadId = inbox_thread_id('ITEM', $bookingRequestId, $itemId);
    $createdByUserId = current_admin_user_id();
    $createdByRole = is_role_admin_session() ? 'ADMIN' : 'PROVIDER';

    $insertColumns = [
        'title', 'description', 'start_at', 'end_at', 'all_day', 'event_type', 'request_id', 'item_id',
        'thread_id', 'created_by_role', 'created_by_user_id', 'provider_id', 'client_user_id', 'status', 'updated_at'
    ];
    $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', 'NOW()'];
    $types = 'ssssisiissiiis';
    $params = [
        $title,
        $description,
        $startAt,
        $endAt,
        0,
        'ITEM',
        $bookingRequestId,
        $itemId,
        $threadId,
        $createdByRole,
        $createdByUserId,
        $providerId,
        $clientUserId,
        'proposed',
    ];

    if ($columns['organizer_admin_user_id']) {
        $insertColumns[] = 'organizer_admin_user_id';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $organizerAdminUserId;
    }
    if ($columns['integration_mode']) {
        $insertColumns[] = 'integration_mode';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $integrationMode;
    }
    if ($columns['organizer_email'] && $organizerEmail !== '') {
        $insertColumns[] = 'organizer_email';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $organizerEmail;
    }
    if ($columns['appointment_mode'] && $appointmentMode !== '') {
        $insertColumns[] = 'appointment_mode';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $appointmentMode;
    }

    $sql = 'INSERT INTO calendar_events (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_prepare_error'];
    }

    bind_stmt_params($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'error' => 'db_error: ' . $err];
    }

    $calendarEventId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    $syncResult = google_calendar_sync_item_status_for_transition($conexion, $itemId, 'appointment_proposed');
    if (empty($syncResult['ok'])) {
        return ['ok' => false, 'error' => (string)($syncResult['error'] ?? 'item_status_sync_failed')];
    }

    return [
        'ok' => true,
        'calendar_event_id' => $calendarEventId,
        'organizer_admin_user_id' => $organizerAdminUserId,
        'title' => $title,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'timezone' => $timezone,
    ];
}

function normalize_message_text($text)
{
    return trim((string)preg_replace('/\s+/', ' ', (string)$text));
}

function safe_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function resolve_patientcare_admin_email($conexion)
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

function provider_status_label($status)
{
    $map = [
        'provider_confirmed'          => 'Caso aceptado',
        'provider_rejected'           => 'Caso rechazado',
        'provider_proposed_change'    => 'Cita propuesta',
        'virtual_assessment_pending'  => 'Valoración virtual pendiente',
        'virtual_assessment_done'     => 'Valoración realizada',
        'treatment_plan_agreed'       => 'Plan clínico acordado',
        'procedure_scheduled'         => 'Procedimiento presencial agendado',
        'treatment_completed'         => 'Tratamiento completado',
        'post_treatment_follow_up'    => 'Seguimiento post tratamiento',
        'case_closed'                 => 'Caso cerrado',
    ];
    $key = trim((string)$status);
    return isset($map[$key]) ? $map[$key] : $key;
}

function generic_status_label_es($status)
{
    $status = strtolower(trim((string)$status));
    $map = [
        'pending' => 'Pendiente',
        'pending_provider' => 'Pendiente de revisión del prestador',
        'provider_reviewing' => 'Pendiente de revisión del prestador',
        'needs_more_info' => 'Información adicional requerida',
        'provider_confirmed' => 'Caso aceptado',
        'client_accepted' => 'Caso aceptado',
        'provider_rejected' => 'Caso rechazado',
        'client_rejected' => 'Caso rechazado',
        'awaiting_client' => 'Pendiente de respuesta del paciente',
        'provider_proposed_change' => 'Cita propuesta',
        'not_applicable' => 'No aplica',
        'required_pending' => 'Comisión pendiente',
        'paid' => 'Comisión pagada',
        'waived' => 'Comisión exonerada',
        'disabled_manually' => 'Comisión desactivada manualmente',
        'doctor_assigned' => 'Médico asignado',
        'date_proposed' => 'Cita propuesta',
        'date_confirmed' => 'Cita confirmada',
        'rescheduled' => 'Cita reprogramada',
        'appointment_proposed' => 'Cita propuesta',
        'appointment_confirmed' => 'Cita confirmada',
        'appointment_requested_change' => 'Cambio de cita solicitado',
        'appointment_cancelled' => 'Cita cancelada',
        'treatment_completed'         => 'Tratamiento completado',
        'post_treatment_follow_up'    => 'Seguimiento post tratamiento',
        'completed'                   => 'Atención realizada',
        'cancelled'                   => 'Caso cerrado',
        // Nuevos estados 2026-04-15
        'virtual_assessment_pending'  => 'Valoración virtual pendiente',
        'virtual_assessment_done'     => 'Valoración virtual realizada',
        'treatment_plan_agreed'       => 'Plan clínico acordado',
        'procedure_scheduled'         => 'Procedimiento presencial agendado',
        'case_closed'                 => 'Caso cerrado (exitoso)',
        'confirmed'                   => 'Confirmado',
        'new'                         => 'Nuevo caso',
    ];
    return isset($map[$status]) ? $map[$status] : ($status !== '' ? $status : 'Sin definir');
}

function fee_status_label_es($status)
{
    $status = strtolower(trim((string)$status));
    $map = [
        'pending' => 'Pendiente',
        'not_applicable' => 'No aplica',
        'required_pending' => 'Comisión pendiente',
        'paid' => 'Comisión pagada',
        'waived' => 'Comisión exonerada',
        'disabled_manually' => 'Comisión desactivada manualmente',
    ];
    return isset($map[$status]) ? $map[$status] : ($status !== '' ? $status : 'Sin definir');
}

function appointment_status_label_es($status)
{
    $status = strtolower(trim((string)$status));
    $map = [
        'pending' => 'Pendiente',
        'pending_provider' => 'Cita pendiente de propuesta',
        'provider_reviewing' => 'Cita pendiente de propuesta',
        'needs_more_info' => 'Información adicional requerida',
        'doctor_assigned' => 'Médico pendiente de propuesta de cita',
        'date_proposed' => 'Cita propuesta',
        'date_confirmed' => 'Cita confirmada',
        'rescheduled' => 'Cita reprogramada',
        'treatment_completed' => 'Tratamiento completado',
        'post_treatment_follow_up' => 'Seguimiento post tratamiento',
        'completed' => 'Atención realizada',
        'cancelled' => 'Cita cancelada',
        'confirmed' => 'Cita confirmada',
        'scheduled' => 'Cita programada',
        'proposed' => 'Cita propuesta',
    ];
    return isset($map[$status]) ? $map[$status] : ($status !== '' ? generic_status_label_es($status) : 'Sin definir');
}

function role_label_es($role)
{
    $role = strtoupper(trim((string)$role));
    $map = [
        'CLIENT' => 'Cliente',
        'PROVIDER' => 'Prestador',
        'COORDINATOR' => 'Coordinación',
        'DOCTOR' => 'Médico',
        'SYSTEM' => 'Sistema',
    ];
    return isset($map[$role]) ? $map[$role] : ($role !== '' ? $role : 'Sistema');
}

function event_type_label_es($eventType)
{
    $eventType = strtolower(trim((string)$eventType));
    $map = [
        'coordination_fee_required' => 'Comisión pendiente',
        'coordination_fee_paid' => 'Comisión pagada',
        'coordination_fee_waived' => 'Comisión exonerada',
        'contact_unlocked' => 'Contacto desbloqueado',
        'doctor_assigned' => 'Médico asignado',
        'medical_docs_requested' => 'Documentos solicitados',
        'medical_docs_uploaded' => 'Documentos cargados',
        'appointment_proposed' => 'Cita propuesta',
        'appointment_confirmed' => 'Cita confirmada',
        'appointment_rescheduled' => 'Cita reprogramada',
        'appointment_cancelled' => 'Cita cancelada',
    ];
    return isset($map[$eventType]) ? $map[$eventType] : ($eventType !== '' ? $eventType : 'Evento');
}

function first_existing_column($conexion, $table, $candidates)
{
    foreach ((array)$candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '' && table_has_column($conexion, $table, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

function normalize_coordination_fee_status($rawStatus, $feeRequired)
{
    $status = strtolower(trim((string)$rawStatus));
    $feeRequired = (int)$feeRequired === 1;

    if ($status === 'paid') {
        return 'paid';
    }
    if ($status === 'waived') {
        return 'waived';
    }
    if (in_array($status, ['disabled_manually', 'manual_disabled', 'disabled'], true)) {
        return 'disabled_manually';
    }
    if (in_array($status, ['not_required', 'not_applicable'], true)) {
        return 'not_applicable';
    }
    if (in_array($status, ['pending', 'required_pending'], true)) {
        return $feeRequired ? 'required_pending' : 'not_applicable';
    }

    return $feeRequired ? 'required_pending' : 'not_applicable';
}

function coordination_fee_is_unlocked($functionalStatus, $isAdminSession = false)
{
    if ($isAdminSession) {
        return true;
    }
    return in_array((string)$functionalStatus, ['not_applicable', 'paid', 'waived', 'disabled_manually'], true);
}

function mask_contact_value($value, $kind)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    if ($kind === 'email') {
        $parts = explode('@', $value, 2);
        if (count($parts) === 2) {
            $local = $parts[0];
            $domain = $parts[1];
            $visible = strlen($local) > 1 ? substr($local, 0, 1) : '';
            return $visible . '***@' . $domain;
        }
    }

    $digits = preg_replace('/\D+/', '', $value);
    if ($kind === 'phone' && $digits !== '') {
        $tail = strlen($digits) > 2 ? substr($digits, -2) : $digits;
        return '*** *** ' . $tail;
    }

    if (strlen($value) <= 2) {
        return str_repeat('*', strlen($value));
    }
    return substr($value, 0, 1) . str_repeat('*', max(strlen($value) - 2, 3)) . substr($value, -1);
}

function my_booking_normalize_meeting_integration_mode($value)
{
    $mode = trim((string)$value);
    if (in_array($mode, ['internal_only', 'calendar_only', 'calendar_plus_meet'], true)) {
        return $mode;
    }
    return 'calendar_plus_meet';
}

function resolve_contact_access_state($email, $phone, $functionalFeeStatus, $isAdminSession = false)
{
    $unlocked = coordination_fee_is_unlocked($functionalFeeStatus, $isAdminSession);
    $locked = !$unlocked;
    $note = $locked ? 'Bloqueado hasta pagar la comisión de coordinación' : '';

    return [
        'locked' => $locked,
        'unlocked' => $unlocked,
        'note' => $note,
        'email_display' => $locked ? mask_contact_value($email, 'email') : (trim((string)$email) !== '' ? trim((string)$email) : '-'),
        'phone_display' => $locked ? mask_contact_value($phone, 'phone') : (trim((string)$phone) !== '' ? trim((string)$phone) : '-'),
    ];
}

function build_coordination_fee_meta($conexion, $bookingRequestId, $seedRow, $isAdminSession = false)
{
    $bookingRequestId = (int)$bookingRequestId;
    $seedRow = is_array($seedRow) ? $seedRow : [];
    $bookingData = $seedRow;

    if ($bookingRequestId > 0 && table_exists($conexion, 'booking_requests')) {
        $candidateColumns = [
            'id',
            'fee_status',
            'fee_required',
            'coordination_fee_amount',
            'fee_amount',
            'coordination_fee_paid_at',
            'fee_paid_at',
            'coordination_fee_waived_at',
            'fee_waived_at',
            'coordination_unlocked_at',
            'fee_unlocked_at',
            'coordination_unlock_scope',
            'fee_unlock_scope',
        ];
        $selectCols = [];
        foreach ($candidateColumns as $col) {
            if (table_has_column($conexion, 'booking_requests', $col)) {
                $selectCols[] = 'br.`' . $col . '`';
            }
        }
        if (!empty($selectCols)) {
            $sql = "SELECT " . implode(', ', $selectCols) . " FROM booking_requests br WHERE br.id = ?";
            if (table_has_column($conexion, 'booking_requests', 'is_deleted')) {
                $sql .= " AND br.is_deleted = 0";
            }
            $sql .= " LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $bookingRequestId);
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    $row = $res ? mysqli_fetch_assoc($res) : null;
                    if ($row) {
                        $bookingData = array_merge($bookingData, $row);
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    $legacyRequired = ($bookingRequestId > 0 && is_booking_fee_required($conexion, $bookingRequestId)) ? 1 : 0;
    $feeRequired = array_key_exists('fee_required', $bookingData)
        ? (((int)($bookingData['fee_required'] ?? 0) === 1) ? 1 : 0)
        : $legacyRequired;
    if ($feeRequired !== 1 && $legacyRequired === 1) {
        $feeRequired = 1;
    }

    $functionalStatus = normalize_coordination_fee_status($bookingData['fee_status'] ?? '', $feeRequired);
    $amount = array_key_exists('coordination_fee_amount', $bookingData)
        ? $bookingData['coordination_fee_amount']
        : (array_key_exists('fee_amount', $bookingData) ? $bookingData['fee_amount'] : null);
    $paidAt = array_key_exists('coordination_fee_paid_at', $bookingData)
        ? $bookingData['coordination_fee_paid_at']
        : (array_key_exists('fee_paid_at', $bookingData) ? $bookingData['fee_paid_at'] : null);
    $waivedAt = array_key_exists('coordination_fee_waived_at', $bookingData)
        ? $bookingData['coordination_fee_waived_at']
        : (array_key_exists('fee_waived_at', $bookingData) ? $bookingData['fee_waived_at'] : null);
    $unlockedAt = array_key_exists('coordination_unlocked_at', $bookingData)
        ? $bookingData['coordination_unlocked_at']
        : (array_key_exists('fee_unlocked_at', $bookingData) ? $bookingData['fee_unlocked_at'] : null);
    $unlockScope = array_key_exists('coordination_unlock_scope', $bookingData)
        ? $bookingData['coordination_unlock_scope']
        : (array_key_exists('fee_unlock_scope', $bookingData) ? $bookingData['fee_unlock_scope'] : null);

    if ((trim((string)$unlockedAt) === '') && coordination_fee_is_unlocked($functionalStatus, $isAdminSession)) {
        $unlockedAt = $paidAt ?: $waivedAt;
    }

    return [
        'status' => $functionalStatus,
        'status_label_es' => fee_status_label_es($functionalStatus),
        'required' => $feeRequired ? 1 : 0,
        'amount' => ($amount !== null && $amount !== '') ? $amount : null,
        'paid_at' => trim((string)$paidAt) !== '' ? (string)$paidAt : null,
        'waived_at' => trim((string)$waivedAt) !== '' ? (string)$waivedAt : null,
        'unlocked_at' => trim((string)$unlockedAt) !== '' ? (string)$unlockedAt : null,
        'unlock_scope' => trim((string)$unlockScope) !== '' ? (string)$unlockScope : null,
        'unlocked' => coordination_fee_is_unlocked($functionalStatus, $isAdminSession),
        'message' => $functionalStatus === 'required_pending' ? 'Comisión pendiente' : '',
    ];
}

function apply_provider_commission_gate_override($conexion, array &$coordinationFee, array $seedRow)
{
    if (!function_exists('commission_gate_fetch_settings')) {
        return;
    }
    $providerIds = [];
    if (!empty($seedRow['provider_id'])) {
        $providerIds[] = (int)$seedRow['provider_id'];
    }
    if (!empty($seedRow['service_provider_id'])) {
        $providerIds[] = (int)$seedRow['service_provider_id'];
    }
    // If no provider context, nothing to check
    if (empty($providerIds)) {
        return;
    }
    $settings = commission_gate_fetch_settings($conexion, $providerIds);
    // If settings were found and the gate is not enabled, treat coordination as not applicable
    if (!empty($settings['found']) && empty($settings['enabled'])) {
        $coordinationFee['unlocked'] = true;
        $coordinationFee['required'] = 0;
        $coordinationFee['status'] = 'not_applicable';
        $coordinationFee['message'] = '';
    }
}

function detect_message_role($message)
{
    $sender = strtolower(trim((string)($message['sender'] ?? 'system')));
    if (in_array($sender, ['admin', 'patientcare', 'coordinator'], true)) {
        return 'COORDINATOR';
    }
    if ($sender === 'doctor') {
        return 'DOCTOR';
    }
    if ($sender === 'provider') {
        return 'PROVIDER';
    }
    if ($sender === 'client') {
        return 'CLIENT';
    }
    $actor = strtolower(trim((string)($message['actor'] ?? '')));
    if ($actor !== '' && strpos($actor, 'doctor') !== false) {
        return 'DOCTOR';
    }
    return 'SYSTEM';
}

function fetch_named_entity($conexion, $table, $id, $nameCandidates, $extraCandidates = [])
{
    $id = (int)$id;
    if ($id <= 0 || !table_exists($conexion, $table)) {
        return [];
    }

    $nameCol = first_existing_column($conexion, $table, $nameCandidates);
    if ($nameCol === null) {
        return [];
    }

    $selects = ["`{$nameCol}` AS entity_name"];
    foreach ((array)$extraCandidates as $alias => $candidates) {
        $col = first_existing_column($conexion, $table, (array)$candidates);
        if ($col !== null) {
            $selects[] = "`{$col}` AS `{$alias}`";
        }
    }

    $sql = "SELECT " . implode(', ', $selects) . " FROM `{$table}` WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: [];
}

function build_in_clause($values)
{
    $count = count((array)$values);
    if ($count <= 0) {
        return '';
    }
    return implode(',', array_fill(0, $count, '?'));
}

function fetch_calendar_event_trace_map($conexion, $itemIds)
{
    $itemIds = array_values(array_unique(array_filter(array_map('intval', (array)$itemIds))));
    if (empty($itemIds) || !table_exists($conexion, 'calendar_events')) {
        return [];
    }

    $inClause = build_in_clause($itemIds);
    if ($inClause === '') {
        return [];
    }

    $eventColumns = my_booking_calendar_event_columns($conexion);
    $googleEventExpr = $eventColumns['google_event_id'] ? 'ce.google_event_id' : "''";
    $googleHtmlLinkExpr = $eventColumns['google_html_link'] ? 'ce.google_html_link' : "''";
    $googleMeetExpr = $eventColumns['google_meet_url'] ? 'ce.google_meet_url' : "''";
    $organizerEmailExpr = $eventColumns['organizer_email'] ? 'ce.organizer_email' : "''";

    $sql = "SELECT
                ce.id,
                ce.item_id,
                ce.title,
                ce.description,
                ce.start_at,
                ce.end_at,
                ce.status,
                {$googleEventExpr} AS google_event_id,
                {$googleHtmlLinkExpr} AS google_html_link,
                {$googleMeetExpr} AS google_meet_url,
                {$organizerEmailExpr} AS organizer_email,
                ce.created_at,
                ce.updated_at
            FROM calendar_events ce
            WHERE ce.event_type = 'ITEM'
              AND ce.item_id IN ({$inClause})
            ORDER BY ce.item_id ASC, ce.start_at ASC, ce.id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    $types = str_repeat('i', count($itemIds));
    $params = $itemIds;
    if (!bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [];
    }

    $res = mysqli_stmt_get_result($stmt);
    $grouped = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $grouped[(int)($row['item_id'] ?? 0)][] = $row;
    }
    mysqli_stmt_close($stmt);

    $nowTs = time();
    $traceMap = [];
    foreach ($grouped as $itemId => $events) {
        $latest = null;
        $confirmedAt = '';
        $proposedAt = '';
        $nextEvent = null;
        $activeCount = 0;
        foreach ($events as $event) {
            $status = strtolower(trim((string)($event['status'] ?? 'scheduled')));
            $eventTs = strtotime((string)($event['start_at'] ?? ''));
            if ($status !== 'cancelled') {
                $activeCount++;
                if ($eventTs !== false && $eventTs >= $nowTs && $nextEvent === null) {
                    $nextEvent = $event;
                }
            }
            if ($status === 'confirmed') {
                $confirmedAt = (string)($event['start_at'] ?? '');
            }
            if (in_array($status, ['proposed', 'scheduled'], true)) {
                $proposedAt = (string)($event['start_at'] ?? '');
            }
            $latest = $event;
        }

        $rescheduleCount = max(0, $activeCount - 1);
        $latestStatus = strtolower(trim((string)($latest['status'] ?? '')));
        $appointmentStatus = '';
        if ($latestStatus === 'cancelled') {
            $appointmentStatus = 'cancelled';
        } elseif ($latestStatus === 'confirmed') {
            $appointmentStatus = ($rescheduleCount > 0) ? 'rescheduled' : 'date_confirmed';
        } elseif (in_array($latestStatus, ['proposed', 'scheduled'], true)) {
            $appointmentStatus = ($rescheduleCount > 0) ? 'rescheduled' : 'date_proposed';
        }

        $lastActionText = '';
        if ($latest) {
            if ($latestStatus === 'confirmed') {
                $lastActionText = 'Cita confirmada';
            } elseif ($latestStatus === 'cancelled') {
                $lastActionText = 'Cita cancelada';
            } elseif (in_array($latestStatus, ['proposed', 'scheduled'], true)) {
                $lastActionText = 'Cita propuesta';
            } else {
                $lastActionText = trim((string)($latest['title'] ?? ''));
            }
        }

        $traceMap[$itemId] = [
            'events' => $events,
            'proposed_appointment_date' => $proposedAt !== '' ? $proposedAt : null,
            'confirmed_appointment_date' => $confirmedAt !== '' ? $confirmedAt : null,
            'next_appointment' => $nextEvent ? [
                'title' => (string)($nextEvent['title'] ?? ''),
                'start_at' => (string)($nextEvent['start_at'] ?? ''),
                'status' => (string)($nextEvent['status'] ?? ''),
                'google_event_id' => (string)($nextEvent['google_event_id'] ?? ''),
                'google_html_link' => (string)($nextEvent['google_html_link'] ?? ''),
                'google_meet_url' => (string)($nextEvent['google_meet_url'] ?? ''),
                'organizer_email' => (string)($nextEvent['organizer_email'] ?? ''),
            ] : null,
            'appointment_status' => $appointmentStatus,
            'reschedule_count' => $rescheduleCount,
            'last_provider_action' => $lastActionText !== '' ? $lastActionText : null,
            'updated_at' => $latest ? ((string)($latest['updated_at'] ?? '') !== '' ? (string)$latest['updated_at'] : (string)($latest['start_at'] ?? '')) : null,
        ];
    }

    return $traceMap;
}

function derive_medical_coordination_status($row, $trace)
{
    $explicit = trim((string)($row['medical_coordination_status'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }

    $appointmentStatus = trim((string)($trace['appointment_status'] ?? ''));
    if ($appointmentStatus !== '') {
        return $appointmentStatus;
    }

    $providerStatus = trim((string)($row['provider_status'] ?? $row['item_status'] ?? ''));
    if (in_array($providerStatus, ['provider_rejected', 'client_rejected', 'cancelled'], true)) {
        return 'cancelled';
    }
    if ($providerStatus === 'provider_confirmed') {
        return !empty($row['assigned_doctor']) ? 'doctor_assigned' : 'provider_reviewing';
    }
    if (!empty($row['assigned_doctor'])) {
        return 'doctor_assigned';
    }
    return 'pending_provider';
}

function enrich_item_trace_row($conexion, $row, $calendarTraceMap)
{
    $row = is_array($row) ? $row : [];
    $itemId = (int)($row['item_id'] ?? 0);
    $trace = isset($calendarTraceMap[$itemId]) && is_array($calendarTraceMap[$itemId]) ? $calendarTraceMap[$itemId] : [];

    $providerStatus = trim((string)($row['provider_status'] ?? ''));
    if ($providerStatus === '') {
        $providerStatus = normalize_legacy_item_status($row['item_status'] ?? '');
    }
    $row['provider_status'] = $providerStatus;

    $providerId = (int)($row['provider_id'] ?? 0);
    $serviceProviderId = (int)($row['service_provider_id'] ?? 0);
    $providerInfo = $providerId > 0
        ? fetch_named_entity($conexion, 'providers', $providerId, ['name', 'provider_name'], ['provider_type' => ['type'], 'provider_timezone' => ['provider_timezone', 'timezone']])
        : [];
    $serviceProviderInfo = $serviceProviderId > 0
        ? fetch_named_entity($conexion, 'service_providers', $serviceProviderId, ['provider_name', 'name'], ['provider_timezone' => ['provider_timezone', 'timezone']])
        : [];

    $assignedProvider = trim((string)($row['assigned_provider'] ?? ''));
    if ($assignedProvider === '') {
        $assignedProvider = trim((string)($providerInfo['entity_name'] ?? $serviceProviderInfo['entity_name'] ?? ''));
    }

    $row = provider_staff_apply_assignment_payload($conexion, $row);

    $assignedDoctor = trim((string)($row['assigned_doctor'] ?? ''));
    $clinic = trim((string)($row['clinic'] ?? ''));
    $providerType = strtolower(trim((string)($providerInfo['provider_type'] ?? '')));
    if ($assignedDoctor === '' && $providerType === 'medico') {
        $assignedDoctor = trim((string)($providerInfo['entity_name'] ?? ''));
    }
    if ($clinic === '' && $providerType === 'clinica') {
        $clinic = trim((string)($providerInfo['entity_name'] ?? ''));
    }

    $row['assigned_provider'] = $assignedProvider !== '' ? $assignedProvider : null;
    $row['assigned_doctor'] = $assignedDoctor !== '' ? $assignedDoctor : null;
    $row['clinic'] = $clinic !== '' ? $clinic : null;
    $row['proposed_appointment_date'] = $row['proposed_appointment_date'] ?? ($trace['proposed_appointment_date'] ?? null);
    $row['confirmed_appointment_date'] = $row['confirmed_appointment_date'] ?? ($trace['confirmed_appointment_date'] ?? null);

    $timezone = trim((string)($row['timezone'] ?? ''));
    if ($timezone === '') {
        $timezone = trim((string)($providerInfo['provider_timezone'] ?? $serviceProviderInfo['provider_timezone'] ?? ''));
    }
    $row['timezone'] = $timezone !== '' ? $timezone : null;

    $location = trim((string)($row['location'] ?? ''));
    $row['location'] = $location !== '' ? $location : null;
    $row['reschedule_count'] = isset($row['reschedule_count']) && $row['reschedule_count'] !== null && $row['reschedule_count'] !== ''
        ? (int)$row['reschedule_count']
        : (int)($trace['reschedule_count'] ?? 0);
    $row['last_provider_action'] = trim((string)($row['last_provider_action'] ?? '')) !== ''
        ? (string)$row['last_provider_action']
        : (isset($trace['last_provider_action']) ? (string)$trace['last_provider_action'] : null);
    $row['updated_at'] = trim((string)($row['updated_at'] ?? '')) !== ''
        ? (string)$row['updated_at']
        : (trim((string)($row['item_updated_at'] ?? '')) !== '' ? (string)$row['item_updated_at'] : (isset($trace['updated_at']) ? (string)$trace['updated_at'] : null));
    $row['appointment_status'] = trim((string)($row['appointment_status'] ?? '')) !== ''
        ? (string)$row['appointment_status']
        : (isset($trace['appointment_status']) ? (string)$trace['appointment_status'] : null);
    $row['medical_coordination_status'] = derive_medical_coordination_status($row, $trace);
    $row['next_appointment'] = isset($trace['next_appointment']) ? $trace['next_appointment'] : null;

    return $row;
}

function build_detail_event_log($detailRow, $itemsHistory, $messages, $documents)
{
    $events = [];
    $detailRow = is_array($detailRow) ? $detailRow : [];
    $itemsHistory = is_array($itemsHistory) ? $itemsHistory : [];
    $messages = is_array($messages) ? $messages : [];
    $documents = is_array($documents) ? $documents : [];

    $feeMeta = isset($detailRow['coordination_fee']) && is_array($detailRow['coordination_fee']) ? $detailRow['coordination_fee'] : [];
    $feeStatus = (string)($feeMeta['status'] ?? '');
    $bookingRequestId = (int)($detailRow['booking_request_id'] ?? 0);
    if ($feeStatus === 'required_pending') {
        $events[] = [
            'scope' => 'request',
            'request_id' => $bookingRequestId,
            'item_id' => 0,
            'event_type' => 'coordination_fee_required',
            'actor_role' => 'SYSTEM',
            'time' => (string)($detailRow['booking_updated_at'] ?? $detailRow['booking_created_at'] ?? ''),
            'summary' => 'La coordinación permanece bloqueada hasta completar la comisión.',
        ];
    }
    if (trim((string)($feeMeta['paid_at'] ?? '')) !== '') {
        $events[] = [
            'scope' => 'request',
            'request_id' => $bookingRequestId,
            'item_id' => 0,
            'event_type' => 'coordination_fee_paid',
            'actor_role' => 'SYSTEM',
            'time' => (string)$feeMeta['paid_at'],
            'summary' => 'La comisión de coordinación fue marcada como pagada.',
        ];
    }
    if (trim((string)($feeMeta['waived_at'] ?? '')) !== '') {
        $events[] = [
            'scope' => 'request',
            'request_id' => $bookingRequestId,
            'item_id' => 0,
            'event_type' => 'coordination_fee_waived',
            'actor_role' => 'SYSTEM',
            'time' => (string)$feeMeta['waived_at'],
            'summary' => 'La comisión de coordinación fue exonerada.',
        ];
    }
    if (!empty($feeMeta['unlocked']) && trim((string)($feeMeta['unlocked_at'] ?? '')) !== '') {
        $events[] = [
            'scope' => 'request',
            'request_id' => $bookingRequestId,
            'item_id' => 0,
            'event_type' => 'contact_unlocked',
            'actor_role' => 'SYSTEM',
            'time' => (string)$feeMeta['unlocked_at'],
            'summary' => 'Los datos de contacto quedaron disponibles para coordinación.',
        ];
    }

    foreach ($itemsHistory as $item) {
        $itemId = (int)($item['item_id'] ?? 0);
        $assignedDoctor = trim((string)($item['assigned_doctor'] ?? ''));
        if ($assignedDoctor !== '') {
            $events[] = [
                'scope' => 'item',
                'request_id' => $bookingRequestId,
                'item_id' => $itemId,
                'event_type' => 'doctor_assigned',
                'actor_role' => 'SYSTEM',
                'time' => (string)($item['updated_at'] ?? $item['item_updated_at'] ?? ''),
                'summary' => 'Médico asignado: ' . $assignedDoctor,
            ];
        }
        if (trim((string)($item['proposed_appointment_date'] ?? '')) !== '') {
            $events[] = [
                'scope' => 'item',
                'request_id' => $bookingRequestId,
                'item_id' => $itemId,
                'event_type' => 'appointment_proposed',
                'actor_role' => 'PROVIDER',
                'time' => (string)$item['proposed_appointment_date'],
                'summary' => 'Se propuso una cita.',
            ];
        }
        if (trim((string)($item['confirmed_appointment_date'] ?? '')) !== '') {
            $events[] = [
                'scope' => 'item',
                'request_id' => $bookingRequestId,
                'item_id' => $itemId,
                'event_type' => (($item['reschedule_count'] ?? 0) > 0) ? 'appointment_rescheduled' : 'appointment_confirmed',
                'actor_role' => 'COORDINATOR',
                'time' => (string)$item['confirmed_appointment_date'],
                'summary' => (($item['reschedule_count'] ?? 0) > 0) ? 'La cita fue reprogramada y confirmada.' : 'La cita fue confirmada.',
            ];
        }
        if ((string)($item['appointment_status'] ?? '') === 'cancelled') {
            $events[] = [
                'scope' => 'item',
                'request_id' => $bookingRequestId,
                'item_id' => $itemId,
                'event_type' => 'appointment_cancelled',
                'actor_role' => 'SYSTEM',
                'time' => (string)($item['updated_at'] ?? $item['item_updated_at'] ?? ''),
                'summary' => 'La cita fue cancelada.',
            ];
        }
    }

    foreach ($documents as $doc) {
        $events[] = [
            'scope' => 'request',
            'request_id' => $bookingRequestId,
            'item_id' => (int)($doc['item_id'] ?? 0),
            'event_type' => 'medical_docs_uploaded',
            'actor_role' => 'CLIENT',
            'time' => (string)($doc['uploaded_at'] ?? ''),
            'summary' => 'Documento médico cargado: ' . trim((string)($doc['title'] ?? $doc['original_filename'] ?? $doc['filename'] ?? 'Documento')),
        ];
    }

    foreach ($messages as $message) {
        $body = strtolower(trim((string)($message['body'] ?? '')));
        if ($body === '') {
            continue;
        }
        $eventType = '';
        $summary = '';
        if (strpos($body, 'requested clinical photos') !== false || strpos($body, 'requested medical documents') !== false) {
            $eventType = 'medical_docs_requested';
            $summary = 'Se solicitaron documentos médicos.';
        } elseif (strpos($body, 'appointment proposed') !== false) {
            $eventType = 'appointment_proposed';
            $summary = 'Se propuso una cita en la conversación.';
        } elseif (strpos($body, 'appointment confirmed') !== false) {
            $eventType = 'appointment_confirmed';
            $summary = 'Se confirmó una cita en la conversación.';
        }

        if ($eventType !== '') {
            $events[] = [
                'scope' => strtoupper((string)($message['thread_type'] ?? 'CARE')) === 'ITEM' ? 'item' : 'request',
                'request_id' => $bookingRequestId,
                'item_id' => (int)($message['thread_item_id'] ?? 0),
                'event_type' => $eventType,
                'actor_role' => detect_message_role($message),
                'time' => (string)($message['time'] ?? ''),
                'summary' => $summary,
            ];
        }
    }

    usort($events, function ($a, $b) {
        $ta = strtotime((string)($a['time'] ?? ''));
        $tb = strtotime((string)($b['time'] ?? ''));
        if ($ta === $tb) {
            return strcmp((string)($a['event_type'] ?? ''), (string)($b['event_type'] ?? ''));
        }
        return ($ta > $tb) ? -1 : 1;
    });

    return $events;
}

function parse_additional_notes_messages($additionalNotes)
{
    $messages = [];
    $notes = trim((string)$additionalNotes);
    if ($notes === '') {
        return $messages;
    }

    $lines = preg_split('/\R+/', $notes);
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^\[(CLIENT_MESSAGE|PROVIDER_MESSAGE)\]\[(.*?)\](?:\[(.*?)\])?\s*(.*)$/', $line, $m)) {
            $type = strtoupper((string)$m[1]);
            $actorRaw = isset($m[3]) ? trim((string)$m[3]) : '';
            $threadType = 'CARE';
            $threadItemId = 0;
            $actor = $actorRaw;
            if ($actorRaw !== '') {
                if (preg_match('/(?:^|\|)THREAD:ITEM:(\d+)/i', $actorRaw, $scopeMatch)) {
                    $threadType = 'ITEM';
                    $threadItemId = (int)$scopeMatch[1];
                } elseif (preg_match('/(?:^|\|)THREAD:CARE(?:\||$)/i', $actorRaw)) {
                    $threadType = 'CARE';
                }
                $actorParts = explode('|', $actorRaw);
                $actorClean = [];
                foreach ($actorParts as $part) {
                    $part = trim((string)$part);
                    if ($part === '' || stripos($part, 'THREAD:') === 0) {
                        continue;
                    }
                    $actorClean[] = $part;
                }
                $actor = implode('|', $actorClean);
            }
            $messages[] = [
                'sender' => ($type === 'CLIENT_MESSAGE') ? 'client' : 'provider',
                'type' => strtolower($type),
                'time' => trim((string)$m[2]),
                'actor' => $actor,
                'body' => trim((string)$m[4]),
                'thread_type' => $threadType,
                'thread_item_id' => $threadItemId,
            ];
        }
    }

    return $messages;
}

function build_thread_actor($threadType, $threadItemId, $actor)
{
    $threadType = strtoupper(trim((string)$threadType));
    $actor = trim((string)$actor);
    if ($threadType === 'ITEM' && (int)$threadItemId > 0) {
        return 'THREAD:ITEM:' . (int)$threadItemId . ($actor !== '' ? ('|' . $actor) : '');
    }
    return 'THREAD:CARE' . ($actor !== '' ? ('|' . $actor) : '');
}

function sort_messages_by_time(&$messages)
{
    usort($messages, function ($a, $b) {
        $ta = strtotime((string)($a['time'] ?? ''));
        $tb = strtotime((string)($b['time'] ?? ''));
        if ($ta === $tb) {
            return 0;
        }
        return ($ta < $tb) ? -1 : 1;
    });
}

function strip_medtravel_services_requested_block($additionalNotes)
{
    $notes = trim((string)$additionalNotes);
    if ($notes === '') {
        return '';
    }

    $cleaned = preg_replace('/(?:\R|^)\s*MedTravel Services Requested:\s*(?:\R\s*-\s.*)*/i', '', $notes);
    if (!is_string($cleaned)) {
        return $notes;
    }
    $cleaned = preg_replace('/\R{3,}/', "\n\n", $cleaned);
    return trim((string)$cleaned);
}

function find_explicit_provider_complementary_relation($conexion)
{
    $candidates = [
        ['table' => 'provider_complementary_services', 'provider_col' => 'provider_id', 'service_col' => 'medtravel_service_id'],
        ['table' => 'provider_medtravel_services', 'provider_col' => 'provider_id', 'service_col' => 'medtravel_service_id'],
        ['table' => 'provider_complementary_service_map', 'provider_col' => 'provider_id', 'service_col' => 'medtravel_service_id'],
        ['table' => 'medical_provider_complementary_services', 'provider_col' => 'provider_id', 'service_col' => 'medtravel_service_id'],
        ['table' => 'provider_complementary_services', 'provider_col' => 'provider_id', 'service_col' => 'complementary_service_id'],
        ['table' => 'provider_medtravel_services', 'provider_col' => 'provider_id', 'service_col' => 'complementary_service_id'],
    ];

    foreach ($candidates as $candidate) {
        $table = $candidate['table'];
        $providerCol = $candidate['provider_col'];
        $serviceCol = $candidate['service_col'];
        if (!table_exists($conexion, $table)) {
            continue;
        }
        if (!table_has_column($conexion, $table, $providerCol) || !table_has_column($conexion, $table, $serviceCol)) {
            continue;
        }
        return $candidate;
    }

    return null;
}

function build_medical_provider_scope($conexion, $providerId)
{
    $providerId = (int)$providerId;
    $medicalClause = "(bri.item_type = 'medical_offer' AND EXISTS (
        SELECT 1
        FROM provider_service_offers o_scope
        WHERE o_scope.id = bri.offer_id
          AND o_scope.provider_id = ?
    ))";

    $relation = find_explicit_provider_complementary_relation($conexion);
    if (is_array($relation)) {
        $table = $relation['table'];
        $providerCol = $relation['provider_col'];
        $serviceCol = $relation['service_col'];
        $complementaryClause = "(bri.item_type = 'complementary_service' AND EXISTS (
            SELECT 1
            FROM `{$table}` rel
            WHERE rel.`{$providerCol}` = ?
              AND rel.`{$serviceCol}` = bri.medtravel_service_id
        ))";
        return [
            'where' => ' AND (' . $medicalClause . ' OR ' . $complementaryClause . ')',
            'types' => 'ii',
            'params' => [$providerId, $providerId],
        ];
    }

    return [
        'where' => ' AND ' . $medicalClause,
        'types' => 'i',
        'params' => [$providerId],
    ];
}

function fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete)
{
    $assignedStaffExpr = table_has_column($conexion, 'booking_request_items', 'assigned_staff_id') ? 'bri.assigned_staff_id' : 'NULL';
    $providerIdExpr = table_has_column($conexion, 'booking_request_items', 'provider_id') ? 'bri.provider_id' : 'NULL';
    $clientUserIdExpr = table_has_column($conexion, 'booking_requests', 'client_user_id') ? 'br.client_user_id' : 'NULL';
    $sql = "SELECT
                bri.id,
                bri.booking_request_id,
                {$providerIdExpr} AS provider_id,
                {$clientUserIdExpr} AS client_user_id,
                {$assignedStaffExpr} AS assigned_staff_id,
                CASE
                    WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                    WHEN bri.item_status = 'completed' THEN 'treatment_completed'
                    ELSE bri.item_status
                END AS current_status,
                COALESCE(NULLIF(bri.currency, ''), NULLIF(o.currency, ''), NULLIF(ms.currency, ''), 'USD') AS base_currency,
                COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            LEFT JOIN service_catalog sc ON sc.id = o.service_id
            LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
            WHERE bri.id = ?";

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    $types = 'i' . $scopeTypes;
    $params = array_merge([$itemId], $scopeParams);
    bind_stmt_params($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function current_booking_actor_user_id()
{
    $userId = current_admin_user_id();
    if ($userId > 0) {
        return $userId;
    }

    $userId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
    if ($userId > 0) {
        return $userId;
    }

    $userId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    return $userId > 0 ? $userId : 0;
}

function fetch_item_assignment_context($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete, $offerProviderCatalogServiceExpr)
{
    $assignedDoctorExpr = table_has_column($conexion, 'booking_request_items', 'assigned_doctor')
        ? 'bri.assigned_doctor'
        : (table_has_column($conexion, 'booking_request_items', 'doctor_name') ? 'bri.doctor_name' : "''");
    $clinicExpr = table_has_column($conexion, 'booking_request_items', 'clinic')
        ? 'bri.clinic'
        : (table_has_column($conexion, 'booking_request_items', 'clinic_name') ? 'bri.clinic_name' : "''");

    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id,
                bri.item_type,
                " . (table_has_column($conexion, 'booking_request_items', 'provider_id') ? 'bri.provider_id' : 'o.provider_id') . " AS provider_id,
                bri.offer_id,
                o.service_id AS service_id,
                {$offerProviderCatalogServiceExpr} AS provider_catalog_service_id,
                " . (table_has_column($conexion, 'booking_request_items', 'assigned_staff_id') ? 'bri.assigned_staff_id' : 'NULL') . " AS assigned_staff_id,
                {$assignedDoctorExpr} AS assigned_doctor,
                {$clinicExpr} AS clinic
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            WHERE bri.id = ?";

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    $types = 'i' . $scopeTypes;
    $params = array_merge([$itemId], $scopeParams);
    bind_stmt_params($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_staff_assignment_service_sets($conexion, $providerId, $staffId)
{
    $providerId = (int)$providerId;
    $staffId = (int)$staffId;
    if ($providerId <= 0 || $staffId <= 0 || !table_exists($conexion, 'provider_medical_staff_services')) {
        return ['provider_catalog_service_ids' => [], 'service_ids' => []];
    }

    $hasRelActive = table_has_column($conexion, 'provider_medical_staff_services', 'active');
    $hasRelProviderCatalogServiceId = table_has_column($conexion, 'provider_medical_staff_services', 'provider_catalog_service_id');
    $hasProviderCatalogServices = table_exists($conexion, 'provider_catalog_services') && table_has_column($conexion, 'provider_catalog_services', 'id');
    $hasProviderCatalogServiceActive = $hasProviderCatalogServices && table_has_column($conexion, 'provider_catalog_services', 'is_active');
    $hasServiceCatalog = table_exists($conexion, 'service_catalog') && table_has_column($conexion, 'service_catalog', 'id');
    $hasServiceActive = $hasServiceCatalog && table_has_column($conexion, 'service_catalog', 'is_active');
    $hasServiceDeleted = $hasServiceCatalog && table_has_column($conexion, 'service_catalog', 'is_deleted');

    $providerCatalogServiceSelect = $hasProviderCatalogServices && $hasRelProviderCatalogServiceId
        ? 'COALESCE(pcs.id, pcs_legacy.provider_catalog_service_id)'
        : 'pcs_legacy.provider_catalog_service_id';
    $serviceIdSelect = $hasProviderCatalogServices && $hasRelProviderCatalogServiceId
        ? 'COALESCE(pcs.service_id, pcs_legacy.service_id, rel.service_id)'
        : 'COALESCE(pcs_legacy.service_id, rel.service_id)';

    $sql = 'SELECT
                ' . $providerCatalogServiceSelect . ' AS provider_catalog_service_id,
                ' . $serviceIdSelect . ' AS service_id
            FROM provider_medical_staff_services rel
            INNER JOIN provider_medical_staff pms ON pms.id = rel.provider_medical_staff_id';

    if ($hasProviderCatalogServices && $hasRelProviderCatalogServiceId) {
        $sql .= ' LEFT JOIN provider_catalog_services pcs
                    ON pcs.id = rel.provider_catalog_service_id
                   AND pcs.provider_id = pms.provider_id';
        $sql .= ' LEFT JOIN (
                    SELECT provider_id, service_id, MIN(id) AS provider_catalog_service_id
                    FROM provider_catalog_services'
            . ($hasProviderCatalogServiceActive ? ' WHERE is_active = 1' : '')
            . ' GROUP BY provider_id, service_id
                    HAVING COUNT(*) = 1
                 ) pcs_legacy
                    ON pcs_legacy.provider_id = pms.provider_id
                   AND pcs_legacy.service_id = rel.service_id';
    } elseif ($hasProviderCatalogServices) {
        $sql .= ' LEFT JOIN (
                    SELECT provider_id, service_id, NULL AS provider_catalog_service_id
                    FROM provider_catalog_services
                    GROUP BY provider_id, service_id
                 ) pcs_legacy
                    ON pcs_legacy.provider_id = pms.provider_id
                   AND pcs_legacy.service_id = rel.service_id';
    } else {
        $sql .= ' LEFT JOIN (
                    SELECT NULL AS provider_id, NULL AS service_id, NULL AS provider_catalog_service_id
                 ) pcs_legacy ON 1=0';
    }

    if ($hasServiceCatalog) {
        $sql .= ' INNER JOIN service_catalog sc
                    ON sc.id = ' . $serviceIdSelect;
    }

    $sql .= ' WHERE pms.provider_id = ?
              AND rel.provider_medical_staff_id = ?';

    if ($hasRelActive) {
        $sql .= ' AND rel.active = 1';
    }
    if ($hasProviderCatalogServiceActive && $hasProviderCatalogServices && $hasRelProviderCatalogServiceId) {
        $sql .= ' AND COALESCE(pcs.is_active, 1) = 1';
    }
    if ($hasServiceCatalog && $hasServiceActive) {
        $sql .= ' AND sc.is_active = 1';
    }
    if ($hasServiceCatalog && $hasServiceDeleted) {
        $sql .= ' AND sc.is_deleted = 0';
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['provider_catalog_service_ids' => [], 'service_ids' => []];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $providerId, $staffId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $providerCatalogServiceIds = [];
    $serviceIds = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (isset($row['provider_catalog_service_id']) && $row['provider_catalog_service_id'] !== null) {
            $providerCatalogServiceIds[] = (int)$row['provider_catalog_service_id'];
        }
        $resolvedServiceId = (int)($row['service_id'] ?? 0);
        if ($resolvedServiceId > 0) {
            $serviceIds[] = $resolvedServiceId;
        }
    }
    mysqli_stmt_close($stmt);

    return [
        'provider_catalog_service_ids' => array_values(array_unique($providerCatalogServiceIds)),
        'service_ids' => array_values(array_unique($serviceIds)),
    ];
}

function is_staff_eligible_for_item_assignment($itemContext, $staffServiceSets)
{
    $targetProviderCatalogServiceId = (int)($itemContext['provider_catalog_service_id'] ?? 0);
    $targetServiceId = (int)($itemContext['service_id'] ?? 0);
    $providerCatalogServiceIds = array_map('intval', (array)($staffServiceSets['provider_catalog_service_ids'] ?? []));
    $serviceIds = array_map('intval', (array)($staffServiceSets['service_ids'] ?? []));

    $matchesProviderCatalogService = ($targetProviderCatalogServiceId > 0)
        ? in_array($targetProviderCatalogServiceId, $providerCatalogServiceIds, true)
        : false;
    $matchesLegacyService = ($targetServiceId > 0)
        ? in_array($targetServiceId, $serviceIds, true)
        : false;

    return $matchesProviderCatalogService || (!$matchesProviderCatalogService && $matchesLegacyService);
}

function maybe_assign_item_to_current_linked_staff($conexion, $itemId, $providerId, $currentLinkedStaffId, $currentAssignedStaffId, $hasItemAssignedStaffId, $hasItemAssignedAt, $hasItemAssignedByUserId, $hasItemsSoftDelete, $hasRequestsSoftDelete, $currentStatus = '')
{
    $itemId = (int)$itemId;
    $providerId = (int)$providerId;
    $currentLinkedStaffId = (int)$currentLinkedStaffId;
    $currentAssignedStaffId = (int)$currentAssignedStaffId;

    if ($itemId <= 0 || $providerId <= 0 || $currentLinkedStaffId <= 0 || !$hasItemAssignedStaffId) {
        return ['ok' => true, 'assigned' => false];
    }

    if ($currentAssignedStaffId > 0) {
        if ($currentAssignedStaffId !== $currentLinkedStaffId) {
            return ['ok' => false, 'assigned' => false, 'message' => 'item_assigned_to_other_staff', 'status' => 403];
        }
        return ['ok' => true, 'assigned' => false];
    }

    if (normalize_legacy_item_status($currentStatus) !== 'pending_provider') {
        return [
            'ok' => false,
            'assigned' => false,
            'message' => 'Este item ya no puede asumirse automáticamente. La administración del prestador debe asignar al responsable operativo.',
            'status' => 409
        ];
    }

    $staffRow = provider_staff_fetch_basic_row($conexion, $currentLinkedStaffId, $providerId);
    if (!$staffRow) {
        return ['ok' => false, 'assigned' => false, 'message' => 'invalid_staff_context', 'status' => 403];
    }

    $setParts = ['bri.assigned_staff_id = ?'];
    $types = 'i';
    $params = [$currentLinkedStaffId];

    if ($hasItemAssignedAt) {
        $setParts[] = 'bri.assigned_at = NOW()';
    }

    $actorUserId = current_booking_actor_user_id();
    if ($hasItemAssignedByUserId && $actorUserId > 0) {
        $setParts[] = 'bri.assigned_by_user_id = ?';
        $types .= 'i';
        $params[] = $actorUserId;
    }

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET " . implode(', ', $setParts) . "
            WHERE bri.id = ?
              AND (bri.assigned_staff_id IS NULL OR bri.assigned_staff_id = 0)";
    $types .= 'i';
    $params[] = $itemId;

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['ok' => false, 'assigned' => false, 'message' => 'db_prepare_error', 'status' => 500];
    }

    bind_stmt_params($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'assigned' => false, 'message' => 'db_error: ' . $err, 'status' => 500];
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected <= 0) {
        return ['ok' => false, 'assigned' => false, 'message' => 'item_assignment_conflict', 'status' => 409];
    }

    return ['ok' => true, 'assigned' => true, 'assigned_staff_id' => $currentLinkedStaffId];
}

function fetch_booking_additional_notes($conexion, $bookingRequestId, $hasRequestsSoftDelete)
{
    if (!table_has_column($conexion, 'booking_requests', 'additional_notes')) {
        return '';
    }

    $sql = "SELECT additional_notes FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return '';
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (string)($row['additional_notes'] ?? '');
}

function sync_booking_fee_gate_state($conexion, $bookingRequestId, $hasRequestsSoftDelete)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0) {
        return;
    }
    if (!table_exists($conexion, 'booking_requests') || !table_exists($conexion, 'booking_request_items')) {
        return;
    }

    $hasFeeStatus = table_has_column($conexion, 'booking_requests', 'fee_status');
    $hasFeeRequired = table_has_column($conexion, 'booking_requests', 'fee_required');
    if (!$hasFeeStatus && !$hasFeeRequired) {
        return;
    }
    if (!table_has_column($conexion, 'booking_request_items', 'item_status')) {
        return;
    }

    $hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $normalizedStatusExpr = "CASE
        WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
        WHEN bri.item_status = 'completed' THEN 'treatment_completed'
        ELSE bri.item_status
    END";

    $statsSql = "SELECT
                    COUNT(*) AS total_count,
                      SUM(CASE WHEN {$normalizedStatusExpr} IN ('provider_confirmed', 'appointment_confirmed', 'client_accepted', 'treatment_completed', 'post_treatment_follow_up') THEN 1 ELSE 0 END) AS confirmed_count,
                  SUM(CASE WHEN {$normalizedStatusExpr} IN ('provider_rejected', 'cancelled', 'appointment_cancelled') THEN 1 ELSE 0 END) AS terminal_count
                 FROM booking_request_items bri
                 WHERE bri.booking_request_id = ?";
    if ($hasItemsSoftDelete) {
        $statsSql .= " AND bri.is_deleted = 0";
    }
    $statsSql .= " LIMIT 1";

    $stmtStats = mysqli_prepare($conexion, $statsSql);
    if (!$stmtStats) {
        return;
    }
    mysqli_stmt_bind_param($stmtStats, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmtStats)) {
        mysqli_stmt_close($stmtStats);
        return;
    }
    $statsRes = mysqli_stmt_get_result($stmtStats);
    $statsRow = $statsRes ? mysqli_fetch_assoc($statsRes) : null;
    mysqli_stmt_close($stmtStats);
    if (!$statsRow) {
        return;
    }

    $totalCount = (int)($statsRow['total_count'] ?? 0);
    $confirmedCount = (int)($statsRow['confirmed_count'] ?? 0);
    $terminalCount = (int)($statsRow['terminal_count'] ?? 0);

    $targetFeeRequired = 0;
    $targetFeeStatus = 'pending';
    if ($confirmedCount > 0) {
        $targetFeeRequired = 1;
        $targetFeeStatus = 'pending';
    } elseif ($totalCount > 0 && $terminalCount >= $totalCount) {
        $targetFeeRequired = 0;
        $targetFeeStatus = 'not_required';
    }

    $setParts = [];
    $types = '';
    $params = [];
    if ($hasFeeRequired) {
        $setParts[] = 'fee_required = ?';
        $types .= 'i';
        $params[] = $targetFeeRequired;
    }
    if ($hasFeeStatus) {
        $setParts[] = "fee_status = CASE
            WHEN LOWER(TRIM(COALESCE(fee_status, 'pending'))) = 'paid' THEN 'paid'
            ELSE ?
        END";
        $types .= 's';
        $params[] = $targetFeeStatus;
    }
    if (empty($setParts)) {
        return;
    }

    $updateSql = "UPDATE booking_requests
                  SET " . implode(', ', $setParts) . "
                  WHERE id = ?";
    $types .= 'i';
    $params[] = $bookingRequestId;
    if ($hasRequestsSoftDelete) {
        $updateSql .= " AND is_deleted = 0";
    }
    $updateSql .= " LIMIT 1";

    $stmtUpdate = mysqli_prepare($conexion, $updateSql);
    if (!$stmtUpdate) {
        return;
    }
    bind_stmt_params($stmtUpdate, $types, $params);
    mysqli_stmt_execute($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
}

function rollup_booking_status($conexion, $bookingRequestId, $hasRequestsSoftDelete)
{
    $bookingRequestId = (int)$bookingRequestId;
    if ($bookingRequestId <= 0) {
        return;
    }
    if (!table_exists($conexion, 'booking_requests') || !table_exists($conexion, 'booking_request_items')) {
        return;
    }
    if (!table_has_column($conexion, 'booking_requests', 'status')) {
        return;
    }
    if (!table_has_column($conexion, 'booking_request_items', 'item_status')) {
        return;
    }

    $hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
    $normalizedStatusExpr = "CASE
        WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
        WHEN bri.item_status = 'completed' THEN 'treatment_completed'
        ELSE bri.item_status
    END";

    $statsSql = "SELECT
                    COUNT(*) AS total_count,
                      SUM(CASE WHEN {$normalizedStatusExpr} IN ('provider_confirmed', 'appointment_confirmed', 'client_accepted', 'treatment_completed', 'post_treatment_follow_up') THEN 1 ELSE 0 END) AS confirmed_count,
                  SUM(CASE WHEN {$normalizedStatusExpr} IN ('provider_rejected', 'cancelled', 'appointment_cancelled') THEN 1 ELSE 0 END) AS terminal_count
                 FROM booking_request_items bri
                 WHERE bri.booking_request_id = ?";
    if ($hasItemsSoftDelete) {
        $statsSql .= " AND bri.is_deleted = 0";
    }
    $statsSql .= " LIMIT 1";

    $stmtStats = mysqli_prepare($conexion, $statsSql);
    if (!$stmtStats) {
        return;
    }
    mysqli_stmt_bind_param($stmtStats, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmtStats)) {
        mysqli_stmt_close($stmtStats);
        return;
    }
    $statsRes = mysqli_stmt_get_result($stmtStats);
    $statsRow = $statsRes ? mysqli_fetch_assoc($statsRes) : null;
    mysqli_stmt_close($stmtStats);
    if (!$statsRow) {
        return;
    }

    $totalCount = (int)($statsRow['total_count'] ?? 0);
    $confirmedCount = (int)($statsRow['confirmed_count'] ?? 0);
    $terminalCount = (int)($statsRow['terminal_count'] ?? 0);

    $targetStatus = 'pending';
    if ($confirmedCount > 0) {
        $targetStatus = 'confirmed';
    } elseif ($totalCount > 0 && $terminalCount >= $totalCount) {
        $targetStatus = 'cancelled';
    }

    $currentSql = "SELECT status FROM booking_requests WHERE id = ?";
    if ($hasRequestsSoftDelete) {
        $currentSql .= " AND is_deleted = 0";
    }
    $currentSql .= " LIMIT 1";
    $stmtCurrent = mysqli_prepare($conexion, $currentSql);
    if (!$stmtCurrent) {
        return;
    }
    mysqli_stmt_bind_param($stmtCurrent, 'i', $bookingRequestId);
    if (!mysqli_stmt_execute($stmtCurrent)) {
        mysqli_stmt_close($stmtCurrent);
        return;
    }
    $currentRes = mysqli_stmt_get_result($stmtCurrent);
    $currentRow = $currentRes ? mysqli_fetch_assoc($currentRes) : null;
    mysqli_stmt_close($stmtCurrent);
    if (!$currentRow) {
        return;
    }
    $currentStatus = strtolower(trim((string)($currentRow['status'] ?? '')));

    if ($targetStatus === 'pending' && $currentStatus !== 'pending') {
        return;
    }
    if ($currentStatus === $targetStatus) {
        return;
    }

    $setParts = ['status = ?'];
    $types = 's';
    $params = [$targetStatus];
    if (table_has_column($conexion, 'booking_requests', 'updated_at')) {
        $setParts[] = 'updated_at = NOW()';
    }

    $updateSql = "UPDATE booking_requests
                  SET " . implode(', ', $setParts) . "
                  WHERE id = ?";
    $types .= 'i';
    $params[] = $bookingRequestId;
    if ($hasRequestsSoftDelete) {
        $updateSql .= " AND is_deleted = 0";
    }
    $updateSql .= " LIMIT 1";

    $stmtUpdate = mysqli_prepare($conexion, $updateSql);
    if (!$stmtUpdate) {
        return;
    }
    bind_stmt_params($stmtUpdate, $types, $params);
    mysqli_stmt_execute($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);
}

if (!user_can(PERM_BOOKING_VIEW) && !user_can(PERM_BOOKING_MANAGE)) {
    json_err('forbidden', 403);
}

if (!table_exists($conexion, 'booking_request_items')) {
    json_err('booking_request_items_not_available', 409);
}

$providerId = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
$serviceProviderId = isset($_SESSION['service_provider_id']) ? intval($_SESSION['service_provider_id']) : 0;
$isAdminSession = is_role_admin_session();
$isComplementarySession = is_complementary_user_session();
$sessionRoleText = strtolower(trim((string)($_SESSION['rol'] ?? '')));
$sessionRoleId = current_role_id();
$hasComplementaryRoleHint = strpos($sessionRoleText, 'complement') !== false || strpos($sessionRoleText, 'partner') !== false;
$isLikelyMedicalProviderRole = in_array((int)$sessionRoleId, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true)
    || strpos($sessionRoleText, 'prestador') !== false
    || (!$hasComplementaryRoleHint && strpos($sessionRoleText, 'provider') !== false);
$isMedicalProviderSession = !$isAdminSession && ($isLikelyMedicalProviderRole || ($providerId > 0 && !$isComplementarySession));

if ($isMedicalProviderSession && $providerId <= 0) {
    json_err('provider_id_required', 401);
}

if (!$isAdminSession && !$isMedicalProviderSession && $serviceProviderId <= 0) {
    json_err('forbidden', 403);
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : 'list');
$GLOBALS['my_booking_requests_debug_action'] = (string)$action;
my_booking_requests_debug_log('request', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'post_bytes' => strlen((string)http_build_query($_POST)),
    'post_keys' => array_keys($_POST),
    'item_id' => $_POST['item_id'] ?? $_GET['item_id'] ?? null,
]);
$canonicalItemStatuses = [
    'pending_provider',
    'provider_confirmed',
    'provider_rejected',
    'provider_proposed_change',
    'awaiting_client',
    'client_accepted',
    // Nuevos estados ciclo de vida médico 2026-04-15
    'virtual_assessment_pending',
    'virtual_assessment_done',
    'treatment_plan_agreed',
    'procedure_scheduled',
    'treatment_completed',
    'post_treatment_follow_up',
    'case_closed',
    'client_rejected',
    'cancelled',
];
$providerAllowedTargets = [
    'provider_confirmed',
    'provider_rejected',
    'provider_proposed_change',
    // Nuevos targets 2026-04-15
    'virtual_assessment_pending',
    'virtual_assessment_done',
    'treatment_plan_agreed',
    'procedure_scheduled',
    'treatment_completed',
    'post_treatment_follow_up',
    'case_closed',
    // Reversas controladas (requieren motivo)
    'pending_provider',
    'provider_confirmed',
    'virtual_assessment_pending',
];

$hasItemsSoftDelete = table_has_column($conexion, 'booking_request_items', 'is_deleted');
$hasRequestsSoftDelete = table_has_column($conexion, 'booking_requests', 'is_deleted');
$hasItemsProviderId = table_has_column($conexion, 'booking_request_items', 'provider_id');
$hasItemsServiceProviderId = table_has_column($conexion, 'booking_request_items', 'service_provider_id');
$hasItemStatus = table_has_column($conexion, 'booking_request_items', 'item_status');
$hasItemAssignedStaffId = table_has_column($conexion, 'booking_request_items', 'assigned_staff_id');
$hasItemAssignedAt = table_has_column($conexion, 'booking_request_items', 'assigned_at');
$hasItemAssignedByUserId = table_has_column($conexion, 'booking_request_items', 'assigned_by_user_id');
$hasItemCreatedAt = table_has_column($conexion, 'booking_request_items', 'created_at');
$hasItemUpdatedAt = table_has_column($conexion, 'booking_request_items', 'updated_at');
$hasItemCurrency = table_has_column($conexion, 'booking_request_items', 'currency');
$hasItemNotes = table_has_column($conexion, 'booking_request_items', 'notes');
$hasItemProposedPrice = table_has_column($conexion, 'booking_request_items', 'proposed_price');

$hasProviderResponseAt = table_has_column($conexion, 'booking_request_items', 'provider_response_at');
$hasProviderResponseBy = table_has_column($conexion, 'booking_request_items', 'provider_response_by');
$hasProviderRejectReason = table_has_column($conexion, 'booking_request_items', 'provider_reject_reason');
$hasProviderProposedDateFrom = table_has_column($conexion, 'booking_request_items', 'provider_proposed_date_from');
$hasProviderProposedDateTo = table_has_column($conexion, 'booking_request_items', 'provider_proposed_date_to');
$hasProviderProposedPrice = table_has_column($conexion, 'booking_request_items', 'provider_proposed_price');
$hasProviderProposedCurrency = table_has_column($conexion, 'booking_request_items', 'provider_proposed_currency');
$hasProviderNotes = table_has_column($conexion, 'booking_request_items', 'provider_notes');
$hasTreatmentCompletedAt = table_has_column($conexion, 'booking_request_items', 'treatment_completed_at');
$hasTreatmentCompletedByUserId = table_has_column($conexion, 'booking_request_items', 'treatment_completed_by_user_id');
$hasFollowUpStartedAt = table_has_column($conexion, 'booking_request_items', 'follow_up_started_at');
$hasFollowUpStartedByUserId = table_has_column($conexion, 'booking_request_items', 'follow_up_started_by_user_id');
// Nuevos guards ciclo de vida médico 2026-04-15
$hasAssessmentDoneAt = table_has_column($conexion, 'booking_request_items', 'assessment_done_at');
$hasAssessmentDoneByUserId = table_has_column($conexion, 'booking_request_items', 'assessment_done_by_user_id');
$hasAssessmentNotes = table_has_column($conexion, 'booking_request_items', 'assessment_notes');
$hasPlanAgreedAt = table_has_column($conexion, 'booking_request_items', 'plan_agreed_at');
$hasPlanAgreedByUserId = table_has_column($conexion, 'booking_request_items', 'plan_agreed_by_user_id');
$hasPlanDescription = table_has_column($conexion, 'booking_request_items', 'plan_description');
$hasProcedureScheduledAt = table_has_column($conexion, 'booking_request_items', 'procedure_scheduled_at');
$hasProcedureScheduledByUserId = table_has_column($conexion, 'booking_request_items', 'procedure_scheduled_by_user_id');
$hasCaseClosedAt = table_has_column($conexion, 'booking_request_items', 'case_closed_at');
$hasCaseClosedByUserId = table_has_column($conexion, 'booking_request_items', 'case_closed_by_user_id');
$hasCaseCloseReason = table_has_column($conexion, 'booking_request_items', 'case_close_reason');
$hasReversalReason = table_has_column($conexion, 'booking_request_items', 'reversal_reason');
$hasReversalByUserId = table_has_column($conexion, 'booking_request_items', 'reversal_by_user_id');
$hasReversalAt = table_has_column($conexion, 'booking_request_items', 'reversal_at');

$hasTimelineFrom = table_has_column($conexion, 'booking_requests', 'timeline_from');
$hasTimelineTo = table_has_column($conexion, 'booking_requests', 'timeline_to');
$hasSpecialRequest = table_has_column($conexion, 'booking_requests', 'special_request');
$hasAdditionalNotes = table_has_column($conexion, 'booking_requests', 'additional_notes');
$hasBookingName = table_has_column($conexion, 'booking_requests', 'name');
$hasBookingEmail = table_has_column($conexion, 'booking_requests', 'email');
$hasBookingPhone = table_has_column($conexion, 'booking_requests', 'phone');
$hasBookingFeeStatus = table_has_column($conexion, 'booking_requests', 'fee_status');
$hasFeeRequired = table_has_column($conexion, 'booking_requests', 'fee_required');
$hasBookingClientUserId = table_has_column($conexion, 'booking_requests', 'client_user_id');
$hasBookingOrigin = table_has_column($conexion, 'booking_requests', 'origin');
$hasBookingPersons = table_has_column($conexion, 'booking_requests', 'persons');
$hasBookingCategory = table_has_column($conexion, 'booking_requests', 'category');
$hasBookingServiceCategories = table_has_column($conexion, 'booking_requests', 'service_categories');
$hasBookingMedicalServices = table_has_column($conexion, 'booking_requests', 'medical_services');
$hasSvcCatTable = table_exists($conexion, 'service_categories');
$hasBookingBudget = table_has_column($conexion, 'booking_requests', 'budget');
$hasBookingStatus = table_has_column($conexion, 'booking_requests', 'status');
$hasBookingDatetime = table_has_column($conexion, 'booking_requests', 'booking_datetime');
$hasBookingSelectedOffers = table_has_column($conexion, 'booking_requests', 'selected_offers');
$hasBookingCreatedAt = table_has_column($conexion, 'booking_requests', 'created_at');
$hasBookingUpdatedAt = table_has_column($conexion, 'booking_requests', 'updated_at');
$hasTimelineUpdatedAt = table_has_column($conexion, 'booking_requests', 'timeline_updated_at');
$hasTimelineUpdatedByUserId = table_has_column($conexion, 'booking_requests', 'timeline_updated_by_user_id');
$hasTimelineUpdateReason = table_has_column($conexion, 'booking_requests', 'timeline_update_reason');

// TODO: when richer coordination columns land in DB, these optional aliases will start populating automatically.
$itemProviderStatusCol = first_existing_column($conexion, 'booking_request_items', ['provider_status']);
$itemMedicalCoordStatusCol = first_existing_column($conexion, 'booking_request_items', ['medical_coordination_status', 'coordination_status']);
$itemAssignedStaffExpr = $hasItemAssignedStaffId ? 'bri.assigned_staff_id' : 'NULL';
$itemAssignedDoctorCol = first_existing_column($conexion, 'booking_request_items', ['assigned_doctor', 'doctor_name']);
$itemClinicCol = first_existing_column($conexion, 'booking_request_items', ['clinic', 'clinic_name']);
$itemProposedAppointmentCol = first_existing_column($conexion, 'booking_request_items', ['proposed_appointment_date']);
$itemConfirmedAppointmentCol = first_existing_column($conexion, 'booking_request_items', ['confirmed_appointment_date']);
$itemTimezoneCol = first_existing_column($conexion, 'booking_request_items', ['timezone', 'provider_timezone']);
$itemLocationCol = first_existing_column($conexion, 'booking_request_items', ['location']);
$itemRescheduleCountCol = first_existing_column($conexion, 'booking_request_items', ['reschedule_count']);
$itemLastProviderActionCol = first_existing_column($conexion, 'booking_request_items', ['last_provider_action']);
$offerProviderCatalogServiceExpr = table_has_column($conexion, 'provider_service_offers', 'provider_catalog_service_id')
    ? 'o.provider_catalog_service_id'
    : 'NULL';

if (!$hasItemStatus) {
    json_err('item_status_not_available', 409);
}

$currentLinkedStaffId = current_provider_medical_staff_id($conexion);
$isLinkedMedicalStaffSession = $isMedicalProviderSession && $currentLinkedStaffId > 0;

$scopeWhere = '';
$scopeTypes = '';
$scopeParams = [];
if ($isAdminSession) {
    $scopeWhere = '';
    $scopeTypes = '';
    $scopeParams = [];
} elseif ($isMedicalProviderSession) {
    if ($hasItemsProviderId) {
        $scopeWhere = " AND bri.provider_id = ? AND bri.item_type = 'medical_offer'";
        $scopeTypes = 'i';
        $scopeParams = [$providerId];
        if ($isLinkedMedicalStaffSession && $hasItemAssignedStaffId) {
            $scopeWhere .= " AND (bri.assigned_staff_id = ? OR (COALESCE(bri.assigned_staff_id, 0) = 0 AND (bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review'))))";
            $scopeTypes .= 'i';
            $scopeParams[] = $currentLinkedStaffId;
        }
    } else {
        $medicalScope = build_medical_provider_scope($conexion, $providerId);
        $scopeWhere = (string)$medicalScope['where'];
        $scopeTypes = (string)$medicalScope['types'];
        $scopeParams = is_array($medicalScope['params']) ? $medicalScope['params'] : [];
        if ($isLinkedMedicalStaffSession && $hasItemAssignedStaffId) {
            $scopeWhere .= " AND (bri.assigned_staff_id = ? OR (COALESCE(bri.assigned_staff_id, 0) = 0 AND (bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review'))))";
            $scopeTypes .= 'i';
            $scopeParams[] = $currentLinkedStaffId;
        }
    }
} else {
    if (!$hasItemsServiceProviderId) {
        json_err('service_provider_id_not_available', 409);
    }
    $scopeWhere = " AND bri.service_provider_id = ? AND bri.item_type = 'complementary_service'";
    $scopeTypes = 'i';
    $scopeParams = [$serviceProviderId];
}

$timelineFromExpr = $hasTimelineFrom ? 'br.timeline_from' : 'NULL';
$timelineToExpr = $hasTimelineTo ? 'br.timeline_to' : 'NULL';
$specialRequestExpr = $hasSpecialRequest ? 'br.special_request' : 'NULL';
$additionalNotesExpr = $hasAdditionalNotes ? 'br.additional_notes' : 'NULL';
$responseAtExpr = $hasProviderResponseAt ? 'bri.provider_response_at' : 'NULL';
$rejectReasonExpr = $hasProviderRejectReason ? 'bri.provider_reject_reason' : 'NULL';
$providerNotesExpr = $hasProviderNotes ? 'bri.provider_notes' : ($hasItemNotes ? 'bri.notes' : 'NULL');
$proposedDateFromExpr = $hasProviderProposedDateFrom ? 'bri.provider_proposed_date_from' : 'NULL';
$proposedDateToExpr = $hasProviderProposedDateTo ? 'bri.provider_proposed_date_to' : 'NULL';
$proposedPriceExpr = $hasProviderProposedPrice ? 'bri.provider_proposed_price' : ($hasItemProposedPrice ? 'bri.proposed_price' : 'NULL');
$proposedCurrencyExpr = $hasProviderProposedCurrency ? 'bri.provider_proposed_currency' : ($hasItemCurrency ? 'bri.currency' : 'NULL');
$providerStatusExpr = $itemProviderStatusCol ? 'bri.`' . $itemProviderStatusCol . '`' : 'NULL';
$medicalCoordinationStatusExpr = $itemMedicalCoordStatusCol ? 'bri.`' . $itemMedicalCoordStatusCol . '`' : 'NULL';
$assignedDoctorExpr = $itemAssignedDoctorCol ? 'bri.`' . $itemAssignedDoctorCol . '`' : 'NULL';
$clinicExpr = $itemClinicCol ? 'bri.`' . $itemClinicCol . '`' : 'NULL';
$proposedAppointmentExpr = $itemProposedAppointmentCol ? 'bri.`' . $itemProposedAppointmentCol . '`' : 'NULL';
$confirmedAppointmentExpr = $itemConfirmedAppointmentCol ? 'bri.`' . $itemConfirmedAppointmentCol . '`' : 'NULL';
$timezoneExpr = $itemTimezoneCol ? 'bri.`' . $itemTimezoneCol . '`' : 'NULL';
$locationExpr = $itemLocationCol ? 'bri.`' . $itemLocationCol . '`' : 'NULL';
$rescheduleCountExpr = $itemRescheduleCountCol ? 'bri.`' . $itemRescheduleCountCol . '`' : 'NULL';
$lastProviderActionExpr = $itemLastProviderActionCol ? 'bri.`' . $itemLastProviderActionCol . '`' : 'NULL';

if ($action === 'list_threads') {
    $threads = [];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 200);
    if ($limit < 1) {
        $limit = 200;
    }
    if ($limit > 500) {
        $limit = 500;
    }

    if ($isAdminSession) {
        $careSql = "SELECT br.id AS booking_request_id,
                           br.created_at,
                           br.destination,
                           " . ($hasBookingUpdatedAt ? "COALESCE(br.updated_at, br.created_at)" : "br.created_at") . " AS thread_updated_at
                    FROM booking_requests br
                    WHERE 1=1";
        if ($hasRequestsSoftDelete) {
            $careSql .= " AND br.is_deleted = 0";
        }
        $careSql .= " ORDER BY thread_updated_at DESC LIMIT " . (int)$limit;
        $careRes = mysqli_query($conexion, $careSql);
        if ($careRes) {
            while ($row = mysqli_fetch_assoc($careRes)) {
                $bookingId = (int)($row['booking_request_id'] ?? 0);
                if ($bookingId <= 0) {
                    continue;
                }
                $threads[] = [
                    'thread_key' => 'CARE:' . $bookingId,
                    'thread_type' => 'CARE',
                    'booking_request_id' => $bookingId,
                    'item_id' => 0,
                    'title' => 'General - Request #' . $bookingId,
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['thread_updated_at'] ?? $row['created_at'] ?? ''),
                ];
            }
        }
    }

    $itemSql = "SELECT
                    bri.id AS item_id,
                    bri.booking_request_id,
                    COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                    br.destination,
                    " . ($hasProviderResponseAt && $hasItemUpdatedAt && $hasItemCreatedAt
                        ? "COALESCE(bri.provider_response_at, bri.updated_at, bri.created_at, br.created_at)"
                        : ($hasItemUpdatedAt && $hasItemCreatedAt
                            ? "COALESCE(bri.updated_at, bri.created_at, br.created_at)"
                            : ($hasItemCreatedAt ? "COALESCE(bri.created_at, br.created_at)" : "br.created_at"))) . " AS thread_updated_at
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                LEFT JOIN service_catalog sc ON sc.id = o.service_id
                LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                WHERE 1=1";
    if ($hasItemsSoftDelete) {
        $itemSql .= " AND bri.is_deleted = 0";
    }
    if ($hasRequestsSoftDelete) {
        $itemSql .= " AND br.is_deleted = 0";
    }
    $itemSql .= $scopeWhere;
    $itemSql .= " ORDER BY thread_updated_at DESC, bri.id DESC LIMIT " . (int)$limit;

    $stmtThreads = mysqli_prepare($conexion, $itemSql);
    if ($stmtThreads) {
        if ($scopeTypes !== '') {
            bind_stmt_params($stmtThreads, $scopeTypes, $scopeParams);
        }
        if (mysqli_stmt_execute($stmtThreads)) {
            $threadsRes = mysqli_stmt_get_result($stmtThreads);
            while ($threadsRes && ($row = mysqli_fetch_assoc($threadsRes))) {
                $itemId = (int)($row['item_id'] ?? 0);
                $bookingId = (int)($row['booking_request_id'] ?? 0);
                if ($itemId <= 0 || $bookingId <= 0) {
                    continue;
                }
                $title = trim((string)($row['item_name'] ?? ''));
                if ($title === '') {
                    $title = 'Item #' . $itemId;
                }
                $threads[] = [
                    'thread_key' => 'ITEM:' . $itemId,
                    'thread_type' => 'ITEM',
                    'booking_request_id' => $bookingId,
                    'item_id' => $itemId,
                    'title' => $title . ' - Request #' . $bookingId,
                    'subtitle' => trim((string)($row['destination'] ?? '')),
                    'updated_at' => (string)($row['thread_updated_at'] ?? ''),
                ];
            }
        }
        mysqli_stmt_close($stmtThreads);
    }

    usort($threads, function ($a, $b) {
        $ta = strtotime((string)($a['updated_at'] ?? ''));
        $tb = strtotime((string)($b['updated_at'] ?? ''));
        if ($ta === $tb) {
            return strcmp((string)($a['thread_key'] ?? ''), (string)($b['thread_key'] ?? ''));
        }
        return ($ta > $tb) ? -1 : 1;
    });

    json_ok(['threads' => $threads]);
}

if ($action === 'list') {
    my_booking_requests_set_debug_branch('list');
    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id,
                bri.item_type,
                " . ($hasItemsProviderId ? 'bri.provider_id' : 'NULL') . " AS provider_id,
                {$itemAssignedStaffExpr} AS assigned_staff_id,
                {$assignedDoctorExpr} AS assigned_doctor,
                {$clinicExpr} AS clinic,
                bri.offer_id,
                bri.medtravel_service_id,
                CASE
                    WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                    ELSE bri.item_status
                END AS item_status,
                bri.created_at AS item_created_at,
                br.created_at AS booking_created_at,
                br.destination,
                {$timelineFromExpr} AS timeline_from,
                {$timelineToExpr} AS timeline_to,
                br.timeline,
                {$specialRequestExpr} AS special_request,
                {$additionalNotesExpr} AS additional_notes,
                {$responseAtExpr} AS provider_response_at,
                COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                COALESCE(NULLIF(ms.currency, ''), NULLIF(o.currency, ''), NULLIF(bri.currency, ''), 'USD') AS item_currency
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            LEFT JOIN service_catalog sc ON sc.id = o.service_id
            LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
            WHERE 1=1";

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' ORDER BY br.created_at DESC, bri.id DESC';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }
    if ($scopeTypes !== '') {
        bind_stmt_params($stmt, $scopeTypes, $scopeParams);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if ($isMedicalProviderSession && isset($row['additional_notes'])) {
            $row['additional_notes'] = strip_medtravel_services_requested_block((string)$row['additional_notes']);
        }
        $row = provider_staff_apply_assignment_payload($conexion, $row);
        $row = apply_operational_owner_meta($row, $isLinkedMedicalStaffSession, $currentLinkedStaffId);
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    json_ok(['data' => $rows]);
}

if ($action === 'get_detail') {
    my_booking_requests_set_debug_branch('get_detail');
    $itemId = intval($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id');
    }

    $bookingNameExpr = $hasBookingName ? 'br.name' : "''";
    $bookingEmailExpr = $hasBookingEmail ? 'br.email' : "''";
    $bookingPhoneExpr = $hasBookingPhone ? 'br.phone' : "''";
    $bookingFeeStatusExpr = $hasBookingFeeStatus ? 'br.fee_status' : "'pending'";
    $bookingFeeRequiredExpr = $hasFeeRequired ? 'br.fee_required' : '0';
    $bookingClientUserExpr = $hasBookingClientUserId ? 'br.client_user_id' : 'NULL';
    $bookingOriginExpr = $hasBookingOrigin ? 'br.origin' : "''";
    $bookingPersonsExpr = $hasBookingPersons ? 'br.persons' : "''";
    $bookingCategoryExpr = $hasBookingCategory ? 'br.category' : "''";
    $bookingServiceCategoriesExpr = $hasBookingServiceCategories ? 'br.service_categories' : "''";
    $bookingMedicalServicesExpr = $hasBookingMedicalServices ? 'br.medical_services' : "''";
    // Resolve category label via service_categories if table exists
    $svcCatJoin = ($hasSvcCatTable && $hasBookingCategory)
        ? "LEFT JOIN service_categories scat ON CAST(br.category AS UNSIGNED) = scat.id"
        : '';
    $categoryResolvedExpr = ($hasSvcCatTable && $hasBookingCategory)
        ? "COALESCE(NULLIF(scat.name, ''), NULLIF(br.category, ''), '')"
        : $bookingCategoryExpr;
    $bookingBudgetExpr = $hasBookingBudget ? 'br.budget' : "NULL";
    $bookingStatusExpr = $hasBookingStatus ? 'br.status' : "'pending'";
    $bookingDatetimeExpr = $hasBookingDatetime ? 'br.booking_datetime' : "''";
    $bookingSelectedOffersExpr = $hasBookingSelectedOffers ? 'br.selected_offers' : "''";
    $bookingCreatedAtExpr = $hasBookingCreatedAt ? 'br.created_at' : "NULL";
    $bookingUpdatedAtExpr = $hasBookingUpdatedAt ? 'br.updated_at' : "NULL";

    $sql = "SELECT
                bri.id AS item_id,
                bri.booking_request_id,
                bri.item_type,
                " . ($hasItemsProviderId ? 'bri.provider_id' : 'NULL') . " AS provider_id,
                " . ($hasItemsServiceProviderId ? 'bri.service_provider_id' : 'NULL') . " AS service_provider_id,
                bri.offer_id,
                o.service_id AS service_id,
                {$offerProviderCatalogServiceExpr} AS provider_catalog_service_id,
                CASE
                    WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                    ELSE bri.item_status
                END AS item_status,
                {$providerStatusExpr} AS provider_status,
                {$medicalCoordinationStatusExpr} AS medical_coordination_status,
                {$itemAssignedStaffExpr} AS assigned_staff_id,
                {$assignedDoctorExpr} AS assigned_doctor,
                {$clinicExpr} AS clinic,
                {$proposedAppointmentExpr} AS proposed_appointment_date,
                {$confirmedAppointmentExpr} AS confirmed_appointment_date,
                {$timezoneExpr} AS timezone,
                {$locationExpr} AS location,
                {$rescheduleCountExpr} AS reschedule_count,
                {$lastProviderActionExpr} AS last_provider_action,
                {$bookingNameExpr} AS client_name,
                {$bookingEmailExpr} AS client_email,
                {$bookingPhoneExpr} AS client_phone,
                {$bookingFeeStatusExpr} AS fee_status,
                {$bookingFeeRequiredExpr} AS fee_required,
                {$bookingClientUserExpr} AS client_user_id,
                {$bookingOriginExpr} AS origin,
                br.destination,
                {$bookingPersonsExpr} AS persons,
                {$categoryResolvedExpr} AS category,
                {$bookingServiceCategoriesExpr} AS service_categories,
                {$bookingMedicalServicesExpr} AS medical_services,
                {$bookingBudgetExpr} AS budget,
                {$bookingStatusExpr} AS booking_status,
                {$bookingDatetimeExpr} AS booking_datetime,
                {$bookingSelectedOffersExpr} AS selected_offers,
                {$bookingCreatedAtExpr} AS booking_created_at,
                {$bookingUpdatedAtExpr} AS booking_updated_at,
                {$timelineFromExpr} AS timeline_from,
                {$timelineToExpr} AS timeline_to,
                br.timeline,
                {$specialRequestExpr} AS special_request,
                {$additionalNotesExpr} AS additional_notes,
                {$responseAtExpr} AS provider_response_at,
                {$rejectReasonExpr} AS provider_reject_reason,
                {$providerNotesExpr} AS provider_notes,
                {$proposedDateFromExpr} AS provider_proposed_date_from,
                {$proposedDateToExpr} AS provider_proposed_date_to,
                {$proposedPriceExpr} AS provider_proposed_price,
                {$proposedCurrencyExpr} AS provider_proposed_currency,
                COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                COALESCE(NULLIF(ms.currency, ''), NULLIF(o.currency, ''), NULLIF(bri.currency, ''), 'USD') AS item_currency
            FROM booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            LEFT JOIN service_catalog sc ON sc.id = o.service_id
            LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
            {$svcCatJoin}
            WHERE bri.id = ?";

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    $types = 'i' . $scopeTypes;
    $params = array_merge([$itemId], $scopeParams);
    bind_stmt_params($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        json_err('not_found', 404);
    }

    $bookingRequestId = (int)$row['booking_request_id'];
    $coordinationFee = build_coordination_fee_meta($conexion, $bookingRequestId, $row, $isAdminSession);
    apply_provider_commission_gate_override($conexion, $coordinationFee, $row);
    $feeLocked = !$coordinationFee['unlocked'];
    $row['coordination_fee'] = $coordinationFee;
    $row['fee_locked'] = $feeLocked ? 1 : 0;
    $row['fee_status'] = $coordinationFee['status'];
    $row['fee_required'] = (int)$coordinationFee['required'];

    $contactAccess = resolve_contact_access_state($row['client_email'] ?? '', $row['client_phone'] ?? '', $coordinationFee['status'], $isAdminSession);
    $row['contact_access'] = $contactAccess;
    $row['client_email_raw'] = trim((string)($row['client_email'] ?? ''));
    $row['client_phone_raw'] = trim((string)($row['client_phone'] ?? ''));
    $row['client_email'] = $contactAccess['email_display'];
    $row['client_phone'] = $contactAccess['phone_display'];
    $rawAdditionalNotes = (string)($row['additional_notes'] ?? '');
    $row['messages'] = parse_additional_notes_messages($rawAdditionalNotes);
    if ($isMedicalProviderSession) {
        $row['additional_notes'] = strip_medtravel_services_requested_block($rawAdditionalNotes);
    }
    sort_messages_by_time($row['messages']);

    $documents = [];
    $documentsError = '';
    $clientId = (int)($row['client_user_id'] ?? 0);
    if ($clientId <= 0 && $hasBookingEmail) {
        $clientEmail = trim((string)($row['client_email'] ?? ''));
        if ($clientEmail !== '' && table_exists($conexion, 'clientes') && table_has_column($conexion, 'clientes', 'email')) {
            $clientLookupSql = "SELECT id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1";
            $stmtClient = mysqli_prepare($conexion, $clientLookupSql);
            if ($stmtClient) {
                mysqli_stmt_bind_param($stmtClient, 's', $clientEmail);
                if (mysqli_stmt_execute($stmtClient)) {
                    $clientRes = mysqli_stmt_get_result($stmtClient);
                    $clientRow = $clientRes ? mysqli_fetch_assoc($clientRes) : null;
                    if ($clientRow) {
                        $clientId = (int)($clientRow['id'] ?? 0);
                    }
                }
                mysqli_stmt_close($stmtClient);
            }
        }
    }

    if ($bookingRequestId > 0 && table_exists($conexion, 'client_documents')) {
        $docHasShared = table_has_column($conexion, 'client_documents', 'shared_with_provider');
        $docHasRequestId = table_has_column($conexion, 'client_documents', 'booking_request_id');
        $docHasItemId = table_has_column($conexion, 'client_documents', 'item_id');

        if (!$docHasRequestId || !$docHasItemId) {
            $documentsError = 'client_documents_scope_missing';
        } else {
            // Scope by booking_request_id only — same pattern as inbox.
            // client_documents.client_id = clientes.id; booking_requests.client_user_id = usuarios.id.
            // These are different ID spaces; using client_user_id as client_id filter is incorrect.
            $docSql = "SELECT id, document_type, file_path, filename, original_filename, file_size, mime_type, title, uploaded_at, booking_request_id, item_id
                       FROM client_documents WHERE 1=1";
            $docTypes = '';
            $docParams = [];
            if ($docHasShared) {
                $docSql .= " AND shared_with_provider = 1";
            }
            $docSql .= " AND booking_request_id = ?";
            $docTypes .= 'i';
            $docParams[] = $bookingRequestId;
            if ($itemId > 0) {
                $docSql .= " AND (item_id = ? OR item_id IS NULL)";
                $docTypes .= 'i';
                $docParams[] = $itemId;
            }
            $docSql .= " ORDER BY uploaded_at DESC";

            $stmtDocs = mysqli_prepare($conexion, $docSql);
            if ($stmtDocs) {
                bind_stmt_params($stmtDocs, $docTypes, $docParams);
                if (mysqli_stmt_execute($stmtDocs)) {
                    $docRes = mysqli_stmt_get_result($stmtDocs);
                    while ($docRes && ($docRow = mysqli_fetch_assoc($docRes))) {
                        $docRow['download_url'] = '/admin/ajax/download_medical_document.php?doc_id=' . (int)($docRow['id'] ?? 0);
                        $documents[] = $docRow;
                    }
                }
                mysqli_stmt_close($stmtDocs);
            }
        }
    }
    $row['documents'] = $documents;
    $row['documents_access'] = [
        'locked' => !$coordinationFee['unlocked'] && !$isAdminSession,
        'note' => (!$coordinationFee['unlocked'] && !$isAdminSession) ? 'Documentos bloqueados por condición de coordinación' : '',
        'has_documents' => !empty($documents),
    ];
    if ($documentsError !== '') {
        $row['documents_error'] = $documentsError;
    }

    $history = [];
    $historySql = "SELECT
                    bri.id AS item_id,
                    bri.item_type,
                    " . ($hasItemsProviderId ? 'bri.provider_id' : 'NULL') . " AS provider_id,
                    " . ($hasItemsServiceProviderId ? 'bri.service_provider_id' : 'NULL') . " AS service_provider_id,
                    CASE
                        WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                        ELSE bri.item_status
                    END AS item_status,
                    {$providerStatusExpr} AS provider_status,
                    {$medicalCoordinationStatusExpr} AS medical_coordination_status,
                    {$itemAssignedStaffExpr} AS assigned_staff_id,
                    {$assignedDoctorExpr} AS assigned_doctor,
                    {$clinicExpr} AS clinic,
                    {$proposedAppointmentExpr} AS proposed_appointment_date,
                    {$confirmedAppointmentExpr} AS confirmed_appointment_date,
                    {$timezoneExpr} AS timezone,
                    {$locationExpr} AS location,
                    {$rescheduleCountExpr} AS reschedule_count,
                    {$lastProviderActionExpr} AS last_provider_action,
                    " . ($hasItemCreatedAt ? "bri.created_at" : "NULL") . " AS item_created_at,
                    " . ($hasItemUpdatedAt ? "bri.updated_at" : "NULL") . " AS item_updated_at,
                    {$responseAtExpr} AS provider_response_at,
                    {$rejectReasonExpr} AS provider_reject_reason,
                    {$providerNotesExpr} AS provider_notes,
                    {$proposedDateFromExpr} AS provider_proposed_date_from,
                    {$proposedDateToExpr} AS provider_proposed_date_to,
                    {$proposedPriceExpr} AS provider_proposed_price,
                    {$proposedCurrencyExpr} AS provider_proposed_currency,
                    COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                    COALESCE(NULLIF(ms.currency, ''), NULLIF(o.currency, ''), NULLIF(bri.currency, ''), 'USD') AS item_currency
                FROM booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                LEFT JOIN service_catalog sc ON sc.id = o.service_id
                LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                WHERE bri.booking_request_id = ?";

    if ($hasItemsSoftDelete) {
        $historySql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $historySql .= ' AND br.is_deleted = 0';
    }
    $historySql .= $scopeWhere;
    $historySql .= ' ORDER BY bri.id ASC';

    $stmtHistory = mysqli_prepare($conexion, $historySql);
    if ($stmtHistory) {
        $historyTypes = 'i' . $scopeTypes;
        $historyParams = array_merge([$bookingRequestId], $scopeParams);
        bind_stmt_params($stmtHistory, $historyTypes, $historyParams);
        if (mysqli_stmt_execute($stmtHistory)) {
            $historyRes = mysqli_stmt_get_result($stmtHistory);
            while ($historyRes && ($historyRow = mysqli_fetch_assoc($historyRes))) {
                $history[] = $historyRow;
            }
        }
        mysqli_stmt_close($stmtHistory);
    }

    // Derive medical_services from item names (already in $history)
    $derivedSvcs = [];
    foreach ($history as $hRow) {
        $iname = trim((string)($hRow['item_name'] ?? ''));
        if ($iname !== '' && strpos($iname, 'Item #') !== 0) {
            $derivedSvcs[] = $iname;
        }
    }
    if (!empty($derivedSvcs)) {
        $row['medical_services'] = implode(', ', array_unique($derivedSvcs));
    }

    // Derive service_categories from items via service_catalog → service_categories
    if ($bookingRequestId > 0 && $hasSvcCatTable && table_exists($conexion, 'service_catalog')
        && table_has_column($conexion, 'service_catalog', 'category_id')) {
        $catDeriveSql = "SELECT DISTINCT COALESCE(NULLIF(scat.name, ''), 'General Medical') AS cat_name
            FROM booking_request_items bri
            LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
            LEFT JOIN service_catalog sc ON sc.id = o.service_id
            LEFT JOIN service_categories scat ON scat.id = sc.category_id
            WHERE bri.booking_request_id = ?";
        if ($hasItemsSoftDelete) {
            $catDeriveSql .= ' AND bri.is_deleted = 0';
        }
        $stmtCatDerive = mysqli_prepare($conexion, $catDeriveSql);
        if ($stmtCatDerive) {
            mysqli_stmt_bind_param($stmtCatDerive, 'i', $bookingRequestId);
            if (mysqli_stmt_execute($stmtCatDerive)) {
                $catDeriveRes = mysqli_stmt_get_result($stmtCatDerive);
                $derivedCats = [];
                while ($catDeriveRes && ($cdr = mysqli_fetch_assoc($catDeriveRes))) {
                    if (!empty($cdr['cat_name'])) {
                        $derivedCats[] = $cdr['cat_name'];
                    }
                }
                if (!empty($derivedCats)) {
                    $row['service_categories'] = implode(', ', array_unique($derivedCats));
                }
            }
            mysqli_stmt_close($stmtCatDerive);
        }
    }

    $calendarTraceMap = fetch_calendar_event_trace_map($conexion, array_merge([$itemId], array_column($history, 'item_id')));
    $row = enrich_item_trace_row($conexion, $row, $calendarTraceMap);
    foreach ($history as $historyIndex => $historyRow) {
        $history[$historyIndex] = enrich_item_trace_row($conexion, $historyRow, $calendarTraceMap);
    }

    $row['provider_catalog_service_id'] = isset($row['provider_catalog_service_id']) && $row['provider_catalog_service_id'] !== null
        ? (int)$row['provider_catalog_service_id']
        : null;
    $row['service_id'] = (int)($row['service_id'] ?? 0);
    $row['offer_id'] = (int)($row['offer_id'] ?? 0);
    $row['provider_id'] = (int)($row['provider_id'] ?? 0);
    $row['assigned_staff_id'] = (int)($row['assigned_staff_id'] ?? 0);
    $row['can_assign_staff'] = (!$isLinkedMedicalStaffSession && ($row['item_type'] ?? '') === 'medical_offer' && (int)$row['provider_id'] > 0 && ((int)$row['offer_id'] > 0 || (int)$row['service_id'] > 0)) ? 1 : 0;
    $row = apply_operational_owner_meta($row, $isLinkedMedicalStaffSession, $currentLinkedStaffId);
    $linkedStaffOperationalConversationAccess = (!$isAdminSession && $isLinkedMedicalStaffSession)
        ? mt_provider_staff_can_open_operational_conversation($conexion, $row, $providerId, $currentLinkedStaffId)
        : false;

    $row['coordination_unlocked'] = $coordinationFee['unlocked'] ? 1 : 0;
    $row['coordination_actions_locked'] = (!$coordinationFee['unlocked'] && !$isAdminSession) ? 1 : 0;
    $row['coordination_pending_message'] = $coordinationFee['message'];
    $row['coordination_inbox_locked'] = (!$coordinationFee['unlocked'] && !$isAdminSession && !$linkedStaffOperationalConversationAccess) ? 1 : 0;
    $row['coordination_inbox_pending_message'] = $row['coordination_inbox_locked'] ? $coordinationFee['message'] : '';
    $row['provider_staff_operational_conversation_access'] = $linkedStaffOperationalConversationAccess ? 1 : 0;
    $row['booking_status_label_es'] = generic_status_label_es($row['booking_status'] ?? '');
    $row['item_status_label_es'] = generic_status_label_es($row['item_status'] ?? '');
    $row['provider_status_label_es'] = generic_status_label_es($row['provider_status'] ?? '');
    $row['appointment_status_label_es'] = appointment_status_label_es($row['appointment_status'] ?? $row['medical_coordination_status'] ?? '');
    $row['medical_coordination_status_label_es'] = generic_status_label_es($row['medical_coordination_status'] ?? '');
    $row['fee_status_label_es'] = fee_status_label_es($coordinationFee['status']);
    $row['summary'] = [
        'coordination_fee_status' => $coordinationFee['status'],
        'coordination_fee_status_label_es' => fee_status_label_es($coordinationFee['status']),
        'coordination_unlocked' => $coordinationFee['unlocked'] ? 'yes' : 'no',
        'assigned_provider' => $row['assigned_provider'] ?? null,
        'assigned_doctor' => $row['assigned_doctor'] ?? null,
        'operational_owner' => $row['operational_owner_short_label'] ?? null,
        'next_appointment' => isset($row['next_appointment']['start_at']) ? $row['next_appointment']['start_at'] : null,
    ];
    $row['event_log'] = build_detail_event_log($row, $history, $row['messages'], $documents);
    foreach ($row['event_log'] as $eventIndex => $eventRow) {
        $row['event_log'][$eventIndex]['event_type_label_es'] = event_type_label_es($eventRow['event_type'] ?? '');
        $row['event_log'][$eventIndex]['actor_role_label_es'] = role_label_es($eventRow['actor_role'] ?? '');
    }
    foreach ($history as $historyIndex => $historyRow) {
        $history[$historyIndex]['item_status_label_es'] = generic_status_label_es($historyRow['item_status'] ?? '');
        $history[$historyIndex]['provider_status_label_es'] = generic_status_label_es($historyRow['provider_status'] ?? '');
        $history[$historyIndex]['appointment_status_label_es'] = appointment_status_label_es($historyRow['appointment_status'] ?? $historyRow['medical_coordination_status'] ?? '');
        $history[$historyIndex]['medical_coordination_status_label_es'] = generic_status_label_es($historyRow['medical_coordination_status'] ?? '');
    }

    json_ok(['data' => $row, 'items_history' => $history]);
}

if ($action === 'assign_staff') {
    my_booking_requests_set_debug_branch('assign_staff');
    if (!$hasItemAssignedStaffId) {
        json_err('assigned_staff_not_available', 409);
    }
    if ($isLinkedMedicalStaffSession) {
        json_err('forbidden', 403);
    }

    $itemId = intval($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
    $staffId = intval($_POST['staff_id'] ?? $_GET['staff_id'] ?? 0);
    if ($itemId <= 0 || $staffId <= 0) {
        json_err('invalid_assignment_payload', 422);
    }

    $itemContext = fetch_item_assignment_context(
        $conexion,
        $itemId,
        $scopeWhere,
        $scopeTypes,
        $scopeParams,
        $hasItemsSoftDelete,
        $hasRequestsSoftDelete,
        $offerProviderCatalogServiceExpr
    );
    if (!$itemContext) {
        my_booking_requests_debug_log('assign_staff_context_missing', ['item_id' => $itemId]);
        json_err('not_found', 404);
    }
    if (($itemContext['item_type'] ?? '') !== 'medical_offer') {
        json_err('item_not_assignable', 422);
    }

    $itemProviderId = (int)($itemContext['provider_id'] ?? 0);
    if ($itemProviderId <= 0) {
        json_err('provider_context_missing', 409);
    }
    if ((int)($itemContext['offer_id'] ?? 0) <= 0 && (int)($itemContext['service_id'] ?? 0) <= 0) {
        json_err('service_context_missing', 409);
    }

    $staffRow = provider_staff_fetch_basic_row($conexion, $staffId, $itemProviderId);
    if (!$staffRow) {
        json_err('staff_not_found', 404);
    }
    if ((int)($staffRow['is_active'] ?? 1) !== 1) {
        json_err('staff_not_active', 422);
    }

    $staffServiceSets = fetch_staff_assignment_service_sets($conexion, $itemProviderId, $staffId);
    if (!is_staff_eligible_for_item_assignment($itemContext, $staffServiceSets)) {
        my_booking_requests_debug_log('assign_staff_not_eligible', [
            'item_id' => $itemId,
            'staff_id' => $staffId,
            'provider_id' => $itemProviderId,
            'item_context' => $itemContext,
            'staff_service_sets' => $staffServiceSets,
        ]);
        json_err('staff_not_eligible_for_item', 422);
    }

    $currentAssignedStaffId = (int)($itemContext['assigned_staff_id'] ?? 0);
    if ($currentAssignedStaffId !== $staffId) {
        $setParts = ['bri.assigned_staff_id = ?'];
        $types = 'i';
        $params = [$staffId];

        if ($hasItemAssignedAt) {
            $setParts[] = 'bri.assigned_at = NOW()';
        }
        $actorUserId = current_booking_actor_user_id();
        if ($hasItemAssignedByUserId && $actorUserId > 0) {
            $setParts[] = 'bri.assigned_by_user_id = ?';
            $types .= 'i';
            $params[] = $actorUserId;
        }
        if ($hasItemUpdatedAt) {
            $setParts[] = 'bri.updated_at = NOW()';
        }

        $sql = "UPDATE booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                SET " . implode(', ', $setParts) . "
                WHERE bri.id = ?";
        $types .= 'i';
        $params[] = $itemId;

        if ($hasItemsSoftDelete) {
            $sql .= ' AND bri.is_deleted = 0';
        }
        if ($hasRequestsSoftDelete) {
            $sql .= ' AND br.is_deleted = 0';
        }
        $sql .= $scopeWhere;
        $sql .= ' LIMIT 1';
        if ($scopeTypes !== '') {
            $types .= $scopeTypes;
            $params = array_merge($params, $scopeParams);
        }

        my_booking_requests_debug_log('assign_staff_update_attempt', [
            'item_id' => $itemId,
            'staff_id' => $staffId,
            'types' => $types,
            'param_count' => count($params),
            'scope_types' => $scopeTypes,
            'scope_param_count' => count($scopeParams),
        ]);

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            my_booking_requests_debug_log('assign_staff_prepare_failed', ['sql' => $sql, 'db_error' => mysqli_error($conexion)]);
            json_err('db_prepare_error', 500);
        }
        if (!bind_stmt_params($stmt, $types, $params)) {
            mysqli_stmt_close($stmt);
            my_booking_requests_debug_log('assign_staff_bind_failed', [
                'types' => $types,
                'param_count' => count($params),
            ]);
            json_err('db_bind_error', 500);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            my_booking_requests_debug_log('assign_staff_execute_failed', ['db_error' => $err]);
            json_err('db_error: ' . $err, 500);
        }
        mysqli_stmt_close($stmt);
    }

    $updatedContext = fetch_item_assignment_context(
        $conexion,
        $itemId,
        $scopeWhere,
        $scopeTypes,
        $scopeParams,
        $hasItemsSoftDelete,
        $hasRequestsSoftDelete,
        $offerProviderCatalogServiceExpr
    );
    if (!$updatedContext) {
        json_err('assignment_saved_but_reload_failed', 500);
    }

    $updatedContext['provider_id'] = (int)($updatedContext['provider_id'] ?? 0);
    $updatedContext['offer_id'] = (int)($updatedContext['offer_id'] ?? 0);
    $updatedContext['service_id'] = (int)($updatedContext['service_id'] ?? 0);
    $updatedContext['assigned_staff_id'] = (int)($updatedContext['assigned_staff_id'] ?? 0);
    $updatedContext['provider_catalog_service_id'] = isset($updatedContext['provider_catalog_service_id']) && $updatedContext['provider_catalog_service_id'] !== null
        ? (int)$updatedContext['provider_catalog_service_id']
        : null;
    $updatedContext = provider_staff_apply_assignment_payload($conexion, $updatedContext);
    $updatedContext = apply_operational_owner_meta($updatedContext, $isLinkedMedicalStaffSession, $currentLinkedStaffId);

    json_ok([
        'data' => [
            'item_id' => (int)($updatedContext['item_id'] ?? $itemId),
            'provider_id' => (int)($updatedContext['provider_id'] ?? $itemProviderId),
            'offer_id' => (int)($updatedContext['offer_id'] ?? 0),
            'service_id' => (int)($updatedContext['service_id'] ?? 0),
            'provider_catalog_service_id' => $updatedContext['provider_catalog_service_id'],
            'assigned_staff_id' => (int)($updatedContext['assigned_staff_id'] ?? 0),
            'assigned_doctor' => $updatedContext['assigned_doctor'] ?? null,
            'clinic' => $updatedContext['clinic'] ?? null,
            'operational_owner_label' => $updatedContext['operational_owner_label'] ?? null,
            'operational_owner_short_label' => $updatedContext['operational_owner_short_label'] ?? null,
            'operational_owner_role_label_es' => $updatedContext['operational_owner_role_label_es'] ?? null,
            'operational_owner_note_es' => $updatedContext['operational_owner_note_es'] ?? null,
            'supervisor_override_required' => (int)($updatedContext['supervisor_override_required'] ?? 0),
            'supervisor_override_message' => $updatedContext['supervisor_override_message'] ?? '',
            'linked_staff_auto_claim_available' => (int)($updatedContext['linked_staff_auto_claim_available'] ?? 0),
            'linked_staff_auto_claim_message' => $updatedContext['linked_staff_auto_claim_message'] ?? '',
            'assigned_staff' => $updatedContext['assigned_staff'] ?? null,
        ],
    ]);
}

if ($action === 'list_messages') {
    my_booking_requests_set_debug_branch('list_messages');
    $threadTypeRaw = trim((string)($_POST['thread_type'] ?? $_GET['thread_type'] ?? ''));
    $legacyMode = ($threadTypeRaw === '');
    $threadType = strtoupper($threadTypeRaw);
    $itemId = intval($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
    $bookingRequestId = intval($_POST['booking_request_id'] ?? $_GET['booking_request_id'] ?? $_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);

    if ($threadType === '') {
        $threadType = 'ITEM';
    }
    if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
        json_err('invalid_thread_type', 422);
    }
    if ($threadType === 'CARE' && !$isAdminSession) {
        json_err('forbidden', 403);
    }

    if ($threadType === 'ITEM') {
        if ($itemId <= 0) {
            json_err('invalid_id');
        }
        $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
        if (!$itemRow) {
            json_err('not_found', 404);
        }
        $bookingRequestId = (int)$itemRow['booking_request_id'];
    } else {
        if ($bookingRequestId <= 0) {
            json_err('invalid_booking_id', 422);
        }
        $bookingSql = "SELECT id FROM booking_requests WHERE id = ?";
        if ($hasRequestsSoftDelete) {
            $bookingSql .= " AND is_deleted = 0";
        }
        $bookingSql .= " LIMIT 1";
        $bookingStmt = mysqli_prepare($conexion, $bookingSql);
        if (!$bookingStmt) {
            json_err('db_prepare_error', 500);
        }
        mysqli_stmt_bind_param($bookingStmt, 'i', $bookingRequestId);
        if (!mysqli_stmt_execute($bookingStmt)) {
            $err = mysqli_stmt_error($bookingStmt);
            mysqli_stmt_close($bookingStmt);
            json_err('db_error: ' . $err, 500);
        }
        $bookingRes = mysqli_stmt_get_result($bookingStmt);
        $bookingRow = $bookingRes ? mysqli_fetch_assoc($bookingRes) : null;
        mysqli_stmt_close($bookingStmt);
        if (!$bookingRow) {
            json_err('not_found', 404);
        }
    }

    $coordinationFee = build_coordination_fee_meta($conexion, $bookingRequestId, [], $isAdminSession);
    if (function_exists('commission_gate_resolve_provider_ids') && $itemId > 0) {
        $provContext = commission_gate_resolve_provider_ids($conexion, $itemId);
        apply_provider_commission_gate_override($conexion, $coordinationFee, $provContext);
    } else {
        apply_provider_commission_gate_override($conexion, $coordinationFee, []);
    }
    $feeLocked = !$coordinationFee['unlocked'];

    $parsedMessages = parse_additional_notes_messages(fetch_booking_additional_notes($conexion, $bookingRequestId, $hasRequestsSoftDelete));
    $messages = [];
    foreach ($parsedMessages as $m) {
        if ($legacyMode) {
            $messages[] = $m;
            continue;
        }
        $mThreadType = strtoupper((string)($m['thread_type'] ?? 'CARE'));
        $mThreadItemId = (int)($m['thread_item_id'] ?? 0);
        if ($threadType === 'CARE') {
            if ($mThreadType !== 'ITEM') {
                $messages[] = $m;
            }
        } else {
            if ($mThreadType === 'ITEM' && $mThreadItemId === $itemId) {
                $messages[] = $m;
            }
        }
    }

    if ($threadType === 'ITEM') {
        $timelineSql = "SELECT
                            bri.id AS row_item_id,
                            CASE
                                WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                                ELSE bri.item_status
                            END AS item_status,
                            {$providerNotesExpr} AS provider_notes,
                            {$rejectReasonExpr} AS provider_reject_reason,
                            {$responseAtExpr} AS provider_response_at,
                            " . ($hasItemUpdatedAt ? "bri.updated_at" : "NULL") . " AS item_updated_at,
                            " . ($hasItemCreatedAt ? "bri.created_at" : "NULL") . " AS item_created_at
                        FROM booking_request_items bri
                        INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                        WHERE " . ($legacyMode ? "bri.booking_request_id = ?" : "bri.id = ?");

        if ($hasItemsSoftDelete) {
            $timelineSql .= ' AND bri.is_deleted = 0';
        }
        if ($hasRequestsSoftDelete) {
            $timelineSql .= ' AND br.is_deleted = 0';
        }
        $timelineSql .= $scopeWhere;
        if ($legacyMode) {
            $timelineSql .= ' ORDER BY bri.id ASC';
        } else {
            $timelineSql .= ' LIMIT 1';
        }

        $stmtTimeline = mysqli_prepare($conexion, $timelineSql);
        if ($stmtTimeline) {
            $timelineTypes = 'i' . $scopeTypes;
            $timelineParams = array_merge([$legacyMode ? $bookingRequestId : $itemId], $scopeParams);
            bind_stmt_params($stmtTimeline, $timelineTypes, $timelineParams);
            if (mysqli_stmt_execute($stmtTimeline)) {
                $timelineRes = mysqli_stmt_get_result($stmtTimeline);
                while ($timelineRes && ($timelineRow = mysqli_fetch_assoc($timelineRes))) {
                    $rowItemId = (int)($timelineRow['row_item_id'] ?? $itemId);
                    $eventTime = trim((string)($timelineRow['provider_response_at'] ?? ''));
                    if ($eventTime === '') {
                        $eventTime = trim((string)($timelineRow['item_updated_at'] ?? ''));
                    }
                    if ($eventTime === '') {
                        $eventTime = trim((string)($timelineRow['item_created_at'] ?? ''));
                    }

                    $providerNotes = trim((string)($timelineRow['provider_notes'] ?? ''));
                    if ($providerNotes !== '') {
                        $messages[] = [
                            'sender' => 'provider',
                            'type' => 'provider_note',
                            'time' => $eventTime,
                            'actor' => '',
                            'body' => $providerNotes,
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }

                    $rejectReason = trim((string)($timelineRow['provider_reject_reason'] ?? ''));
                    if ($rejectReason !== '') {
                        $messages[] = [
                            'sender' => 'provider',
                            'type' => 'provider_reject_reason',
                            'time' => $eventTime,
                            'actor' => '',
                            'body' => 'Motivo del rechazo: ' . $rejectReason,
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }

                    $status = normalize_legacy_item_status($timelineRow['item_status'] ?? '');
                    if ($status !== '') {
                        $messages[] = [
                            'sender' => 'system',
                            'type' => 'status_update',
                            'time' => $eventTime,
                            'actor' => '',
                            'body' => 'Estado del caso actualizado a: ' . generic_status_label_es($status),
                            'thread_type' => 'ITEM',
                            'thread_item_id' => $rowItemId,
                        ];
                    }
                }
            }
            mysqli_stmt_close($stmtTimeline);
        }
    }

    if (inbox_table_exists($conexion, 'inbox_messages')) {
        $threadId = inbox_thread_id($threadType, $bookingRequestId, $itemId);
        $stmtInbox = mysqli_prepare($conexion, "SELECT id, sender_role, sender_user_id, body, created_at FROM inbox_messages WHERE thread_id = ? ORDER BY id ASC");
        if ($stmtInbox) {
            mysqli_stmt_bind_param($stmtInbox, 's', $threadId);
            if (mysqli_stmt_execute($stmtInbox)) {
                $resInbox = mysqli_stmt_get_result($stmtInbox);
                while ($resInbox && ($rowInbox = mysqli_fetch_assoc($resInbox))) {
                    $body = (string)($rowInbox['body'] ?? '');
                    $type = 'inbox_message';
                    if (stripos($body, '[ACTION]') === 0) {
                        $type = 'quick_action';
                    } elseif (stripos($body, '[REPLY]') === 0) {
                        $type = 'quick_reply';
                    }
                    $messages[] = [
                        'sender' => inbox_sender_to_ui($rowInbox['sender_role'] ?? ''),
                        'type' => $type,
                        'time' => (string)($rowInbox['created_at'] ?? ''),
                        'actor' => '',
                        'body' => $body,
                        'thread_type' => $threadType,
                        'thread_item_id' => $threadType === 'ITEM' ? $itemId : 0,
                    ];
                }
            }
            mysqli_stmt_close($stmtInbox);
        }
    }

    sort_messages_by_time($messages);
    foreach ($messages as $idx => $message) {
        $messages[$idx]['display_role'] = detect_message_role($message);
        $messages[$idx]['display_role_label_es'] = role_label_es($messages[$idx]['display_role']);
    }
    json_ok([
        'booking_request_id' => $bookingRequestId,
        'thread_type' => $threadType,
        'item_id' => $threadType === 'ITEM' ? $itemId : 0,
        'fee_locked' => $feeLocked ? 1 : 0,
        'coordination_fee' => $coordinationFee,
        'messages' => $messages
    ]);
}

if ($action === 'send_message') {
    $messageText = trim((string)($_POST['message'] ?? ''));
    if ($messageText === '') {
        json_err('message_required', 422);
    }
    if (!$hasAdditionalNotes) {
        json_err('additional_notes_not_available', 409);
    }

    $threadType = strtoupper(trim((string)($_POST['thread_type'] ?? '')));
    $itemId = intval($_POST['item_id'] ?? 0);
    $bookingRequestId = intval($_POST['booking_request_id'] ?? $_POST['booking_id'] ?? 0);
    if ($threadType === '') {
        $threadType = 'ITEM';
    }
    if (!in_array($threadType, ['CARE', 'ITEM'], true)) {
        json_err('invalid_thread_type', 422);
    }
    if ($threadType === 'CARE' && !$isAdminSession) {
        json_err('forbidden', 403);
    }

    if ($threadType === 'ITEM') {
        if ($itemId <= 0) {
            json_err('invalid_id', 422);
        }
        $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
        if (!$itemRow) {
            json_err('not_found', 404);
        }
        if ($isLinkedMedicalStaffSession) {
            $assignmentResult = maybe_assign_item_to_current_linked_staff(
                $conexion,
                $itemId,
                $providerId,
                $currentLinkedStaffId,
                (int)($itemRow['assigned_staff_id'] ?? 0),
                $hasItemAssignedStaffId,
                $hasItemAssignedAt,
                $hasItemAssignedByUserId,
                $hasItemsSoftDelete,
                $hasRequestsSoftDelete,
                (string)($itemRow['current_status'] ?? $itemRow['item_status'] ?? '')
            );
            if (empty($assignmentResult['ok'])) {
                json_err((string)($assignmentResult['message'] ?? 'item_assignment_failed'), (int)($assignmentResult['status'] ?? 409));
            }
        }
        $bookingRequestId = (int)$itemRow['booking_request_id'];
    } else {
        if ($bookingRequestId <= 0) {
            json_err('invalid_booking_id', 422);
        }
        $bookingSql = "SELECT id FROM booking_requests WHERE id = ?";
        if ($hasRequestsSoftDelete) {
            $bookingSql .= " AND is_deleted = 0";
        }
        $bookingSql .= " LIMIT 1";
        $stmtBooking = mysqli_prepare($conexion, $bookingSql);
        if (!$stmtBooking) {
            json_err('db_prepare_error', 500);
        }
        mysqli_stmt_bind_param($stmtBooking, 'i', $bookingRequestId);
        if (!mysqli_stmt_execute($stmtBooking)) {
            $err = mysqli_stmt_error($stmtBooking);
            mysqli_stmt_close($stmtBooking);
            json_err('db_error: ' . $err, 500);
        }
        $bookingRes = mysqli_stmt_get_result($stmtBooking);
        $bookingRow = $bookingRes ? mysqli_fetch_assoc($bookingRes) : null;
        mysqli_stmt_close($stmtBooking);
        if (!$bookingRow) {
            json_err('not_found', 404);
        }
    }

    $coordinationFee = build_coordination_fee_meta($conexion, $bookingRequestId, [], $isAdminSession);
    if (!empty($itemRow) && is_array($itemRow)) {
        apply_provider_commission_gate_override($conexion, $coordinationFee, $itemRow);
    } else {
        apply_provider_commission_gate_override($conexion, $coordinationFee, []);
    }
    $feeLocked = !$coordinationFee['unlocked'];
    if ($feeLocked && !$isAdminSession) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'coordination_fee_required', 'code' => 'FEE_REQUIRED']);
        exit;
    }

    $stamp = date('Y-m-d H:i:s');
    $normalizedMessage = normalize_message_text($messageText);
    $actor = 'provider';
    if ($providerId > 0) {
        $actor = 'provider:' . $providerId;
    }
    if ($serviceProviderId > 0) {
        $actor .= '|service_provider:' . $serviceProviderId;
    } elseif ($isAdminSession) {
        $adminId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
        if ($adminId > 0) {
            $actor = 'admin:' . $adminId;
        } else {
            $actor = 'admin';
        }
    }
    $threadActor = build_thread_actor($threadType, $itemId, $actor);
    $entry = '[PROVIDER_MESSAGE][' . $stamp . '][' . $threadActor . '] ' . $normalizedMessage;

    $currentNotes = fetch_booking_additional_notes($conexion, $bookingRequestId, $hasRequestsSoftDelete);
    $newNotes = trim($currentNotes) !== '' ? (rtrim($currentNotes) . "\n" . $entry) : $entry;

    $updateSql = 'UPDATE booking_requests SET additional_notes = ?';
    if ($hasBookingUpdatedAt) {
        $updateSql .= ', updated_at = NOW()';
    }
    $updateSql .= ' WHERE id = ?';
    if ($hasRequestsSoftDelete) {
        $updateSql .= ' AND is_deleted = 0';
    }
    $updateSql .= ' LIMIT 1';

    $stmtUpdate = mysqli_prepare($conexion, $updateSql);
    if (!$stmtUpdate) {
        json_err('db_prepare_error', 500);
    }
    mysqli_stmt_bind_param($stmtUpdate, 'si', $newNotes, $bookingRequestId);
    if (!mysqli_stmt_execute($stmtUpdate)) {
        $err = mysqli_stmt_error($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
        json_err('db_error: ' . $err, 500);
    }
    mysqli_stmt_close($stmtUpdate);

    json_ok([
        'booking_request_id' => $bookingRequestId,
        'thread_type' => $threadType,
        'item_id' => $threadType === 'ITEM' ? $itemId : 0,
        'message' => [
            'sender' => 'provider',
            'type' => 'provider_message',
            'time' => $stamp,
            'actor' => $actor,
            'body' => $normalizedMessage,
            'thread_type' => $threadType,
            'thread_item_id' => $threadType === 'ITEM' ? $itemId : 0,
        ],
    ]);
}

if ($action === 'propose_dates') {
    if ($isAdminSession) {
        json_err('forbidden', 403);
    }
    if (!$hasProviderProposedDateFrom || !$hasProviderProposedDateTo) {
        json_err('proposed_dates_not_available', 409);
    }

    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id', 422);
    }

    $dateFrom = trim((string)($_POST['date_from'] ?? ''));
    $dateTo = trim((string)($_POST['date_to'] ?? ''));
    if ($dateFrom === '' || $dateTo === '') {
        json_err('dates_required', 422);
    }
    if (!is_valid_date_ymd($dateFrom) || !is_valid_date_ymd($dateTo)) {
        json_err('invalid_proposed_dates', 422);
    }
    if (strcmp($dateFrom, $dateTo) > 0) {
        json_err('invalid_date_range', 422);
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }
    if ($isLinkedMedicalStaffSession) {
        $assignmentResult = maybe_assign_item_to_current_linked_staff(
            $conexion,
            $itemId,
            $providerId,
            $currentLinkedStaffId,
            (int)($itemRow['assigned_staff_id'] ?? 0),
            $hasItemAssignedStaffId,
            $hasItemAssignedAt,
            $hasItemAssignedByUserId,
            $hasItemsSoftDelete,
            $hasRequestsSoftDelete,
            (string)($itemRow['current_status'] ?? $itemRow['item_status'] ?? '')
        );
        if (empty($assignmentResult['ok'])) {
            json_err((string)($assignmentResult['message'] ?? 'item_assignment_failed'), (int)($assignmentResult['status'] ?? 409));
        }
    }

    $currentStatus = normalize_legacy_item_status($itemRow['current_status'] ?? '');
    $allowedCurrentStatuses = [];
    if ($targetStatus === 'provider_confirmed' || $targetStatus === 'provider_rejected') {
        $allowedCurrentStatuses = ['pending_provider'];
    } elseif ($targetStatus === 'provider_proposed_change') {
        $allowedCurrentStatuses = ['pending_provider', 'provider_proposed_change', 'awaiting_client'];
    } elseif ($targetStatus === 'treatment_completed') {
        $allowedCurrentStatuses = ['provider_confirmed', 'client_accepted', 'treatment_completed'];
    }
    if (empty($allowedCurrentStatuses)) {
        json_err('transition_not_allowed', 403);
    }
    if (!in_array($currentStatus, $allowedCurrentStatuses, true)) {
        json_err('transition_not_allowed_from_' . $currentStatus, 409);
    }

    $providerResponseBy = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
    if ($providerResponseBy <= 0) {
        $providerResponseBy = null;
    }

    $setParts = [
        'bri.item_status = ?',
        'bri.provider_proposed_date_from = ?',
        'bri.provider_proposed_date_to = ?'
    ];
    $types = 'sss';
    $params = ['provider_proposed_change', $dateFrom, $dateTo];

    if ($hasItemUpdatedAt) {
        $setParts[] = 'bri.updated_at = NOW()';
    }
    if ($hasProviderResponseAt) {
        $setParts[] = 'bri.provider_response_at = NOW()';
    }
    if ($hasProviderResponseBy) {
        $setParts[] = 'bri.provider_response_by = ?';
        $types .= 'i';
        $params[] = $providerResponseBy;
    }

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET " . implode(', ', $setParts) . "
            WHERE bri.id = ?";
    $types .= 'i';
    $params[] = $itemId;
    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' LIMIT 1';

    $finalTypes = $types . $scopeTypes;
    $finalParams = array_merge($params, $scopeParams);

    if ($targetStatus === 'provider_proposed_change') {
        mysqli_begin_transaction($conexion);
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        if ($targetStatus === 'provider_proposed_change') {
            mysqli_rollback($conexion);
        }
        json_err('db_prepare_error', 500);
    }
    bind_stmt_params($stmt, $finalTypes, $finalParams);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        if ($targetStatus === 'provider_proposed_change') {
            mysqli_rollback($conexion);
        }
        json_err('db_error: ' . $err, 500);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected <= 0) {
        if ($targetStatus === 'provider_proposed_change') {
            mysqli_rollback($conexion);
        }
        json_err('not_found_or_no_change', 404);
    }

    $bookingRequestId = (int)($itemRow['booking_request_id'] ?? 0);
    if ($bookingRequestId > 0) {
        sync_booking_fee_gate_state($conexion, $bookingRequestId, $hasRequestsSoftDelete);
        rollup_booking_status($conexion, $bookingRequestId, $hasRequestsSoftDelete);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        json_err('inbox_messages_not_available', 409);
    }

    $message = '[REPLY] PROPOSED_DATES ' . $dateFrom . ' to ' . $dateTo;
    $threadId = inbox_thread_id('ITEM', $bookingRequestId, $itemId);
    $senderRole = 'PROVIDER';
    $senderUserId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, 'ITEM', ?, ?, ?, ?, ?)"
    );
    if (!$stmtMsg) {
        json_err('db_prepare_error', 500);
    }
    mysqli_stmt_bind_param($stmtMsg, 'siisis', $threadId, $bookingRequestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        json_err('db_error: ' . $err, 500);
    }
    $newMessageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);
    my_booking_emit_realtime_inbox_message(['thread_id' => $threadId, 'message_id' => $newMessageId], 'propose_dates_item');

    json_ok([
        'booking_request_id' => $bookingRequestId,
        'thread_type' => 'ITEM',
        'item_id' => $itemId,
        'message' => [
            'sender' => 'provider',
            'type' => 'quick_reply',
            'time' => date('Y-m-d H:i:s'),
            'actor' => '',
            'body' => $message,
            'thread_type' => 'ITEM',
            'thread_item_id' => $itemId,
        ],
    ]);
}

if ($action === 'send_final_decision') {
    if ($isAdminSession) {
        json_err('forbidden', 403);
    }

    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id', 422);
    }

    $decisionKey = strtoupper(trim((string)($_POST['decision_key'] ?? '')));
    $reasonKey = strtoupper(trim((string)($_POST['reason_key'] ?? '')));
    $decisionMap = [
        'FINAL_APPROVED' => 'provider_confirmed',
        'FINAL_NOT_ELIGIBLE' => 'provider_rejected'
    ];
    if ($decisionKey === '' || !isset($decisionMap[$decisionKey])) {
        json_err('invalid_decision_key', 422);
    }

    $reasonMap = [
        'NOT_A_FIT' => 'Not a fit',
        'INSUFFICIENT_INFO' => 'Insufficient info',
        'OUT_OF_SCOPE' => 'Out of scope',
        'NOT_AVAILABLE' => 'Not available'
    ];
    $reasonLabel = '';
    if ($reasonKey !== '' && isset($reasonMap[$reasonKey])) {
        $reasonLabel = $reasonMap[$reasonKey];
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }
    if ($isLinkedMedicalStaffSession) {
        $assignmentResult = maybe_assign_item_to_current_linked_staff(
            $conexion,
            $itemId,
            $providerId,
            $currentLinkedStaffId,
            (int)($itemRow['assigned_staff_id'] ?? 0),
            $hasItemAssignedStaffId,
            $hasItemAssignedAt,
            $hasItemAssignedByUserId,
            $hasItemsSoftDelete,
            $hasRequestsSoftDelete,
            (string)($itemRow['current_status'] ?? $itemRow['item_status'] ?? '')
        );
        if (empty($assignmentResult['ok'])) {
            json_err((string)($assignmentResult['message'] ?? 'item_assignment_failed'), (int)($assignmentResult['status'] ?? 409));
        }
    }

    $currentStatus = normalize_legacy_item_status($itemRow['current_status'] ?? '');
    if ($currentStatus === 'cancelled' || $currentStatus === 'client_accepted' || $currentStatus === 'client_rejected') {
        json_err('transition_not_allowed_from_' . $currentStatus, 409);
    }

    if ($decisionKey === 'FINAL_APPROVED' && $currentStatus === 'provider_confirmed') {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'code' => 'ALREADY_CONFIRMED',
            'message' => 'already_confirmed'
        ]);
        exit;
    }

    $targetStatus = $decisionMap[$decisionKey];
    $shouldUpdate = ($currentStatus !== $targetStatus);

    if ($shouldUpdate) {
        $providerResponseBy = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
        if ($providerResponseBy <= 0) {
            $providerResponseBy = null;
        }

        $setParts = ['bri.item_status = ?'];
        $types = 's';
        $params = [$targetStatus];

        if ($hasItemUpdatedAt) {
            $setParts[] = 'bri.updated_at = NOW()';
        }
        if ($hasProviderResponseAt) {
            $setParts[] = 'bri.provider_response_at = NOW()';
        }
        if ($hasProviderResponseBy) {
            $setParts[] = 'bri.provider_response_by = ?';
            $types .= 'i';
            $params[] = $providerResponseBy;
        }

        $sql = "UPDATE booking_request_items bri
                INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                SET " . implode(', ', $setParts) . "
                WHERE bri.id = ?";
        $types .= 'i';
        $params[] = $itemId;
        if ($hasItemsSoftDelete) {
            $sql .= ' AND bri.is_deleted = 0';
        }
        if ($hasRequestsSoftDelete) {
            $sql .= ' AND br.is_deleted = 0';
        }
        $sql .= $scopeWhere;
        $sql .= ' LIMIT 1';

        $finalTypes = $types . $scopeTypes;
        $finalParams = array_merge($params, $scopeParams);

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            json_err('db_prepare_error', 500);
        }
        bind_stmt_params($stmt, $finalTypes, $finalParams);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            json_err('db_error: ' . $err, 500);
        }
        mysqli_stmt_close($stmt);
    }

    $bookingRequestId = (int)($itemRow['booking_request_id'] ?? 0);
    if ($bookingRequestId > 0) {
        sync_booking_fee_gate_state($conexion, $bookingRequestId, $hasRequestsSoftDelete);
        rollup_booking_status($conexion, $bookingRequestId, $hasRequestsSoftDelete);
    }

    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        json_err('inbox_messages_not_available', 409);
    }

    $message = '[REPLY] ' . $decisionKey;
    if ($decisionKey === 'FINAL_NOT_ELIGIBLE' && $reasonLabel !== '') {
        $message .= ': ' . $reasonLabel;
    }

    $threadId = inbox_thread_id('ITEM', $bookingRequestId, $itemId);
    $senderRole = 'PROVIDER';
    $senderUserId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;

    $stmtMsg = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, 'ITEM', ?, ?, ?, ?, ?)"
    );
    if (!$stmtMsg) {
        json_err('db_prepare_error', 500);
    }
    mysqli_stmt_bind_param($stmtMsg, 'siisis', $threadId, $bookingRequestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmtMsg)) {
        $err = mysqli_stmt_error($stmtMsg);
        mysqli_stmt_close($stmtMsg);
        json_err('db_error: ' . $err, 500);
    }
    $newMessageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtMsg);
    my_booking_emit_realtime_inbox_message(['thread_id' => $threadId, 'message_id' => $newMessageId], 'send_final_decision_item');

    json_ok([
        'booking_request_id' => $bookingRequestId,
        'thread_type' => 'ITEM',
        'item_id' => $itemId,
        'item_status' => $targetStatus,
        'message' => [
            'sender' => 'provider',
            'type' => 'quick_reply',
            'time' => date('Y-m-d H:i:s'),
            'actor' => '',
            'body' => $message,
            'thread_type' => 'ITEM',
            'thread_item_id' => $itemId,
        ],
    ]);
}

if ($action === 'send_quick_reply') {
    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id', 422);
    }

    $replyKey = strtoupper(trim((string)($_POST['reply_key'] ?? '')));
    $quickReplies = [
        'DATES_OK' => 'Dates available',
        'DATES_AVAILABLE' => 'Dates available',
        'DATES_NOT_AVAILABLE' => 'Dates not available',
        'REQUEST_MEDICAL_HISTORY' => 'REQUEST HISTORY',
        'REQUEST_LABS' => 'REQUEST LABS',
        'REQUEST_IMAGING' => 'REQUEST IMAGING',
        'REQUEST_PHOTOS' => 'REQUEST PHOTOS'
    ];
    if ($replyKey === '' || !isset($quickReplies[$replyKey])) {
        json_err('invalid_reply_key', 422);
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }
    if ($isLinkedMedicalStaffSession) {
        $assignmentResult = maybe_assign_item_to_current_linked_staff(
            $conexion,
            $itemId,
            $providerId,
            $currentLinkedStaffId,
            (int)($itemRow['assigned_staff_id'] ?? 0),
            $hasItemAssignedStaffId,
            $hasItemAssignedAt,
            $hasItemAssignedByUserId,
            $hasItemsSoftDelete,
            $hasRequestsSoftDelete,
            (string)($itemRow['current_status'] ?? $itemRow['item_status'] ?? '')
        );
        if (empty($assignmentResult['ok'])) {
            json_err((string)($assignmentResult['message'] ?? 'item_assignment_failed'), (int)($assignmentResult['status'] ?? 409));
        }
    }
    $bookingRequestId = (int)$itemRow['booking_request_id'];
    if ($bookingRequestId <= 0) {
        json_err('invalid_booking_id', 422);
    }
    if (!inbox_table_exists($conexion, 'inbox_messages')) {
        json_err('inbox_messages_not_available', 409);
    }

    $message = '[REPLY] ' . $quickReplies[$replyKey];
    $threadId = inbox_thread_id('ITEM', $bookingRequestId, $itemId);
    $senderRole = $isAdminSession ? 'ADMIN' : 'PROVIDER';
    $senderUserId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO inbox_messages
            (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
         VALUES (?, 'ITEM', ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }
    mysqli_stmt_bind_param($stmt, 'siisis', $threadId, $bookingRequestId, $itemId, $senderRole, $senderUserId, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }
    $newMessageId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    my_booking_emit_realtime_inbox_message(['thread_id' => $threadId, 'message_id' => $newMessageId], 'send_quick_reply_item');

    json_ok([
        'booking_request_id' => $bookingRequestId,
        'thread_type' => 'ITEM',
        'item_id' => $itemId,
        'message' => [
            'sender' => $isAdminSession ? 'admin' : 'provider',
            'type' => 'quick_reply',
            'time' => date('Y-m-d H:i:s'),
            'actor' => '',
            'body' => $message,
            'thread_type' => 'ITEM',
            'thread_item_id' => $itemId,
        ],
    ]);
}

if ($action === 'cancel_meeting') {
    my_booking_requests_set_debug_branch('provider_action_cancel_meeting');

    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id');
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }

    $actorRole = $isAdminSession ? 'ADMIN' : 'PROVIDER';
    $actorUserId = current_admin_user_id();
    $cancelResult = google_calendar_cancel_item_meeting($conexion, $itemId, [
        'role' => $actorRole,
        'user_id' => $actorUserId,
    ]);
    if (empty($cancelResult['ok'])) {
        json_err((string)($cancelResult['error'] ?? 'meeting_cancel_failed'), 409);
    }

    json_ok([
        'item_id' => $itemId,
        'request_id' => (int)($cancelResult['request_id'] ?? 0),
        'item_status' => normalize_legacy_item_status((string)($cancelResult['item_status'] ?? '')),
        'meeting' => [
            'status' => 'cancelled',
            'calendar_event_id' => (int)($cancelResult['calendar_event_id'] ?? 0),
            'event_id' => (string)($cancelResult['google_event_id'] ?? ''),
            'start_at' => (string)($cancelResult['start_at'] ?? ''),
            'end_at' => (string)($cancelResult['end_at'] ?? ''),
            'integration_mode' => (string)($cancelResult['integration_mode'] ?? ''),
            'cancelled_by_role' => (string)($cancelResult['cancelled_by_role'] ?? $actorRole),
        ],
        'message' => [
            'id' => (int)($cancelResult['message_id'] ?? 0),
            'sender' => strtolower($actorRole),
            'body' => (string)($cancelResult['message_body'] ?? ''),
            'time' => date('Y-m-d H:i:s'),
        ],
    ]);
}

if (in_array($action, ['provider_confirm', 'provider_reject', 'provider_propose_change', 'update_item_status'], true)) {
    my_booking_requests_set_debug_branch('provider_action_' . $action);
    my_booking_requests_trace('provider_action_enter', [
        'action' => $action,
        'post_item_id' => isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0,
        'referer' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
        'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
    ]);
    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_err('invalid_id');
    }

    $targetStatus = '';
    if ($action === 'provider_confirm') {
        $targetStatus = 'provider_confirmed';
    } elseif ($action === 'provider_reject') {
        $targetStatus = 'provider_rejected';
    } elseif ($action === 'provider_propose_change') {
        $targetStatus = 'provider_proposed_change';
    } else {
        $targetStatus = trim((string)($_POST['status'] ?? ''));
    }

    if (!in_array($targetStatus, $canonicalItemStatuses, true) || !in_array($targetStatus, $providerAllowedTargets, true)) {
        json_err('transition_not_allowed', 403);
    }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) {
        json_err('not_found', 404);
    }
    if ($isLinkedMedicalStaffSession) {
        $assignmentResult = maybe_assign_item_to_current_linked_staff(
            $conexion,
            $itemId,
            $providerId,
            $currentLinkedStaffId,
            (int)($itemRow['assigned_staff_id'] ?? 0),
            $hasItemAssignedStaffId,
            $hasItemAssignedAt,
            $hasItemAssignedByUserId,
            $hasItemsSoftDelete,
            $hasRequestsSoftDelete,
            (string)($itemRow['current_status'] ?? $itemRow['item_status'] ?? '')
        );
        if (empty($assignmentResult['ok'])) {
            json_err((string)($assignmentResult['message'] ?? 'item_assignment_failed'), (int)($assignmentResult['status'] ?? 409));
        }
        if (!empty($assignmentResult['assigned'])) {
            $itemRow['assigned_staff_id'] = (int)$assignmentResult['assigned_staff_id'];
        }
    }

    $currentStatus = normalize_legacy_item_status($itemRow['current_status'] ?? '');
    if (!in_array($currentStatus, $canonicalItemStatuses, true)) {
        json_err('invalid_current_status', 409);
    }
    // Reversas controladas: requieren motivo
    $reversalTargets = ['pending_provider', 'provider_confirmed', 'virtual_assessment_pending'];
    $isReversal = in_array($targetStatus, $reversalTargets, true);

    $allowedCurrentStatuses = [];
    // ── Avances ──────────────────────────────────────────────────────────────
    if ($targetStatus === 'provider_confirmed') {
        $allowedCurrentStatuses = ['pending_provider', 'virtual_assessment_pending']; // reversa desde assessment
    } elseif ($targetStatus === 'provider_rejected') {
        $allowedCurrentStatuses = ['pending_provider'];
    } elseif ($targetStatus === 'provider_proposed_change') {
        $allowedCurrentStatuses = ['pending_provider', 'provider_proposed_change', 'awaiting_client'];
    } elseif ($targetStatus === 'virtual_assessment_pending') {
        // Avance: desde provider_confirmed/client_accepted/awaiting_client
        // Reversa: desde virtual_assessment_done (volver a pendiente de valoración)
        $allowedCurrentStatuses = [
            'provider_confirmed', 'client_accepted', 'awaiting_client',
            'virtual_assessment_done', // reversa
        ];
    } elseif ($targetStatus === 'virtual_assessment_done') {
        $allowedCurrentStatuses = ['virtual_assessment_pending'];
    } elseif ($targetStatus === 'treatment_plan_agreed') {
        $allowedCurrentStatuses = ['virtual_assessment_done', 'provider_confirmed', 'client_accepted'];
    } elseif ($targetStatus === 'procedure_scheduled') {
        $allowedCurrentStatuses = ['treatment_plan_agreed', 'provider_confirmed', 'client_accepted'];
    } elseif ($targetStatus === 'treatment_completed') {
        $allowedCurrentStatuses = ['procedure_scheduled', 'provider_confirmed', 'client_accepted', 'treatment_completed'];
    } elseif ($targetStatus === 'post_treatment_follow_up') {
        $allowedCurrentStatuses = ['treatment_completed', 'post_treatment_follow_up'];
    } elseif ($targetStatus === 'case_closed') {
        $allowedCurrentStatuses = ['treatment_completed', 'post_treatment_follow_up'];
    }
    // ── Reversas desde estados avanzados ──────────────────────────────────────
    if ($isReversal && $targetStatus === 'pending_provider') {
        // Solo admin puede reabrir hasta triage
        if (!$isAdminSession) {
            json_err('reversal_requires_admin', 403);
        }
        $allowedCurrentStatuses = [
            'provider_confirmed', 'provider_rejected', 'awaiting_client',
            'virtual_assessment_pending', 'virtual_assessment_done',
            'treatment_plan_agreed', 'procedure_scheduled',
        ];
    }

    if (empty($allowedCurrentStatuses)) {
        json_err('transition_not_allowed', 403);
    }
    if (!in_array($currentStatus, $allowedCurrentStatuses, true)) {
        json_err('transition_not_allowed_from_' . $currentStatus, 409);
    }

    // Reversas deben incluir motivo.
    // virtual_assessment_pending es reversa solo si viene de virtual_assessment_done;
    // desde provider_confirmed/client_accepted/awaiting_client es avance normal.
    $advanceSourcesForVirtualPending = ['provider_confirmed', 'client_accepted', 'awaiting_client'];
    $isActualReversal = $isReversal && !(
        $targetStatus === 'virtual_assessment_pending'
        && in_array($currentStatus, $advanceSourcesForVirtualPending, true)
    );
    if ($isActualReversal) {
        $reversalReasonRaw = trim((string)($_POST['reversal_reason'] ?? ''));
        if ($reversalReasonRaw === '') {
            json_err('reversal_reason_required', 422);
        }
    }

    $providerResponseBy = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
    if ($providerResponseBy <= 0) {
        $providerResponseBy = null;
    }

    $meetingResult = null;
    $postCommitRealtimeEvents = [];

    $setParts = ['bri.item_status = ?'];
    $types = 's';
    $params = [$targetStatus];

    if ($hasItemUpdatedAt) {
        $setParts[] = 'bri.updated_at = NOW()';
    }
    if ($hasProviderResponseAt) {
        $setParts[] = 'bri.provider_response_at = NOW()';
    }
    if ($hasProviderResponseBy && $providerResponseBy !== null) {
        $setParts[] = 'bri.provider_response_by = ?';
        $types .= 'i';
        $params[] = $providerResponseBy;
    }

    if ($targetStatus === 'treatment_completed') {
        if ($hasTreatmentCompletedAt) {
            $setParts[] = 'bri.treatment_completed_at = NOW()';
        }
        if ($hasTreatmentCompletedByUserId && $providerResponseBy !== null) {
            $setParts[] = 'bri.treatment_completed_by_user_id = ?';
            $types .= 'i';
            $params[] = $providerResponseBy;
        }
    }

    if ($targetStatus === 'post_treatment_follow_up') {
        if ($hasFollowUpStartedAt) {
            $setParts[] = 'bri.follow_up_started_at = NOW()';
        }
        if ($hasFollowUpStartedByUserId && $providerResponseBy !== null) {
            $setParts[] = 'bri.follow_up_started_by_user_id = ?';
            $types .= 'i';
            $params[] = $providerResponseBy;
        }
    }

    // ── Nuevos estados ciclo de vida médico 2026-04-15 ────────────────────────
    if ($targetStatus === 'virtual_assessment_done') {
        $assessmentNotes = substr(trim((string)($_POST['assessment_notes'] ?? '')), 0, 2000);
        if ($hasAssessmentDoneAt) { $setParts[] = 'bri.assessment_done_at = NOW()'; }
        if ($hasAssessmentDoneByUserId && $providerResponseBy !== null) {
            $setParts[] = 'bri.assessment_done_by_user_id = ?';
            $types .= 'i'; $params[] = $providerResponseBy;
        }
        if ($hasAssessmentNotes && $assessmentNotes !== '') {
            $setParts[] = 'bri.assessment_notes = ?';
            $types .= 's'; $params[] = $assessmentNotes;
        }
    }

    if ($targetStatus === 'treatment_plan_agreed') {
        $planDescription = substr(trim((string)($_POST['plan_description'] ?? '')), 0, 5000);
        if ($planDescription === '') { json_err('plan_description_required', 422); }
        if ($hasPlanAgreedAt) { $setParts[] = 'bri.plan_agreed_at = NOW()'; }
        if ($hasPlanAgreedByUserId && $providerResponseBy !== null) {
            $setParts[] = 'bri.plan_agreed_by_user_id = ?';
            $types .= 'i'; $params[] = $providerResponseBy;
        }
        if ($hasPlanDescription) {
            $setParts[] = 'bri.plan_description = ?';
            $types .= 's'; $params[] = $planDescription;
        }
    }

    if ($targetStatus === 'procedure_scheduled') {
        // Validar que la fecha de procedimiento quede dentro de la ventana vigente del booking
        $procedureDate = trim((string)($_POST['procedure_date'] ?? ''));
        $procedureNotes = substr(trim((string)($_POST['procedure_notes'] ?? '')), 0, 2000);
        if ($procedureDate === '') { json_err('procedure_date_required', 422); }
        $procedureMysql = normalize_datetime_local_to_mysql($procedureDate);
        if ($procedureMysql === '') { json_err('procedure_date_invalid_format', 422); }
        $procedureDateOnly = substr($procedureMysql, 0, 10);

        // Cargar ventana vigente del booking
        $timelineFrom = trim((string)($itemRow['timeline_from'] ?? ''));
        $timelineTo = trim((string)($itemRow['timeline_to'] ?? ''));
        if ($timelineFrom !== '' && $procedureDateOnly < $timelineFrom) {
            json_err('procedure_date_before_timeline_from', 422);
        }
        if ($timelineTo !== '' && $procedureDateOnly > $timelineTo) {
            json_err('procedure_date_after_timeline_to', 422);
        }

        if ($hasProcedureScheduledAt) {
            $setParts[] = 'bri.procedure_scheduled_at = ?';
            $types .= 's'; $params[] = $procedureMysql;
        }
        if ($hasProcedureScheduledByUserId && $providerResponseBy !== null) {
            $setParts[] = 'bri.procedure_scheduled_by_user_id = ?';
            $types .= 'i'; $params[] = $providerResponseBy;
        }
        if ($hasProviderNotes && $procedureNotes !== '') {
            $setParts[] = 'bri.provider_notes = ?';
            $types .= 's'; $params[] = $procedureNotes;
        }
    }

    if ($targetStatus === 'case_closed') {
        $caseCloseReason = substr(trim((string)($_POST['case_close_reason'] ?? '')), 0, 2000);
        if ($caseCloseReason === '') { json_err('case_close_reason_required', 422); }
        if ($hasCaseClosedAt) { $setParts[] = 'bri.case_closed_at = NOW()'; }
        if ($hasCaseClosedByUserId && $providerResponseBy !== null) {
            $setParts[] = 'bri.case_closed_by_user_id = ?';
            $types .= 'i'; $params[] = $providerResponseBy;
        }
        if ($hasCaseCloseReason) {
            $setParts[] = 'bri.case_close_reason = ?';
            $types .= 's'; $params[] = $caseCloseReason;
        }
    }

    // ── Reversas: guardar motivo ───────────────────────────────────────────────
    if ($isReversal) {
        $reversalReasonSafe = substr($reversalReasonRaw, 0, 2000);
        if ($hasReversalReason) {
            $setParts[] = 'bri.reversal_reason = ?';
            $types .= 's'; $params[] = $reversalReasonSafe;
        }
        if ($hasReversalByUserId && $providerResponseBy !== null) {
            $setParts[] = 'bri.reversal_by_user_id = ?';
            $types .= 'i'; $params[] = $providerResponseBy;
        }
        if ($hasReversalAt) { $setParts[] = 'bri.reversal_at = NOW()'; }
    }
    // ─────────────────────────────────────────────────────────────────────────

    if ($targetStatus === 'provider_rejected') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            json_err('reject_reason_required', 422);
        }
        $reason = substr($reason, 0, 255);

        if ($hasProviderRejectReason) {
            $setParts[] = 'bri.provider_reject_reason = ?';
            $types .= 's';
            $params[] = $reason;
        }
        if ($hasProviderNotes) {
            $setParts[] = 'bri.provider_notes = ?';
            $types .= 's';
            $params[] = $reason;
        } elseif ($hasItemNotes) {
            $setParts[] = 'bri.notes = ?';
            $types .= 's';
            $params[] = $reason;
        }
    }

    if ($targetStatus === 'provider_proposed_change') {
        my_booking_requests_trace('provider_propose_change_enter', [
            'item_id' => $itemId,
            'current_status' => $currentStatus,
            'session_user_id' => current_admin_user_id(),
            'session_provider_id' => isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0,
            'linked_staff_id' => function_exists('current_provider_medical_staff_id') ? (int)current_provider_medical_staff_id($conexion) : 0,
        ]);
        $providerNotes = trim((string)($_POST['provider_notes'] ?? ''));
        $integrationMode = my_booking_normalize_meeting_integration_mode($_POST['integration_mode'] ?? 'calendar_plus_meet');
        $appointmentModePost = function_exists('calendar_normalize_appointment_mode')
            ? calendar_normalize_appointment_mode($_POST['appointment_mode'] ?? '')
            : '';

        $meetingStartRaw = trim((string)($_POST['proposed_start_at'] ?? ''));
        $meetingEndRaw = trim((string)($_POST['proposed_end_at'] ?? ''));
        $meetingStart = normalize_datetime_local_to_mysql($meetingStartRaw);
        $meetingEnd = normalize_datetime_local_to_mysql($meetingEndRaw);
        $meetingTimezone = trim((string)($itemRow['timezone'] ?? 'America/Bogota'));
        if ($meetingTimezone === '') {
            $meetingTimezone = 'America/Bogota';
        }
        if ($meetingStart === '' || $meetingEnd === '') {
            json_err('meeting_schedule_required', 422);
        }
        if (strtotime($meetingEnd) <= strtotime($meetingStart)) {
            json_err('invalid_meeting_range', 422);
        }
        if ($integrationMode !== 'internal_only' && my_booking_pick_operational_admin_user_id($conexion, current_admin_user_id()) <= 0) {
            json_err('no_google_admin_connected', 409);
        }

        $dateFrom = trim((string)($_POST['proposed_date_from'] ?? ''));
        $dateTo = trim((string)($_POST['proposed_date_to'] ?? ''));
        $priceRaw = trim((string)($_POST['proposed_price'] ?? ''));
        $currency = strtoupper(trim((string)($_POST['proposed_currency'] ?? '')));

        if ($dateFrom === '') {
            $dateFrom = substr($meetingStart, 0, 10);
        }
        if ($dateTo === '') {
            $dateTo = substr($meetingEnd, 0, 10);
        }

        if (!is_valid_date_ymd($dateFrom) || !is_valid_date_ymd($dateTo)) {
            json_err('invalid_proposed_dates', 422);
        }
        if ($dateFrom !== '' && $dateTo !== '' && strcmp($dateFrom, $dateTo) > 0) {
            json_err('invalid_date_range', 422);
        }

        $proposedPrice = null;
        if ($priceRaw !== '') {
            if (!is_numeric($priceRaw)) {
                json_err('invalid_proposed_price', 422);
            }
            $proposedPrice = round((float)$priceRaw, 2);
            if ($proposedPrice < 0) {
                json_err('invalid_proposed_price', 422);
            }
        }

        $baseCurrency = strtoupper(trim((string)($itemRow['base_currency'] ?? 'USD')));
        if ($baseCurrency === '') {
            $baseCurrency = 'USD';
        }
        if ($currency === '') {
            $currency = $baseCurrency;
        }
        if (!in_array($currency, ['USD', 'COP'], true)) {
            json_err('invalid_proposed_currency', 422);
        }

        if ($hasProviderNotes) {
            $setParts[] = 'bri.provider_notes = ?';
            $types .= 's';
            $params[] = $providerNotes;
        } elseif ($hasItemNotes) {
            $setParts[] = 'bri.notes = ?';
            $types .= 's';
            $params[] = $providerNotes;
        }

        if ($hasProviderProposedDateFrom) {
            if ($dateFrom !== '') {
                $setParts[] = 'bri.provider_proposed_date_from = ?';
                $types .= 's';
                $params[] = $dateFrom;
            } else {
                $setParts[] = 'bri.provider_proposed_date_from = NULL';
            }
        }
        if ($hasProviderProposedDateTo) {
            if ($dateTo !== '') {
                $setParts[] = 'bri.provider_proposed_date_to = ?';
                $types .= 's';
                $params[] = $dateTo;
            } else {
                $setParts[] = 'bri.provider_proposed_date_to = NULL';
            }
        }

        if ($hasProviderProposedPrice) {
            if ($proposedPrice !== null) {
                $setParts[] = 'bri.provider_proposed_price = ?';
                $types .= 'd';
                $params[] = $proposedPrice;
            } else {
                $setParts[] = 'bri.provider_proposed_price = NULL';
            }
        } elseif ($hasItemProposedPrice && $proposedPrice !== null) {
            $setParts[] = 'bri.proposed_price = ?';
            $types .= 'd';
            $params[] = $proposedPrice;
        } elseif ($hasItemProposedPrice) {
            $setParts[] = 'bri.proposed_price = NULL';
        }

        if ($hasProviderProposedCurrency) {
            $setParts[] = 'bri.provider_proposed_currency = ?';
            $types .= 's';
            $params[] = $currency;
        } elseif ($hasItemCurrency) {
            $setParts[] = 'bri.currency = ?';
            $types .= 's';
            $params[] = $currency;
        }
    }

    $sql = "UPDATE booking_request_items bri
            INNER JOIN booking_requests br ON br.id = bri.booking_request_id
            SET " . implode(', ', $setParts) . "
            WHERE bri.id = ?";

    $types .= 'i';
    $params[] = $itemId;

    if ($hasItemsSoftDelete) {
        $sql .= ' AND bri.is_deleted = 0';
    }
    if ($hasRequestsSoftDelete) {
        $sql .= ' AND br.is_deleted = 0';
    }
    $sql .= $scopeWhere;
    $sql .= ' LIMIT 1';

    $finalTypes = $types . $scopeTypes;
    $finalParams = array_merge($params, $scopeParams);

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_err('db_prepare_error', 500);
    }

    bind_stmt_params($stmt, $finalTypes, $finalParams);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        json_err('not_found_or_no_change', 404);
    }

    $bookingRequestId = (int)($itemRow['booking_request_id'] ?? 0);
    if ($bookingRequestId > 0) {
        sync_booking_fee_gate_state($conexion, $bookingRequestId, $hasRequestsSoftDelete);
        rollup_booking_status($conexion, $bookingRequestId, $hasRequestsSoftDelete);
    }

    if ($targetStatus === 'provider_proposed_change') {
        $meetingResult = my_booking_create_proposed_meeting_event($conexion, [
            'id' => $itemId,
            'booking_request_id' => $bookingRequestId,
            'provider_id' => (int)($itemRow['provider_id'] ?? 0),
            'client_user_id' => (int)($itemRow['client_user_id'] ?? 0),
            'item_name' => (string)($itemRow['item_name'] ?? ''),
        ], [
            'provider_notes' => $providerNotes,
            'start_at' => $meetingStart,
            'end_at' => $meetingEnd,
            'timezone' => $meetingTimezone,
            'integration_mode' => $integrationMode,
            'appointment_mode' => $appointmentModePost,
        ], current_admin_user_id());

        if (empty($meetingResult['ok'])) {
            my_booking_requests_trace('provider_propose_change_meeting_failed', [
                'item_id' => $itemId,
                'meeting_result' => $meetingResult,
            ]);
            mysqli_rollback($conexion);
            json_err((string)($meetingResult['error'] ?? 'meeting_schedule_create_failed'), 409);
        }

        my_booking_requests_trace('provider_propose_change_meeting_ok', [
            'item_id' => $itemId,
            'meeting_result' => $meetingResult,
        ]);

        if (inbox_table_exists($conexion, 'inbox_messages')) {
            $threadId = inbox_thread_id('ITEM', $bookingRequestId, $itemId);
            $senderRole = $isAdminSession ? 'ADMIN' : 'PROVIDER';
            $senderUserId = current_admin_user_id();
            $proposalMessage = '[MEETING_PROPOSAL] ' . json_encode([
                'start_at' => $meetingStart,
                'end_at' => $meetingEnd,
                'note' => $providerNotes,
                'integration_mode' => $integrationMode,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmtMsg = mysqli_prepare(
                $conexion,
                "INSERT INTO inbox_messages
                    (thread_id, thread_type, request_id, item_id, sender_role, sender_user_id, body)
                 VALUES (?, 'ITEM', ?, ?, ?, ?, ?)"
            );
            if ($stmtMsg) {
                mysqli_stmt_bind_param($stmtMsg, 'siisis', $threadId, $bookingRequestId, $itemId, $senderRole, $senderUserId, $proposalMessage);
                mysqli_stmt_execute($stmtMsg);
                $newMessageId = (int)mysqli_insert_id($conexion);
                mysqli_stmt_close($stmtMsg);
                my_booking_requests_trace('provider_propose_change_item_insert', [
                    'item_id' => $itemId,
                    'thread_id' => $threadId,
                    'message_id' => $newMessageId,
                    'sender_role' => $senderRole,
                    'sender_user_id' => $senderUserId,
                    'insert_id_ok' => $newMessageId > 0,
                ]);
                $postCommitRealtimeEvents[] = ['thread_id' => $threadId, 'message_id' => $newMessageId, 'sender_role' => $senderRole, 'created_at' => date('c')];
            } else {
                my_booking_requests_trace('provider_propose_change_item_prepare_failed', [
                    'db_error' => mysqli_error($conexion),
                    'request_id' => $bookingRequestId,
                    'item_id' => $itemId,
                    'thread_id' => $threadId,
                ]);
            }
        }

        mysqli_commit($conexion);
        my_booking_requests_trace('provider_propose_change_commit', [
            'item_id' => $itemId,
            'booking_request_id' => $bookingRequestId,
            'care_thread_id' => inbox_thread_id('CARE', $bookingRequestId, 0),
            'event_count' => count($postCommitRealtimeEvents),
            'function_exists_emit' => function_exists('mt_realtime_emit_inbox_message'),
            'events' => $postCommitRealtimeEvents,
        ]);
        if (!empty($postCommitRealtimeEvents)) {
            foreach ($postCommitRealtimeEvents as $eventPayload) {
                my_booking_emit_realtime_inbox_message($eventPayload, 'provider_propose_change_item');
                // El paciente normalmente está en el hilo CARE. Emitir también al CARE
                // (sin insertar nada) para que su fetchNewMessages dispare has_structured_item_actions
                // y el widget de propuesta se muestre en vivo. Patrón idéntico a inbox.php L1217-1224.
                if ($bookingRequestId > 0) {
                    $careThreadId = inbox_thread_id('CARE', $bookingRequestId, 0);
                    if ($careThreadId !== (string)($eventPayload['thread_id'] ?? '')) {
                        my_booking_emit_realtime_inbox_message(
                            ['thread_id' => $careThreadId, 'message_id' => (int)($eventPayload['message_id'] ?? 0), 'sender_role' => (string)($eventPayload['sender_role'] ?? ''), 'created_at' => (string)($eventPayload['created_at'] ?? date('c'))],
                            'provider_propose_change_care_alert'
                        );
                    }
                }
            }
        } else {
            my_booking_requests_trace('provider_propose_change_emit_skipped', [
                'item_id' => $itemId,
                'reason' => 'no_events_to_emit',
            ]);
        }
    }

    try {
        $notifySql = "SELECT
                        br.id AS booking_id,
                        br.name AS client_name,
                        br.email AS client_email,
                        CASE
                            WHEN bri.item_status IS NULL OR bri.item_status = '' OR bri.item_status IN ('pending_admin', 'pending_review') THEN 'pending_provider'
                            WHEN bri.item_status = 'completed' THEN 'treatment_completed'
                            ELSE bri.item_status
                        END AS item_status,
                        {$providerNotesExpr} AS provider_notes,
                        {$rejectReasonExpr} AS provider_reject_reason,
                        {$proposedDateFromExpr} AS provider_proposed_date_from,
                        {$proposedDateToExpr} AS provider_proposed_date_to,
                        {$proposedPriceExpr} AS provider_proposed_price,
                        {$proposedCurrencyExpr} AS provider_proposed_currency,
                        COALESCE(NULLIF(sc.name, ''), NULLIF(o.title, ''), NULLIF(ms.service_name, ''), CONCAT('Item #', bri.id)) AS item_name,
                        COALESCE(NULLIF(ms.currency, ''), NULLIF(o.currency, ''), NULLIF(bri.currency, ''), 'USD') AS item_currency
                    FROM booking_request_items bri
                    INNER JOIN booking_requests br ON br.id = bri.booking_request_id
                    LEFT JOIN provider_service_offers o ON o.id = bri.offer_id
                    LEFT JOIN service_catalog sc ON sc.id = o.service_id
                    LEFT JOIN medtravel_services_catalog ms ON ms.id = bri.medtravel_service_id
                    WHERE bri.id = ?";
        if ($hasItemsSoftDelete) {
            $notifySql .= ' AND bri.is_deleted = 0';
        }
        if ($hasRequestsSoftDelete) {
            $notifySql .= ' AND br.is_deleted = 0';
        }
        $notifySql .= $scopeWhere . ' LIMIT 1';

        $notifyStmt = mysqli_prepare($conexion, $notifySql);
        $notifyRow = null;
        if ($notifyStmt) {
            $notifyTypes = 'i' . $scopeTypes;
            $notifyParams = array_merge([$itemId], $scopeParams);
            bind_stmt_params($notifyStmt, $notifyTypes, $notifyParams);
            if (mysqli_stmt_execute($notifyStmt)) {
                $notifyRes = mysqli_stmt_get_result($notifyStmt);
                $notifyRow = $notifyRes ? mysqli_fetch_assoc($notifyRes) : null;
            }
            mysqli_stmt_close($notifyStmt);
        }

        if (is_array($notifyRow) && !empty($notifyRow)) {
            $bookingId = (int)($notifyRow['booking_id'] ?? 0);
            $clientName = trim((string)($notifyRow['client_name'] ?? ''));
            $clientEmail = trim((string)($notifyRow['client_email'] ?? ''));
            $itemName = trim((string)($notifyRow['item_name'] ?? ''));
            $statusNow = trim((string)($notifyRow['item_status'] ?? $targetStatus));
            $providerNotes = trim((string)($notifyRow['provider_notes'] ?? ''));
            $rejectReason = trim((string)($notifyRow['provider_reject_reason'] ?? ''));
            $propFrom = trim((string)($notifyRow['provider_proposed_date_from'] ?? ''));
            $propTo = trim((string)($notifyRow['provider_proposed_date_to'] ?? ''));
            $propPriceRaw = $notifyRow['provider_proposed_price'] ?? null;
            $propCurrency = strtoupper(trim((string)($notifyRow['provider_proposed_currency'] ?? $notifyRow['item_currency'] ?? 'USD')));
            if ($propCurrency === '') {
                $propCurrency = 'USD';
            }

            $summaryHtml = '';
            $summaryText = '';
            if ($providerNotes !== '') {
                $summaryHtml .= '<p><strong>Provider notes:</strong> ' . safe_html($providerNotes) . '</p>';
                $summaryText .= "Provider notes: " . $providerNotes . "\n";
            }
            if ($rejectReason !== '') {
                $summaryHtml .= '<p><strong>Reject reason:</strong> ' . safe_html($rejectReason) . '</p>';
                $summaryText .= "Reject reason: " . $rejectReason . "\n";
            }
            if ($targetStatus === 'provider_proposed_change') {
                $summaryHtml .= '<p><strong>Proposed changes:</strong></p><ul>';
                $summaryText .= "Proposed changes:\n";
                if ($propFrom !== '' || $propTo !== '') {
                    $dateRange = trim(($propFrom !== '' ? $propFrom : '?') . ' to ' . ($propTo !== '' ? $propTo : '?'));
                    $summaryHtml .= '<li>Dates: ' . safe_html($dateRange) . '</li>';
                    $summaryText .= "- Dates: " . $dateRange . "\n";
                }
                if ($propPriceRaw !== null && $propPriceRaw !== '') {
                    $propPrice = number_format((float)$propPriceRaw, 2);
                    $summaryHtml .= '<li>Price: ' . safe_html($propCurrency . ' ' . $propPrice) . '</li>';
                    $summaryText .= "- Price: " . $propCurrency . ' ' . $propPrice . "\n";
                }
                if ($providerNotes === '' && $rejectReason === '' && $propFrom === '' && $propTo === '' && ($propPriceRaw === null || $propPriceRaw === '')) {
                    $summaryHtml .= '<li>Provider submitted a change proposal.</li>';
                    $summaryText .= "- Provider submitted a change proposal.\n";
                }
                $summaryHtml .= '</ul>';
            }

            $statusLabel = provider_status_label($statusNow);
            $subject = 'Update on your MedTravel request #' . $bookingId;
            $safeClientName = $clientName !== '' ? $clientName : 'Patient';
            $safeItemName = $itemName !== '' ? $itemName : ('Item #' . $itemId);
            $loginUrl = 'https://medtravel.com.co/login.php';

            $contentHtml = '<p>Hello ' . safe_html($safeClientName) . ',</p>'
                . '<p>There is a new update on your MedTravel request.</p>'
                . '<p><strong>Request ID:</strong> #' . safe_html((string)$bookingId) . '<br>'
                . '<strong>Service:</strong> ' . safe_html($safeItemName) . '<br>'
                . '<strong>New status:</strong> ' . safe_html($statusLabel) . '</p>'
                . $summaryHtml
                . '<p>You can log in to your client portal to review details.</p>';

            $htmlBody = $contentHtml;
            if (function_exists('renderMedTravelEmail')) {
                $htmlBody = renderMedTravelEmail(
                    'Request update',
                    'There is a new update on your MedTravel request.',
                    $contentHtml,
                    'This is an automated message.',
                    [
                        'text' => 'Log in to your client portal',
                        'url' => $loginUrl,
                    ]
                );
            }

            $altBody = "Hello {$safeClientName},\n\n"
                . "There is a new update on your MedTravel request.\n"
                . "Request ID: #{$bookingId}\n"
                . "Service: {$safeItemName}\n"
                . "New status: {$statusLabel}\n";
            if ($summaryText !== '') {
                $altBody .= "\n" . trim($summaryText) . "\n";
            }
            $altBody .= "\nYou can log in to your client portal to review details.\n";

            if (filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    sendEmail($clientEmail, $subject, $htmlBody, 'patientcare', ['alt_body' => $altBody], $conexion);
                } catch (Throwable $emailEx) {
                    error_log('provider_action_email_client_error item_id=' . $itemId . ' action=' . $action . ' msg=' . $emailEx->getMessage());
                }
            }

            $adminEmail = resolve_patientcare_admin_email($conexion);
            if ($adminEmail !== '') {
                $adminSubject = '[ADMIN] ' . $subject;
                $adminHtml = '<p>Provider action received.</p>'
                    . '<p><strong>Request ID:</strong> #' . safe_html((string)$bookingId) . '<br>'
                    . '<strong>Item ID:</strong> #' . safe_html((string)$itemId) . '<br>'
                    . '<strong>Status:</strong> ' . safe_html($statusLabel) . '<br>'
                    . '<strong>Client:</strong> ' . safe_html($safeClientName) . ' (' . safe_html($clientEmail) . ')</p>'
                    . $summaryHtml;
                $adminAlt = "Provider action received.\n"
                    . "Request ID: #{$bookingId}\n"
                    . "Item ID: #{$itemId}\n"
                    . "Status: {$statusLabel}\n"
                    . "Client: {$safeClientName} ({$clientEmail})\n";
                if ($summaryText !== '') {
                    $adminAlt .= "\n" . trim($summaryText) . "\n";
                }

                try {
                    sendEmail($adminEmail, $adminSubject, $adminHtml, 'patientcare', ['alt_body' => $adminAlt], $conexion);
                } catch (Throwable $emailEx) {
                    error_log('provider_action_email_admin_error item_id=' . $itemId . ' action=' . $action . ' msg=' . $emailEx->getMessage());
                }
            }
        }
    } catch (Throwable $e) {
        error_log('provider_action_email_error item_id=' . $itemId . ' action=' . $action . ' msg=' . $e->getMessage());
    }

    json_ok([
        'message' => 'Respuesta guardada',
        'status' => $targetStatus,
        'meeting' => $meetingResult,
    ]);
}

// ── Actualización formal de ventana de fechas del booking (post-valoración) ──
if ($action === 'update_timeline_window') {
    my_booking_requests_set_debug_branch('update_timeline_window');
    if (!$isAdminSession && !$isMedicalProviderSession) {
        json_err('forbidden', 403);
    }
    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) { json_err('invalid_id'); }

    $itemRow = fetch_scoped_item($conexion, $itemId, $scopeWhere, $scopeTypes, $scopeParams, $hasItemsSoftDelete, $hasRequestsSoftDelete);
    if (!$itemRow) { json_err('not_found', 404); }

    $bookingRequestId = (int)($itemRow['booking_request_id'] ?? 0);
    if ($bookingRequestId <= 0) { json_err('invalid_booking_request', 409); }

    $newFrom = trim((string)($_POST['timeline_from'] ?? ''));
    $newTo   = trim((string)($_POST['timeline_to'] ?? ''));
    $reason  = substr(trim((string)($_POST['reason'] ?? '')), 0, 2000);

    if (!is_valid_date_ymd($newFrom) || !is_valid_date_ymd($newTo)) {
        json_err('invalid_dates', 422);
    }
    if ($newFrom === '' || $newTo === '') { json_err('dates_required', 422); }
    if ($newFrom > $newTo) { json_err('invalid_date_range', 422); }
    if ($reason === '') { json_err('reason_required', 422); }

    $setParts = [];
    $types = '';
    $params = [];

    if ($hasTimelineFrom) {
        $setParts[] = 'timeline_from = ?'; $types .= 's'; $params[] = $newFrom;
    }
    if ($hasTimelineTo) {
        $setParts[] = 'timeline_to = ?'; $types .= 's'; $params[] = $newTo;
    }
    if ($hasTimelineUpdatedAt) {
        $setParts[] = 'timeline_updated_at = NOW()';
    }
    $actorUserId = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
    if ($hasTimelineUpdatedByUserId && $actorUserId > 0) {
        $setParts[] = 'timeline_updated_by_user_id = ?'; $types .= 'i'; $params[] = $actorUserId;
    }
    if ($hasTimelineUpdateReason) {
        $setParts[] = 'timeline_update_reason = ?'; $types .= 's'; $params[] = $reason;
    }
    if ($hasBookingUpdatedAt) {
        $setParts[] = 'updated_at = NOW()';
    }

    if (empty($setParts)) { json_err('timeline_columns_missing', 409); }

    $sql = 'UPDATE booking_requests SET ' . implode(', ', $setParts) . ' WHERE id = ?';
    $types .= 'i'; $params[] = $bookingRequestId;
    if ($hasRequestsSoftDelete) { $sql .= ' AND is_deleted = 0'; }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) { json_err('db_prepare_error', 500); }
    bind_stmt_params($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_err('db_error: ' . $err, 500);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) { json_err('not_found_or_no_change', 404); }

    json_ok([
        'ok' => true,
        'timeline_from' => $newFrom,
        'timeline_to'   => $newTo,
        'message'       => 'Ventana de fechas actualizada correctamente.',
    ]);
}

json_err('invalid_action');
